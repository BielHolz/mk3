<?php
require "common.php";
admin_required();
$raw = input($_GET, "id");
$id =
    $raw === ""
        ? 0
        : filter_var($raw, FILTER_VALIDATE_INT, [
            "options" => ["min_range" => 1],
        ]);
if ($id === false) {
    abort_request(400, "Identificador inválido.");
}
$v = [];
if ($id) {
    $st = db()->prepare("SELECT * FROM vehicles WHERE id=?");
    $st->execute([$id]);
    $v = $st->fetch();
    if (!$v) {
        abort_request(404, "Veículo não encontrado.");
    }
}
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    try {
        $values = vehicle_values($_POST);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($id) {
                $lock = $pdo->prepare(
                    "SELECT id FROM vehicles WHERE id=? FOR UPDATE",
                );
                $lock->execute([$id]);
                if (!$lock->fetchColumn()) {
                    throw new InvalidArgumentException(
                        "Veículo removido por outra sessão.",
                    );
                }
                $sql =
                    "UPDATE vehicles SET " .
                    implode(
                        ",",
                        array_map(fn($key) => $key . "=?", array_keys($values)),
                    ) .
                    " WHERE id=?";
                $pdo->prepare($sql)->execute([...array_values($values), $id]);
            } else {
                $sql =
                    "INSERT INTO vehicles (" .
                    implode(",", array_keys($values)) .
                    ") VALUES (" .
                    implode(",", array_fill(0, count($values), "?")) .
                    ")";
                $pdo->prepare($sql)->execute(array_values($values));
                $id = (int) $pdo->lastInsertId();
            }
            $changes = [];
            foreach ($values as $k => $val) {
                if ((string) ($v[$k] ?? "") !== (string) ($val ?? "")) {
                    $changes[] = $k;
                }
            }
            admin_log(
                $v ? "update-vehicle" : "create-vehicle",
                "#" . $id . " • " . implode(", ", $changes),
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        go("edit.php?id=" . $id . "&saved=1");
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        $error = $e->getMessage();
        $_POST["featured"] = input($_POST, "featured") === "1" ? "1" : "0";
        $v = array_replace(
            $v,
            array_map(fn($x) => is_scalar($x) ? (string) $x : "", $_POST),
        );
    }
}
admin_top($id ? "Editar veículo #" . $id : "Novo veículo");
flash_error($error);
if (
    isset($_GET["saved"])
): ?><div class="alert ok" role="status">Veículo salvo. As alterações já estão disponíveis no site conforme a situação escolhida.</div><?php endif;
?>
<form method="post" class="userbox"><?= token_field() ?><div class="section-head compact"><h2>Informações do anúncio</h2><?php if (
    $id &&
    in_array($v["status"], ["available", "reserved"], true)
): ?><a class="text-link" href="../veiculo.php?id=<?= $id ?>" target="_blank" rel="noopener">Ver no site ↗</a><?php endif; ?></div><div class="form-grid">
<?php foreach (
    [
        "title" => ["Título do anúncio *", 160],
        "brand" => ["Marca *", 100],
        "model" => ["Modelo *", 120],
        "version" => ["Versão", 120],
        "transmission" => ["Câmbio", 60],
        "fuel" => ["Combustível", 60],
        "color" => ["Cor", 60],
    ]
    as $key => [$label, $max]
): ?><label><?= h(
    $label,
) ?><input name="<?= $key ?>" maxlength="<?= $max ?>" value="<?= h(
    $v[$key] ?? "",
) ?>" <?= in_array($key, ["title", "brand", "model"], true)
    ? "required"
    : "" ?>></label><?php endforeach; ?>
<?php foreach (
    [
        "manufacture_year" => ["Ano de fabricação", 1900, (int) date("Y") + 2],
        "model_year" => ["Ano do modelo", 1900, (int) date("Y") + 2],
        "mileage" => ["Quilometragem", 0, 10000000],
        "featured_order" => ["Ordem no carrossel (menor primeiro)", 0, 9999],
    ]
    as $key => [$label, $min, $max]
): ?><label><?= h(
    $label,
) ?><input name="<?= $key ?>" type="number" min="<?= $min ?>" max="<?= $max ?>" value="<?= h(
    $v[$key] ?? "",
) ?>"></label><?php endforeach; ?>
<label>Preço (R$)<input type="number" step="0.01" min="0" max="99999999.99" name="price" value="<?= h(
    $v["price"] ?? "",
) ?>" placeholder="Em branco = sob consulta"></label><label>Situação<select name="status"><?php foreach (
    status_labels()
    as $key => $label
): ?><option value="<?= $key ?>" <?= ($v["status"] ?? "available") === $key
    ? "selected"
    : "" ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
