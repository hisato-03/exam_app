<?php
/**
 * admin_import.php
 * AIが生成したJSONをスプレッドシートに一括登録するツール
 */
require "auth.php"; // ログイン認証
require_once __DIR__ . '/load_credentials.php';
require_once 'predicted_service.php';

// 認証情報の復元
restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');

$message = "";
$messageClass = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonText = $_POST['json_data'] ?? '';
    $targetSheet = $_POST['sheet_name'] ?? '';
    
    // JSONを配列にデコード
    $data = json_decode($jsonText, true);
    
    if (is_array($data) && !empty($targetSheet)) {
        try {
            // predicted_service.php で定義した関数を呼び出し
            appendQuestionsToSheet($targetSheet, $data);
            $count = count($data);
            $message = "✅ 成功！ {$count} 件の問題を「{$targetSheet}」の最終行に追加しました。";
            $messageClass = "success";
        } catch (Exception $e) {
            $message = "❌ エラーが発生しました: " . $e->getMessage();
            $messageClass = "error";
        }
    } else {
        $message = "⚠️ エラー: JSONの形式が正しくないか、シート名が選択されていません。";
        $messageClass = "warning";
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI問題一括登録ツール</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .import-container { max-width: 800px; margin: 40px auto; padding: 20px; }
        .msg { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .warning { background-color: #fff3e0; color: #ef6c00; border: 1px solid #ffe0b2; }
        .error { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        textarea { width: 100%; font-family: 'Courier New', monospace; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; padding: 10px; }
        select { width: 100%; padding: 12px; margin: 10px 0 25px 0; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; }
        label { font-weight: bold; color: #333; }
        .btn-import { background: #4CAF50; color: white; border: none; padding: 15px 30px; border-radius: 30px; cursor: pointer; font-size: 18px; width: 100%; transition: 0.3s; }
        .btn-import:hover { background: #43a047; box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
    </style>
</head>
<body>

<div class="import-container card-style">
    <h2 style="text-align:center; color:#2196F3;">🚀 AI予想問題 一括登録ツール</h2>
    <hr>

    <?php if ($message): ?>
        <div class="msg <?php echo $messageClass; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="sheet_name">1. 登録先のシート（科目）を選択</label>
        <select name="sheet_name" id="sheet_name" required>
            <option value="">-- シートを選択してください --</option>
            <option value="社会の理解・自社">社会の理解・自社</option>
            <option value="こころとからだのしくみ・自社">こころとからだのしくみ・自社</option>
            <option value="生活支援技術・自社">生活支援技術・自社</option>
            </select>

        <label for="json_data">2. AIが作成したJSONデータを貼り付け</label>
        <textarea name="json_data" id="json_data" rows="15" placeholder='[
  {
    "id": "PRE001",
    "question": "問題文...",
    "option1": "選択肢1",
    "option2": "選択肢2",
    "option3": "選択肢3",
    "option4": "選択肢4",
    "option5": "選択肢5",
    "answer": 1,
    "explanation": "解説...",
    "origin": "AI予想",
    "category_sub": "小項目名",
    "image_url": ""
  }
]' required></textarea>

        <p style="font-size: 0.85em; color: #666; margin-top: 10px;">
            ※AIには「この形式のJSON配列で出力して」と指示してください。
        </p>

        <button type="submit" class="btn-import" onclick="return confirm('スプレッドシートに書き込みます。よろしいですか？')">
            スプレッドシートへ一括保存
        </button>
    </form>

    <div style="margin-top: 30px; text-align: center;">
        <a href="index.php" style="text-decoration: none; color: #666;">🏠