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
    $client->setAuthConfig(__DIR__ . '/credentials.json');
    $client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
    $service = new Google\Service\Sheets($client);

    // ▼ 辞書データの取得
    try {
        $dictResponse = $service->spreadsheets_values->get('1LDr4Acf_4SE-Wzp-ypPxM6COZdOt2QYumak8hIVVdxo', 'dictionary_upload!A2:B');
        $dictValues = $dictResponse->getValues() ?? [];
        $dictMap = [];
        foreach ($dictValues as $row) {
            if (!empty($row[0])) $dictMap[$row[0]] = $row[1] ?? '';
        }
        $dictJson = json_encode($dictMap, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) { $dictJson = '{}'; }

    $sheetId = '1wBLqdju-BmXS--aPCMMC3PipvCpBFXmdVemT0X2rKew';
    $subjectsInHistory = array_unique(array_column($records, 'subject'));

    if (!function_exists('norm_id')) {
        function norm_id($s) { return strtoupper(mb_convert_kana(trim((string)$s), 'as')); }
    }

    $questionMap = [];
    foreach ($subjectsInHistory as $tabName) {
        if (!$tabName) continue;
        $cacheKey = "subject_data_" . $tabName;
        if (isset($_SESSION[$cacheKey]) && $_SESSION[$cacheKey]['expires'] > time()) {
            $sheetValues = $_SESSION[$cacheKey]['data'];
        } else {
            try {
                $range = $tabName . '!A2:I';
                $sheetResponse = $service->spreadsheets_values->get($sheetId, $range);
                $sheetValues = $sheetResponse->getValues() ?? [];
                $_SESSION[$cacheKey] = ['data' => $sheetValues, 'expires' => time() + 600];
            } catch (Exception $e) { continue; }
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
} catch (PDOException $e) { die("DBエラー: " . htmlspecialchars($e->getMessage())); }
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>学習履歴</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="main-layout container">
  <h1>📊 学習履歴（試験のみ）</h1>
  <div class="footer-actions main-layout">
    <a href="test.php" class="btn-round" style="background:#2196F3;">◀ 試験画面へ戻る</a>
    <a href="review.php" class="btn-round" style="background:#d32f2f;">📝 復習モードへ</a>
    <a href="dictionary_history.php" class="btn-round" style="background:#6c757d;">🔍 単語履歴を見る</a>
   </div>
  <?php if (!empty($subjectStats)): ?>
    <div class="card-style" style="margin-bottom: 30px;">
        <h2>📊 科目別分析グラフ</h2>
        <div style="max-width: 800px; margin: 0 auto; height: 400px;">
            <canvas id="subjectChart"></canvas>
        </div>
    </div>

    <div class="card-style" style="margin-bottom: 30px;">
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
    </div>
  <?php endif; ?>

  <?php if (!empty($records)): ?>
    <div class="card-style">
        <h2>履歴一覧</h2>
   

        <div style="overflow-x: auto;">
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
                  <td style="white-space:nowrap;"><?php echo htmlspecialchars($row["exam_number"]); ?></td>
                  <td><?php echo htmlspecialchars($row["answer"]); ?></td>
                  <td><?php echo htmlspecialchars($row["correct"]); ?></td>
                  <td style="font-weight:bold; color: <?php echo $row["is_correct"] ? '#d9534f' : '#337ab7'; ?>;">
                    <?php echo $row["is_correct"] ? "○" : "×"; ?>
                  </td>
                  <td><?php echo htmlspecialchars($row["subject"] ?? ''); ?></td>
                  <td style="font-size:0.85em; white-space:nowrap;"><?php echo htmlspecialchars($row["created_at"]); ?></td>
                  <td>
                    <button class="show-detail btn-round" data-target="detail-<?php echo $id; ?>" style="background:#6c757d; cursor:pointer;">確認</button>
                  </td>
                </tr>
                <tr id="detail-<?php echo $id; ?>" class="detail-row" style="display:none">
                  <td colspan="9">
                    <div class="detail-content" style="padding:15px; background:#f9f9f9; border-radius:8px; text-align:left;">
                        <?php if ($q): ?>
                          <div style="margin-bottom:12px; border-bottom:1px solid #eee; padding-bottom:8px;">
                            <span class="exam-badge" style="background:#e3f2fd; color:#1976d2; padding:2px 8px; border-radius:4px; font-weight:bold;"><?php echo $dispEx; ?></span>
                            <span style="margin-left:10px; color:#666; font-size:0.85em;">[科目: <?php echo htmlspecialchars($q['subject']); ?>]</span>
                          </div>
                          <div class="content-ruby">
                            <strong>問題文:</strong> <?php echo htmlspecialchars($q['text']); ?><br>
                            <ul style="margin: 10px 0; list-style:none; padding-left:0;">
                              <?php foreach ($q['choices'] as $idx => $ch): ?>
                                <li style="margin-bottom:5px; <?php echo ($idx+1 == (int)$q['correct']) ? 'color:#d9534f; font-weight:bold;' : ''; ?>">
                                  <?php echo ($idx+1); ?>. <?php echo htmlspecialchars($ch); ?>
                                  <?php if($idx+1 == (int)$row["answer"]) echo " ← あなたの回答"; ?>
                                </li>
                              <?php endforeach; ?>
                            </ul>
                            <strong>💡 解説:</strong> 
                            <div style="margin-top:5px; padding:10px; background:#fff9c4; border-radius:4px; border-left:4px solid #fbc02d;">
                                <?php echo htmlspecialchars($q['explain']); ?>
                            </div>
                          </div>
                        <?php else: ?>
                          <strong>詳細未取得:</strong> question_id=<?php echo $qidEsc; ?> のデータが見つかりませんでした。
                        <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </table>
        </div>
    </div>
  <?php else: ?>
    <p>まだ履歴はありません。</p>
  <?php endif; ?>


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>window.dictMap = <?php echo $dictJson ?? '{}'; ?>;</script>
<script src="script.js"></script>

<script>
$(function() {
    // 1. 詳細表示の切り替え
    $(".show-detail").on("click", function() {
        const targetId = $(this).data("target");
        const $detailRow = $("#" + targetId);
        $detailRow.toggle();
        if ($detailRow.is(":visible") && typeof window.applyRuby === "function") {
            const $target = $detailRow.find('.content-ruby');
            window.applyRuby($target[0]);
            window.applyRubyVisibility($target);
        }
    });

    // 2. グラフ描画
    const statsData = <?php echo json_encode($subjectStats); ?>;
    if (statsData && statsData.length > 0) {
        const labels = statsData.map(item => item.subject);
        const accuracyData = statsData.map(item => {
            return item.total > 0 ? ((item.correct / item.total) * 100).toFixed(1) : 0;
        });

        const ctx = document.getElementById('subjectChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '正解率 (%)',
                    data: accuracyData,
                    backgroundColor: 'rgba(33, 150, 243, 0.6)',
                    borderColor: 'rgba(33, 150, 243, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true, max: 100, title: { display: true, text: '正解率 (%)' } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) { return `正解率: ${context.parsed.x}%`; }
                        }
                    }
                }
            }
        });
    }
});
</script>
</body>
</html>