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

// CSSを直接埋め込み（style.cssを意識したデザイン）
echo "
<style>
    body { font-family: sans-serif; background: #f5f5f5; padding: 20px; color: #333; }
    .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h2 { color: #2196F3; border-bottom: 2px solid #2196F3; padding-bottom: 10px; }
    .status { background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-size: 0.9em; }
    textarea { width: 100%; height: 200px; padding: 15px; border: 1px solid #ddd; border-radius: 8px; font-family: monospace; background: #fafafa; }
    .copy-btn { background: #2196F3; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.3s; margin: 10px 0 30px; }
    .copy-btn:hover { background: #1976D2; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th { background: #2196F3; color: white; padding: 12px; text-align: left; }
    td { padding: 10px; border-bottom: 1px solid #eee; }
    tr:hover { background: #f9f9f9; }
    .count { font-weight: bold; color: #d32f2f; }
</style>
<div class='container'>
<h2>🔍 全データ一括チェック（ルビ未登録抽出）</h2>";

try {
    // 1. 辞書読み込み
    $dictResponse = $service->spreadsheets_values->get($dictionarySheetId, 'dictionary_upload!A2:B');
    $dictValues = $dictResponse->getValues() ?? [];
    $registeredWords = [];
    foreach ($dictValues as $row) {
        if (!empty($row[0])) $registeredWords[trim($row[0])] = true;
    }

    // 2. 全問題テキスト取得
    echo "<div class='status'>";
    $allText = "";
    foreach ($subjects as $s) {
        try {
            $response = $service->spreadsheets_values->get($problemSheetId, "{$s}!A2:M");
            $rows = $response->getValues() ?? [];
            foreach ($rows as $row) {
                // 問題文＋選択肢を結合
                for($i=1; $i<=8; $i++) { $allText .= ($row[$i] ?? '') . " "; }
            }
            echo "✅ {$s} 読み込み完了<br>";
        } catch (Exception $e) {
            echo "❌ {$s} 読み込み失敗<br>";
        }
    }
    echo "</div>";
    flush();

    // --- 3. 単語の抽出とフィルタリング ---
    // 本文中から「漢字＋送り仮名」の塊をすべて抜き出す
    preg_match_all('/[一-龠]+[ぁ-ん]*/u', $allText, $matches);
    $rawWords = $matches[0];
    
    // 辞書単語を「文字が長い順」にソート（script.jsの挙動をシミュレート）
    $dictKeys = array_keys($registeredWords);
    usort($dictKeys, function($a, $b) {
        return mb_strlen($b) - mb_strlen($a);
    });

    $missingCount = [];
    $counts = array_count_values($rawWords);

    foreach ($counts as $word => $num) {
        if (mb_strlen($word) < 2) continue; // 1文字は除外
        if (preg_match('/[0-9A-Za-z]/', $word)) continue; // 英数字混じりは除外

        // すでに辞書のいずれかの単語でルビがカバーされているかチェック
        $isCovered = false;
        foreach ($dictKeys as $regWord) {
            if (mb_strpos($word, $regWord) !== false) {
                $isCovered = true;
                break; // 辞書のどれかに含まれていれば、その単語にはルビがつくのでOK
            }
        }

        // 辞書のどの単語にも引っかからなかった場合のみ、リストに追加
        if (!$isCovered) {
            $missingCount[$word] = $num;
        }
    }

    arsort($missingCount);

    arsort($missingCount);

    // --- 4. 統計情報の計算 ---
    $totalUniqueMissing = count($missingCount); // 未登録の単語の種類数
    $totalTotalMissing = array_sum($missingCount); // 未登録単語の総出現回数

    // --- 5. 表示：統計・サマリー ---
    echo "<style>
        .summary-box { 
            background: #fff3e0; 
            border-left: 5px solid #ff9800; 
            padding: 15px; 
            margin: 20px 0; 
            border-radius: 4px;
        }
        .summary-box h3 { margin-top: 0; color: #e65100; font-size: 1.2em; }
        .summary-box ul { list-style: none; padding: 0; margin: 10px 0; }
        .summary-box li { margin: 5px 0; font-size: 1.1em; }
        .summary-box strong { font-size: 1.3em; color: #d84315; }
    </style>";

    echo "<div class='summary-box'>";
    echo "<h3>📊 未登録単語の集計結果</h3>";
    echo "<ul>";
    echo "<li>未登録の単語（種類）: <strong>" . number_format($totalUniqueMissing) . "</strong> 件</li>";
    echo "<li>本文中の総出現数（合計）: <strong>" . number_format($totalTotalMissing) . "</strong> 回</li>";
    echo "</ul>";
    echo "<p style='margin:0; font-size:0.85em; color:#666;'>※上位200件を優先的にリストアップしています。</p>";
    echo "</div>";

    // --- 6. 表示：Python貼り付け用エリア ---
    echo "<h3>📋 Python貼り付け用（未登録リスト 200件）</h3>";
    echo "<p>AIで読み仮名を一括生成するためのリストです。</p>";
    echo "<textarea id='copyArea' readonly>";
    $i = 0;
    foreach ($missingCount as $word => $count) {
        if ($i++ >= 200) break;
        echo htmlspecialchars($word) . "\n";
    }
    echo "</textarea>";
    echo "<button class='copy-btn' onclick='copyToClipboard()'>リストを全選択してコピー</button>";

    // --- 7. 表示：詳細テーブル ---
    echo "<h3>📊 出現頻度の高い単語（詳細）</h3>";
    echo "<table>";
    echo "<tr><th>未登録単語</th><th>出現数</th></tr>";
    $i = 0;
    foreach ($missingCount as $word => $count) {
        if ($i++ >= 200) break;
        echo "<tr><td>" . htmlspecialchars($word) . "</td><td><span class='count'>" . number_format($count) . "</span> 回</td></tr>";
    }
    echo "</table>";
    echo "</div>"; // container end（もし冒頭でdivを開いていれば）

    echo "
    <script>
    function copyToClipboard() {
        var copyArea = document.getElementById('copyArea');
        copyArea.select();
        document.execCommand('copy');
        alert('リストをコピーしました！');
    }
    </script>";

} catch (Exception $e) {
    echo "<div style='color:red; padding:20px;'>エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>