<?php
require_once('../auth.php'); // ログインチェック

// Google Sheets API 読み込み（マスターデータ取得用）
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_VIDEO_B64');

use Google\Client;
use Google\Service\Sheets;

$userId = $_SESSION["user_id"] ?? 0;

try {
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. 視聴履歴を取得
    $stmt = $pdo->prepare("SELECT subject, video_title, watched_at FROM video_history WHERE user_id = ? ORDER BY watched_at DESC");
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. スプレッドシートから科目名と単元名のマスターデータを取得
    $client = new Client();
    $client->setAuthConfig(__DIR__ . '/credentials.json');
    $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
    $service = new Sheets($client);
    $spreadsheetId = '1evXOkxn2Pjpv9vXr95jMknI8UGK3IxXP1FbvWSeQIKY';
    $range = '管理表!A3:F100'; 
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();

    // 3. 変換用マップを作成 [ファイル名 => ['subject' => 科目名, 'unit' => 単元名]]
    $videoMaster = [];
    if (!empty($values)) {
        foreach ($values as $row) {
            $row = array_pad($row, 6, '');
            // A:コード, B:科目名, C:節, D:単元名, F:ファイル名
            $sName = $row[1];
            $uName = $row[3];
            $fName = $row[5];
            if ($fName) {
                $videoMaster[$fName] = ['subject' => $sName, 'unit' => $uName];
            }
        }
    }

} catch (Exception $e) {
    die("エラー: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>視聴履歴</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="main-layout container">
  <h1 style="text-align: center; margin-bottom: 10px;">🎬 動画視聴履歴</h1>

  <div style="text-align: center; margin-bottom: 30px;">
    <a href="index.php" class="btn-round" style="background: #2196F3; padding: 10px 40px;">▶ 動画一覧へ戻る</a>
  </div>

  <div class="card-style">
    <?php if (empty($history)): ?>
      <p style="text-align: center; padding: 30px; color: #888;">まだ視聴履歴はありません。</p>
    <?php else: ?>
      <div style="overflow-x: auto;">
        <table class="history-table">
          <thead>
            <tr>
              <th style="width: 25%; white-space:nowrap;">科目</th>
              <th>動画タイトル（単元）</th>
              <th style="width: 25%; white-space:nowrap;">視聴日時</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $row): 
                // マスターデータから名称を検索、なければDBの値をそのまま使う
                $fName = $row['video_title'];
                $displaySubject = $videoMaster[$fName]['subject'] ?? $row['subject'];
                $displayUnit    = $videoMaster[$fName]['unit'] ?? $fName;
            ?>
              <tr>
                <td style="vertical-align: top;">
                  <span style="background: #eef2f7; padding: 2px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; color: #124a86;">
                    <?php echo htmlspecialchars($displaySubject); ?>
                  </span>
                </td>
                <td style="text-align: left; font-weight: bold;">
                  <?php echo htmlspecialchars($displayUnit); ?>
                </td>
                <td style="font-size: 0.85em; color: #888; white-space:nowrap; vertical-align: top;">
                  <?php echo htmlspecialchars($row['watched_at']); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div style="text-align: center; margin-top: 40px; display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
    <a href="../index.php" class="btn-round" style="background: #6c757d; padding: 12px 30px;">🏠 トップページへ</a>
    <a href="../history.php" class="btn-round" style="background: #4CAF50; padding: 12px 30px;">📊 学習履歴を見る</a>
  </div>

  <footer style="text-align: center; margin-top: 50px; color: #888; font-size: 0.9em;">
    &copy; <?php echo date('Y'); ?> 介護学習支援プロジェクト
  </footer>
</div>

</body>
</html>