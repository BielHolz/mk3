<?php
/** Testes HTTP/SQL reais. Somente banco vazio cujo nome termine em _test.
 * MK3_DB_NAME=mk3_test MK3_TEST_BASE_URL=http://127.0.0.1:8081 php tests/integration.php
 * O servidor HTTP precisa apontar para O MESMO banco de testes.
 */
require dirname(__DIR__) . "/app/core.php";
require dirname(__DIR__) . "/app/media.php";
if (PHP_SAPI !== "cli" || !str_ends_with($config["db_name"], "_test")) {
    throw new RuntimeException("Use banco exclusivo terminado em _test.");
}
$base = rtrim(getenv("MK3_TEST_BASE_URL") ?: "http://127.0.0.1:8081", "/");
if (
    !in_array(parse_url($base, PHP_URL_HOST), ["127.0.0.1", "localhost"], true)
) {
    throw new RuntimeException("Teste permitido somente em servidor local.");
}
foreach (
    ["vehicles", "users", "store_settings", "login_attempts", "audit_logs"]
    as $table
) {
    if (
        (int) db()
            ->query("SELECT COUNT(*) FROM " . $table)
            ->fetchColumn()
    ) {
        throw new RuntimeException(
            "Banco de testes precisa estar vazio: " . $table,
        );
    }
}
$cookies = tempnam(sys_get_temp_dir(), "mk3-cookie-");
$anonymous = tempnam(sys_get_temp_dir(), "mk3-anon-");
$temp = [];
$ids = [];
$adminId = null;
$assertions = 0;
$failed = false;
$serverVerified = false;
function expect(bool $ok, string $label): void
{
    global $assertions;
    $assertions++;
    if (!$ok) {
        throw new RuntimeException("FAIL " . $label);
    }
    echo "PASS " . $label . "\n";
}
function request(string $path, ?array $data = null, bool $auth = true): array
{
    global $base, $cookies, $anonymous;
    $curl = curl_init($base . $path);
    $headers = [];
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_COOKIEFILE => $auth ? $cookies : $anonymous,
        CURLOPT_COOKIEJAR => $auth ? $cookies : $anonymous,
        CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$headers) {
            $headers[] = trim($line);
            return strlen($line);
        },
    ]);
    if ($data !== null) {
        $multipart = false;
        foreach ($data as $v) {
            if ($v instanceof CURLFile) {
                $multipart = true;
            }
        }
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt(
            $curl,
            CURLOPT_POSTFIELDS,
            $multipart ? $data : http_build_query($data),
        );
    }
    $body = curl_exec($curl);
    if ($body === false) {
        throw new RuntimeException("HTTP indisponível: " . curl_error($curl));
    }
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return [
        "code" => $code,
        "body" => $body,
        "headers" => implode("\n", $headers),
    ];
}
function token(array $response): string
{
    if (
        !preg_match('/name="csrf" value="([a-f0-9]+)"/', $response["body"], $m)
    ) {
        throw new RuntimeException("Token CSRF ausente.");
    }
    return $m[1];
}
function scalar(string $sql, array $args = []): mixed
{
    $s = db()->prepare($sql);
    $s->execute($args);
    return $s->fetchColumn();
}
try {
    $probe = request("/");
    if (
        !str_contains(
            strtolower($probe["headers"]),
            "x-mk3-test-database: " . hash("sha256", $config["db_name"]),
        )
    ) {
        throw new RuntimeException(
            "Servidor HTTP não confirmou o banco descartável correto. Defina MK3_TEST_MODE=1 apenas na instância de testes. Nenhum dado foi enviado.",
        );
    }
    $serverVerified = true;
    $r = request("/instalar.php");
    expect($r["code"] === 200, "instalador local disponível");
    $csrf = token($r);
    $email = "fixture-" . bin2hex(random_bytes(5)) . "@example.test";
    $password = bin2hex(random_bytes(18));
    $r = request("/instalar.php", [
        "csrf" => $csrf,
        "name" => "Administrador de teste",
        "email" => $email,
        "pass" => $password,
    ]);
    expect(
        str_contains($r["body"], "Administrador criado."),
        "criação do primeiro administrador",
    );
    $adminId = (int) scalar("SELECT id FROM users WHERE email=?", [$email]);
    expect(
        (password_get_info(
            (string) scalar("SELECT password_hash FROM users WHERE id=?", [
                $adminId,
            ]),
        )["options"]["cost"] ??
            0) >=
            12,
        "hash mantém custo mínimo 12 no PHP 8.2",
    );
    expect(
        request("/instalar.php")["code"] === 403,
        "instalador bloqueado após primeira conta",
    );
    expect(
        request("/admin/index.php", null, false)["code"] === 303,
        "painel exige autenticação",
    );
    $r = request("/admin/login.php");
    $csrf = token($r);
    $r = request("/admin/login.php", [
        "csrf" => $csrf,
        "email" => "inexistente@example.test",
        "password" => "senha-incorreta",
    ]);
    expect(
        str_contains($r["body"], "E-mail ou senha inválidos."),
        "erro de login não enumera conta",
    );
    $r = request("/admin/login.php", [
        "csrf" => $csrf,
        "email" => $email,
        "password" => $password,
    ]);
    expect($r["code"] === 303, "login real");
    expect(
        str_contains($r["headers"], "HttpOnly") &&
            str_contains($r["headers"], "SameSite=Strict"),
        "cookie de sessão protegido",
    );
    $secondCsrf = token(request("/admin/login.php", null, false));
    expect(
        request(
            "/admin/login.php",
            ["csrf" => $secondCsrf, "email" => $email, "password" => $password],
            false,
        )["code"] === 303,
        "segunda sessão autenticada",
    );
    $csrf = token(request("/admin/edit.php"));
    $vehicle = [
        "csrf" => $csrf,
        "title" => "Fixture MK3 <script>alert(1)</script>",
        "brand" => "Marca teste",
        "model" => "Modelo teste",
        "version" => "Versão teste",
        "manufacture_year" => "2020",
        "model_year" => "2021",
        "mileage" => "42000",
        "price" => "12345.67",
        "transmission" => "Automático",
        "fuel" => "Flex",
        "color" => "Preto",
        "status" => "available",
        "featured" => "1",
        "featured_order" => "2",
        "description" => "Descrição <script>alert(2)</script>",
        "features" => "Ar-condicionado\nEquipamento confirmado",
    ];
    $r = request(
        "/admin/edit.php",
        array_replace($vehicle, ["csrf" => "invalid"]),
    );
    expect(
        $r["code"] === 403,
        "CSRF rejeita alteração (HTTP " . $r["code"] . ")",
    );
    $r = request("/admin/edit.php", $vehicle);
    expect($r["code"] === 303, "cadastro do veículo");
    preg_match("/Location: edit.php\?id=(\d+)/i", $r["headers"], $m);
    $id = (int) $m[1];
    $ids[] = $id;
    expect(
        scalar("SELECT version FROM vehicles WHERE id=?", [$id]) ===
            "Versão teste",
        "versão persistida",
    );
    expect(
        (int) scalar("SELECT model_year FROM vehicles WHERE id=?", [$id]) ===
            2021,
        "anos estruturados persistidos",
    );
    $r = request(
        "/admin/edit.php?id=" . $id,
        array_replace($vehicle, ["price" => "-1"]),
    );
    expect(
        str_contains($r["body"], "Preço inválido") &&
            scalar("SELECT price FROM vehicles WHERE id=?", [$id]) ===
                "12345.67",
        "validação não sobrescreve valores válidos",
    );
    $invalidForm=array_replace($vehicle,['price'=>'-1']);
    unset($invalidForm['featured']);
    $invalidResponse=request('/admin/edit.php?id='.$id,$invalidForm);
    preg_match('/<input\b(?=[^>]*name="featured")[^>]*>/s',$invalidResponse['body'],$featuredField);
    expect(isset($featuredField[0]) && !preg_match('/\schecked(?:\s|>|=)/i',$featuredField[0]),'formulário inválido preserva destaque desmarcado sem alterar o banco');
    $r = request("/?q=Fixture");
    expect(
        $r["code"] === 200 &&
            str_contains($r["body"], "&lt;script&gt;alert(1)&lt;/script&gt;"),
        "catálogo escapa HTML",
    );
    expect(
        !str_contains($r["body"], "<script>alert(1)</script>"),
        "XSS armazenado neutralizado",
    );
    expect(
        !str_contains(request("/?q=%27%20OR%201%3D1--")["body"], "car-card"),
        "busca não permite SQL injection",
    );
    expect(
        !str_contains(request("/?min_price=20000")["body"], "car-card"),
        "filtro de preço",
    );
    expect(
        !str_contains(request("/?brand=Outra")["body"], "car-card"),
        "filtro de marca",
    );
    expect(
        str_contains(
            request("/?min_year=2021&max_year=2021")["body"],
            "car-card",
        ),
        "filtro de ano",
    );
    expect(
        request("/veiculo.php?id=" . $id)["code"] === 200,
        "detalhe público",
    );
    expect(
        request("/veiculo.php?id[]=1")["code"] === 404,
        "identificador malformado rejeitado",
    );
    $emptyDetail = request("/veiculo.php?id=" . $id);
    expect(
        !str_contains($emptyDetail["body"], "placeholder-thumb.svg"),
        "veículo sem fotos usa miniatura existente",
    );
    expect(
        request("/assets/placeholder.svg")["code"] === 200,
        "marcador neutro de foto acessível",
    );
    $image = imagecreatetruecolor(80, 60);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 45));
    $imageFile = tempnam(sys_get_temp_dir(), "mk3-img-");
    imagepng($image, $imageFile);
    imagedestroy($image);
    $temp[] = $imageFile;
    // JPEG real com orientação EXIF 6: a conversão deve girar antes de remover metadados.
    $jpeg = tempnam(sys_get_temp_dir(), "mk3-jpeg-");
    $source = imagecreatefrompng($imageFile);
    imagejpeg($source, $jpeg, 90);
    imagedestroy($source);
    $exif =
        "Exif\0\0II" .
        pack("v", 42) .
        pack("V", 8) .
        pack("v", 1) .
        pack("vvV", 0x0112, 3, 1) .
        pack("v", 6) .
        "\0\0" .
        pack("V", 0);
    $bytes = file_get_contents($jpeg);
    file_put_contents(
        $jpeg,
        substr($bytes, 0, 2) .
            "\xff\xe1" .
            pack("n", strlen($exif) + 2) .
            $exif .
            substr($bytes, 2),
    );
    $temp[] = $jpeg;
    $upload = [
        "csrf" => $csrf,
        "vehicle_id" => $id,
        "action" => "upload",
        "photos[0]" => new CURLFile($imageFile, "image/png", "one.png"),
        "photos[1]" => new CURLFile($jpeg, "image/jpeg", "two.jpg"),
    ];
    expect(
        request("/admin/photo.php", $upload)["code"] === 303,
        "upload múltiplo real",
    );
    $photos = car_photos($id);
    expect(count($photos) === 2, "duas fotografias associadas");
    $oriented = getimagesize(dirname(__DIR__) . "/" . $photos[1]["path"]);
    expect(
        $oriented[0] === 144 && $oriented[1] === 81,
        "orientação EXIF aplicada antes da conversão WebP",
    );
    expect(
        is_file(dirname(__DIR__) . "/" . thumbnail_path($photos[0]["path"])),
        "miniatura otimizada gerada",
    );
    $info = getimagesize(dirname(__DIR__) . "/" . $photos[0]["path"]);
    expect($info["mime"] === "image/webp", "imagem reprocessada para WebP");
    expect(
        $info[0] * 9 === $info[1] * 16 && $info[0] <= 1920,
        "foto de veículo WebP em 16:9",
    );
    $thumbInfo = getimagesize(
        dirname(__DIR__) . "/" . thumbnail_path($photos[0]["path"]),
    );
    expect(
        $thumbInfo[0] * 9 === $thumbInfo[1] * 16 && $thumbInfo[0] <= 480,
        "miniatura WebP em 16:9",
    );
    $decoded = imagecreatefromwebp(dirname(__DIR__) . "/" . $photos[0]["path"]);
    $corner = imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0));
    $center = imagecolorsforindex(
        $decoded,
        imagecolorat($decoded, (int) ($info[0] / 2), (int) ($info[1] / 2)),
    );
    expect(
        $corner["red"] < 50 && $center["red"] > 150,
        "canvas contém foto completa com margens neutras",
    );
    imagedestroy($decoded);
    $r = request("/admin/photo.php", [
        "csrf" => $csrf,
        "vehicle_id" => $id,
        "photo_id" => $photos[1]["id"],
        "action" => "cover",
    ]);
    expect(
        $r["code"] === 303 && cover($id) === $photos[1]["path"],
        "alteração real da capa",
    );
    expect(
        str_contains(request("/")["body"], $photos[1]["path"]),
        "capa sincronizada no catálogo e destaque",
    );
    $before = glob(dirname(__DIR__) . "/uploads/*.webp");
    $bad = tempnam(sys_get_temp_dir(), "mk3-bad-");
    file_put_contents($bad, '<?php echo "invalid";');
    $temp[] = $bad;
    $r = request(
        "/admin/photo.php",
        array_replace($upload, [
            "photos[1]" => new CURLFile($bad, "image/png", "fake.png"),
        ]),
    );
    expect($r["code"] === 422, "arquivo disfarçado de imagem rejeitado");
    expect(
        count(car_photos($id)) === 2 &&
            count(glob(dirname(__DIR__) . "/uploads/*.webp")) ===
                count($before),
        "lote inválido reverte banco e arquivos",
    );
    $excess = ["csrf" => $csrf, "vehicle_id" => $id, "action" => "upload"];
    for ($i = 0; $i < 11; $i++) {
        $excess["photos[" . $i . "]"] = new CURLFile(
            $imageFile,
            "image/png",
            "extra-" . $i . ".png",
        );
    }
    expect(
        request("/admin/photo.php", $excess)["code"] === 422 &&
            count(car_photos($id)) === 2,
        "limite de 12 fotos considera as já cadastradas",
    );
    $r = request(
        "/admin/edit.php",
        array_replace($vehicle, [
            "title" => "Outro veículo",
            "featured" => "0",
        ]),
    );
    preg_match("/Location: edit.php\?id=(\d+)/i", $r["headers"], $m);
    $other = (int) $m[1];
    $ids[] = $other;
    expect(
        request("/admin/photo.php", [
            "csrf" => $csrf,
            "vehicle_id" => $other,
            "photo_id" => $photos[0]["id"],
            "action" => "remove",
        ])["code"] === 422,
        "foto de outro veículo não pode ser alterada",
    );
    $current = car_photos($id);
    $r = request("/admin/photo.php", [
        "csrf" => $csrf,
        "vehicle_id" => $id,
        "photo_id" => $current[0]["id"],
        "action" => "down",
    ]);
    expect(
        $r["code"] === 303 && car_photos($id)[1]["id"] === $current[0]["id"],
        "reordenação de fotos",
    );
    $current = car_photos($id);
    $oldPath = $current[0]["path"];
    $r = request("/admin/photo.php", [
        "csrf" => $csrf,
        "vehicle_id" => $id,
        "photo_id" => $current[0]["id"],
        "action" => "replace",
        "photo" => new CURLFile($imageFile, "image/png", "replacement.png"),
    ]);
    expect(
        $r["code"] === 303 && !is_file(dirname(__DIR__) . "/" . $oldPath),
        "substituição remove imagem antiga após salvar",
    );
    foreach (
        ["reserved" => 200, "sold" => 404, "hidden" => 404, "available" => 200]
        as $status => $code
    ) {
        request(
            "/admin/edit.php?id=" . $id,
            array_replace($vehicle, ["status" => $status]),
        );
        $r = request("/veiculo.php?id=" . $id);
        expect($r["code"] === $code, "visibilidade: " . $status);
        if ($status === "reserved") {
            expect(
                str_contains($r["body"], "Reservado"),
                "reserva identificada no detalhe",
            );
        }
    }
    for ($i = 0; $i < 10; $i++) {
        db()
            ->prepare(
                "INSERT INTO vehicles(title,brand,model,status,price,model_year) VALUES(?,?,?,'available',?,?)",
            )
            ->execute([
                "Pagina fixture " . $i,
                "Marca pagina",
                "Modelo",
                $i * 1000,
                2000 + $i,
            ]);
        $ids[] = (int) db()->lastInsertId();
    }
    $r = request("/?q=Pagina&sort=price_asc");
    expect(
        substr_count($r["body"], 'class="car-card reveal"') === 9,
        "paginação limita primeira página a nove",
    );
    $r = request("/?q=Pagina&page=2");
    expect(
        substr_count($r["body"], 'class="car-card reveal"') === 1,
        "segunda página com restante",
    );
    $set = settings();
    $data = array_intersect_key(
        $set,
        array_flip([
            "name",
            "phone",
            "whatsapp",
            "address",
            "hours",
            "instagram",
            "about",
            "hero_text",
            "seo_title",
            "seo_description",
        ]),
    );
    $data["csrf"] = $csrf;
    $data["name"] = "Loja teste <MK3>";
    expect(
        str_contains(
            request("/admin/settings.php", $data)["body"],
            "Confirme que",
        ),
        "alteração institucional exige confirmação",
    );
    $data["confirmed"] = "1";
    expect(
        request("/admin/settings.php", $data)["code"] === 303,
        "configurações persistidas",
    );
    expect(
        str_contains(request("/")["body"], "Loja teste &lt;MK3&gt;"),
        "configuração refletida no site com escape",
    );
    $data["instagram"] = "javascript:alert(1)";
    expect(
        str_contains(
            request("/admin/settings.php", $data)["body"],
            "endereço HTTPS",
        ),
        "URL institucional maliciosa rejeitada",
    );
    expect(
        request("/admin/delete.php?id=" . $id)["code"] === 405,
        "exclusão não aceita GET",
    );
    $paths = array_column(car_photos($id), "path");
    expect(
        request("/admin/delete.php", ["csrf" => $csrf, "id" => $id])["code"] ===
            303,
        "exclusão autenticada",
    );
    expect(
        (int) scalar("SELECT COUNT(*) FROM photos WHERE vehicle_id=?", [
            $id,
        ]) === 0,
        "exclusão em cascata das fotos",
    );
    foreach ($paths as $path) {
        expect(
            !is_file(dirname(__DIR__) . "/" . $path),
            "arquivo excluído com veículo",
        );
    }
    expect(
        !password_rehash_needed(
            password_hash("fixture-password", PASSWORD_BCRYPT, ["cost" => 13]),
        ),
        "hash mais forte não é rebaixado ao trocar versão do PHP",
    );
    $newPassword = bin2hex(random_bytes(18));
    $r = request("/admin/account.php", [
        "csrf" => $csrf,
        "old" => $password,
        "new" => $newPassword,
        "confirm" => $newPassword,
    ]);
    expect(
        str_contains($r["body"], "Senha atualizada."),
        "troca de senha real",
    );
    expect(
        request("/admin/index.php", null, false)["code"] === 303,
        "troca de senha invalida outra sessão",
    );
    $csrf = token(request("/admin/account.php"));
    expect(
        request("/admin/logout.php", ["csrf" => $csrf])["code"] === 303,
        "logout com CSRF",
    );
    expect(request("/admin/index.php")["code"] === 303, "sessão encerrada");
    for ($i = 0; $i < 9; $i++) {
        $csrf = token(request("/admin/login.php"));
        $r = request("/admin/login.php", [
            "csrf" => $csrf,
            "email" => $email,
            "password" => "incorrect",
        ]);
    }
    expect($r["code"] === 429, "rate limit aplicado a tentativas repetidas");
    expect(
        (int) scalar("SELECT COUNT(*) FROM audit_logs WHERE user_id=?", [
            $adminId,
        ]) > 10,
        "operações importantes auditadas",
    );
    foreach (
        ["/app/config.php", "/database.sql", "/tests/integration.php"]
        as $path
    ) {
        expect(
            request($path)["code"] === 403,
            "arquivo privado bloqueado: " . $path,
        );
    }
    echo "OK: $assertions verificações funcionais.\n";
} catch (Throwable $e) {
    $failed = true;
    fwrite(STDERR, $e->getMessage() . "\n");
} finally {
    foreach ($ids as $id) {
        foreach (car_photos($id) as $p) {
            remove_image($p["path"]);
        }
        db()
            ->prepare("DELETE FROM vehicles WHERE id=?")
            ->execute([$id]);
    }
    if ($adminId) {
        db()
            ->prepare("DELETE FROM audit_logs WHERE user_id=?")
            ->execute([$adminId]);
        db()
            ->prepare("DELETE FROM users WHERE id=?")
            ->execute([$adminId]);
    }
    if ($serverVerified) {
        db()->exec("DELETE FROM store_settings");
        db()->exec("DELETE FROM login_attempts");
    }
    foreach (array_merge([$cookies, $anonymous], $temp) as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
exit($failed ? 1 : 0);
