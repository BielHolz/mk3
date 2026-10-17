<?php
require "common.php";
require dirname(__DIR__) . "/app/media.php";
admin_required();
require_post();
$id = filter_var(input($_POST, "id"), FILTER_VALIDATE_INT, [
    "options" => ["min_range" => 1],
]);
if (!$id) {
    abort_request(400, "Identificador inválido.");
}
$pdo = db();
$pdo->beginTransaction();
try {
    $s = $pdo->prepare("SELECT id FROM vehicles WHERE id=? FOR UPDATE");
    $s->execute([$id]);
    if (!$s->fetchColumn()) {
        $pdo->rollBack();
        abort_request(404, "Veículo não encontrado.");
    }
    $paths = array_column(car_photos($id), "path");
    $pdo->prepare("DELETE FROM vehicles WHERE id=?")->execute([$id]);
    admin_log("delete-vehicle", "#" . $id);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
foreach ($paths as $path) {
    remove_image($path);
}
go("index.php");
