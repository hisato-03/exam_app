<?php
// --- 1. サーバー制限の解除 ---
set_time_limit(0);
ini_set('memory_limit', '1024M');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require "auth.php"; 
require 'vendor/autoload.php';
require_once __DIR__ . '/load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');

use Google\Client;
use Google\Service\Sheets;

// --- 設定 ---
$dictionarySheetId = '1LDr4Acf_4SE-Wzp-ypPxM6COZdOt2QYumak8hIVVdxo';
$problemSheetId    = '1wBLqdju-BmXS--aPCMMC3PipvCpBFXmdVemT0X2rKew';
$subjects = ["人間の尊厳と自立", "人間関係とコミュニケーション", "社会の理解", "こころとからだ", "発達と老化の理解", "認知症の理解", "障害の理解", "医療的ケア", "介護の基本", "コミュニケーション技術", "生活支援技術", "介護過程", "総合問題"];

$client = new Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->setScopes([Sheets::SPREADSHEETS_READONLY]);
$service = new Sheets($client);

// 抽出用データの準備
$allText = "";
$missingCount = [];
$totalTotalMissing = 0; // のべ出現回数のカウンタ

try {
    // 1. 辞書読み込み
    $dictResponse = $service->spreadsheets_values->get($dictionarySheetId, 'dictionary_upload!A2:C');
    $dictValues = $dictResponse->getValues() ?? [];
    $registeredWords = [];

    foreach ($dictValues as $row) {
        if (!empty($row[0])) {
            $registeredWords[trim($row[0])] = true;
        }
        if (!empty($row[2])) {
            $allText .= " " . $row[2]; // 意味カラムをスキャン対象に追加
        }
    }

    // 2. 全問題テキスト取得
    $loadStatus = "";
    foreach ($subjects as $s) {
        try {
            $response = $service->spreadsheets_values->get($problemSheetId, "{$s}!A2:M");
            $rows = $response->getValues() ?? [];
            foreach ($rows as $row) {
                for($i=1; $i<=8; $i++) {
                    $allText .= " " . ($row[$i] ?? '');
                }
            }
            $loadStatus .= "✅ {$s} ";
        } catch (Exception $e) {
            $loadStatus .= "❌ {$s} ";
        }
    }

    // 3. 単語の抽出とフィルタリング
    preg_match_all('/[一-龠]+[ぁ-ん]*/u', $allText, $matches);
    $counts = array_count_values($matches[0]);

    $dictKeys = array_keys($registeredWords);
    usort($dictKeys, function($a, $b) {
        return mb_strlen($b) - mb_strlen($a);
    });

    foreach ($counts as $word => $num) {
        if (mb_strlen($word) < 2 || preg_match('/[0-9A-Za-z]/', $word)) continue;

        $isCovered = false;
        foreach ($dictKeys as $regWord) {
            if (mb_strpos($word, $regWord) !== false) {
                $isCovered = true;
                break;
            }
        }
        if (!$isCovered) {
            $missingCount[$word] = $num;
            $totalTotalMissing += $num; // ここで合計出現数を加算
        }
    }
    arsort($missingCount);

} catch (Exception $e) {
    die("エラーが発生しました: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>単語選別システム</title>
    <style>
        body { font-family: sans-serif; background: #f5f5f5; padding: 20px; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #2196F3; border-bottom: 2px solid #2196F3; padding-bottom: 10px; }
        .status { background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-size: 0.85em; color: #444; max-height: 80px; overflow-y: auto; }
        textarea { width: 100%; height: 120px; padding: 15px; border: 2px solid #2196F3; border-radius: 8px; font-family: monospace; background: #fafafa; margin-bottom: 10px; box-sizing: border-box; }
        .copy-btn { background: #4CAF50; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; font-size: 1.1em; transition: 0.2s; }
        .copy-btn:hover { background: #45a049; }
        .sticky-top { position: sticky; top: 0; background: white; z-index: 100; padding: 10px 0; border-bottom: 2px solid #eee; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        th { background: #2196F3; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f1f7ff; }
        .tag { font-size: 0.75em; padding: 2px 6px; border-radius: 4px; margin-left: 5px; font-weight: bold; }
        .tag-expert { background: #ffeb3b; color: #827717; }
        .tag-kanji { background: #e1f5fe; color: #0288d1; }
        .count-badge { color: #d32f2f; font-weight: bold; }
        .summary-box { 
            background: #fff3e0; 
            border-left: 5px solid #ff9800; 
            padding: 15px; 
            margin-bottom: 25px; 
            border-radius: 4px;
        }
        .summary-box strong { font-size: 1.2em; color: #d84315; }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<div class="container">
    <h2>🔍 精鋭単語選別システム (辞書強化)</h2>
    
    <div class="status"><?php echo $loadStatus; ?></div>

    <div class="summary-box">
        <h3>📊 未登録単語の集計結果</h3>
        <ul>
            <li>未登録の単語（種類）: <strong><?php echo number_format(count($missingCount)); ?></strong> 件</li>
            <li>本文中の総出現数（合計）: <strong><?php echo number_format($totalTotalMissing); ?></strong> 回</li>
        </ul>
        <p style="margin:0; font-size:0.85em; color:#666;">※専門用語や頻出熟語を優先的に自動チェックしています。</p>
    </div>

    <div class="sticky-top">
        <h3>📋 Python貼り付け用リスト</h3>
        <textarea id="copyArea" readonly placeholder="下の表でチェックを入れるとここに表示されます"></textarea>
        <button class="copy-btn" onclick="copyToClipboard()">選択したリストをコピー</button>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:40px;"><input type="checkbox" id="selectAll"></th>
                <th>未登録単語</th>
                <th>出現頻度</th>
                <th>判定理由</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $limit = 0;
            foreach ($missingCount as $word => $count):
                if ($limit++ >= 300) break;

                // 重要度判定
                $isExpert = preg_match('/[脊椎躁鬱妄認障介護]/u', $word);
                $isKanjiOnly = preg_match('/^[一-龠]{2,}$/u', $word);
                $autoCheck = ($isExpert || ($isKanjiOnly && $count >= 2)) ? "checked" : "";
                
                $tags = "";
                if ($isExpert) $tags .= "<span class='tag tag-expert'>💡専門</span>";
                if ($isKanjiOnly) $tags .= "<span class='tag tag-kanji'>⭐熟語</span>";
            ?>
            <tr>
                <td><input type="checkbox" class="word-check" value="<?php echo htmlspecialchars($word); ?>" <?php echo $autoCheck; ?>></td>
                <td><strong><?php echo htmlspecialchars($word); ?></strong></td>
                <td><span class="count-badge"><?php echo $count; ?></span> 回</td>
                <td><?php echo $tags; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function copyToClipboard() {
    const copyArea = document.getElementById('copyArea');
    if (!copyArea.value.trim()) {
        alert('単語が選択されていません。');
        return;
    }
    copyArea.select();
    document.execCommand('copy');
    alert('コピーしました！Pythonスクリプトに貼り付けてください。');
}

$(function() {
    function updateTextarea() {
        let list = [];
        $('.word-check:checked').each(function() {
            list.push($(this).val());
        });
        $('#copyArea').val(list.join('\n'));
    }

    $(document).on('change', '.word-check', updateTextarea);

    $('#selectAll').on('change', function() {
        $('.word-check').prop('checked', this.checked);
        updateTextarea();
    });

    updateTextarea();
});
</script>
</body>
</html>