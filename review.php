<?php
/**
 * review.php
 * 間違えた問題の復習ページ（試験回・全科目対応版）
 */
require "auth.php";
require_once __DIR__ . '/load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');

$user = $_SESSION["user"] ?? "guest";
$subject = $_GET['subject'] ?? 'すべて';
$selectedYear = $_GET['year'] ?? '';

$subjects = ["すべて", "人間の尊厳と自立", "人間関係とコミュニケーション", "社会の理解", "こころとからだ", "発達と老化の理解", "認知症の理解", "障害の理解", "医療的ケア", "介護の基本", "コミュニケーション技術", "生活支援技術", "介護過程", "総合問題"];

require 'vendor/autoload.php';
use Google\Client;
use Google\Service\Sheets;

echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>復習モード</title>';
echo '<link rel="stylesheet" href="style.css">';
echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
echo '</head><body style="background:#fff5f5;">'; // 復習モードと分かるよう少し色を変える

// --- ダッシュボード ---
echo '<div class="dashboard" style="max-width:900px; margin:20px auto; padding:20px; background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,0.1); text-align:center;">';
echo "  <div style='display:flex; justify-content:space-between; align-items:center;'>";
echo "    <h2 style='margin:0; color:#d32f2f;'>📝 間違えた問題の復習</h2>";
echo '    <a href="test.php" class="btn" style="background:#9e9e9e; color:white; padding:8px 15px; border-radius:20px; text-decoration:none;">◀ テストに戻る</a>';
echo "  </div>";
echo '</div>';

// --- API・DB準備 ---
$client = new Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
$service = new Google\Service\Sheets($client);

$dsn = 'mysql:host=db;dbname=exam_app;charset=utf8mb4';
// save_history.php と同じユーザー名とパスワードに合わせる
$pdo = new PDO($dsn, "exam_user", "exam_pass");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 辞書Map取得
try {
    $dictResponse = $service->spreadsheets_values->get('1LDr4Acf_4SE-Wzp-ypPxM6COZdOt2QYumak8hIVVdxo', 'dictionary_upload!A2:B');
    $dictMap = []; foreach ($dictResponse->getValues() ?? [] as $row) { if(!empty($row[0])) $dictMap[$row[0]] = $row[1] ?? ''; }
    echo "<script>window.dictMap = " . json_encode($dictMap, JSON_UNESCAPED_UNICODE) . ";</script>";
} catch (Exception $e) {}

// --- データ取得 ---
// 1. まず全科目（または選択科目）のマスターデータを取得
$masterData = [];
$fetchSubjects = ($subject === 'すべて') ? array_slice($subjects, 1) : [$subject];
foreach ($fetchSubjects as $s) {
    try {
        $resp = $service->spreadsheets_values->get('1wBLqdju-BmXS--aPCMMC3PipvCpBFXmdVemT0X2rKew', "{$s}!A2:M");
        foreach ($resp->getValues() ?? [] as $row) {
            $row[] = $s; // 13列目に科目名を保持
            $masterData[$row[0]] = $row; // IDをキーに保存
        }
    } catch (Exception $e) {}
}

// 2. DBから「最後に間違えた」問題IDを取得
$stmt = $pdo->prepare("SELECT question_id FROM history WHERE user_id = ? AND is_correct = 0 ORDER BY created_at DESC");
$stmt->execute([$user]);
$wrongIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$wrongIds = array_unique($wrongIds);

// 3. マスターデータと照合してフィルタリング
$filteredValues = [];
$availableYears = [];
foreach ($wrongIds as $id) {
    if (isset($masterData[$id])) {
        $row = $masterData[$id];
        $rawExamNum = $row[9] ?? '';
        
        // 年度リストの作成
        if ($rawExamNum !== '') {
            $yearOnly = explode('-', $rawExamNum)[0];
            if (!in_array($yearOnly, $availableYears)) $availableYears[] = $yearOnly;
        }

        // 年度フィルタ
        if ($selectedYear === '' || (explode('-', $rawExamNum)[0] === $selectedYear)) {
            $filteredValues[] = $row;
        }
    }
}
sort($availableYears);

// --- ツールバー ---
echo '<div class="toolbar" style="max-width:900px; margin:0 auto 30px; background:#fff0f0; padding:15px; border-radius:10px; border:1px solid #ffcdd2;">';
echo '  <form method="GET" class="no-ruby" style="display:flex; flex-wrap:wrap; gap:12px; justify-content:center; align-items:center;">';
echo '    <label>📚 科目: <select name="subject" style="padding:8px; border-radius:5px; border:2px solid #d32f2f;">';
foreach ($subjects as $s) { $sel = ($subject === $s) ? "selected" : ""; echo "<option value='$s' $sel>$s</option>"; }
echo '    </select></label>';

