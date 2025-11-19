<?php
require "auth.php"; // ログインチェック
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>学習ページ</title>
</head>
<body>
  <h1>ようこそ <?php echo htmlspecialchars($_SESSION["user"]); ?> さん！</h1>
  <p>ここがログイン後のメイン画面です。</p>
  <a href="logout.php">ログアウト</a>
</body>
</html>
