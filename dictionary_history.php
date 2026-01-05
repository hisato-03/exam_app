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
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<div class="main-layout container">
    <div class="flex-between" style="margin-bottom:20px;">
        <h1>🔍 単語検索履歴</h1>
        <button id="toggleRubyBtn" class="btn-round" style="background:#6c757d;">ふりがな表示切替</button>
    </div>

    <div class="card-style" style="margin-bottom:30px;">
        <p style="margin-bottom:15px; color:#666;">
            <strong><?php echo htmlspecialchars($userName); ?></strong> さんがこれまでに調べた単語の一覧です。
        </p>

        <?php if (!empty($records)): ?>
            <div style="overflow-x: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th style="width: 20%; white-space:nowrap;">単語</th>
                            <th>意味</th>
                            <th style="width: 25%; white-space:nowrap;">検索日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td class="ruby-target" style="font-weight:bold; vertical-align: top;">
                                    <?php echo htmlspecialchars($row["word"]); ?>
                                </td>
                                <td class="ruby-target" style="line-height:1.6;">
                                    <?php echo htmlspecialchars($row["meaning"]); ?>
                                </td>
                                <td style="font-size: 0.85em; color: #888; white-space:nowrap; vertical-align: top;">
                                    <?php echo htmlspecialchars($row["created_at"]); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:40px; color:#999;">
                <p>まだ単語履歴はありません。</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="flex-between" style="justify-content:center; gap:15px; margin-top:40px;">
        <a href="test.php" class="btn-round" style="background:#2196F3; padding:12px 25px;">◀ 試験画面へ戻る</a>
        <a href="review.php" class="btn-round" style="background:#d32f2f; padding:12px 25px;">📝 復習モードへ</a>
        <a href="history.php" class="btn-round" style="background:#6c757d; padding:12px 25px;">📊 学習履歴へ</a>
    </div>
</div>

<script>window.dictMap = <?php echo $dictJson; ?>;</script>
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