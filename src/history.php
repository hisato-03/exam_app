<?php
require "auth.php"; // ログインチェック

// ▼ フィルター（GETパラメータ）
$subject = $_GET['subject'] ?? '';
$user    = $_SESSION["user"] ?? "guest";

// ▼ DB接続
try {
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ▼ フィルター（GETパラメータ）
$subject = $_GET['subject'] ?? '';
$userId  = $_SESSION["user_id"] ?? 0;

// ▼ 履歴取得（ユーザーごと）
$query = "
    SELECT h.*, u.username
    FROM history h
    JOIN users u ON h.user_id = u.id
    WHERE h.user_id=?
";
$params = [$userId];

if ($subject) {
    $query .= " AND h.subject=?";
    $params[] = $subject;
}
$query .= " ORDER BY h.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ▼ 科目別集計
$stmt = $pdo->prepare("
    SELECT h.subject, SUM(h.is_correct) AS correct, COUNT(*) AS total
    FROM history h
    WHERE h.user_id=?
    GROUP BY h.subject
");
$stmt->execute([$userId]);
$subjectStats = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    die("DBエラー: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>学習履歴</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <h1>学習履歴（試験のみ）</h1>

    <!-- ▼ フィルター付きフォーム -->
    <form method="get">
      <label for="subject">科目で絞り込み:</label>
      <select name="subject" id="subject" class="subject-select">
        <option value="">全科目</option>
        <?php
        $subjects = [
          "人間の尊厳と自立","人間関係とコミュニケーション","社会の理解","こころとからだ",
          "発達と老化の理解","認知症の理解","障害の理解","医療的ケア","介護の基本",
          "コミュニケーション技術","生活支援技術","介護過程","総合問題"
        ];
        foreach ($subjects as $s) {
            $selected = ($subject === $s) ? "selected" : "";
            echo "<option value='".htmlspecialchars($s)."' {$selected}>".htmlspecialchars($s)."</option>";
        }
        ?>
      </select>

      <button type="submit" class="btn btn-subject">表示</button>
    </form>

    <!-- ▼ 科目別正解率一覧 -->
    <?php if (!empty($subjectStats)): ?>
      <h2>科目別正解率一覧</h2>
      <table>
        <tr>
          <th>科目</th>
          <th>正解数</th>
          <th>総数</th>
          <th>正解率</th>
        </tr>
        <?php foreach ($subjectStats as $stats): ?>
          <?php $acc = round(($stats["correct"] / $stats["total"]) * 100, 1); ?>
          <tr>
            <td><?php echo htmlspecialchars($stats["subject"]); ?></td>
            <td><?php echo $stats["correct"]; ?></td>
            <td><?php echo $stats["total"]; ?></td>
            <td><?php echo $acc; ?>%</td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>

    <!-- ▼ 履歴テーブル -->
    <?php if (!empty($records)): ?>
      <h2>履歴一覧</h2>
      <table>
        <tr>
          <th>ID</th>
          <th>問題ID</th>
          <th>試験番号</th>
          <th>回答</th>
          <th>正解</th>
          <th>判定</th>
          <th>科目</th>
          <th>日時</th>
        </tr>
        <?php foreach ($records as $row): ?>
          <tr>
            <td><?php echo htmlspecialchars($row["id"]); ?></td>
            <td><?php echo htmlspecialchars($row["question_id"]); ?></td>
            <td><?php echo htmlspecialchars($row["exam_number"]); ?></td>
            <td><?php echo htmlspecialchars($row["answer"]); ?></td>
            <td><?php echo htmlspecialchars($row["correct"]); ?></td>
            <td><?php echo $row["is_correct"] ? "○" : "×"; ?></td>
            <td><?php echo htmlspecialchars($row["subject"] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($row["created_at"]); ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p>まだ履歴はありません。</p>
    <?php endif; ?>

    <!-- ▼ 戻るリンク -->
    <a href="test.php">← メイン画面へ戻る</a>
  </div>
</body>
</html>
