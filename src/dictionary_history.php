<?php
session_start();
require "auth.php"; // ログインチェック

// ▼ ユーザー情報
$userId   = $_SESSION["user_id"] ?? 0;
$userName = $_SESSION["user"] ?? "guest";

if ($userId === 0) {
    die("ログインしてください。");
}

// ▼ DB接続
try {
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ▼ 履歴取得（ユーザーごと）
$stmt = $pdo->prepare("
    SELECT s.*, u.username
    FROM searched_words s
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id=?
    ORDER BY s.created_at DESC
");
$stmt->execute([$userId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userName = $records[0]['username'] ?? $userName;


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
</head>
<body>
  <div class="container">
    <h1><?php echo htmlspecialchars($userName); ?> さんの調べた単語履歴</h1>

    <!-- ▼ 履歴テーブル -->
    <?php if (!empty($records)): ?>
      <table>
        <tr>
          <th>ID</th>
          <th>単語</th>
          <th>意味</th>
          <th>日時</th>
        </tr>
        <?php foreach ($records as $row): ?>
          <tr>
            <td><?php echo htmlspecialchars($row["id"]); ?></td>
            <td><?php echo htmlspecialchars($row["word"]); ?></td>
            <td><?php echo htmlspecialchars($row["meaning"]); ?></td>
            <td><?php echo htmlspecialchars($row["created_at"]); ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p>まだ単語履歴はありません。</p>
    <?php endif; ?>

    <!-- ▼ 戻るリンク群 -->
    <?php
      // 履歴に subject が保存されている場合はそれを利用、なければデフォルト科目
      $lastSubject = $records[0]["subject"] ?? "人間の尊厳と自立";
    ?>
    <div style="margin-top:20px; text-align:center;">
      <a href="history.php" class="btn-link" style="margin-right:20px;">← 学習履歴へ戻る</a>
      <a href="test.php?subject=<?= urlencode($lastSubject) ?>" class="btn-link">試験画面へ戻る →</a>
    </div>
  </div>
</body>
</html>
