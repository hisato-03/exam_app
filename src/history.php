<?php
require "auth.php"; // ログインチェック

// ▼ 科目フィルター（GETパラメータがなければ空＝全科目）
$subject = $_GET['subject'] ?? '';
$user = $_SESSION["user"] ?? "guest";

// ▼ DB接続
try {
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ▼ 全履歴取得（ユーザーごと）
    if ($subject) {
        $stmt = $pdo->prepare("SELECT * FROM history WHERE user=? AND subject=? ORDER BY created_at DESC");
        $stmt->execute([$user, $subject]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM history WHERE user=? ORDER BY created_at DESC");
        $stmt->execute([$user]);
    }
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ▼ 正解率計算
    $correctCount = 0;
    $totalCount = count($records);
    foreach ($records as $row) {
        if ($row["is_correct"]) $correctCount++;
    }
    $accuracy = $totalCount > 0 ? round(($correctCount / $totalCount) * 100, 1) : 0;

    // ▼ 科目別集計
    $stmt = $pdo->prepare("
        SELECT subject, SUM(is_correct) AS correct, COUNT(*) AS total
        FROM history
        WHERE user=?
        GROUP BY subject
    ");
    $stmt->execute([$user]);
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
    <h1><?php echo htmlspecialchars($user); ?> さんの学習履歴</h1>

    <!-- ▼ 正解率表示 -->
    <?php if ($totalCount > 0): ?>
      <p>正解率: <?php echo $accuracy; ?>%</p>
    <?php else: ?>
      <p>まだ履歴はありません。</p>
    <?php endif; ?>

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

    <!-- ▼ 科目フィルター付きフォーム -->
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

    <!-- ▼ 履歴テーブル -->
    <?php if (!empty($records)): ?>
      <table>
        <tr>
          <th>問題ID</th>
          <th>試験番号</th>
          <th>回答番号</th>
          <th>正解番号</th>
          <th>判定</th>
          <th>日時</th>
          <th>科目</th>
        </tr>
        <?php foreach ($records as $row): ?>
          <tr>
            <td><?php echo htmlspecialchars($row["question_id"]); ?></td>
            <td><?php echo htmlspecialchars($row["exam_number"]); ?></td>
            <td><?php echo htmlspecialchars($row["answer"]); ?></td>
            <td><?php echo htmlspecialchars($row["correct"]); ?></td>
            <td class="<?php echo ($row["is_correct"]) ? "correct" : "wrong"; ?>">
              <?php echo $row["is_correct"] ? "○" : "×"; ?>
            </td>
            <td><?php echo htmlspecialchars($row["created_at"]); ?></td>
            <td><?php echo htmlspecialchars($row["subject"]); ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>

    <!-- ▼ 戻るリンク -->
    <?php if ($subject): ?>
      <a href="test.php?subject=<?php echo urlencode($subject); ?>">← メイン画面へ戻る</a>
    <?php else: ?>
      <a href="test.php">← メイン画面へ戻る</a>
    <?php endif; ?>
  </div>
</body>
</html>
