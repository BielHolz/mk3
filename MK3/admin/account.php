<?php
require "common.php";
admin_required();
$error = "";
$saved = false;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    $old = raw_input($_POST, "old");
    $new = raw_input($_POST, "new");
    $confirm = raw_input($_POST, "confirm");
    $s = db()->prepare("SELECT password_hash FROM users WHERE id=?");
    $s->execute([$_SESSION["admin_id"]]);
    if (!password_verify($old, (string) $s->fetchColumn())) {
        $error = "Senha atual incorreta.";
    } elseif (strlen($new) < 12 || strlen($new) > 72) {
        $error = "A nova senha precisa ter de 12 a 72 bytes.";
    } elseif (!hash_equals($new, $confirm)) {
        $error = "A confirmação da senha não confere.";
    } elseif (hash_equals($old, $new)) {
        $error = "Escolha uma senha diferente da atual.";
    } else {
        db()->beginTransaction();
        try {
            db()
                ->prepare(
                    "UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?",
                )
                ->execute([hash_password($new), $_SESSION["admin_id"]]);
            admin_log("change-password");
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }
        $_SESSION["session_version"]++;
        session_regenerate_id(true);
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
        $saved = true;
    }
}
admin_top("Minha conta");
flash_error($error);
if (
    $saved
): ?><div class="alert ok" role="status">Senha atualizada. Outras sessões foram encerradas.</div><?php endif;
?><form method="post" class="userbox narrow"><?= token_field() ?><h2>Alterar senha</h2><label>Senha atual<input type="password" name="old" required autocomplete="current-password"></label><label>Nova senha (12 a 72 bytes)<input type="password" name="new" required minlength="12" maxlength="72" autocomplete="new-password"></label><label>Confirmar nova senha<input type="password" name="confirm" required minlength="12" maxlength="72" autocomplete="new-password"></label><button class="button red">Atualizar senha</button></form><?php admin_bottom(); ?>
