<?php
require __DIR__ . "/app/core.php";
header("Cache-Control: no-store");
header("X-Robots-Tag: noindex, nofollow");
$local = in_array($_SERVER["REMOTE_ADDR"] ?? "", ["127.0.0.1", "::1"], true);
$installToken = getenv("MK3_INSTALL_TOKEN") ?: "";
if (!$local && $installToken === "") {
    abort_request(403, "Instalação disponível apenas localmente.");
}
if (
    is_file(__DIR__ . "/app/installed.lock") ||
    (int) db()->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0
) {
    abort_request(
        403,
        "Instalação encerrada. Acesse o painel administrativo e remova o instalador da hospedagem.",
    );
}
$error = "";
$success = false;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    $name = input($_POST, "name");
    $email = mb_strtolower(input($_POST, "email"));
    $pass = raw_input($_POST, "pass");
    if (
        !$local &&
        !hash_equals($installToken, raw_input($_POST, "install_token"))
    ) {
        abort_request(403, "Instalação não autorizada.");
    }
    if (
        mb_strlen($name) < 2 ||
        mb_strlen($name) > 120 ||
        strlen($email) > 190 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($pass) < 12 ||
        strlen($pass) > 72
    ) {
        $error = "Informe nome, e-mail válido e senha entre 12 e 72 bytes.";
    } else {
        $pdo = db();
        if (
            !(int) $pdo
                ->query("SELECT GET_LOCK('mk3_first_admin',5)")
                ->fetchColumn()
        ) {
            $error = "Instalação em andamento. Tente novamente.";
        } else {
            try {
                $pdo->beginTransaction();
                if (
                    (int) $pdo
                        ->query("SELECT COUNT(*) FROM users")
                        ->fetchColumn() > 0
                ) {
                    throw new InvalidArgumentException(
                        "Instalação já concluída.",
                    );
                }
                $pdo->prepare(
                    "INSERT INTO users(name,email,password_hash) VALUES(?,?,?)",
                )->execute([
                    $name,
                    $email,
                    password_hash($pass, PASSWORD_DEFAULT),
                ]);
                $pdo->commit();
                $success = true;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error =
                    "Não foi possível criar o administrador. Verifique se a instalação já foi concluída.";
                error_log("MK3 installer: " . get_class($e));
            } finally {
                $pdo->query("SELECT RELEASE_LOCK('mk3_first_admin')");
            };
        }
    }
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><link rel="icon" type="image/png" href="assets/logo-mk3-oficial.png"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Instalação MK3</title><link rel="stylesheet" href="assets/style.css"></head><body><main class="login"><img src="assets/logo-mk3-oficial.png" alt="MK3" width="150" height="78"><h1>Bem-vindo à MK3</h1><p>Crie o primeiro administrador. Importe database.sql e execute a migração antes de continuar.</p><?php
if ($error): ?><div class="alert" role="alert"><?= h(
    $error,
) ?></div><?php endif;
if (
    $success
): ?><div class="alert ok">Administrador criado. O instalador está bloqueado automaticamente. Remova instalar.php da hospedagem.</div><a class="button red" href="admin/login.php">Entrar no painel</a><?php else: ?><form method="post"><?= token_field() ?><label>Nome<input name="name" required maxlength="120" autocomplete="name"></label><label>E-mail<input type="email" name="email" required maxlength="190" autocomplete="username"></label><label>Senha (12 a 72 bytes)<input type="password" name="pass" required minlength="12" maxlength="72" autocomplete="new-password"></label><?php if (
    !$local
): ?><label>Token de instalação<input type="password" name="install_token" required autocomplete="off"></label><?php endif; ?><button class="button red full">Criar administrador</button></form><?php endif;
?></main></body></html>
