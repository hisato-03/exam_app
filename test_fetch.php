<?php
// test_fetch.php の修正版
require "auth.php"; // ログインチェック
require_once __DIR__ . '/load_credentials.php'; // 認証関数
restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64'); // 認証実行

require 'vendor/autoload.php';
require_once 'predicted_service.php'; // 先ほど作ったサービス

// .env から ID を読み込む（もし .env を使っていなければ直接 ID を書いてもOK）
// $spreadsheetId = 'あなたのスプレッドシートID'; 

$sheetName = '社会の理解・自社';
$data = getPredictedQuestionsFromSheet($sheetName);

echo "<h1>Sheet Data Test: " . htmlspecialchars($sheetName) . "</h1>";

if (empty($data)) {
    echo "<p style='color:red;'>データが取得できませんでした。以下を確認してください：</p>";
    echo "<ul><li>シート名が正しいか</li><li>スプレッドシートIDが正しいか</li><li>サービスアカウントに共有権限があるか</li></ul>";
} else {
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}