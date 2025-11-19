<?php
// セッションがまだ開始されていない場合のみ開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログインしていない場合は guest として扱う
if (!isset($_SESSION["user"])) {
    $_SESSION["user"] = "guest";
}
?>