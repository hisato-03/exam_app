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
    // ▼ 1. DB接続
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ▼ 2. 履歴取得
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

    // ▼ 3. 科目別集計
    $stmt = $pdo->prepare("
        SELECT h.subject, 
               SUM(CASE WHEN h.is_correct = 1 THEN 1 ELSE 0 END) AS correct, 
               COUNT(*) AS total
        FROM history h WHERE h.user_id=? GROUP BY h.subject
    ");
    $stmt->execute([$userId]);
    $subjectStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ▼ 4. 辞書データのみ取得 (グラフには影響しないが script.js で使用)
    // ここは短時間で終わるため残します。もしここも重いならさらに削ります。
    $client = new Google\Client();
    $client->setAuthConfig(__DIR__ . '/credentials.json');
    $client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
    $service = new Google\Service\Sheets($client);
    
    try {
        $dictResponse = $service->spreadsheets_values->get('1LDr4Acf_4SE-Wzp-ypPxM6COZdOt2QYumak8hIVVdxo', 'dictionary_upload!A2:B');
        $dictValues = $dictResponse->getValues() ?? [];
        $dictMap = [];
        foreach ($dictValues as $row) {
            if (!empty($row[0])) $dictMap[$row[0]] = $row[1] ?? '';
        }
        $dictJson = json_encode($dictMap, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) { $dictJson = '{}'; }

    // ▼ 5. 重要な変更：詳細データ(questionMap)はここでは取得しない
    $questionMap = []; 

    if (!function_exists('norm_id')) {
        function norm_id($s) { return strtoupper(mb_convert_kana(trim((string)$s), 'as')); }
    }

} catch (Exception $e) { 
    die("エラーが発生しました: " . htmlspecialchars($e->getMessage())); 
}

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
                    <div class="detail-content not-loaded" style="padding:15px; background:#f9f9f9; border-radius:8px; text-align:left;">
                        ⌛ 読み込み待機中...
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
    // --- A. グラフ描画コード (ここが消えていました) ---
    try {
        const statsData = <?php echo json_encode($subjectStats); ?>;
        if (statsData && statsData.length > 0) {
            const labels = statsData.map(item => item.subject || '未分類');
            const accuracyData = statsData.map(item => {
                return item.total > 0 ? ((item.correct / item.total) * 100).toFixed(1) : 0;
            });
            const canvas = document.getElementById('subjectChart');
            if (canvas) {
                new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '正解率 (%)',
                            data: accuracyData,
                            backgroundColor: 'rgba(33, 150, 243, 0.6)',
                            borderColor: 'rgba(33, 150, 243, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { x: { beginAtZero: true, max: 100 } }
                    }
                });
            }
        }
    } catch (e) { console.error("Chart error:", e); }

    // --- B. 確認ボタン（詳細表示）の切り替え ---
    $(".show-detail").on("click", function() {
        const $btn = $(this);
        const targetId = $btn.data("target");
        const $detailRow = $("#" + targetId);
        const $content = $detailRow.find(".detail-content");

        if ($content.hasClass("not-loaded")) {
            const $row = $btn.closest("tr");
            const qid = $row.find("td:eq(1)").text().trim();
            const subject = $row.find("td:eq(6)").text().trim();
            const yourAnswer = $row.find("td:eq(3)").text().trim();

            $content.html('<div style="color:#666;">⌛ Google Sheetsから詳細を読み込み中...</div>');

            $.getJSON("get_question_detail.php", { qid: qid, subject: subject })
                .done(function(data) {
                    if (data.error) {
                        $content.html('<span style="color:red;">⚠️ ' + data.error + '</span>');
                    } else {
                        let choicesHtml = '<ul style="margin: 10px 0; list-style:none; padding-left:0;">';
                        data.choices.forEach((ch, idx) => {
                            if (!ch) return;
                            const num = idx + 1;
                            let style = (num == data.correct) ? "color:#d9534f; font-weight:bold;" : "";
                            let label = (num == yourAnswer) ? " ← あなたの回答" : "";
                            choicesHtml += `<li style="margin-bottom:5px; ${style}">${num}. ${ch}${label}</li>`;
                        });
                        choicesHtml += '</ul>';

                        const html = `
                            <div class="content-ruby">
                                <strong>問題文:</strong> ${data.text}<br>
                                ${choicesHtml}
                                <strong>💡 解説:</strong> 
                                <div style="margin-top:5px; padding:10px; background:#fff9c4; border-radius:4px; border-left:4px solid #fbc02d;">
                                    ${data.explain}
                                </div>
                            </div>`;
                        
                        $content.html(html).removeClass("not-loaded");
                        if (typeof window.applyRuby === "function") {
                            window.applyRuby($content.find('.content-ruby')[0]);
                        }
                    }
                })
                .fail(function() {
                    $content.html('<span style="color:red;">⚠️ 通信エラーが発生しました。</span>');
                });
        }
        $detailRow.toggle();
    });
});
</script>
</body>
</html>