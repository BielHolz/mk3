<?php
require __DIR__ . "/app/core.php";
require __DIR__ . "/app/layout.php";
$s = settings();
$catalog = catalog($_GET);
$featured = db()
    ->query(
        "SELECT v.*,(SELECT path FROM photos WHERE vehicle_id=v.id ORDER BY position,id LIMIT 1) cover FROM vehicles v WHERE status IN ('available','reserved') AND featured=1 ORDER BY featured_order,id DESC LIMIT 12",
    )
    ->fetchAll();
$slides = array_map(
    fn($v) => [
        "src" => photo_path($v["cover"]),
        "title" => $v["title"],
        "link" => "veiculo.php?id=" . $v["id"],
    ],
    $featured,
);
$hero = $slides[0] ?? [
    "src" => photo_path($s["hero_image"]),
    "title" => "Conheça nosso estoque",
    "link" => "#estoque",
];
$facets = [];
foreach (["brand", "fuel", "transmission"] as $field) {
    $facets[$field] = db()
        ->query(
            "SELECT DISTINCT $field FROM vehicles WHERE status IN ('available','reserved') AND $field<>'' ORDER BY $field",
        )
        ->fetchAll(PDO::FETCH_COLUMN);
}
site_head($s["seo_title"]);
?><main id="conteudo"><section class="hero" id="inicio" aria-label="Veículos em destaque" aria-roledescription="carrossel"><div class="hero-glow"></div><div class="wrap hero-content"><div class="hero-copy"><div class="eyebrow">MK3 Multimarcas • Joinville / SC</div><h1>Seu sonho.<br><em>Nossa</em><br>paixão.</h1><p><?= h(
    $s["hero_text"],
) ?></p><div class="hero-actions"><a href="#estoque" class="button red">Explorar estoque ↗</a><a href="<?= h(
    wa("um veículo"),
) ?>" class="button outline" target="_blank" rel="noopener noreferrer">Fale com a MK3</a></div></div><div class="hero-visual" id="hero-stage"><div class="hero-halo"></div><a class="hero-frame" id="hero-link" href="<?= h(
    $hero["link"],
) ?>"><img id="hero-img" src="<?= h($hero["src"]) ?>" alt="<?= h(
    $hero["title"],
) ?>" width="900" height="640" fetchpriority="high"><img id="hero-next" src="<?= h(
    $hero["src"],
) ?>" alt="" width="900" height="640" aria-hidden="true"><span class="hero-label" id="hero-name"><?= h(
    $hero["title"],
) ?></span></a><div class="hero-controls"><span class="slide-count"><strong id="hero-index"><?= count(
    $slides,
)
    ? "01"
    : "00" ?></strong> / <?= str_pad(
    (string) count($slides),
    2,
    "0",
    STR_PAD_LEFT,
) ?></span><div id="hero-dots" class="hero-dots" aria-label="Escolher destaque"></div><div class="arrow-group"><button type="button" id="hero-prev" aria-label="Destaque anterior" <?= count(
    $slides,
) < 2
    ? "disabled"
    : "" ?>>←</button><button type="button" id="hero-pause" aria-label="Pausar troca automática" aria-pressed="false" <?= count(
    $slides,
) < 2
    ? "disabled"
    : "" ?>>Ⅱ</button><button type="button" id="hero-forward" aria-label="Próximo destaque" <?= count(
    $slides,
) < 2
    ? "disabled"
    : "" ?>>→</button></div></div><p class="fineprint" id="hero-status" aria-live="polite"><?= count(
    $slides,
)
    ? "Destaques selecionados pela loja."
    : "Novos destaques em breve. Explore o estoque disponível." ?></p></div></div></section>
