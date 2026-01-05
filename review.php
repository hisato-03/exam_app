<?php
require "auth.php";
require_once __DIR__ . '/load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');

require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

$userId = $_SESSION["user_id"] ?? 0;

// 1. DBから間違えた問題（is_correct=0）を抽出
// DISTINCTエラー回避のため GROUP BY と MAX(created_at) を使用
try {
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT question_id, subject, exam_number 
        FROM history 
        WHERE user_id=? AND is_correct=0 
        GROUP BY question_id, subject, exam_number 
        ORDER BY MAX(created_at) DESC
    ");
    $stmt->execute([$userId]);
    $wrongRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("DBエラー: " . htmlspecialchars($e->getMessage()));
}

// 2. Google Sheets から問題データを取得
$client = new Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
$service = new Sheets($client);
$spreadsheetId = '1wBLqdju-BmXS--aPCMMC3PipvCpBFXmdVemT0X2rKew';

// 辞書取得
try {
    $dictResponse = $service->spreadsheets_values->get('1LDr4Acf_4SE-Wzp-ypPxM6COZdOt2QYumak8hIVVdxo', 'dictionary_upload!A2:B');
    $dictValues = $dictResponse->getValues() ?? [];
    $dictMap = []; foreach ($dictValues as $row) { if (!empty($row[0])) $dictMap[$row[0]] = $row[1] ?? ''; }
    $dictJson = json_encode($dictMap, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) { $dictJson = '{}'; }

// 問題データのマッピング
$questions = [];
$subjectsToFetch = array_unique(array_column($wrongRecords, 'subject'));

foreach ($subjectsToFetch as $sub) {
    if (!$sub) continue;
    try {
        $response = $service->spreadsheets_values->get($spreadsheetId, "{$sub}!A2:M");
        $values = $response->getValues() ?? [];
        foreach ($values as $row) {
            $row = array_pad($row, 13, '');
            $questions[$row[0]] = [
                'text'    => $row[1],
                'choices' => array_slice($row, 2, 5),
                'correct' => $row[7],
                'explain' => $row[8],
                'subject' => $sub,
                'exam_no' => $row[9]
            ];
        }
    } catch (Exception $e) { continue; }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>復習モード</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<div class="main-layout container">
    <div class="flex-between" style="margin-bottom:20px;">
        <h1>📝 復習モード</h1>
        <div style="display:flex; gap:10px;">
            <a href="test.php" class="btn-round" style="background:#2196F3;">◀ 試験画面へ</a>
            <a href="history.php" class="btn-round" style="background:#4CAF50;">📊 履歴を見る</a>
        </div>
    </div>

    <div class="card-style" style="margin-bottom:30px; background:#fff5f5; border-left:5px solid #d32f2f;">
        <p style="margin:0; font-weight:bold; color:#d32f2f;">過去に間違えた問題を表示しています（全 <?php echo count($wrongRecords); ?> 問）</p>
    </div>

    <?php if (empty($wrongRecords)): ?>
        <div class="card-style" style="text-align:center;">
            <p>間違えた問題はありません！素晴らしいですね。👏</p>
        </div>
    <?php else: ?>
        <?php foreach ($wrongRecords as $rec): ?>
            <?php 
                $q = $questions[$rec['question_id']] ?? null; 
                if (!$q) continue;
                
                $dispEx = "問題";
                if (!empty($q['exam_no']) && strpos($q['exam_no'], '-') !== false) {
                    $parts = explode('-', $q['exam_no']);
                    $dispEx = "第" . $parts[0] . "回 問" . $parts[1];
                }
            ?>
            <div class="question-card card-style" style="margin-bottom:25px;">
                <form class="qa-form" action="save_history.php" method="post">
                    <div class="question-text content-ruby" style="margin-bottom:20px; font-size:1.1em;">
                        <span style="background:#ffebee; color:#c62828; padding:2px 8px; border-radius:4px; font-size:0.9em; margin-right:8px; font-weight:bold;"><?php echo $dispEx; ?></span>
                        <span style="color:#666; font-size:0.85em;">[<?php echo htmlspecialchars($q['subject']); ?>]</span><br>
                        <strong></strong> <?php echo htmlspecialchars($q['text']); ?>
                    </div>

                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($rec['question_id']); ?>">
                    <input type="hidden" name="exam_number" value="<?php echo htmlspecialchars($q['exam_no']); ?>">
                    <input type="hidden" name="correct" value="<?php echo htmlspecialchars($q['correct']); ?>">
                    <input type="hidden" name="subject" value="<?php echo htmlspecialchars($q['subject']); ?>">

                    <ul class="choices content-ruby" style="list-style:none; padding:0;">
                        <?php foreach ($q['choices'] as $i => $choice): if (empty($choice)) continue; ?>
                            <li style="margin-bottom:10px; padding:10px; border:1px solid #f0f0f0; border-radius:6px;">
                                <label style="display:block; cursor:pointer;">
                                    <input type="radio" name="answer" value="<?php echo ($i+1); ?>" required> 
                                    <?php echo htmlspecialchars($choice); ?>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" class="btn-answer btn-round" style="background:#4CAF50; padding:12px 40px; border:none; cursor:pointer; font-weight:bold;">回答を送信する</button>
                    </div>

                    <div class="answer content-ruby"></div>
                    <div class="explanation content-ruby" style="display:none; margin-top:20px; padding:15px; background:#e3f2fd; border-left:5px solid #2196F3;">
                        <strong>💡 解説:</strong> <?php echo htmlspecialchars($q['explain']); ?>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>window.dictMap = <?php echo $dictJson; ?>;</script>
<script src="script.js"></script>
<script>
$(function() {
    $('.qa-form').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $resultDiv = $form.find('.answer');
        const $submitBtn = $form.find('.btn-answer');
        const $explanation = $form.find('.explanation');
        $submitBtn.prop('disabled', true).text('送信中...');
        
        $.ajax({ url: 'save_history.php', type: 'POST', data: $form.serialize(), dataType: 'json' })
        .done(function(data) {
            let html = data.is_correct ? 
                '<div style="color:#d9534f; font-weight:bold; font-size:1.3em; margin:15px 0;">⭕ 正解です！克服しました！</div>' : 
                '<div style="color:#337ab7; font-weight:bold; font-size:1.3em; margin:15px 0;">❌ 正解は [' + data.correct + '] です。</div>';
            $resultDiv.html(html);
            if (typeof window.applyRuby === "function") {
                window.applyRuby($resultDiv); window.applyRuby($explanation);
                window.applyRubyVisibility($resultDiv); window.applyRubyVisibility($explanation);
            }
            $explanation.slideDown();
            $submitBtn.text('回答済み').css({'background':'#ccc','cursor':'default'});
        });
    });
});
</script>
</body>
</html>