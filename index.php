<?php
$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'exam_app';
$user = getenv('DB_USER') ?: 'exam_user';
$pass = getenv('DB_PASS') ?: 'exam_pass';

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
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
    http_response_code(500);
    // エラーだけは表示しておくとデバッグに便利
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
    <h1 class="center-text">学習する内容を選んでください</h1>

    <div class="center-text">
        
        <p class="intro">
            このページは学習用アプリの入口です。<br>
            下のボタンから目的のアプリを選んでください。
        </p>
        <ul class="intro-list">
            <li>📘 <strong>試験アプリ</strong>：過去問や練習問題を解いて、解説を確認できます。</li>
            <li>🎥 <strong>動画アプリ</strong>：解説動画を視聴して、理解を深めることができます。</li>
        </ul>
        <div class="app-links">
            <a href="/exam_app/test.php" class="btn btn-primary">📘 試験問題学習へ</a>
            <a href="/exam_app/video_app/index.php" class="btn btn-success">🎥 動画学習へ</a>
        </div>
    </div>
</body>
</html>
