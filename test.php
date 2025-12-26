<?php
/**
 * test.php（最新・全科目対応版）
 */
require "auth.php";
require_once __DIR__ . '/load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');

// 1. 基本設定と科目リストの定義（ここを一番上に持ってくる）
$subject = $_GET['subject'] ?? '人間の尊厳と自立';
$mode = $_GET['mode'] ?? 'sequential';
$selectedYear = $_GET['year'] ?? '';

// ★ここで全科目を定義
$subjects = ["すべて", "人間の尊厳と自立", "人間関係とコミュニケーション", "社会の理解", "こころとからだ", "発達と老化の理解", "認知症の理解", "障害の理解", "医療的ケア", "介護の基本", "コミュニケーション技術", "生活支援技術", "介護過程", "総合問題"];

require 'vendor/autoload.php';
use Google\Client;
use Google\Service\Sheets;

echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>試験ページ</title>';
echo '<link rel="stylesheet" href="style.css">';
$metaPath = __DIR__ . "/ruby_meta_tags.html";
echo file_exists($metaPath) ? file_get_contents($metaPath) : "";
echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
echo '</head><body>';

$user = $_SESSION["user"] ?? "guest";

// --- ダッシュボード ---
echo '<div class="dashboard" style="max-width:900px; margin:20px auto; padding:20px; background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,0.1); text-align:center;">';
if ($user === "guest") {
    echo "<h2>👋 ようこそ、ゲストさん！</h2><a href='login.php' class='btn btn-primary'>ログイン画面へ</a>";
} else {
    echo "<div style='display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;'>";
    echo "  <h2 style='margin:0; font-size:1.4em;'>👤 " . htmlspecialchars($user) . " さん、こんにちは！</h2>";
    echo "  <div style='display:flex; gap:8px;'>";
    echo '    <a href="history.php" class="btn" style="background:#4CAF50; color:white; padding:6px 15px; border-radius:20px; text-decoration:none;">📊 学習履歴</a>';
    echo '    <a href="logout.php" class="btn" style="background:#f44336; color:white; padding:6px 15px; border-radius:20px; text-decoration:none;">🚪 ログアウト</a>';
    echo '    <a href="/exam_app/index.php" class="btn" style="background:#9e9e9e; color:white; padding:6px 15px; border-radius:20px; text-decoration:none;">🏠 閉じる</a>';
    echo "  </div>";
    echo "</div>";
}
echo '</div>';

// --- API接続 ---
$client = new Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
$service = new Google\Service\Sheets($client);

// 辞書取得
try {
    $dictResponse = $service->spreadsheets_values->get('1LDr4Acf_4SE-Wzp-ypPxM6COZdOt2QYumak8hIVVdxo', 'dictionary_upload!A2:B');
    $dictValues = $dictResponse->getValues() ?? [];
    $dictMap = []; foreach ($dictValues as $row) { if (!empty($row[0])) $dictMap[$row[0]] = $row[1] ?? ''; }
    echo "<script>window.dictMap = " . json_encode($dictMap, JSON_UNESCAPED_UNICODE) . ";</script>";
} catch (Exception $e) {}

// --- データ取得ロジック（「すべて」対応） ---
$allValues = [];
if ($subject === "すべて") {
    foreach ($subjects as $s) {
        if ($s === "すべて") continue;
        try {
            $response = $service->spreadsheets_values->get('1wBLqdju-BmXS--aPCMMC3PipvCpBFXmdVemT0X2rKew', "{$s}!A2:M");
            $sheetValues = $response->getValues() ?? [];
            $allValues = array_merge($allValues, $sheetValues);
        } catch (Exception $e) { continue; }
    }
} else {
    $cacheKey = "subject_" . $subject;
    if (isset($_SESSION[$cacheKey]) && $_SESSION[$cacheKey]['expires'] > time()) {
        $allValues = $_SESSION[$cacheKey]['data'];
    } else {
        try {
            $response = $service->spreadsheets_values->get('1wBLqdju-BmXS--aPCMMC3PipvCpBFXmdVemT0X2rKew', "{$subject}!A2:M");
            $allValues = $response->getValues() ?? [];
            $_SESSION[$cacheKey] = ['data' => $allValues, 'expires' => time() + 600];
        } catch (Exception $e) { $allValues = []; }
    }
}

// 試験回リスト抽出（33-77形式）
$years = [];
foreach ($allValues as $row) {
    $rawExamNum = $row[9] ?? '';
    if ($rawExamNum !== '') {
        $parts = explode('-', $rawExamNum);
        $yearOnly = $parts[0];
        if (!in_array($yearOnly, $years)) $years[] = $yearOnly;
    }
}
sort($years);