<div class="divider-band"><div class="wrap stats"><?php foreach (
    [
        "Compra" => "Seu próximo carro está aqui",
        "Venda" => "Converse com nossa equipe",
        "Troca" => "Negocie seu usado",
        "Financia" => "Consulte as condições",
    ]
    as $label => $text
): ?><div><strong><?= h($label) ?></strong><span><?= h(
    $text,
) ?></span></div><?php endforeach; ?></div></div>
<section class="section wrap" id="estoque"><div class="section-head"><div><div class="eyebrow">Estoque atualizado</div><h2>Escolha seu<br><em>próximo carro.</em></h2></div><p class="section-lead">Encontre o veículo para sua próxima história. Consulte os detalhes e converse com a equipe.</p></div>
<form method="get" action="index.php#estoque" class="inventory-filters" role="search" aria-label="Filtrar estoque"><label class="search-field">Buscar veículo<input type="search" name="q" value="<?= h(
    input($_GET, "q"),
) ?>" maxlength="100" placeholder="Nome, marca, modelo ou versão"></label><?php
foreach (
    ["brand" => "Marca", "fuel" => "Combustível", "transmission" => "Câmbio"]
    as $key => $label
): ?><label><?= h($label) ?><select name="<?= h(
    $key,
) ?>"><option value="">Todos</option><?php foreach (
    $facets[$key]
    as $value
): ?><option value="<?= h($value) ?>" <?= input($_GET, $key) === $value
    ? "selected"
    : "" ?>><?= h(
    $value,
) ?></option><?php endforeach; ?></select></label><?php endforeach;
foreach (
    [
        "min_price" => 'Preço mínimo (R$)',
        "max_price" => 'Preço máximo (R$)',
        "min_year" => "Ano mínimo",
        "max_year" => "Ano máximo",
    ]
    as $key => $label
): ?><label><?= h($label) ?><input type="number" min="<?= str_contains(
    $key,
    "year",
)
    ? "1900"
    : "0" ?>" max="<?= str_contains($key, "year")
    ? "2200"
    : "99999999" ?>" name="<?= h($key) ?>" value="<?= h(
    input($_GET, $key),
) ?>"></label><?php endforeach;
?><label>Ordenar por<select name="sort"><?php foreach (
    [
        "recent" => "Mais recentes",
        "price_asc" => "Menor preço",
        "price_desc" => "Maior preço",
        "year" => "Ano mais novo",
    ]
    as $k => $label
): ?><option value="<?= $k ?>" <?= input($_GET, "sort", "recent") === $k
    ? "selected"
    : "" ?>><?= h(
    $label,
) ?></option><?php endforeach; ?></select></label><div class="filter-actions"><button class="button red">Filtrar estoque</button><a href="index.php#estoque" class="text-link">Limpar filtros</a></div></form>
<p class="muted" role="status"><?= $catalog[
    "total"
] ?> veículo(s) encontrado(s)</p><div class="inventory-grid" id="stock-grid"><?php foreach (
     $catalog["rows"]
     as $v
 ) {
     vehicle_card($v);
 } ?></div><?php if (
    !$catalog["total"]
): ?><div class="empty-state"><h3>Nenhum veículo encontrado.</h3><p>Experimente outros filtros ou fale com a equipe.</p><a class="text-link" href="<?= h(
    wa("o estoque"),
) ?>" target="_blank" rel="noopener noreferrer">Consultar a loja ↗</a></div><?php endif; ?>
<?php if (
    $catalog["pages"] > 1
): ?><nav class="pagination" aria-label="Páginas do estoque"><?php for (
    $p = max(1, $catalog["page"] - 2);
    $p <= min($catalog["pages"], $catalog["page"] + 2);
    $p++
):

    $query = [];
    foreach (
        [
            "q",
            "brand",
            "fuel",
            "transmission",
            "min_price",
            "max_price",
            "min_year",
            "max_year",
            "sort",
        ]
        as $key
    ) {
        if (input($_GET, $key) !== "") {
            $query[$key] = input($_GET, $key);
        }
    }
    $query["page"] = $p;
    ?><a href="?<?= h(http_build_query($query)) ?>#estoque" <?= $p ===
$catalog["page"]
    ? 'aria-current="page"'
    : "" ?>><?= $p ?></a><?php
endfor; ?></nav><?php endif; ?></section>
<section class="section about" id="sobre"><div class="wrap about-layout"><div class="about-brand reveal"><img src="assets/logo-mk3-oficial.png" width="460" height="260" alt="Logo original MK3 Multimarcas"><span>Seu sonho. Nossa paixão.</span></div><div class="reveal"><div class="eyebrow">Conheça a MK3</div><h2>Seu novo<br><em>caminho.</em></h2><p class="body-copy"><?= nl2br(
    h($s["about"]),
) ?></p><a class="button red" href="<?= h(
    wa("uma negociação"),
) ?>" target="_blank" rel="noopener noreferrer">Conversar com a equipe ↗</a></div></div></section>
<section class="section wrap" id="contato"><div class="section-head"><div><div class="eyebrow">Vamos conversar?</div><h2>Sua próxima<br><em>história começa aqui.</em></h2></div><a class="button red" href="<?= h(
    wa("um carro"),
) ?>" target="_blank" rel="noopener noreferrer">Atendimento WhatsApp ↗</a></div><div class="contact-grid"><div><h3>Visite a loja</h3><p><?= nl2br(
    h($s["address"]),
) ?></p></div><div><h3>Horários</h3><p><?= nl2br(
    h($s["hours"]),
) ?></p></div><div><h3>Fale com a MK3</h3><?php
if ($s["phone"]): ?><p><a href="tel:<?= h(
    preg_replace("/[^+0-9]/", "", $s["phone"]),
) ?>"><?= h($s["phone"]) ?></a></p><?php endif;
if ($s["instagram"]): ?><a class="text-link" href="<?= h(
    $s["instagram"],
) ?>" target="_blank" rel="noopener noreferrer">Instagram ↗</a><?php endif;
?></div></div></section></main>
<script type="application/json" id="slide-data" nonce="<?= h(
    nonce(),
) ?>"><?= json_encode(
    $slides,
    JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_UNESCAPED_UNICODE |
        JSON_THROW_ON_ERROR,
) ?></script><script src="assets/site.js" defer></script><?php site_end(); ?>
