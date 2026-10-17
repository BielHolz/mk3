<?php
require "common.php";
admin_required();
require_post();
admin_log("logout");
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), "", [
    "expires" => time() - 3600,
    "path" => $params["path"],
    "secure" => $params["secure"],
    "httponly" => true,
    "samesite" => "Strict",
]);
session_destroy();
go("login.php");
