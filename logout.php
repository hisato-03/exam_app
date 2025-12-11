<?php
require "auth.php";

// ログアウト＝guestに戻す
$_SESSION["user"] = "guest";

// test.php に戻す
header("Location: test.php");
exit;
