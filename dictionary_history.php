<?php
session_start();
require "auth.php"; // ログインチェック
require_once __DIR__ . '/load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');
require __DIR__ . '/vendor/autoload.php';

$userId   = $_SESSION["user_id"] ?? 0;
$userName = $_SESSION["user"] ?? "guest";

if ($userId === 0) {
    die("ログインしてください。");
}

use Google\Client;
use Google\Service\Sheets;

try {
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ▼ 履歴取得
    $stmt = $pdo->prepare("
        SELECT s.*, u.username
        FROM searched_words s
        JOIN users u ON s.user_id = u.id
        WHERE s.user_id=?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$userId]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($records)) {
        $userName = $records[0]['username'];
    }

    // --- ルビ振りのための辞書データ取得 ---
    $client = new Google\Client();
    $client->setAuthConfig(__DIR__ . '/credentials.json');
    $client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
    $service = new Google\Service\Sheets($client);

    try {
        $dictResponse = $service->spreadsheets_values->get(
            '1LDr4Acf_4SE-Wzp-ypPxM6COZdOt2QYumak8hIVVdxo',
            'dictionary_upload!A2:B'
        );
        $dictValues = $dictResponse->getValues() ?? [];
        $dictMap = [];
        foreach ($dictValues as $row) {
            if (!empty($row[0]) && !empty($row[1])) $dictMap[$row[0]] = $row[1];
        }
        $dictJson = json_encode($dictMap, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        $dictJson = '{}';
    }

} catch (PDOException $e) {
    die("DBエラー: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>調べた単語履歴</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* 他のページとボタンデザインを統一 */
    .nav-btn {
        display: inline-block;
        padding: 12px 25px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: bold;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        transition: opacity 0.2s;
        border: none;
    }
    .nav-btn:hover { opacity: 0.8; }
    .btn-blue { background: #2196F3; color: white !important; }
    .btn-red { background: #d32f2f; color: white !important; }
    .btn-gray { background: #6c757d; color: white !important; }
    
    .history-table th { background: #6c757d; color: white; }
    .ruby-target { line-height: 1.8; }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
  <div class="container" style="max-width: 900px; margin: 0 auto; padding: 20px;">
    <h1>🔍 <?php echo htmlspecialchars($userName); ?> さんの単語履歴</h1>

    <div style="text-align:right; margin-bottom:20px;">
        <button id="toggleRubyBtn" style="padding:8px 15px; border-radius:5px; cursor:pointer; background:#6c757d; color:white; border:none;">ふりがな表示切替</button>
    </div>

    <?php if (!empty($records)): ?>
      <table class="history-table">
        <tr>
          <th style="width: 25%;">単語</th>
          <th>意味</th>
          <th style="width: 20%;">検索日時</th>
        </tr>
        <?php foreach ($records as $row): ?>
          <tr>
            <td class="ruby-target"><strong><?php echo htmlspecialchars($row["word"]); ?></strong></td>
            <td class="ruby-target"><?php echo htmlspecialchars($row["meaning"]); ?></td>
            <td style="font-size: 0.85em; color: #666;"><?php echo htmlspecialchars($row["created_at"]); ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p style="text-align:center; padding:50px; background:#f9f9f9; border-radius:10px;">まだ単語履歴はありません。</p>
    <?php endif; ?>

    <div style="margin-top:40px; text-align:center; display:flex; justify-content:center; gap:15px; flex-wrap:wrap;">
      <a href="test.php" class="nav-btn btn-blue">◀ 試験画面へ戻る</a>
      <a href="review.php" class="nav-btn btn-red">📝 復習モードへ</a>
      <a href="history.php" class="nav-btn btn-gray">📊 学習履歴へ戻る</a>
    </div>
  </div>

<script>
window.dictMap = <?php echo $dictJson; ?>;
</script>
<script src="script.js"></script>
<script>
$(function() {
    // ページ読み込み時にルビを適用
    if (typeof window.applyRuby === "function") {
        setTimeout(function() {
            window.applyRuby($('.ruby-target'));
            window.applyRubyVisibility($('.ruby-target'));
        }, 100);
    }
});
</script>
</body>
</html>