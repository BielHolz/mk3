<?php
// Aceita apenas imagens raster decodificadas; regrava sem metadados em WebP.
function upload_image(array $file, int $maxWidth = 1800): string
{
    if (
        ($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
        ($file["size"] ?? 0) <= 0 ||
        $file["size"] > 5 * 1024 * 1024 ||
        !is_string($file["tmp_name"] ?? null) ||
        !is_uploaded_file($file["tmp_name"])
    ) {
        throw new InvalidArgumentException(
            "Envie imagens JPG, PNG ou WebP de até 5 MB cada.",
        );
    }
    $tmp = $file["tmp_name"];
    $mime = new finfo(FILEINFO_MIME_TYPE)->file($tmp);
    $info = @getimagesize($tmp);
    if (
        !$info ||
        !in_array($mime, ["image/jpeg", "image/png", "image/webp"], true) ||
        $info[0] > 8000 ||
        $info[1] > 8000 ||
        $info[0] * $info[1] > 16000000
    ) {
        throw new InvalidArgumentException(
            "Imagem inválida ou resolução excessiva (máximo 16 megapixels).",
        );
    }
    if (!extension_loaded("gd") || !function_exists("imagewebp")) {
        throw new RuntimeException("Extensão GD com WebP necessária.");
    }
    $load = match ($mime) {
        "image/jpeg" => "imagecreatefromjpeg",
        "image/png" => "imagecreatefrompng",
        "image/webp" => "imagecreatefromwebp",
    };
    $image = @$load($tmp);
    if (!$image) {
        throw new InvalidArgumentException(
            "Não foi possível decodificar a fotografia.",
        );
    }
    $path = "uploads/" . bin2hex(random_bytes(16)) . ".webp";
    $full = dirname(__DIR__) . "/" . $path;
    $thumb = thumbnail_path($path);
    try {
        foreach (
            [$full => $maxWidth, dirname(__DIR__) . "/" . $thumb => 480]
            as $destination => $width
        ) {
            $ratio = min(1, $width / imagesx($image), $width / imagesy($image));
            $w = max(1, (int) round(imagesx($image) * $ratio));
            $h = max(1, (int) round(imagesy($image) * $ratio));
            $output = imagecreatetruecolor($w, $h);
            imagealphablending($output, false);
            imagesavealpha($output, true);
            imagecopyresampled(
                $output,
                $image,
                0,
                0,
                0,
                0,
                $w,
                $h,
                imagesx($image),
                imagesy($image),
            );
            try {
                if (!imagewebp($output, $destination, 84)) {
                    throw new RuntimeException(
                        "Não foi possível gravar a imagem.",
                    );
                }
            } finally {
                imagedestroy($output);
            }
            chmod($destination, 0644);
        }
    } catch (Throwable $e) {
        remove_image($path);
        throw $e;
    } finally {
        imagedestroy($image);
    }
    return $path;
}
function thumbnail_path(string $path): string
{
    return preg_replace('/\.(jpg|png|webp)$/D', '-thumb.$1', $path);
}
function remove_image(string $path): void
{
    if (!preg_match('~^uploads/[a-f0-9]{32}\.(jpg|png|webp)$~D', $path)) {
        return;
    }
    foreach ([$path, thumbnail_path($path)] as $p) {
        $full = dirname(__DIR__) . "/" . $p;
        if (is_file($full) && !is_link($full) && !@unlink($full)) {
            error_log("MK3: falha ao remover arquivo de mídia.");
        }
    }
}
function upload_files(string $key): array
{
    $source = $_FILES[$key] ?? [];
    if (!isset($source["name"])) {
        return [];
    }
    if (!is_array($source["name"])) {
        return [$source];
    }
    $files = [];
    foreach (array_keys($source["name"]) as $i) {
        $f = [];
        foreach (["name", "type", "tmp_name", "error", "size"] as $field) {
            $f[$field] = $source[$field][$i] ?? null;
        }
        if ($f["error"] !== UPLOAD_ERR_NO_FILE) {
            $files[] = $f;
        }
    }
    return $files;
}
