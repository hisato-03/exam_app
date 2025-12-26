<?php

session_start();

$userId = $_SESSION["user_id"] ?? 0; // ログインユーザーID or 0（ゲスト）

// POSTで受け取るデータ
$questionId  = $_POST["question_id"] ?? "";
$examNumber  = $_POST["exam_number"] ?? "";
$answer      = intval($_POST["answer"] ?? 0);
$correct     = intval($_POST["correct"] ?? 0);
$subject     = $_POST["subject"] ?? "";

// 判定フラグ
$isCorrect = ($answer === $correct) ? 1 : 0;

// ▼ ゲストの場合は保存せずに判定だけ返す
if ($userId === 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "answer"    => $answer,
        "correct"   => $correct,
        "is_correct"=> $isCorrect,
        "judgement" => $isCorrect ? "○" : "×",
        "message"   => "ゲスト利用のため履歴は保存されません。"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


// ▼ ログインユーザーのみDB保存
try {
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        INSERT INTO history (user_id, question_id, exam_number, answer, correct, is_correct, subject, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $questionId, $examNumber, $answer, $correct, $isCorrect, $subject]);

} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["error" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// ▼ JSON返却（ログインユーザー用）
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    "post_data"   => $_POST,
    "question_id" => $questionId,
    "exam_number" => $examNumber,
    "answer"      => $answer,
    "correct"     => $correct,
    "is_correct"  => (bool)$isCorrect, // JavaScriptで扱いやすいようboolに変換
    "judgement"   => $isCorrect ? "○" : "×",
    "saved_at"    => date("Y-m-d H:i:s"),
    "subject"     => $subject
], JSON_UNESCAPED_UNICODE);
