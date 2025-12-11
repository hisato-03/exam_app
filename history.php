<?php
require "auth.php"; // ログインチェック
require __DIR__ . '/vendor/autoload.php'; // Google API Client 読み込み

$subject = $_GET['subject'] ?? '';
$userId  = $_SESSION["user_id"] ?? 0;

use Google\Client;
use Google\Service\Sheets;

try {
    // ▼ DB接続
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ▼ 履歴取得
    $query = "SELECT h.*, u.username FROM history h JOIN users u ON h.user_id = u.id WHERE h.user_id=?";
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
        FROM history h WHERE h.user_id=? GROUP BY h.subject
    ");
    $stmt->execute([$userId]);
    $subjectStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ▼ Google Sheetsから履歴に出てきた科目タブだけを取得
$client = new Google\Client();
$client->setApplicationName('ExamApp');
$client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
$client->setAuthConfig(__DIR__ . '/credentials.json');
$service = new Google\Service\Sheets($client);

$sheetId = '1wBLqdju-BmXS--aPCMMC3PipvCpBFXmdVemT0X2rKew';

// 履歴から科目一覧を抽出
$subjectsInHistory = array_unique(array_column($records, 'subject'));

// 正規化関数
function norm_id($s) {
    return strtoupper(mb_convert_kana(trim((string)$s), 'as'));
}

$questionMap = [];
foreach ($subjectsInHistory as $tabName) {
    try {
        $range = $tabName . '!A2:I';
        $sheetResponse = $service->spreadsheets_values->get($sheetId, $range);
        $sheetValues = $sheetResponse->getValues() ?? [];

        foreach ($sheetValues as $row) {
            $qid = isset($row[0]) ? norm_id($row[0]) : '';
            if ($qid !== '') {
                $questionMap[$qid] = [
                    'text'    => $row[1] ?? '',
                    'choices' => array_map('trim', array_slice($row, 2, 5)),
                    'correct' => isset($row[7]) ? trim($row[7]) : '',
                    'explain' => $row[8] ?? '',
                    'subject' => $tabName
                ];
            }
        }
    } catch (Exception $e) {
        echo "<pre>Sheets API Error in {$tabName}: " . htmlspecialchars($e->getMessage()) . "</pre>";
    }
}


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

  <!-- ▼ 科目別正解率一覧 -->
  <?php if (!empty($subjectStats)): ?>
    <h2>科目別正解率一覧</h2>
    <table>
      <tr><th>科目</th><th>正解数</th><th>総数</th><th>正解率</th></tr>
      <?php foreach ($subjectStats as $stats): ?>
        <?php $acc = $stats["total"] > 0 ? round(($stats["correct"] / $stats["total"]) * 100, 1) : 0; ?>
        <tr>
          <td><?php echo htmlspecialchars($stats["subject"]); ?></td>
          <td><?php echo (int)$stats["correct"]; ?></td>
          <td><?php echo (int)$stats["total"]; ?></td>
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
        <th>ID</th><th>問題ID</th><th>試験番号</th><th>回答</th>
        <th>正解</th><th>判定</th><th>科目</th><th>日時</th><th>確認</th>
      </tr>
      <?php foreach ($records as $row): ?>
        <?php
          $id  = htmlspecialchars($row["id"]);
          $qidDbNorm = norm_id($row["question_id"]);
          $qidEsc = htmlspecialchars($row["question_id"]);
          $q = $questionMap[$qidDbNorm] ?? null;
        ?>
        <tr>
          <td><?php echo $id; ?></td>
          <td><?php echo $qidEsc; ?></td>
          <td><?php echo htmlspecialchars($row["exam_number"]); ?></td>
          <td><?php echo htmlspecialchars($row["answer"]); ?></td>
          <td><?php echo htmlspecialchars($row["correct"]); ?></td>
          <td><?php echo $row["is_correct"] ? "○" : "×"; ?></td>
          <td><?php echo htmlspecialchars($row["subject"] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($row["created_at"]); ?></td>
          <td>
            <button class="show-detail" data-target="detail-<?php echo $id; ?>">確認</button>
          </td>
        </tr>
        <tr id="detail-<?php echo $id; ?>" class="detail-row" style="display:none">
          <td colspan="9">
            <?php if ($q): ?>
              <strong>問題文:</strong> <?php echo htmlspecialchars($q['text']); ?><br>
              <strong>選択肢:</strong>
              <ul>
                <?php foreach ($q['choices'] as $ch): ?>
                  <li><?php echo htmlspecialchars($ch); ?></li>
                <?php endforeach; ?>
              </ul>
              <strong>正解:</strong> <?php echo htmlspecialchars($q['correct']); ?><br>
              <strong>解説:</strong> <?php echo htmlspecialchars($q['explain']); ?><br>
            <?php else: ?>
              <strong>詳細未取得:</strong> question_id=<?php echo $qidEsc; ?> に対応する問題が見つかりません。<br>
              <small>DEBUG keys count: <?php echo count($questionMap); ?></small>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?>
    <p>まだ履歴はありません。</p>
  <?php endif; ?>

  <a href="javascript:history.back()">← 試験画面へ戻る</a>
  <a href="dictionary_history.php">→ 単語履歴を見る</a>
</div>

<!-- ▼ JavaScriptでトグル制御 -->
<script>
document.addEventListener("DOMContentLoaded", function() {
  document.querySelectorAll(".show-detail").forEach(function(btn) {
    btn.addEventListener("click", function() {
      const targetId = this.getAttribute("data-target");
      const detailRow = document.getElementById(targetId);
      if (detailRow) {
        detailRow.style.display = (detailRow.style.display === "none" || detailRow.style.display === "")
          ? "table-row"
          : "none";
      }
    });
  });
});
</script>
</body>
</html>
