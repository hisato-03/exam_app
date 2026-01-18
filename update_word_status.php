<?php
// 自分の弱点を克服するための学習ツール 
// バックグラウンドでMySQLのデータ書き換え
session_start();
$userId = $_SESSION["user_id"] ?? 0;

if ($userId === 0 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$word   = $_POST['word'] ?? '';
$status = (int)($_POST['status'] ?? 0);

try {
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 同じユーザーの同じ単語をすべて更新（履歴リストがグループ化されているため）
    $stmt = $pdo->prepare("UPDATE searched_words SET is_mastered = ? WHERE user_id = ? AND word = ?");
    $success = $stmt->execute([$status, $userId, $word]);

    echo json_encode(['success' => $success]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}