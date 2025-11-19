<?php
session_start();

// ログインユーザー
$user = $_SESSION["user"] ?? "guest";

// POSTで受け取るデータ
$questionId   = $_POST["question_id"] ?? "";
$examNumber   = $_POST["exam_number"] ?? "";
$answerNumber = $_POST["answer"] ?? "";
$correctNumber= $_POST["correct"] ?? "";
$subject      = $_POST["subject"] ?? "";  // ← 追加

// 判定
$result = ($answerNumber == $correctNumber) ? "○" : "×";

// 日時
$datetime = date("Y-m-d H:i:s");

// ユーザーごとの履歴ファイル
$filePath = __DIR__ . "/history_" . $user . ".csv";

// 追記保存
$file = fopen($filePath, "a");
fputcsv($file, [$user, $questionId, $examNumber, $answerNumber, $correctNumber, $result, $datetime, $subject]);
fclose($file);

// 判定結果を画面に返す
echo "<p>問題ID: {$questionId} / 試験番号: {$examNumber}</p>";
echo "<p>あなたの回答: {$answerNumber}</p>";
echo "<p>正解: {$correctNumber}</p>";
echo "<p>判定: {$result}</p>";
echo "<p>保存しました（{$datetime}）</p>";

// 戻るリンク（subjectを正しく渡す）
if ($subject) {
    echo "<a href='test.php?subject=" . urlencode($subject) . "'>← 戻る</a>";
} else {
    echo "<a href='test.php'>← 戻る</a>";
}
?>
