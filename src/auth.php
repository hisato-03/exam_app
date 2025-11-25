<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログインしていない場合は guest として扱う
if (!isset($_SESSION["user_id"])) {
    $_SESSION["user_id"] = 0; // 未ログインは 0
}
if (!isset($_SESSION["user"])) {
    $_SESSION["user"] = "guest";
}
?>
