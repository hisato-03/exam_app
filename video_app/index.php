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
$range = '管理表!A2:F100';
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

<div class="main-layout">
  <h1>🎬 学習動画一覧</h1>

  <div class="flex-between" style="justify-content: center; margin-bottom: 30px; gap: 15px;">
    <a href="video_history.php" class="btn-round" style="background: #4CAF50;">▶ 視聴履歴を見る</a>
    <a href="../index.php" class="btn-round" style="background: #6c757d;">← トップページへ</a>
  </div>

  <?php if (empty($values)): ?>
    <div class="card-style">
      <p style="text-align: center;">動画データが見つかりませんでした。</p>
    </div>
  <?php else: ?>
    <?php
      $subjectIndex = 0;
      foreach ($values as $row):
          $row = array_pad($row, 6, '');
          list($subjectCode, $subjectName, $section, $unit, $dummy, $fileName) = $row;

          // 科目が変わったタイミングでカード（subject-block）を作成
          if ($subjectName && $subjectName !== $currentSubject):
              if ($currentSubject !== '') echo "</ul></div>"; // 前の科目の閉じタグ

              $subjectIndex++;
              $bgClass = 'bg-' . (($subjectIndex % 5) + 1);
              ?>
              <div class="card-style subject-block <?php echo $bgClass; ?>">
                <h2><?php echo htmlspecialchars($subjectName); ?></h2>
                <ul class="video-list">
              <?php
              $currentSubject = $subjectName;
          endif;

          // 動画リンクの表示
          if ($fileName && $unit):
              $path = $subjectCode . "/" . $fileName;
              ?>
              <li>
                <a href="player.php?video=<?php echo urlencode($path); ?>">
                  <span class="unit-label"><?php echo htmlspecialchars($unit); ?></span>
                </a>
              </li>
              <?php
          endif;
      endforeach;

      if ($currentSubject !== '') echo "</ul></div>"; // 最後の閉じタグ
    ?>
  <?php endif; ?>

  <footer style="text-align: center; margin-top: 50px; color: #888; font-size: 0.9em;">
    &copy; <?php echo date('Y'); ?> 介護学習支援プロジェクト
  </footer>
</div>

</body>
</html>