// フィルタリング
$filteredValues = [];
foreach ($allValues as $row) {
    $rawExamNum = $row[9] ?? '';
    if ($selectedYear === '') {
        $filteredValues[] = $row;
    } else {
        $parts = explode('-', $rawExamNum);
        if ($parts[0] === $selectedYear) $filteredValues[] = $row;
    }
}
if ($mode === 'random') shuffle($filteredValues);

// --- ツールバー（ここで $subjects を使ってドロップダウン作成） ---
echo '<div class="toolbar" style="max-width:900px; margin:0 auto 30px; background:#f8f9fa; padding:15px; border-radius:10px; border:1px solid #eee;">';
echo '  <form method="GET" id="filterForm" class="no-ruby" style="display:flex; flex-wrap:wrap; gap:12px; justify-content:center; align-items:center;">';
echo '    <input type="hidden" name="page" value="1">';
echo '    <label>📚 科目: <select name="subject" class="no-ruby" style="padding:8px; border-radius:5px; border:2px solid #2196F3;">';
foreach ($subjects as $s) {
    $sel = ($subject === $s) ? "selected" : "";
    echo "<option value='".htmlspecialchars($s)."' $sel>".htmlspecialchars($s)."</option>";
}
echo '    </select></label>';

echo '    <label>📅 試験回: <select name="year" class="no-ruby" style="padding:8px; border-radius:5px; border:1px solid #ccc;">';
echo '      <option value="">すべて</option>';
foreach ($years as $y) { $sel = ($selectedYear == $y) ? "selected" : ""; echo "<option value='$y' $sel>第{$y}回</option>"; }
echo '    </select></label>';

echo '    <label>⚙️ 形式: <select name="mode" class="no-ruby" style="padding:8px; border-radius:5px; border:1px solid #ccc;">';
echo '      <option value="sequential" '.($mode==='sequential'?'selected':'').'>📋 順番に</option>';
echo '      <option value="random" '.($mode==='random'?'selected':'').'>🎲 ランダム</option>';
echo '    </select></label>';

echo '    <button type="submit" class="no-ruby" style="padding:8px 15px; background:#2196F3; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold;">問題を読み込む</button>';
echo '    <button type="button" id="toggleRubyBtn" class="no-ruby" style="padding:8px 15px; background:#6c757d; color:white; border:none; border-radius:5px; cursor:pointer;">ふりがな表示</button>';
echo '  </form>';
echo '</div>';

// --- 問題表示エリア（以下、前回のコードと同じ） ---
$perPage = 5;
$page = max(1, intval($_GET['page'] ?? 1));
$total = count($filteredValues);
$start = ($page - 1) * $perPage;
$end = min($start + $perPage, $total);