echo '    <label>📅 試験回: <select name="year" style="padding:8px; border-radius:5px; border:1px solid #ccc;">';
echo '      <option value="">すべて</option>';
foreach ($availableYears as $y) { $sel = ($selectedYear == $y) ? "selected" : ""; echo "<option value='$y' $sel>第{$y}回</option>"; }
echo '    </select></label>';

echo '    <button type="submit" style="padding:8px 15px; background:#d32f2f; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold;">復習問題を読み込む</button>';
echo '    <button type="button" id="toggleRubyBtn" style="padding:8px 15px; background:#6c757d; color:white; border:none; border-radius:5px; cursor:pointer;">ふりがな表示</button>';
echo '  </form>';
echo '</div>';

// --- 問題表示 ---
if (empty($filteredValues)) {
    echo "<p style='text-align:center; font-weight:bold;'>現在、復習が必要な問題はありません。素晴らしいです！</p>";
} else {
    foreach ($filteredValues as $row) {
        $rawExamNum = $row[9] ?? '';
        $displayExamNum = "問題";
        if (!empty($rawExamNum) && strpos($rawExamNum, '-') !== false) {
            $parts = explode('-', $rawExamNum);
            $displayExamNum = "第" . $parts[0] . "回 問" . $parts[1];
        }

        echo "<div class='question-card' style='max-width:800px; margin:20px auto; padding:25px; background:#fff; border-radius:12px; border-left:8px solid #d32f2f; box-shadow:0 2px 8px rgba(0,0,0,0.05);'>";
        echo "  <form class='qa-form' action='save_history.php' method='post'>";
        echo "    <div class='question-text content-ruby' style='margin-bottom:20px; font-size:1.1em;'>";
        echo "      <span style='background:#ffebee; color:#d32f2f; padding:2px 8px; border-radius:4px; font-size:0.9em; margin-right:8px;'>{$displayExamNum}</span>";
        echo "      " . htmlspecialchars($row[1]);
        echo "    </div>";

        if (!empty(trim($row[12]))) {
            echo "<div style='text-align:center; margin-bottom:20px;'><img src='images/".htmlspecialchars(trim($row[12]), ENT_QUOTES)."' style='max-width:100%; max-height:300px; border-radius:8px;'></div>";
        }

        echo "    <input type='hidden' name='question_id' value='".htmlspecialchars($row[0])."'>";
        echo "    <input type='hidden' name='correct' value='".htmlspecialchars($row[7])."'>";
        echo "    <input type='hidden' name='subject' value='".htmlspecialchars($row[13])."'>"; // 保持していた科目名

        echo "    <ul class='choices content-ruby' style='list-style:none; padding:0;'>";
        for ($i = 1; $i <= 5; $i++) {
            $choiceText = $row[$i+1] ?? '';
            if ($choiceText) {
                echo "<li style='margin-bottom:10px; padding:10px; border:1px solid #f0f0f0; border-radius:6px;'><label style='display:block; cursor:pointer;'><input type='radio' name='answer' value='{$i}' required> ".htmlspecialchars($choiceText)."</label></li>";
            }
        }
        echo "    </ul>";
        echo "    <button type='submit' class='btn-answer no-ruby' style='padding:12px 30px; background:#d32f2f; color:white; border:none; border-radius:25px; cursor:pointer; font-weight:bold;'>もう一度回答する</button>";
        echo "    <div class='answer content-ruby'></div>";
        echo "    <div class='explanation content-ruby' style='display:none; margin-top:20px; padding:15px; background:#fff9c4; border-left:5px solid #fbc02d;'><strong>💡 解説:</strong> ".htmlspecialchars($row[8])."</div>";
        echo "  </form>";
        echo "</div>";
    }
}
?>

<script src="script.js"></script>
<script>
$(function() {
    $(window).on('load', function() {
        if (typeof window.applyRuby === "function") {
            setTimeout(function() { window.applyRuby($('.content-ruby')); window.applyRubyVisibility($('.content-ruby')); }, 100);
        }
    });

    $('.qa-form').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $resultDiv = $form.find('.answer');
        const $submitBtn = $form.find('.btn-answer');
        const $explanation = $form.find('.explanation');
        
        $.ajax({ url: 'save_history.php', type: 'POST', data: $form.serialize(), dataType: 'json' })
        .done(function(data) {
            let html = data.is_correct ? '<div style="color:#d9534f; font-weight:bold; font-size:1.3em; margin:15px 0;">⭕ 正解です！克服しましたね！</div>' : '<div style="color:#337ab7; font-weight:bold; font-size:1.3em; margin:15px 0;">❌ 残念、正解は [' + data.correct + '] です。</div>';
            $resultDiv.html(html);
            if (typeof window.applyRuby === "function") { window.applyRuby($resultDiv); window.applyRuby($explanation); }
            $explanation.slideDown();
            $submitBtn.text('回答完了').css({'background':'#ccc','cursor':'default'}).prop('disabled', true);
        });
    });
});
</script>
</body></html>