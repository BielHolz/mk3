<?php
require dirname(__DIR__) . "/app/core.php";
header("Cache-Control: no-store");
function admin_top(string $title): void
{
    $authenticated = logged(); ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><link rel="icon" type="image/png" href="../assets/logo-mk3-oficial.png"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= h($title) ?> — MK3 Administração</title><link rel="stylesheet" href="../assets/style.css"><script src="../assets/common.js" defer></script><script src="../assets/admin.js" defer></script></head><body class="admin-body"><div class="topline"></div><div class="admin-shell"><aside class="admin-sidebar"><a href="../index.php"><img src="../assets/logo-mk3-oficial.png" width="150" height="78" alt="MK3 Multimarcas"></a><p class="eyebrow">Administração</p><?php if ($authenticated): ?><nav aria-label="Administração"><a href="index.php">Visão geral / Estoque</a><a href="edit.php">+ Novo veículo</a><a href="settings.php">Configurações da loja</a><a href="account.php">Minha conta</a><a href="../index.php" target="_blank" rel="noopener">Abrir site ↗</a></nav><form action="logout.php" method="post"><?= token_field() ?><button class="button outline full">Sair da conta</button></form><?php else: ?><a class="text-link" href="../index.php">← Voltar ao site</a><?php endif; ?></aside><main class="admin-main" id="conteudo"><div class="admin-heading"><div><span class="eyebrow">MK3 Multimarcas</span><h1><?= h($title) ?></h1></div><?php if ($authenticated): ?><span class="pill">Sessão protegida</span><?php endif; ?></div><?php
}
function admin_bottom(): void
{
    echo "</main></div></body></html>";
}
function flash_error(string $error): void
{
    if ($error !== "") {
        echo '<div class="alert" role="alert">' . h($error) . "</div>";
    }
}
