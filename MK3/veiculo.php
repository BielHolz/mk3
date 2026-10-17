<?php
require __DIR__ . "/app/core.php";
require __DIR__ . "/app/layout.php";
$id = filter_var(input($_GET, "id"), FILTER_VALIDATE_INT, [
    "options" => ["min_range" => 1],
]);
$car = $id ? active_vehicle($id) : null;
if (!$car) {
    http_response_code(404);
    site_head("Veículo não encontrado");
    echo '<main class="wrap section" id="conteudo"><h1>Veículo não disponível</h1><p>Este anúncio não está disponível para consulta.</p><a class="button red" href="index.php#estoque">Voltar ao estoque</a></main>';
    site_end();
    exit();
}
$photos = car_photos($id);
$images = array_map(fn($p) => photo_path($p["path"]), $photos);
if (!$images) {
    $images = ["assets/placeholder.svg"];
}
site_head(
    $car["title"],
    mb_substr($car["description"] ?: settings()["seo_description"], 0, 160),
);
?><main class="wrap section" id="conteudo"><a href="index.php#estoque" class="text-link">← Voltar ao estoque</a><div class="vehicle-layout"><section class="vehicle-gallery" aria-label="Galeria do veículo"><div class="main-photo-wrap"><button type="button" id="zoom-open" aria-label="Ampliar fotografia"><img class="vehicle-main" id="main-photo" src="<?= h(
    $images[0],
) ?>" alt="<?= h(
    $car["title"],
) ?> — foto 1" width="1000" height="750"></button><div class="gallery-controls"><button type="button" data-gallery-step="-1" aria-label="Foto anterior" <?= count(
     $images,
 ) < 2
     ? "disabled"
     : "" ?>>←</button><span id="photo-count" aria-live="polite">1 / <?= count(
    $images,
) ?></span><button type="button" data-gallery-step="1" aria-label="Próxima foto" <?= count(
    $images,
) < 2
    ? "disabled"
    : "" ?>>→</button></div></div><p class="fineprint">Toque na foto para ampliar. Deslize para navegar.</p><div class="vehicle-thumbs"><?php foreach (
    $images
    as $i => $path
): ?><button type="button" data-photo="<?= h(
    $path,
) ?>" aria-label="Ver foto <?= $i + 1 ?>" aria-pressed="<?= $i === 0
    ? "true"
    : "false" ?>"><img src="<?= h(
    public_thumbnail($path),
) ?>" data-fallback="<?= h($path) ?>" alt="Miniatura <?= $i +
    1 ?>" width="120" height="90" loading="lazy"></button><?php endforeach; ?></div></section><section class="vehicle-summary"><span class="pill <?= h(
    $car["status"],
) ?>"><?= h(status_labels()[$car["status"]]) ?></span><p class="eyebrow"><?= h(
    $car["brand"],
) ?> • <?= h($car["model"]) ?></p><h1><?= h(
    $car["title"],
) ?></h1><p class="muted"><?= h(
    $car["version"],
) ?></p><p class="vehicle-price"><?= h(
    money($car["price"]),
) ?></p><dl class="vehicle-facts"><?php foreach (
    [
        "Ano / modelo" => vehicle_year($car),
        "Quilometragem" =>
            $car["mileage"] !== null
                ? number_format((int) $car["mileage"], 0, ",", ".") . " km"
                : "Sob consulta",
        "Combustível" => $car["fuel"],
        "Câmbio" => $car["transmission"],
        "Cor" => $car["color"],
    ]
    as $label => $value
): ?><div><dt><?= h($label) ?></dt><dd><?= h(
    $value ?: "Sob consulta",
) ?></dd></div><?php endforeach; ?></dl><a class="button red full" href="<?= h(
    wa($car["title"] . " (anúncio #" . $id . ")"),
) ?>" target="_blank" rel="noopener noreferrer">Tenho interesse • WhatsApp ↗</a><p class="fineprint">Confirme informações, valores e disponibilidade diretamente com a loja. <?= $car[
    "status"
] === "reserved"
    ? "Este veículo está reservado; consulte a equipe."
    : "" ?></p></section></div><section class="vehicle-description userbox"><h2>Conheça os detalhes</h2><p class="body-copy"><?= nl2br(
    h(
        $car["description"] ?:
        "Converse com a equipe para saber mais sobre este veículo.",
    ),
) ?></p><?php if (
    $car["features"]
): ?><h3>Características e equipamentos</h3><ul class="feature-list"><?php foreach (
    array_filter(explode("\n", $car["features"]), fn($f) => trim($f) !== "")
    as $feature
): ?><li><?= h(
    $feature,
) ?></li><?php endforeach; ?></ul><?php endif; ?></section>
<?php
$related = db()->prepare(
    "SELECT v.*,(SELECT path FROM photos WHERE vehicle_id=v.id ORDER BY position,id LIMIT 1) cover FROM vehicles v WHERE status='available' AND id<>? ORDER BY brand=? DESC,created_at DESC LIMIT 3",
);
$related->execute([$id, $car["brand"]]);
$rows = $related->fetchAll();
if (
    $rows
): ?><section class="related"><div class="section-head"><h2>Outros veículos</h2><a class="text-link" href="index.php#estoque">Ver estoque completo ↗</a></div><div class="inventory-grid"><?php foreach (
    $rows
    as $v
) {
    vehicle_card($v);
} ?></div></section><?php endif;
?></main><dialog id="photo-dialog" aria-label="Fotografia ampliada"><div class="lightbox-toolbar"><button type="button" data-gallery-step="-1" aria-label="Foto anterior">←</button><button type="button" id="zoom-close">Fechar ×</button><button type="button" data-gallery-step="1" aria-label="Próxima foto">→</button></div><img id="zoom-photo" src="<?= h(
    $images[0],
) ?>" alt="<?= h(
    $car["title"],
) ?> ampliado"></dialog><script src="assets/vehicle.js" defer></script><?php site_end(); ?>
