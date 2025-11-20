<?php
session_start();

// ログインユーザー
$user = $_SESSION["user"] ?? "guest";

// POSTで受け取るデータ
$questionId   = $_POST["question_id"] ?? "";
$examNumber   = $_POST["exam_number"] ?? "";
$answerNumber = intval($_POST["answer"] ?? 0);
$correctNumber= intval($_POST["correct"] ?? 0);
$subject      = $_POST["subject"] ?? "";  

// 判定
$isCorrect = ($answerNumber === $correctNumber) ? 1 : 0;

// DB接続
try {
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // INSERT
    $stmt = $pdo->prepare("
        INSERT INTO history (user, subject, question_id, exam_number, answer, correct, is_correct)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user, $subject, $questionId, $examNumber, $answerNumber, $correctNumber, $isCorrect]);

    // 判定結果を画面に返す
    echo "<p>問題ID: {$questionId} / 試験番号: {$examNumber}</p>";
    echo "<p>あなたの回答: {$answerNumber}</p>";
    echo "<p>正解: {$correctNumber}</p>";
    echo "<p>判定: " . ($isCorrect ? "○" : "×") . "</p>";
    echo "<p>保存しました（" . date("Y-m-d H:i:s") . "）</p>";

    // 戻るリンク
    if ($subject) {
        echo "<a href='test.php?subject=" . urlencode($subject) . "'>← 戻る</a>";
    } else {
        echo "<a href='test.php'>← 戻る</a>";
    }

} catch (PDOException $e) {
    echo "DBエラー: " . htmlspecialchars($e->getMessage());
}
?>
