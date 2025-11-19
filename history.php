<?php
require "auth.php"; // ログインチェック

// ▼ 科目フィルター（GETパラメータがなければ空＝全科目）
$subject = $_GET['subject'] ?? '';

$user = $_SESSION["user"];
$filePath = __DIR__ . "/history_" . $user . ".csv";

$records = [];
if (file_exists($filePath)) {
    if (($file = fopen($filePath, "r")) !== false) {
        while (($data = fgetcsv($file)) !== false) {
            $records[] = $data;
        }
        fclose($file);
    }
}

// ▼ 正解率計算
$correctCount = 0;
$totalCount = 0;
foreach ($records as $row) {
    // 科目フィルターがある場合は一致するものだけ対象
    if ($subject && ($row[7] ?? '') !== $subject) continue;

    $totalCount++;
    if (($row[5] ?? '') === "○") {
        $correctCount++;
    }
}
$accuracy = $totalCount > 0 ? round(($correctCount / $totalCount) * 100, 1) : 0;

// ▼ 科目別集計
$subjectStats = [];
foreach ($records as $row) {
    $subj = $row[7] ?? '';
    if ($subj === '') continue;

    if (!isset($subjectStats[$subj])) {
        $subjectStats[$subj] = ["correct" => 0, "total" => 0];
    }

    $subjectStats[$subj]["total"]++;
    if (($row[5] ?? '') === "○") {
        $subjectStats[$subj]["correct"]++;
    }
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
        <?php foreach ($subjectStats as $subj => $stats): ?>
          <?php $acc = round(($stats["correct"] / $stats["total"]) * 100, 1); ?>
          <tr>
            <td><?php echo htmlspecialchars($subj); ?></td>
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
        <option value="人間の尊厳と自立" <?= $subject==="人間の尊厳と自立" ? "selected" : "" ?>>人間の尊厳と自立</option>
        <option value="人間関係とコミュニケーション" <?= $subject==="人間関係とコミュニケーション" ? "selected" : "" ?>>人間関係とコミュニケーション</option>
        <option value="社会の理解" <?= $subject==="社会の理解" ? "selected" : "" ?>>社会の理解</option>
        <option value="こころとからだ" <?= $subject==="こころとからだ" ? "selected" : "" ?>>こころとからだ</option>
        <option value="発達と老化の理解" <?= $subject==="発達と老化の理解" ? "selected" : "" ?>>発達と老化の理解</option>
        <option value="認知症の理解" <?= $subject==="認知症の理解" ? "selected" : "" ?>>認知症の理解</option>
        <option value="障害の理解" <?= $subject==="障害の理解" ? "selected" : "" ?>>障害の理解</option>
        <option value="医療的ケア" <?= $subject==="医療的ケア" ? "selected" : "" ?>>医療的ケア</option>
        <option value="介護の基本" <?= $subject==="介護の基本" ? "selected" : "" ?>>介護の基本</option>
        <option value="コミュニケーション技術" <?= $subject==="コミュニケーション技術" ? "selected" : "" ?>>コミュニケーション技術</option>
        <option value="生活支援技術" <?= $subject==="生活支援技術" ? "selected" : "" ?>>生活支援技術</option>
        <option value="介護過程" <?= $subject==="介護過程" ? "selected" : "" ?>>介護過程</option>
        <option value="総合問題" <?= $subject==="総合問題" ? "selected" : "" ?>>総合問題</option>
      </select>
      <button type="submit" class="btn btn-subject">表示</button>
    </form>

    <!-- ▼ 履歴テーブル -->
    <?php if (!empty($records)): ?>
      <table>
        <tr>
          <th>設問番号</th>
          <th>回答番号</th>
          <th>正解番号</th>
          <th>判定</th>
          <th>日時</th>
          <th>科目</th>
        </tr>
        <?php foreach ($records as $row): ?>
          <?php if ($subject && ($row[7] ?? '') !== $subject) continue; ?>
          <tr>
            <td><?php echo htmlspecialchars($row[2]); ?></td>
            <td><?php echo htmlspecialchars($row[3]); ?></td>
            <td><?php echo htmlspecialchars($row[4]); ?></td>
            <td class="<?php echo ($row[5] === "○") ? "correct" : "wrong"; ?>">
              <?php echo htmlspecialchars($row[5]); ?>
            </td>
            <td><?php echo htmlspecialchars($row[6]); ?></td>
            <td><?php echo htmlspecialchars($row[7] ?? ''); ?></td>
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
