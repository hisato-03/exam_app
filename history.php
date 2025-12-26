<?php
require "auth.php"; // ログインチェック
require_once __DIR__ . '/load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');

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

    // ▼ Google Sheets 設定
    $client = new Google\Client();
    $client->setApplicationName('ExamApp');
    $client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
    $client->setAuthConfig(__DIR__ . '/credentials.json');
    $service = new Google\Service\Sheets($client);

    // ▼ 辞書データの取得
    try {
        $dictResponse = $service->spreadsheets_values->get(
            '1LDr4Acf_4SE-Wzp-ypPxM6COZdOt2QYumak8hIVVdxo',
            'dictionary_upload!A2:B'
        );
        $dictValues = $dictResponse->getValues() ?? [];
        $dictMap = [];
        foreach ($dictValues as $row) {
            $kanji = $row[0] ?? '';
            $furigana = $row[1] ?? '';
            if ($kanji && $furigana) $dictMap[$kanji] = $furigana;
        }
        $dictJson = json_encode($dictMap, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        $dictJson = '{}';
    }

    $sheetId = '1wBLqdju-BmXS--aPCMMC3PipvCpBFXmdVemT0X2rKew';
    $subjectsInHistory = array_unique(array_column($records, 'subject'));

    // 正規化関数
    if (!function_exists('norm_id')) {
        function norm_id($s) {
            return strtoupper(mb_convert_kana(trim((string)$s), 'as'));
        }
    }

    $questionMap = [];
    foreach ($subjectsInHistory as $tabName) {
        if (!$tabName) continue;

        $cacheKey = "subject_data_" . $tabName;
        $sheetValues = [];

        if (isset($_SESSION[$cacheKey]) && $_SESSION[$cacheKey]['expires'] > time()) {
            $sheetValues = $_SESSION[$cacheKey]['data'];
        } else {
            try {
                $range = $tabName . '!A2:I';
                $sheetResponse = $service->spreadsheets_values->get($sheetId, $range);
                $sheetValues = $sheetResponse->getValues() ?? [];
                $_SESSION[$cacheKey] = ['data' => $sheetValues, 'expires' => time() + 600];
            } catch (Exception $e) {
                continue;
            }
        }

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
  <style>
    .detail-content { padding: 15px; background: #fdfdfd; border-radius: 8px; border: 1px solid #eee; line-height: 1.6; }
    .exam-badge { background: #e3f2fd; color: #1976d2; padding: 2px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
    .subject-label { margin-left: 10px; color: #666; font-size: 0.85em; }
    .history-back-nav { margin-top: 40px; text-align: center; display: flex; justify-content: center; gap: 15px; flex-wrap: wrap; }
  </style>
</head>
<body>
<div class="container">
  <h1>学習履歴（試験のみ）</h1>

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

          // 出典ラベルの生成
          $rawEx = $row["exam_number"] ?? ''; 
          $dispEx = "問題";
          if (!empty($rawEx) && strpos($rawEx, '-') !== false) {
              $p = explode('-', $rawEx);
              $dispEx = "第" . $p[0] . "回 問" . $p[1];
          }
        ?>
        <tr>
          <td><?php echo $id; ?></td>
          <td><?php echo $qidEsc; ?></td>
          <td><?php echo htmlspecialchars($row["exam_number"]); ?></td>
          <td><?php echo htmlspecialchars($row["answer"]); ?></td>
          <td><?php echo htmlspecialchars($row["correct"]); ?></td>
          <td style="font-weight:bold; color: <?php echo $row["is_correct"] ? '#d9534f' : '#337ab7'; ?>;">
            <?php echo $row["is_correct"] ? "○" : "×"; ?>
          </td>
          <td><?php echo htmlspecialchars($row["subject"] ?? ''); ?></td>
          <td style="font-size:0.85em;"><?php echo htmlspecialchars($row["created_at"]); ?></td>
          <td>
            <button class="show-detail" data-target="detail-<?php echo $id; ?>" style="cursor:pointer; padding:5px 10px;">確認</button>
          </td>
        </tr>
        <tr id="detail-<?php echo $id; ?>" class="detail-row" style="display:none">
          <td colspan="9">
            <div class="detail-content">
                <?php if ($q): ?>
                  <div style="margin-bottom:12px; border-bottom:1px solid #eee; padding-bottom:8px;">
                    <span class="exam-badge"><?php echo $dispEx; ?></span>
                    <span class="subject-label">[科目: <?php echo htmlspecialchars($q['subject']); ?>]</span>
                  </div>

                  <div class="content-ruby">
                    <strong>問題文:</strong> <?php echo htmlspecialchars($q['text']); ?><br>
                    <strong>選択肢:</strong>
                    <ul style="margin: 5px 0;">
                      <?php foreach ($q['choices'] as $idx => $ch): ?>
                        <li style="<?php echo ($idx+1 == (int)$q['correct']) ? 'color:#d9534f; font-weight:bold;' : ''; ?>">
                          <?php echo ($idx+1); ?>. <?php echo htmlspecialchars($ch); ?>
                          <?php if($idx+1 == (int)$row["answer"]) echo " ← あなたの回答"; ?>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                    <strong>解説:</strong> 
                    <div style="margin-top:5px; padding:10px; background:#fff9c4; border-radius:4px;">
                        <?php echo htmlspecialchars($q['explain']); ?>
                    </div>
                  </div>
                <?php else: ?>
                  <strong>詳細未取得:</strong> question_id=<?php echo $qidEsc; ?> に対応する問題データが見つかりませんでした。<br>
                <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?>
    <p>まだ履歴はありません。</p>
  <?php endif; ?>

  <div class="history-back-nav">
    <a href="test.php" class="btn" style="background:#2196F3; color:white; padding:12px 25px; border-radius:30px; text-decoration:none; font-weight:bold; box-shadow:0 2px 5px rgba(0,0,0,0.2);">◀ 試験画面へ戻る</a>
    <a href="review.php" class="btn" style="background:#d32f2f; color:white; padding:12px 25px; border-radius:30px; text-decoration:none; font-weight:bold; box-shadow:0 2px 5px rgba(0,0,0,0.2);">📝 復習モードへ</a>
    <a href="dictionary_history.php" class="btn" style="background:#6c757d; color:white; padding:12px 25px; border-radius:30px; text-decoration:none; font-weight:bold; box-shadow:0 2px 5px rgba(0,0,0,0.2);">🔍 単語履歴を見る</a>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
// PHPから辞書データを渡す
window.dictMap = <?php echo $dictJson ?? '{}'; ?>;
</script>
<script src="script.js"></script>

<script>
$(function() {
    $(".show-detail").on("click", function() {
        const targetId = $(this).data("target");
        const $detailRow = $("#" + targetId);
        
        $detailRow.toggle();

        if ($detailRow.is(":visible")) {
            if (typeof window.applyRuby === "function") {
                // detail-content内の content-ruby クラスを持つ要素に適用
                const $target = $detailRow.find('.content-ruby');
                window.applyRuby($target);
                window.applyRubyVisibility($target);
            }
        }
    });
});
</script>
</body>
</html>