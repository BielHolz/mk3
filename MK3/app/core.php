<?php
declare(strict_types=1);
$config = require __DIR__ . "/config.php";
$local = __DIR__ . "/config.local.php";
if (is_file($local)) {
    $config = array_replace($config, require $local);
}
foreach (
    ["db_host", "db_port", "db_name", "db_user", "db_pass", "local_http"]
    as $key
) {
    $value = getenv("MK3_" . strtoupper($key));
    if ($value !== false) {
        $config[$key] =
            $key === "local_http"
                ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
                : $value;
    }
}
ini_set("display_errors", "0");
ini_set("log_errors", "1");
error_reporting(E_ALL);
set_exception_handler(function (Throwable $e): void {
    error_log(
        "MK3 " .
            get_class($e) .
            " at " .
            basename($e->getFile()) .
            ":" .
            $e->getLine(),
    );
    http_response_code(500);
    if (PHP_SAPI === "cli") {
        fwrite(STDERR, "Falha interna. Verifique configuração e migrações.\n");
        exit(1);
    }
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Serviço indisponível</title><h1>Não foi possível concluir a solicitação.</h1><p>Tente novamente em alguns instantes.</p></html>';
});
if (PHP_SAPI !== "cli") {
    session_name("MK3_SESSION");
    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => !$config["local_http"],
        "httponly" => true,
        "samesite" => "Strict",
    ]);
    session_start();
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header(
        "Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" .
            nonce() .
            "'; style-src 'self'; img-src 'self' data: blob:; font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'",
    );
    if (!$config["local_http"]) {
        header(
            "Strict-Transport-Security: max-age=31536000; includeSubDomains",
        );
    }
}
function nonce(): string
{
    static $n;
    return $n ??= base64_encode(random_bytes(18));
}
function db(): PDO
{
    static $pdo;
    global $config;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO(
        "mysql:host=" .
            $config["db_host"] .
            ";port=" .
            $config["db_port"] .
            ";dbname=" .
            $config["db_name"] .
            ";charset=utf8mb4",
        $config["db_user"],
        $config["db_pass"],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}
function h(mixed $s): string
{
    return htmlspecialchars(
        (string) ($s ?? ""),
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8",
    );
}
function input(array $source, string $key, string $default = ""): string
{
    return isset($source[$key]) && is_scalar($source[$key])
        ? trim((string) $source[$key])
        : $default;
}
function raw_input(array $source, string $key): string
{
    return isset($source[$key]) && is_string($source[$key])
        ? $source[$key]
        : "";
}
function csrf(): string
{
    return $_SESSION["csrf"] ??= bin2hex(random_bytes(32));
}
function check_csrf(): void
{
    if (!hash_equals(csrf(), input($_POST, "csrf"))) {
        abort_request(
            403,
            "Sessão expirada. Recarregue a página e tente novamente.",
        );
    }
}
function token_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
}
function abort_request(int $code, string $message): never
{
    http_response_code($code);
    echo h($message);
    exit();
}
function require_post(): void
{
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        header("Allow: POST");
        abort_request(405, "Método não permitido.");
    }
    check_csrf();
}
function go(string $location): never
{
    header("Location: " . $location, true, 303);
    exit();
}
function logged(): bool
{
    if (
        empty($_SESSION["admin_id"]) ||
        time() - ($_SESSION["last_seen"] ?? 0) > 1800
    ) {
        return false;
    }
    $s = db()->prepare("SELECT session_version FROM users WHERE id=?");
    $s->execute([$_SESSION["admin_id"]]);
    $version = $s->fetchColumn();
    if (
        $version === false ||
        (int) $version !== (int) ($_SESSION["session_version"] ?? 0)
    ) {
        return false;
    }
    $_SESSION["last_seen"] = time();
    return true;
}
function admin_required(): void
{
    header("Cache-Control: no-store");
    if (!logged()) {
        unset($_SESSION["admin_id"]);
        go("login.php");
    }
}
function admin_log(string $action, string $target = ""): void
{
    if (empty($_SESSION["admin_id"])) {
        return;
    }
    $s = db()->prepare(
        "INSERT INTO audit_logs(user_id,action,target,ip) VALUES(?,?,?,?)",
    );
    $s->execute([
        $_SESSION["admin_id"],
        mb_substr($action, 0, 80),
        mb_substr($target, 0, 255),
        substr($_SERVER["REMOTE_ADDR"] ?? "cli", 0, 45),
    ]);
}
function money(mixed $v): string
{
    return $v === null || $v === ""
        ? "Sob consulta"
        : 'R$ ' . number_format((float) $v, 2, ",", ".");
}
function status_labels(): array
{
    return [
        "available" => "Disponível",
        "reserved" => "Reservado",
        "sold" => "Vendido",
        "hidden" => "Oculto",
    ];
}
function photo_path(mixed $path): string
{
    return is_string($path) &&
        preg_match(
            '~^(uploads/[a-f0-9]{32}(?:-thumb)?\.(?:jpg|png|webp)|assets/[a-z0-9-]+\.(?:jpg|png|webp))$~D',
            $path,
        )
        ? $path
        : "assets/placeholder.svg";
}
function car_photos(int $id): array
{
    $s = db()->prepare(
        "SELECT * FROM photos WHERE vehicle_id=? ORDER BY position,id",
    );
    $s->execute([$id]);
    return $s->fetchAll();
}
function cover(int $id): string
{
    $p = car_photos($id);
    return photo_path($p[0]["path"] ?? null);
}
function active_vehicle(int $id): ?array
{
    $s = db()->prepare(
        "SELECT * FROM vehicles WHERE id=? AND status IN ('available','reserved')",
    );
    $s->execute([$id]);
    return $s->fetch() ?: null;
}
function settings(): array
{
    static $settings;
    if ($settings !== null) {
        return $settings;
    }
    global $config;
    $settings = [
        "name" => "MK3 Multimarcas",
        "logo" => "assets/logo-mk3-oficial.png",
        "phone" => "",
        "whatsapp" => $config["whatsapp"],
        "address" => "Rua São Paulo, 3132 • Itaum • Joinville / SC",
        "hours" => "Consulte os horários com a equipe.",
        "instagram" => "",
        "about" =>
            "Compra, venda, troca e financiamento de veículos em Joinville/SC. Converse com a equipe sobre o carro que combina com você.",
        "hero_text" =>
            "Não é apenas sobre encontrar um carro. É sobre encontrar a próxima história que você vai viver.",
        "seo_title" => "MK3 Multimarcas • Joinville / SC",
        "seo_description" =>
            "MK3 Multimarcas em Joinville/SC: conheça veículos e fale com nossa equipe.",
        "hero_image" => "",
    ];
    foreach (
        db()->query("SELECT setting_key,setting_value FROM store_settings")
        as $row
    ) {
        if (array_key_exists($row["setting_key"], $settings)) {
            $settings[$row["setting_key"]] = $row["setting_value"];
        }
    }
    return $settings;
}
function wa(string $name = ""): string
{
    $s = settings();
    return "https://wa.me/" .
        preg_replace("/\D/", "", $s["whatsapp"]) .
        "?text=" .
        rawurlencode(
            "Olá! Vi no site da " .
                $s["name"] .
                " e gostaria de mais informações sobre " .
                $name .
                ".",
        );
}
function vehicle_year(array $v): string
{
    return !empty($v["model_year"])
        ? ($v["manufacture_year"] ?? null ?: "—") . "/" . $v["model_year"]
        : ($v["year_model"] ?? "" ?:
            "Sob consulta");
}
function vehicle_values(array $p): array
{
    $v = [];
    foreach (
        [
            "title" => 160,
            "brand" => 100,
            "model" => 120,
            "version" => 120,
            "transmission" => 60,
            "fuel" => 60,
            "color" => 60,
            "description" => 8000,
            "features" => 4000,
        ]
        as $key => $limit
    ) {
        $v[$key] = input($p, $key);
        if (mb_strlen($v[$key]) > $limit) {
            throw new InvalidArgumentException(
                "O campo " . $key . " excedeu o limite de caracteres.",
            );
        }
    }
    if (!$v["title"] || !$v["brand"] || !$v["model"]) {
        throw new InvalidArgumentException("Informe título, marca e modelo.");
    }
    foreach (
        ["manufacture_year", "model_year", "mileage", "featured_order"]
        as $key
    ) {
        $raw = input($p, $key);
        $max = match ($key) {
            "mileage" => 10000000,
            "featured_order" => 9999,
            default => (int) date("Y") + 2,
        };
        $min = in_array($key, ["manufacture_year", "model_year"], true)
            ? 1900
            : 0;
        $v[$key] =
            $raw === ""
                ? null
                : filter_var($raw, FILTER_VALIDATE_INT, [
                    "options" => ["min_range" => $min, "max_range" => $max],
                ]);
        if ($v[$key] === false) {
            throw new InvalidArgumentException(
                "Confira os anos, quilometragem e ordem dos destaques.",
            );
        }
    }
    if (
        $v["manufacture_year"] &&
        $v["model_year"] &&
        $v["model_year"] < $v["manufacture_year"]
    ) {
        throw new InvalidArgumentException(
            "Ano do modelo deve ser igual ou posterior ao de fabricação.",
        );
    }
    $raw = input($p, "price");
    if ($raw !== "" && !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $raw)) {
        throw new InvalidArgumentException(
            "Preço inválido. Use até duas casas decimais.",
        );
    }
    $v["price"] = $raw === "" ? null : $raw;
    $v["status"] = input($p, "status", "available");
    if (!array_key_exists($v["status"], status_labels())) {
        throw new InvalidArgumentException("Situação inválida.");
    }
    $v["featured"] = input($p, "featured") === "1" ? 1 : 0;
    $v["featured_order"] = $v["featured_order"] ?? 0;
    $v["year_model"] = $v["model_year"]
        ? ($v["manufacture_year"] ?? "") . "/" . $v["model_year"]
        : "";
    return $v;
}
function catalog(array $query): array
{
    $where = ["v.status IN ('available','reserved')"];
    $params = [];
    $q = mb_substr(input($query, "q"), 0, 100);
    if ($q !== "") {
        $where[] = "CONCAT_WS(' ',v.title,v.brand,v.model,v.version) LIKE ?";
        $params[] =
            "%" . str_replace(["!", "%", "_"], ["!!", "!%", "!_"], $q) . "%";
        $where[count($where) - 1] .= " ESCAPE '!'";
    }
    foreach (["brand", "fuel", "transmission"] as $k) {
        if (input($query, $k) !== "") {
            $where[] = "v." . $k . "=?";
            $params[] = mb_substr(input($query, $k), 0, 100);
        }
    }
    foreach (
        [
            "min_price" => ["price", ">=", 0, 99999999],
            "max_price" => ["price", "<=", 0, 99999999],
            "min_year" => ["model_year", ">=", 1900, 2200],
            "max_year" => ["model_year", "<=", 1900, 2200],
        ]
        as $key => [$col, $op, $min, $max]
    ) {
        $val = input($query, $key);
        if (
            $val !== "" &&
            is_numeric($val) &&
            (float) $val >= $min &&
            (float) $val <= $max
        ) {
            $where[] = "v.$col $op ?";
            $params[] = $val;
        }
    }
    $orders = [
        "recent" => "v.created_at DESC,v.id DESC",
        "price_asc" => "v.price IS NULL,v.price ASC,v.id DESC",
        "price_desc" => "v.price IS NULL,v.price DESC,v.id DESC",
        "year" => "v.model_year IS NULL,v.model_year DESC,v.id DESC",
    ];
    $order = $orders[input($query, "sort")] ?? $orders["recent"];
    $clause = implode(" AND ", $where);
    $s = db()->prepare("SELECT COUNT(*) FROM vehicles v WHERE " . $clause);
    $s->execute($params);
    $total = (int) $s->fetchColumn();
    $pages = max(1, (int) ceil($total / 9));
    $page = min($pages, max(1, (int) input($query, "page", "1")));
    $offset = ($page - 1) * 9;
    $s = db()->prepare(
        "SELECT v.*, (SELECT path FROM photos WHERE vehicle_id=v.id ORDER BY position,id LIMIT 1) cover FROM vehicles v WHERE $clause ORDER BY $order LIMIT 9 OFFSET $offset",
    );
    $s->execute($params);
    return [
        "rows" => $s->fetchAll(),
        "total" => $total,
        "page" => $page,
        "pages" => $pages,
    ];
}
function audit_action(string $action): string
{
    return [
        "login" => "Entrada no painel",
        "logout" => "Saída do painel",
        "change-password" => "Senha atualizada",
        "create-vehicle" => "Veículo cadastrado",
        "update-vehicle" => "Veículo atualizado",
        "delete-vehicle" => "Veículo excluído",
        "photo-upload" => "Fotografias enviadas",
        "photo-cover" => "Capa alterada",
        "photo-up" => "Fotografias reordenadas",
        "photo-down" => "Fotografias reordenadas",
        "photo-remove" => "Fotografia removida",
        "photo-replace" => "Fotografia substituída",
        "update-store" => "Configurações da loja atualizadas",
    ][$action] ?? "Atividade administrativa";
}
function audit_target(string $target): string
{
    $fields = [
        "title" => "título",
        "brand" => "marca",
        "model" => "modelo",
        "version" => "versão",
        "manufacture_year" => "ano de fabricação",
        "model_year" => "ano do modelo",
        "year_model" => "ano/modelo",
        "mileage" => "quilometragem",
        "featured_order" => "ordem do destaque",
        "price" => "preço",
        "transmission" => "câmbio",
        "fuel" => "combustível",
        "color" => "cor",
        "description" => "descrição",
        "features" => "equipamentos",
        "status" => "situação",
        "featured" => "destaque",
    ];
    return preg_replace_callback(
        "/\b(" . implode("|", array_keys($fields)) . ")\b/",
        fn($m) => $fields[$m[0]],
        $target,
    );
}
function display_date(string $date): string
{
    return new DateTimeImmutable($date, new DateTimeZone("UTC"))
        ->setTimezone(new DateTimeZone("America/Sao_Paulo"))
        ->format("d/m/Y H:i");
}
function public_thumbnail(string $path): string
{
    $path = photo_path($path);
    if (!str_starts_with($path, 'uploads/')) return $path;
    $thumb = preg_replace('/\.(jpg|png|webp)$/D', '-thumb.$1', $path);
    return is_file(dirname(__DIR__).'/'.$thumb) ? $thumb : $path;
}
