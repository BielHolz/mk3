<?php
require "common.php";
if (logged()) {
    go("index.php");
}
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    $ip = substr($_SERVER["REMOTE_ADDR"] ?? "unknown", 0, 45);
    $email = mb_strtolower(input($_POST, "email"));
    $pass = raw_input($_POST, "password");
    $subject = hash("sha256", $email);
    $pdo = db();
    $locked = (int) $pdo
        ->query("SELECT GET_LOCK('mk3_login_guard',2)")
        ->fetchColumn();
    if (!$locked) {
        http_response_code(429);
        $error = "Aguarde um instante antes de tentar novamente.";
    } else {
        try {
            $pdo->exec(
                "DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)",
            );
            $q = $pdo->prepare(
                "SELECT SUM(ip=?) ip_count,SUM(subject_hash=?) account_count FROM login_attempts WHERE attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)",
            );
            $q->execute([$ip, $subject]);
            $counts = $q->fetch();
            if (
                (int) $counts["ip_count"] >= 8 ||
                (int) $counts["account_count"] >= 20
            ) {
                http_response_code(429);
                header("Retry-After: 900");
                $error =
                    "Muitas tentativas. Aguarde 15 minutos para tentar novamente.";
            } else {
                $pdo->prepare(
                    "INSERT INTO login_attempts(ip,subject_hash) VALUES(?,?)",
                )->execute([$ip, $subject]);
                $q = $pdo->prepare(
                    "SELECT id,password_hash,session_version FROM users WHERE email=?",
                );
                $q->execute([mb_substr($email, 0, 190)]);
                $user = $q->fetch();
                // Mesmo custo de hash para conta inexistente; mensagem não revela cadastros.
                $valid = password_verify(
                    $pass,
                    $user["password_hash"] ??
                        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
                );
                if (
                    $valid &&
                    $user &&
                    strlen($pass) <= 72 &&
                    strlen($email) <= 190
                ) {
                    if (password_rehash_needed($user["password_hash"])) {
                        $pdo->prepare(
                            "UPDATE users SET password_hash=? WHERE id=?",
                        )->execute([hash_password($pass), $user["id"]]);
                    }
                    session_regenerate_id(true);
                    $_SESSION = [
                        "admin_id" => (int) $user["id"],
                        "session_version" => (int) $user["session_version"],
                        "last_seen" => time(),
                        "csrf" => bin2hex(random_bytes(32)),
                    ];
                    admin_log("login");
                    $success = true;
                } else {
                    $error = "E-mail ou senha inválidos.";
                }
            }
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('mk3_login_guard')");
        }
    }
    if (!empty($success)) {
        go("index.php");
    }
}
admin_top("Bem-vindo de volta");
?><div class="login"><span class="eyebrow">Acesso restrito</span><h2>Seu estoque,<br>sob controle.</h2><p class="muted">Entre com sua conta administrativa.</p><?php flash_error(
    $error,
); ?><form method="post"><?= token_field() ?><label>E-mail<input type="email" name="email" required maxlength="190" value="<?= h(
    input($_POST, "email"),
) ?>" autocomplete="username"></label><label>Senha<input type="password" name="password" required maxlength="200" autocomplete="current-password"></label><button class="button red full">Entrar no painel ↗</button></form><p class="fineprint">Acesso exclusivo a responsáveis autorizados pela loja.</p></div><?php admin_bottom(); ?>