if ($total === 0) {
    echo "<p style='text-align:center;'>指定された条件の問題は見つかりませんでした。</p>";
} else {
    echo "<div style='text-align:center; margin-bottom:10px;'>{$subject} " . ($selectedYear ? "第{$selectedYear}回 " : "") . "（全 {$total} 問）</div>";
    for ($index = $start; $index < $end; $index++) {
        $row = array_pad($filteredValues[$index], 13, '');
        echo "<div class='question-card' style='max-width:800px; margin:20px auto; padding:25px; background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05);'>";
        echo "<form class='qa-form' action='save_history.php' method='post'>";
        // J列の「36-77」を「第36回 問77」に変換
        $rawExamNum = $row[9] ?? '';
        $displayExamNum = "問題"; // デフォルト
        if (!empty($rawExamNum) && strpos($rawExamNum, '-') !== false) {
        $parts = explode('-', $rawExamNum);
        $displayExamNum = "第" . $parts[0] . "回 問" . $parts[1];
}

        echo "<div class='question-text content-ruby' style='margin-bottom:20px; font-size:1.1em;'>";
        echo "<span style='background:#e3f2fd; color:#1976d2; padding:2px 8px; border-radius:4px; font-size:0.9em; margin-right:8px;'>{$displayExamNum}</span>";
        echo "<strong></strong> " . htmlspecialchars($row[1]);
        echo "</div>";
        if (!empty(trim($row[12]))) {
            echo "<div style='text-align:center; margin-bottom:20px;'><img src='images/".htmlspecialchars(trim($row[12]), ENT_QUOTES)."' style='max-width:100%; max-height:300px; border-radius:8px;'></div>";
        }
        echo "<input type='hidden' name='question_id' value='".htmlspecialchars($row[0])."'>";
        echo "<input type='hidden' name='exam_number' value='".htmlspecialchars($row[9])."'>";
        echo "<input type='hidden' name='correct' value='".htmlspecialchars($row[7])."'>";
        echo "<input type='hidden' name='subject' value='".htmlspecialchars($subject)."'>";
        echo "<ul class='choices content-ruby' style='list-style:none; padding:0;'>";
        for ($i = 1; $i <= 5; $i++) {
            $choiceText = $row[$i+1] ?? '';
            if ($choiceText) {
                echo "<li style='margin-bottom:10px; padding:10px; border:1px solid #f0f0f0; border-radius:6px;'><label style='display:block; cursor:pointer;'><input type='radio' name='answer' value='{$i}' required> ".htmlspecialchars($choiceText)."</label></li>";
            }
        }
        echo "</ul>";
        echo "<button type='submit' class='btn-answer no-ruby' style='padding:12px 30px; background:#4CAF50; color:white; border:none; border-radius:25px; cursor:pointer; font-weight:bold;'>回答を送信する</button>";
        echo "<div class='answer content-ruby'></div>";
        echo "<div class='explanation content-ruby' style='display:none; margin-top:20px; padding:15px; background:#e3f2fd; border-left:5px solid #2196F3;'><strong>💡 解説:</strong> ".htmlspecialchars($row[8])."</div>";
        echo "</form></div>";
    }

    // ページナビ
    echo "<div style='text-align:center; margin:40px 0;'>";
    $baseUrl = "test.php?subject=".urlencode($subject)."&mode=".urlencode($mode)."&year=".urlencode($selectedYear);
    if ($page > 1) echo "<a href='{$baseUrl}&page=".($page-1)."' style='margin-right:15px; text-decoration:none; padding:12px 25px; background:#ffffff; border:2px solid #2196F3; border-radius:30px; color:#2196F3 !important; font-weight:bold; display:inline-block;'>◀ 前の5問</a>";
    if ($end < $total) echo "<a href='{$baseUrl}&page=".($page+1)."' style='text-decoration:none; padding:12px 25px; background:#2196F3; border:2px solid #2196F3; border-radius:30px; color:#ffffff !important; font-weight:bold; display:inline-block;'>次の5問 ▶</a>";
    echo "</div>";
}
?>

<script src="script.js"></script>
<script>
$(function() {
    $(window).on('load', function() {
        if (typeof window.applyRuby === "function") {
            setTimeout(function() {
                window.applyRuby($('.content-ruby'));
                window.applyRubyVisibility($('.content-ruby'));
            }, 100);
        }
    });

    $('.qa-form').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $resultDiv = $form.find('.answer');
        const $submitBtn = $form.find('.btn-answer');
        const $explanation = $form.find('.explanation');
        $submitBtn.prop('disabled', true).text('送信中...');
        $.ajax({ url: 'save_history.php', type: 'POST', data: $form.serialize(), dataType: 'json' })
        .done(function(data) {
            let html = data.is_correct ? '<div style="color:#d9534f; font-weight:bold; font-size:1.3em; margin:15px 0;">⭕ 正解です！</div>' : '<div style="color:#337ab7; font-weight:bold; font-size:1.3em; margin:15px 0;">❌ 正解は [' + data.correct + '] です。</div>';
            $resultDiv.html(html);
            if (typeof window.applyRuby === "function") {
                window.applyRuby($resultDiv); window.applyRuby($explanation);
                window.applyRubyVisibility($resultDiv); window.applyRubyVisibility($explanation);
            }
            $explanation.slideDown();
            $submitBtn.text('回答済み').css({'background':'#ccc','cursor':'default'});
        });
    });

    $(document).on("mouseup", function(e) {
        const sel = window.getSelection().toString().trim();
        if (sel.length > 0) {
            $("#dictPopup").remove();
            $('<div id="dictPopup">辞書で調べる</div>').css({position:"absolute", left:e.pageX+10, top:e.pageY+10, padding:"10px 20px", background:"#2196F3", color:"#fff", borderRadius:"6px", cursor:"pointer", zIndex:9999})
            .appendTo("body").on("click", function() { location.href = "dictionary.php?word="+encodeURIComponent(sel)+"&subject="+encodeURIComponent("<?php echo $subject; ?>"); });
        } else { if (!$(e.target).closest("#dictPopup").length) $("#dictPopup").remove(); }
    });
});
</script>
</body></html>