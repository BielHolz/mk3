<?php
require "common.php";
require dirname(__DIR__) . "/app/media.php";
admin_required();
require_post();
$id = filter_var(input($_POST, "vehicle_id"), FILTER_VALIDATE_INT, [
    "options" => ["min_range" => 1],
]);
if (!$id) {
    abort_request(400, "Identificador inválido.");
}
$action = input($_POST, "action");
$pid = (int) input($_POST, "photo_id", "0");
$pdo = db();
$created = [];
$remove = [];
try {
    $pdo->beginTransaction();
    $s = $pdo->prepare("SELECT id FROM vehicles WHERE id=? FOR UPDATE");
    $s->execute([$id]);
    if (!$s->fetchColumn()) {
        throw new InvalidArgumentException("Veículo não encontrado.");
    }
    $photos = car_photos($id);
    $photo = null;
    foreach ($photos as $p) {
        if ((int) $p["id"] === $pid) {
            $photo = $p;
        }
    }
    if (
        in_array($action, ["replace", "remove", "cover", "up", "down"], true) &&
        !$photo
    ) {
        throw new InvalidArgumentException(
            "Fotografia não encontrada neste veículo.",
        );
    }
    if ($action === "upload") {
        $files = upload_files("photos");
        if (!$files) {
            $files = upload_files("photo");
        }
        if (!$files || count($photos) + count($files) > 12) {
            throw new InvalidArgumentException(
                "Escolha fotografias respeitando o limite de 12 por veículo.",
            );
        }
        $position = $photos ? max(array_column($photos, "position")) + 1 : 0;
        foreach ($files as $file) {
            $path = upload_image($file);
            $created[] = $path;
            $pdo->prepare(
                "INSERT INTO photos(vehicle_id,path,position) VALUES(?,?,?)",
            )->execute([$id, $path, $position++]);
        }
    } elseif ($action === "replace") {
        $files = upload_files("photo");
        if (count($files) !== 1) {
            throw new InvalidArgumentException(
                "Escolha uma fotografia para substituir.",
            );
        }
        $path = upload_image($files[0]);
        $created[] = $path;
        $pdo->prepare(
            "UPDATE photos SET path=? WHERE id=? AND vehicle_id=?",
        )->execute([$path, $pid, $id]);
        $remove[] = $photo["path"];
    } elseif ($action === "remove") {
        $pdo->prepare(
            "DELETE FROM photos WHERE id=? AND vehicle_id=?",
        )->execute([$pid, $id]);
        $remove[] = $photo["path"];
    } elseif (in_array($action, ["cover", "up", "down"], true)) {
        $ids = array_map("intval", array_column($photos, "id"));
        $index = array_search($pid, $ids, true);
        if ($action === "cover") {
            array_splice($ids, $index, 1);
            array_unshift($ids, $pid);
        } else {
            $next = $index + ($action === "up" ? -1 : 1);
            if (isset($ids[$next])) {
                [$ids[$index], $ids[$next]] = [$ids[$next], $ids[$index]];
            }
        }
        $s = $pdo->prepare(
            "UPDATE photos SET position=? WHERE id=? AND vehicle_id=?",
        );
        foreach ($ids as $position => $photoId) {
            $s->execute([$position, $photoId, $id]);
        }
    } else {
        throw new InvalidArgumentException("Operação de fotografia inválida.");
    }
    admin_log(
        "photo-" . $action,
        "#" . $id . " • foto " . $pid . " • " . count($created) . " arquivo(s)",
    );
    $pdo->commit();
    foreach ($remove as $path) {
        remove_image($path);
    }
    go("edit.php?id=" . $id . "&photos=1#fotos");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($created as $path) {
        remove_image($path);
    }
    if (!($e instanceof InvalidArgumentException)) {
        error_log("MK3 upload: " . get_class($e));
    }
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    admin_top("Não foi possível atualizar a galeria");
    flash_error(
        $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : "Falha ao gravar as fotografias. Nenhuma alteração foi salva. Verifique as permissões e tente novamente.",
    );
    echo '<a class="button outline" href="edit.php?id=' .
        $id .
        '#fotos">Voltar à galeria</a>';
    admin_bottom();
}
