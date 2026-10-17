<?php
// Execute no terminal: php app/migrate.php. Não acessível via HTTP.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
require __DIR__ . "/core.php";
$pdo = db();
if (
    !(int) $pdo
        ->query("SELECT GET_LOCK('mk3_schema_migration',30)")
        ->fetchColumn()
) {
    throw new RuntimeException("Migração em andamento.");
}
try {
    $columns = $pdo
        ->query("SHOW COLUMNS FROM vehicles")
        ->fetchAll(PDO::FETCH_UNIQUE);
    foreach (
        [
            "version" => "VARCHAR(120) NOT NULL DEFAULT ''",
            "manufacture_year" => "SMALLINT UNSIGNED NULL",
            "model_year" => "SMALLINT UNSIGNED NULL",
            "features" => "TEXT NULL",
            "featured_order" => "INT UNSIGNED NOT NULL DEFAULT 0",
        ]
        as $name => $type
    ) {
        if (!isset($columns[$name])) {
            $pdo->exec("ALTER TABLE vehicles ADD $name $type");
        }
    }
    if (!str_contains($columns["status"]["Type"], "'reserved'")) {
        $pdo->exec(
            "ALTER TABLE vehicles MODIFY status ENUM('available','reserved','sold','hidden') NOT NULL DEFAULT 'available'",
        );
    }
    $pdo->exec(
        "UPDATE vehicles SET manufacture_year=CAST(SUBSTRING_INDEX(year_model,'/',1) AS UNSIGNED),model_year=CAST(SUBSTRING_INDEX(year_model,'/',-1) AS UNSIGNED) WHERE model_year IS NULL AND year_model REGEXP '^(19|20)[0-9]{2}/(19|20)[0-9]{2}$'",
    );
    $columns = $pdo
        ->query("SHOW COLUMNS FROM users")
        ->fetchAll(PDO::FETCH_UNIQUE);
    if (!isset($columns["session_version"])) {
        $pdo->exec(
            "ALTER TABLE users ADD session_version INT UNSIGNED NOT NULL DEFAULT 0",
        );
    }
    $columns = $pdo
        ->query("SHOW COLUMNS FROM login_attempts")
        ->fetchAll(PDO::FETCH_UNIQUE);
    if (!isset($columns["subject_hash"])) {
        $pdo->exec(
            "ALTER TABLE login_attempts ADD subject_hash CHAR(64) NULL, ADD INDEX(subject_hash,attempted_at)",
        );
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS store_settings(setting_key VARCHAR(60) PRIMARY KEY,setting_value TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    );
    echo "Migração concluída; dados existentes preservados.\n";
} finally {
    $pdo->query("SELECT RELEASE_LOCK('mk3_schema_migration')");
}
