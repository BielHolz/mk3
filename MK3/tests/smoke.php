<?php
// Verificação rápida sem banco. Propaga falhas reais de sintaxe e arquivos ausentes.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
$base = dirname(__DIR__);
$fail = 0;
$count = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if ($file->getExtension() === "php") {
        $count++;
        $output = [];
        $code = 0;
        exec(
            escapeshellarg(PHP_BINARY) .
                " -l " .
                escapeshellarg($file->getPathname()) .
                " 2>&1",
            $output,
            $code,
        );
        if ($code) {
            $fail++;
            echo implode("\n", $output) . "\n";
        }
    }
}
foreach (
    [
        ".htaccess",
        "app/.htaccess",
        "uploads/.htaccess",
        "tests/.htaccess",
        "database.sql",
        "assets/site.js",
        "assets/common.js",
        "assets/admin.js",
        "assets/vehicle.js",
        "assets/style.css",
        "assets/logo-mk3-oficial.png",
        "assets/placeholder.svg",
    ]
    as $file
) {
    $count++;
    if (!is_file($base . "/" . $file)) {
        $fail++;
        echo "FAIL missing $file\n";
    }
}
echo ($fail ? "FAIL" : "OK") . ": $count verificações, $fail falha(s).\n";
exit($fail ? 1 : 0);
