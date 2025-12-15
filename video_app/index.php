<?php
// 認証を exam_app に一本化
require_once('../auth.php');

// Google Sheets API 読み込み
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_VIDEO_B64');

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
$values = $response->getValues();

$currentSubject = '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>学習動画一覧</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h1>学習動画一覧</h1>

  <!-- ▼ ナビゲーションリンク -->
  <div class="nav-links">
    <a href="video_history.php">▶ 視聴履歴を見る</a>
    <a href="../index.php">← トップページへ戻る</a>
  </div>

  <?php
  if (empty($values)) {
      echo "<p>動画データが見つかりませんでした。</p>";
  } else {
      static $subjectIndex = 0;

      foreach ($values as $row) {
          $row = array_pad($row, 6, '');
          list($subjectCode, $subjectName, $section, $unit, $dummy, $fileName) = $row;

          if ($subjectName && $subjectName !== $currentSubject) {
              if ($currentSubject !== '') echo "</ul></div>";
              $subjectIndex++;
              $bgClass = 'bg-' . (($subjectIndex % 5) + 1);
              echo "<div class='subject-block $bgClass'>";
              echo "<h2>" . htmlspecialchars($subjectName) . "</h2><ul>";
              $currentSubject = $subjectName;
          }

          if ($fileName && $unit) {
              $path = $subjectCode . "/" . $fileName;
              echo "<li><a href='player.php?video=" . urlencode($path) . "'>"
                  . "<span class='unit-label'>" . htmlspecialchars($unit) . "</span>"
                  . "</a></li>";
          }
      }
      if ($currentSubject !== '') echo "</ul></div>";
  }
  ?>
</body>
</html>
