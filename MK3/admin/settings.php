<?php
require "common.php";
require dirname(__DIR__) . "/app/media.php";
admin_required();
$s = settings();
$error = "";
$fields = [
    "name" => ["Nome da loja", 120],
    "phone" => ["Telefone", 30],
    "whatsapp" => ["WhatsApp (país + DDD + número)", 15],
    "address" => ["Endereço", 500],
    "hours" => ["Horários de atendimento", 500],
    "instagram" => ["Instagram (URL HTTPS)", 200],
    "about" => ["Texto institucional", 4000],
    "hero_text" => ["Texto de abertura", 500],
    "seo_title" => ["Título SEO", 100],
    "seo_description" => ["Descrição SEO", 160],
];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    $created = [];
    $old = [];
    $values = [];
    try {
        if (input($_POST, "confirmed") !== "1") {
            throw new InvalidArgumentException(
                "Confirme que as informações institucionais foram autorizadas pela loja.",
            );
        }
        foreach ($fields as $key => [$label, $max]) {
            $values[$key] = input($_POST, $key);
            if (mb_strlen($values[$key]) > $max) {
                throw new InvalidArgumentException(
                    $label . ": limite de " . $max . " caracteres.",
                );
            }
        }
        if ($values["name"] === "" || $values["seo_title"] === "") {
            throw new InvalidArgumentException(
                "Informe o nome da loja e o título SEO.",
            );
        }
        if (!preg_match('/^[0-9]{10,15}$/D', $values["whatsapp"])) {
            throw new InvalidArgumentException(
                "WhatsApp deve conter de 10 a 15 dígitos, incluindo país e DDD.",
            );
        }
        if (
            $values["phone"] !== "" &&
            !preg_match('/^[+0-9() .-]{8,30}$/D', $values["phone"])
        ) {
            throw new InvalidArgumentException("Telefone inválido.");
        }
        if (
            $values["instagram"] !== "" &&
            !preg_match(
                '~^https://(?:www\.)?instagram\.com/[a-zA-Z0-9._]+/?$~D',
                $values["instagram"],
            )
        ) {
            throw new InvalidArgumentException(
                "Use o endereço HTTPS do perfil no Instagram.",
            );
        }
        foreach (["logo", "hero_image"] as $key) {
            $files = upload_files($key);
            if ($files) {
                if (count($files) !== 1) {
                    throw new InvalidArgumentException(
                        "Envie uma imagem por campo.",
                    );
                }
                $values[$key] = upload_image($files[0]);
                $created[] = $values[$key];
            }
        }
        if (input($_POST, "reset_logo") === "1" && !isset($values["logo"])) {
            $values["logo"] = "assets/logo-mk3-oficial.png";
        }
        if (
            input($_POST, "clear_hero") === "1" &&
            !isset($values["hero_image"])
        ) {
            $values["hero_image"] = "";
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Bloqueio compartilhado entre administradores evita perder a referência da mídia substituída.
            if (
                !(int) $pdo
                    ->query("SELECT GET_LOCK('mk3_settings',5)")
                    ->fetchColumn()
            ) {
                throw new RuntimeException("Configuração em uso.");
            }
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO store_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                );
                foreach ($values as $key => $val) {
                    if (in_array($key, ["logo", "hero_image"], true)) {
                        $q = $pdo->prepare(
                            "SELECT setting_value FROM store_settings WHERE setting_key=?",
                        );
                        $q->execute([$key]);
                        $old[] = (string) $q->fetchColumn();
                    }
                    $stmt->execute([$key, $val]);
                }
                admin_log("update-store", "Dados institucionais confirmados");
                $pdo->commit();
            } finally {
                $pdo->query("SELECT RELEASE_LOCK('mk3_settings')");
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        foreach ($old as $path) {
            remove_image($path);
        }
        go("settings.php?saved=1");
    } catch (Throwable $e) {
        http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
        foreach ($created as $path) {
            remove_image($path);
        }
        $s = array_replace($s, array_intersect_key($values, $fields));
        $error =
            $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : "Não foi possível salvar as configurações. Tente novamente.";
        if (!($e instanceof InvalidArgumentException)) {
            error_log("MK3 settings: " . get_class($e));
        }
    }
}
admin_top("Configurações da loja");
flash_error($error);
if (
    isset($_GET["saved"])
): ?><div class="alert ok" role="status">Configurações atualizadas no site.</div><?php endif;
?><form class="userbox" method="post" enctype="multipart/form-data"><?= token_field() ?><h2>Identidade e atendimento</h2><p class="muted">Altere apenas dados aprovados pela MK3. A logo original permanece preservada nos arquivos do projeto.</p><div class="form-grid"><?php foreach (
    $fields
    as $key => [$label, $max]
): ?><label class="<?= in_array(
    $key,
    ["about", "hero_text", "seo_description"],
    true,
)
    ? "span-all"
    : "" ?>"><?=
h($label); ?><?php if (
    in_array($key, ["about", "hours", "hero_text", "seo_description"], true)
): ?><textarea name="<?= $key ?>" maxlength="<?= $max ?>" rows="3"><?= h(
    $s[$key],
) ?></textarea><?php else: ?><input name="<?= $key ?>" maxlength="<?= $max ?>" value="<?= h(
    $s[$key],
) ?>" <?= in_array($key, ["name", "whatsapp", "seo_title"], true)
    ? "required"
    : "" ?> <?= $key === "instagram" ? 'type="url"' : "" ?>><?php endif;
?></label><?php endforeach; ?></div><h2>Imagens institucionais</h2><div class="form-grid"><div><img class="settings-logo" src="../<?= h(
    photo_path($s["logo"]),
) ?>" alt="Logo atual" width="220" height="120"><label>Nova logo (JPG, PNG ou WebP)<input type="file" name="logo" accept="image/jpeg,image/png,image/webp" data-preview="logo-preview"></label><div id="logo-preview" class="upload-preview"></div><label class="checkbox-label"><input type="checkbox" name="reset_logo" value="1"> Restaurar logo original</label></div><div><label>Imagem de abertura sem destaques<input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp" data-preview="hero-preview"></label><div id="hero-preview" class="upload-preview"></div><?php if (
    $s["hero_image"]
): ?><img class="settings-hero" src="../<?= h(
    photo_path($s["hero_image"]),
) ?>" width="300" height="180" alt="Imagem de abertura atual"><?php endif; ?><label class="checkbox-label"><input type="checkbox" name="clear_hero" value="1"> Remover imagem de abertura</label></div></div><label class="checkbox-label confirm-label"><input type="checkbox" name="confirmed" value="1" required> Confirmo que estas informações e imagens foram autorizadas pela loja.</label><button class="button red">Salvar configurações</button></form><?php admin_bottom(); ?>
