<?php
file_put_contents('/tmp/debug.log', "✅ index.php reached at " . date('c') . "\n", FILE_APPEND);
$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'exam_app';
$user = getenv('DB_USER') ?: 'exam_user';
$pass = getenv('DB_PASS') ?: 'exam_pass';

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    file_put_contents('/tmp/debug.log', "✅ DB connected successfully\n", FILE_APPEND);
    // 初期テーブル（なければ作成）
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
 } catch (PDOException $e) {
    file_put_contents('/tmp/debug.log', "❌ DB connection failed: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo "❌ 接続エラー: " . htmlspecialchars($e->getMessage());
}   
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>トップページ</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="intro-section">
    <h1 class="intro-title">オンライン介護学習アプリ</h1>
    <p class="intro-lead">
      学習する内容を選んでください。<br>
      下のボタンから目的のアプリを選んでください。
    </p>
    <ul class="intro-list">
      <li>📘 <strong>試験アプリ</strong>：過去問や練習問題を解いて、解説を確認できます。</li>
      <li>🎥 <strong>動画アプリ</strong>：解説動画を視聴して、理解を深めることができます。</li>
    </ul>
  </div>

  <div class="app-links center-text">
    <a href="/exam_app/test.php" class="btn btn-primary">📘 試験問題学習へ</a>
    <a href="/exam_app/video_app/index.php" class="btn btn-success">🎥 動画学習へ</a>
  </div>

  <div class="account-link center-text">
    <a href="/exam_app/login.php" class="btn btn-secondary">🔐 ログインページへ</a>
  </div>

  <div class="contact-link center-text">
    <a href="https://forms.gle/nw84hGPLwEqgCtXu8" target="_blank" class="btn btn-info">📩 お問い合わせ</a>
  </div>
</body>

</html>
