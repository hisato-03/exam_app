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
    <style>
        /* index.php専用の微調整（必要に応じて） */
        .app-links .btn {
            display: block;
            max-width: 400px;
            margin: 15px auto;
            text-decoration: none;
            padding: 15px;
            font-weight: bold;
            border-radius: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .app-links .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
  <div class="intro-section" style="max-width: 800px; margin: 40px auto; text-align: center;">
    <h1 class="intro-title">オンライン介護学習アプリ</h1>
    <p class="intro-lead">
      学習する内容を選んでください。<br>
      下のボタンから目的のアプリを選んでください。
    </p>
    <div style="display: inline-block; text-align: left; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-top: 20px;">
        <ul class="intro-list" style="margin: 0; padding-left: 20px;">
          <li>📘 <strong>試験アプリ</strong>：過去問や練習問題を解いて、解説を確認できます。</li>
          <li>🎥 <strong>動画アプリ</strong>：解説動画を視聴して、理解を深めることができます。</li>
        </ul>
    </div>
  </div>

  <div class="app-links center-text">
    <a href="/exam_app/test.php" class="btn btn-primary">📘 試験問題学習へ</a>
    
    <a href="/exam_app/review.php" class="btn" style="background-color: #d32f2f; color: white;">🔥 苦手克服モード（復習）</a>
    
    <a href="/exam_app/video_app/index.php" class="btn btn-success">🎥 動画学習へ</a>

    <div style="margin: 30px auto; width: 50px; border-bottom: 2px solid #eee;"></div>

    <a href="/exam_app/login.php" class="btn btn-secondary">🔐 ログイン / アカウント設定</a>

    <a href="https://forms.gle/nw84hGPLwEqgCtXu8" target="_blank" class="btn btn-info" style="background-color: #6c757d; border: none;">📩 フィードバック・お問い合わせ</a>
  </div>

  <footer style="text-align: center; margin-top: 50px; padding: 20px; color: #888; font-size: 0.9em;">
    &copy; <?php echo date('Y'); ?> 介護学習支援プロジェクト
  </footer>
</body>
</html>