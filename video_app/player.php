<?php
require('../auth.php'); // ログインチェック

$videoPath = $_GET['video'] ?? '';
$fullPath = __DIR__ . '/videos/' . $videoPath;

// セキュリティチェック：パスに「..」が含まれていないか確認
if (strpos($videoPath, '..') !== false || !file_exists($fullPath)) {
    die('無効な動画パスです。');
}

// 再生履歴を保存
$userId = $_SESSION["user_id"] ?? 0;

// パスから subject と video_title を抽出
$parts = explode('/', $videoPath);
$subjectCode = $parts[0] ?? '';
$fileName = $parts[1] ?? '';
$videoTitle = $fileName;

try {
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        INSERT INTO video_history (user_id, subject, video_title, video_path, watched_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $subjectCode, $videoTitle, $videoPath]);

} catch (PDOException $e) {
    // ログだけ残して続行
    error_log("履歴保存エラー: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>動画再生</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .video-container {
      max-width: 800px;
      margin: 40px auto;
      text-align: center;
    }
    video {
      width: 100%;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .back-link {
      margin-top: 20px;
    }
  </style>
</head>
<body>
  <div class="video-container">
    <h1>動画再生</h1>
    <video controls autoplay>
      <source src="<?php echo 'videos/' . htmlspecialchars($videoPath); ?>" type="video/mp4">
      お使いのブラウザは video タグに対応していません。
    </video>
    <a href="index.php" class="back-link">← 動画一覧へ戻る</a>
  </div>
</body>
</html>