<label class="checkbox-label"><input type="checkbox" name="featured" value="1" <?= !empty(
    $v["featured"]
)
    ? "checked"
    : "" ?>> Destacar na página inicial</label><label class="span-all">Descrição detalhada<textarea name="description" maxlength="8000" rows="6"><?= h(
    $v["description"] ?? "",
) ?></textarea></label><label class="span-all">Características / equipamentos (um por linha)<textarea name="features" maxlength="4000" rows="5" placeholder="Informe apenas equipamentos confirmados do veículo"><?= h(
    $v["features"] ?? "",
) ?></textarea></label></div><p class="fineprint">Reservados continuam visíveis e identificados. Vendidos e ocultos saem do catálogo e do carrossel.</p><button class="button red">Salvar veículo</button></form>
<?php
if ($id):
    $photos = car_photos(
        $id,
    ); ?><section class="userbox" id="fotos"><div class="section-head compact"><h2>Fotografias</h2><span class="pill"><?= count(
    $photos,
) ?> / 12</span></div><?php if (
     isset($_GET["photos"])
 ): ?><div class="alert ok" role="status">Galeria atualizada.</div><?php endif; ?><p class="fineprint">Até 12 fotos, 5 MB e 16 megapixels por arquivo. JPG, PNG ou WebP. As imagens são convertidas para WebP em 16:9, sem distorcer ou cortar o veículo. A primeira foto é a capa. Você pode organizar a seleção antes de enviar.</p><form method="post" action="photo.php" enctype="multipart/form-data" class="upload-form"><?= token_field() ?><input type="hidden" name="vehicle_id" value="<?= $id ?>"><input type="hidden" name="action" value="upload"><label>Adicionar várias fotos<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required data-preview="upload-preview" data-max-files="<?= max(0, 12 - count($photos)) ?>"></label><div id="upload-preview" class="upload-preview" aria-live="polite"></div><button class="button outline">Enviar fotografias</button></form><div class="photo-grid"><?php foreach (
    $photos
    as $i => $photo
): ?><article class="photo-tile"><img src="../<?= h(
    public_thumbnail($photo["path"]),
) ?>" alt="Fotografia <?= $i +
    1 ?>" width="480" height="270"><span class="pill"><?= $i === 0
    ? "Capa"
    : "Foto " . ($i + 1) ?></span><div class="photo-actions"><?php foreach (
    [
        "cover" => "Tornar capa",
        "up" => "← Mover antes",
        "down" => "Mover depois →",
        "remove" => "Remover",
    ]
    as $action => $label
): ?><form method="post" action="photo.php" <?= $action === "remove"
    ? 'data-confirm="Remover esta fotografia?"'
    : "" ?>><?= token_field() ?><input type="hidden" name="vehicle_id" value="<?= $id ?>"><input type="hidden" name="photo_id" value="<?= (int) $photo[
    "id"
] ?>"><input type="hidden" name="action" value="<?= $action ?>"><button class="button-sm <?= $action ===
"remove"
    ? "danger"
    : "" ?>" <?= ($action === "up" && $i === 0) ||
($action === "down" && $i === count($photos) - 1)
    ? "disabled"
    : "" ?>><?= h(
    $label,
) ?></button></form><?php endforeach; ?></div><details><summary>Substituir foto</summary><form method="post" action="photo.php" enctype="multipart/form-data"><?= token_field() ?><input type="hidden" name="vehicle_id" value="<?= $id ?>"><input type="hidden" name="photo_id" value="<?= (int) $photo[
    "id"
] ?>"><input type="hidden" name="action" value="replace"><label>Nova imagem<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label><button class="button-sm">Substituir</button></form></details></article><?php endforeach; ?></div></section><section class="userbox"><h2>Histórico deste veículo</h2><ol class="activity-list"><?php
$st = db()->prepare(
    "SELECT action,target,created_at FROM audit_logs WHERE target=? OR target LIKE ? ORDER BY id DESC LIMIT 20",
);
$st->execute(["#" . $id, "#" . $id . " •%"]);
foreach ($st as $log): ?><li><time><?= h(
    display_date($log["created_at"]),
) ?></time><span><?= h(audit_action($log["action"])) ?> <?= h(
     audit_target($log["target"]),
 ) ?></span></li><?php endforeach;
?></ol></section><?php
endif;
admin_bottom();
 ?>
