<?php
// デバッグログ
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
    
    // 初期テーブル作成
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
    <title>オンライン介護学習アプリ</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* index.php専用の大きなボタン用スタイル */
        .app-links .btn-large {
            display: block;
            max-width: 450px;
            margin: 15px auto;
            text-decoration: none;
            padding: 18px;
            font-weight: bold;
            border-radius: 50px; /* 丸みのある大きなボタン */
            transition: transform 0.2s, box-shadow 0.2s;
            font-size: 1.1em;
            color: white !important;
            border: none;
        }
        .app-links .btn-large:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
            filter: brightness(1.1);
        }
    </style>
</head>
<body>
  <div class="main-layout" style="margin-top: 50px;">
    
    <div class="intro-section" style="text-align: center; margin-bottom: 40px;">
      <h1 class="intro-title" style="font-size: 2.2em; color: #333;">オンライン介護学習アプリ</h1>
      <p class="intro-lead" style="color: #666; font-size: 1.1em;">
        学習する内容を選んでください。
      </p>
    </div>

    <div class="card-style" style="margin-bottom: 40px; border-top: 5px solid #2196F3;">
        <ul class="intro-list" style="margin: 0; padding: 10px 10px 10px 25px; line-height: 1.8;">
          <li>📘 <strong>試験アプリ</strong>：過去問を解いて、詳しい解説を確認できます。</li>
          <li>🎥 <strong>動画アプリ</strong>：解説動画で、苦手分野の理解を深められます。</li>
        </ul>
    </div>

    <div class="app-links" style="text-align: center;">
      <a href="/exam_app/test.php" class="btn-large" style="background: #2196F3;">📘 試験問題学習へ</a>
      
      <a href="/exam_app/review.php" class="btn-large" style="background: #d32f2f;">🔥 苦手克服モード（復習）</a>
      
      <a href="/exam_app/video_app/index.php" class="btn-large" style="background: #4CAF50;">🎥 動画学習へ</a>

      <div style="margin: 40px auto; width: 100px; border-bottom: 2px solid #ddd;"></div>

      <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 15px;">
        <a href="/exam_app/login.php" class="btn-round" style="background: #6c757d; padding: 12px 30px;">🔐 ログイン / 設定</a>
        <a href="https://forms.gle/nw84hGPLwEqgCtXu8" target="_blank" class="btn-round" style="background: #9e9e9e; padding: 12px 30px;">📩 お問い合わせ</a>
      </div>
    </div>

    <footer style="text-align: center; margin-top: 60px; padding: 20px; color: #999; font-size: 0.9em; border-top: 1px solid #eee;">
      &copy; <?php echo date('Y'); ?> 介護学習支援プロジェクト
    </footer>
  </div>
</body>
</html>