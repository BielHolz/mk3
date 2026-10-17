<?php
require "common.php";
admin_required();
$counts = db()
    ->query(
        "SELECT COUNT(*) total,COALESCE(SUM(status='available'),0) available,COALESCE(SUM(status='reserved'),0) reserved,COALESCE(SUM(status='sold'),0) sold,COALESCE(SUM(status='hidden'),0) hidden,COALESCE(SUM(featured=1),0) featured FROM vehicles",
    )
    ->fetch();
$where = [];
$params = [];
$q = mb_substr(input($_GET, "q"), 0, 100);
$status = input($_GET, "status");
if ($q !== "") {
    $where[] = "CONCAT_WS(' ',v.title,v.brand,v.model) LIKE ?";
    $params[] = "%" . $q . "%";
}
if (array_key_exists($status, status_labels())) {
    $where[] = "v.status=?";
    $params[] = $status;
}
$clause = $where ? "WHERE " . implode(" AND ", $where) : "";
$st = db()->prepare("SELECT COUNT(*) FROM vehicles v $clause");
$st->execute($params);
$total = (int) $st->fetchColumn();
$pages = max(1, (int) ceil($total / 20));
$page = min($pages, max(1, (int) input($_GET, "page", "1")));
$offset = ($page - 1) * 20;
$st = db()->prepare(
    "SELECT v.*,(SELECT path FROM photos p WHERE p.vehicle_id=v.id ORDER BY position,id LIMIT 1) cover FROM vehicles v $clause ORDER BY id DESC LIMIT 20 OFFSET $offset",
);
$st->execute($params);
$rows = $st->fetchAll();
$logs = db()
    ->query(
        "SELECT a.*,u.name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 15",
    )
    ->fetchAll();
admin_top("Visão geral");
?><div class="dashboard-stats"><?php foreach (
    [
        "total" => "Cadastrados",
        "available" => "Disponíveis",
        "reserved" => "Reservados",
        "sold" => "Vendidos",
        "hidden" => "Ocultos",
        "featured" => "Destaques",
    ]
    as $key => $label
): ?><div class="metric"><span><?= h($label) ?></span><strong><?= (int) $counts[
    $key
] ?></strong></div><?php endforeach; ?></div><div class="section-head compact"><h2>Seu estoque</h2><a href="edit.php" class="button red">+ Adicionar veículo</a></div><form method="get" class="admin-filters"><label>Buscar<input type="search" name="q" value="<?= h(
    $q,
) ?>" placeholder="Título, marca ou modelo"></label><label>Situação<select name="status"><option value="">Todas</option><?php foreach (
    status_labels()
    as $k => $label
): ?><option value="<?= $k ?>" <?= $status === $k ? "selected" : "" ?>><?= h(
    $label,
) ?></option><?php endforeach; ?></select></label><button class="button outline">Filtrar</button></form><div class="userbox table-wrap"><table class="admin-table"><thead><tr><th>Veículo</th><th>Valor</th><th>Situação</th><th>Destaque</th><th>Ações</th></tr></thead><tbody><?php foreach (
    $rows
    as $r
): ?><tr><td><div class="table-vehicle"><img src="../<?= h(
    photo_path($r["cover"]),
) ?>" width="96" height="54" alt=""><div><strong><?= h(
    $r["title"],
) ?></strong><small><?= h(vehicle_year($r)) ?> • #<?= (int) $r[
     "id"
 ] ?></small></div></div></td><td><?= h(
    money($r["price"]),
) ?></td><td><span class="pill <?= h($r["status"]) ?>"><?= h(
    status_labels()[$r["status"]],
) ?></span></td><td><?= $r["featured"]
    ? "Sim • ordem " . (int) $r["featured_order"]
    : "—" ?></td><td><div class="table-actions"><a class="text-link" href="edit.php?id=<?= (int) $r[
    "id"
] ?>">Editar / Fotos</a><form method="post" action="delete.php" data-confirm="Excluir este veículo e todas as fotografias? Esta ação não pode ser desfeita."><?= token_field() ?><input type="hidden" name="id" value="<?= (int) $r[
    "id"
] ?>"><button class="button-sm danger">Excluir</button></form></div></td></tr><?php endforeach; ?></tbody></table><?php if (
    !$rows
): ?><p class="empty-state">Nenhum veículo encontrado.</p><?php endif; ?></div><?php if (
    $pages > 1
): ?><nav class="pagination" aria-label="Páginas do painel"><?php if (
    $page > 1
): ?><a href="?<?= h(
    http_build_query(["q" => $q, "status" => $status, "page" => $page - 1]),
) ?>">← Anterior</a><?php endif; ?><span><?= $page ?> / <?= $pages ?></span><?php if (
    $page < $pages
): ?><a href="?<?= h(
    http_build_query(["q" => $q, "status" => $status, "page" => $page + 1]),
) ?>">Próxima →</a><?php endif; ?></nav><?php endif; ?><section class="userbox"><h2>Atividades recentes</h2><p class="fineprint">Alterações relevantes, sem senhas ou dados de autenticação.</p><ol class="activity-list"><?php foreach (
    $logs
    as $log
): ?><li><time><?= h(display_date($log["created_at"])) ?></time><strong><?= h(
    $log["name"] ?? "Administrador",
) ?></strong><span><?= h(audit_action($log["action"])) ?> <?= h(
     audit_target($log["target"]),
 ) ?></span></li><?php endforeach; ?></ol><?php if (
    !$logs
): ?><p class="muted">Nenhuma atividade registrada.</p><?php endif; ?></section><?php admin_bottom(); ?>
