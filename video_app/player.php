<?php
require('../auth.php'); // ログインチェック

// Google Sheets API 読み込み（マスターデータ取得用）
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_VIDEO_B64');

use Google\Client;
use Google\Service\Sheets;

$videoPath = $_GET['video'] ?? '';
$fullPath = __DIR__ . '/videos/' . $videoPath;

// セキュリティチェック
if (strpos($videoPath, '..') !== false || !file_exists($fullPath)) {
    die('無効な動画パスです。');
}

// パスから情報を抽出
$parts = explode('/', $videoPath);
$subjectCode = $parts[0] ?? '';
$fileName = $parts[1] ?? '';

// --- スプレッドシートから日本語名を取得 ---
$displayTitle = pathinfo($fileName, PATHINFO_FILENAME); // デフォルト
$displaySubject = $subjectCode; // デフォルト

try {
    $client = new Client();
    $client->setAuthConfig(__DIR__ . '/credentials.json');
    $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
    $service = new Sheets($client);
    $spreadsheetId = '1evXOkxn2Pjpv9vXr95jMknI8UGK3IxXP1FbvWSeQIKY';
    $range = '管理表!A3:F100'; 
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();

    if (!empty($values)) {
        foreach ($values as $row) {
            $row = array_pad($row, 6, '');
            // F列(インデックス5)がファイル名
            if ($row[5] === $fileName) {
                $displaySubject = $row[1]; // B列: 科目名
                $displayTitle = $row[3];   // D列: 単元名
                break;
            }
        }
    }
} catch (Exception $e) {
    // APIエラー時はデフォルト値を使用
}

// 再生履歴を保存（日本語名ではなく、管理上のID/パスで保存するのが一般的です）
$userId = $_SESSION["user_id"] ?? 0;
try {
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare("INSERT INTO video_history (user_id, subject, video_title, video_path, watched_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$userId, $subjectCode, $fileName, $videoPath]);
} catch (PDOException $e) {
    error_log("履歴保存エラー: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>動画再生 - <?php echo htmlspecialchars($displayTitle); ?></title>
  <link rel="stylesheet" href="style.css">
  <style>
    video {
      width: 100%;
      border-radius: 8px;
      background: #000;
      display: block;
    }
    .video-info {
      margin-top: 15px;
      text-align: left;
      padding: 10px;
    }
  </style>
</head>
<body>

<div class="main-layout container">
  <h1>🎥 動画再生</h1>

  <div class="card-style" style="padding: 15px; background: #fdfdfd;">
    <video controls autoplay>
      <source src="<?php echo 'videos/' . htmlspecialchars($videoPath); ?>" type="video/mp4">
      お使いのブラウザは video タグに対応していません。
    </video>

    <div class="video-info">
      <h2 style="font-size: 1.2em; margin-bottom: 8px; color: #333;">
        <?php echo htmlspecialchars($displayTitle); ?>
      </h2>
      <span style="font-size: 0.9em; color: #124a86; background: #eef2f7; padding: 4px 12px; border-radius: 50px; font-weight: bold;">
        科目: <?php echo htmlspecialchars($displaySubject); ?>
      </span>
    </div>
  </div>

  <div style="text-align: center; margin-top: 30px;">
    <a href="index.php" class="btn-round" style="background: #6c757d; padding: 12px 40px;">
      ◀ 動画一覧へ戻る
    </a>
  </div>

  <footer style="text-align: center; margin-top: 50px; color: #888; font-size: 0.9em;">
    &copy; <?php echo date('Y'); ?> 介護学習支援プロジェクト
  </footer>
</div>

</body>
</html>