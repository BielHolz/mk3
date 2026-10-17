<?php
function site_head(string $title, ?string $description = null): void
{
    $s = settings(); ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><link rel="icon" type="image/png" href="assets/logo-mk3-oficial.png"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#090a0c"><meta name="description" content="<?= h($description ?? $s["seo_description"]) ?>"><meta property="og:title" content="<?= h($title . " | " . $s["name"]) ?>"><meta property="og:description" content="<?= h($description ?? $s["seo_description"]) ?>"><meta property="og:type" content="website"><title><?= h($title) ?> | <?= h($s["name"]) ?></title><link rel="stylesheet" href="assets/style.css"><script src="assets/common.js" defer></script></head><body><a href="#conteudo" class="skip-link">Ir para o conteúdo</a><div class="topline"></div><header class="site-header"><a href="index.php" aria-label="<?= h($s["name"]) ?> — início"><img src="<?= h(photo_path($s["logo"])) ?>" alt="<?= h($s["name"]) ?>" class="logo-official" width="154" height="76"></a><button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav">Menu <span aria-hidden="true">☰</span></button><nav class="main-nav" id="main-nav" aria-label="Navegação principal"><a href="index.php#inicio">Início</a><a href="index.php#estoque">Estoque</a><a href="index.php#sobre">Sobre</a><a href="index.php#contato">Contato</a><a class="nav-cta" href="<?= h(wa("um veículo")) ?>" target="_blank" rel="noopener noreferrer">WhatsApp ↗</a></nav></header><?php
}
function vehicle_card(array $v): void
{
    ?><article class="car-card reveal"><a class="car-photo" href="veiculo.php?id=<?= (int) $v[
    "id"
] ?>" aria-label="Ver <?= h($v["title"]) ?>"><img src="<?= h(photo_path($v["cover"] ?? null)) ?>" alt="<?= h($v["title"]) ?>" width="600" height="400" loading="lazy"><span class="car-tag"><?= h(status_labels()[$v["status"]]) ?></span></a><div class="car-content"><p class="eyebrow"><?= h($v["brand"]) ?></p><h3><a href="veiculo.php?id=<?= (int) $v["id"] ?>"><?= h($v["title"]) ?></a></h3><p class="muted"><?= h(trim($v["model"] . " " . ($v["version"] ?? ""))) ?></p><div class="car-specs"><span><?= h(vehicle_year($v)) ?></span><span><?= $v["mileage"] !== null ? number_format((int) $v["mileage"], 0, ",", ".") . " km" : "KM sob consulta" ?></span><span><?= h($v["transmission"] ?: "Câmbio sob consulta") ?></span></div><div class="card-bottom"><strong><?= h(money($v["price"])) ?></strong><a class="view-btn" href="veiculo.php?id=<?= (int) $v["id"] ?>">Detalhes ↗</a></div><a class="text-link" href="<?= h(wa($v["title"])) ?>" target="_blank" rel="noopener noreferrer">Consultar pelo WhatsApp ↗</a></div></article><?php
}
function site_end(): void
{
    $s = settings(); ?><footer class="site-footer"><div class="wrap footer-layout"><div><img src="<?= h(
    photo_path($s["logo"]),
) ?>" width="120" height="62" alt="<?= h($s["name"]) ?>"><p>© <?= date(
    "Y",
) ?> <?= h(
     $s["name"],
 ) ?></p></div><p>Informações, valores e disponibilidade<br>sujeitos a confirmação com a loja.</p><a href="admin/login.php">Área administrativa ↗</a></div></footer></body></html><?php
}
