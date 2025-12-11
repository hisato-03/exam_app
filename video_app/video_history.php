<?php
require('../auth.php'); // ログインチェック

$userId = $_SESSION["user_id"] ?? 0;

try {
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ▼ 動画履歴取得（ユーザーごと）
    $stmt = $pdo->prepare("SELECT * FROM video_history WHERE user_id=? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("DBエラー: " . htmlspecialchars($e->getMessage()));
}

// ▼ Google Sheets から単元・科目名情報を取得
require 'vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

$client = new Client();
$client->setApplicationName('VideoApp');
$client->setScopes([Sheets::SPREADSHEETS_READONLY]);
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->setAccessType('offline');

$service = new Sheets($client);

$spreadsheetId = '1evXOkxn2Pjpv9vXr95jMknI8UGK3IxXP1FbvWSeQIKY';
$range = '管理表!A3:F100';
$response = $service->spreadsheets_values->get($spreadsheetId, $range);
$sheetValues = $response->getValues();

// ▼ 動画パス → [科目名, 単元] のマッピングを作成
$videoInfoMap = [];
foreach ($sheetValues as $row) {
    $row = array_pad($row, 6, '');
    $subjectCode = $row[0]; // A列
    $subjectName = $row[1]; // B列
    $unit = $row[3];        // D列
    $fileName = $row[5];    // F列

    if ($subjectCode && $fileName) {
        $fullPath = $subjectCode . '/' . $fileName;
        $videoInfoMap[$fullPath] = [
            'subject' => $subjectName ?: '（不明）',
            'unit' => $unit ?: '（不明）'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>動画履歴</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="video-history-page">
  <h1 class="history-title">視聴履歴</h1>

  <?php if (!empty($records)): ?>
    <div class="history-block">
      <table class="history-table">
        <tr>
          <th>ID</th>
          <th>科目名</th>
          <th>単元</th>
          <th>日時</th>
        </tr>
        <?php foreach ($records as $row): ?>
          <?php
            $path = $row["video_path"];
            $info = $videoInfoMap[$path] ?? ['subject' => '（不明）', 'unit' => '（不明）'];
          ?>
          <tr>
            <td><?php echo htmlspecialchars($row["id"]); ?></td>
            <td><?php echo htmlspecialchars($info['subject']); ?></td>
            <td>
              <a href="player.php?video=<?php echo urlencode($path); ?>" class="video-link">
                <?php echo htmlspecialchars($info['unit']); ?>
              </a>
            </td>
            <td><?php echo htmlspecialchars($row["created_at"]); ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php else: ?>
    <p class="no-history">まだ動画履歴はありません。</p>
  <?php endif; ?>

  <!-- ▼ 戻るリンク -->
  <a href="index.php" class="back-link">← 動画一覧へ戻る</a>
</body>
</html>
