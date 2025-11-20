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
    echo "✅ PHP と MySQL の接続に成功しました。<br>";

    // 初期テーブル（なければ作成）
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ users テーブルの準備ができました。";
} catch (PDOException $e) {
    http_response_code(500);
    echo "❌ 接続エラー: " . htmlspecialchars($e->getMessage());
}
