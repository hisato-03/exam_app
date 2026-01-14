<?php
session_start();
require "auth.php"; // ログインチェック
require_once __DIR__ . '/load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');
require __DIR__ . '/vendor/autoload.php';

$userId   = $_SESSION["user_id"] ?? 0;
$userName = $_SESSION["user"] ?? "guest";

if ($userId === 0) {
    die("ログインしてください。");
}

use Google\Client;
use Google\Service\Sheets;

function formatRelativeTime($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return "たった今";
    if ($diff < 3600) return floor($diff / 60) . "分前";
    if ($diff < 86400) return floor($diff / 3600) . "時間前";
    if ($diff < 604800) return floor($diff / 86400) . "日前";
    return date('Y/m/d', strtotime($datetime));
}

try {
    $pdo = new PDO("mysql:host=db;dbname=exam_app;charset=utf8mb4", "exam_user", "exam_pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ▼ 履歴取得（修正版：単語ごとに覚えたかどうか記録、翻訳情報）
   $stmt = $pdo->prepare("
    SELECT 
        word, 
        meaning, 
        MAX(translations) as trans_json, -- 💡 追加
        MAX(created_at) as latest_at, 
        COUNT(*) as search_count,
        MAX(is_mastered) as mastered
    FROM searched_words 
    WHERE user_id = ? 
    GROUP BY word, meaning 
    ORDER BY mastered ASC, latest_at DESC
");
$stmt->execute([$userId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
// ユーザー名はDBから取らなくてもセッションにあるものを使う
if (empty($userName)) {
    $userName = $_SESSION["user"] ?? "guest";
}
    // --- ルビ振りのための辞書データ取得 ---
    $client = new Google\Client();
    $client->setAuthConfig(__DIR__ . '/credentials.json');
    $client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
    $service = new Google\Service\Sheets($client);

    try {
        $dictResponse = $service->spreadsheets_values->get(
            '1LDr4Acf_4SE-Wzp-ypPxM6COZdOt2QYumak8hIVVdxo',
            'dictionary_upload!A2:B'
        );
        $dictValues = $dictResponse->getValues() ?? [];
        $dictMap = [];
        foreach ($dictValues as $row) {
            if (!empty($row[0]) && !empty($row[1])) $dictMap[$row[0]] = $row[1];
        }
        $dictJson = json_encode($dictMap, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        $dictJson = '{}';
    }

} catch (PDOException $e) {
    die("DBエラー: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>調べた単語履歴</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<div class="main-layout container">
<div class="flex-between" style="margin-bottom: 40px;"> <h1>🔍 単語検索履歴</h1>
    <div style="display: flex; align-items: center; gap: 15px;">
        <div style="display: flex; align-items: center; gap: 8px; background: #fff; padding: 4px 12px; border-radius: 25px; border: 2px solid #2196F3; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <span style="font-size: 1.1em;">🌎</span>
            <select id="history-lang-select" style="border: none; background: transparent; font-size: 0.9em; font-weight: bold; color: #1976d2; cursor: pointer; outline: none;">
                <option value="en">🇺🇸 English</option>
                <option value="tl">🇵🇭 Tagalog</option>
                <option value="my">🇲🇲 Myanmar</option>
                <option value="th">🇹🇭 Thai</option>
                <option value="none">❌ 非表示</option>
            </select>
        </div>

        <label class="switch-container">
            <input type="checkbox" id="hideMasteredCsv"> 覚えた単語を隠す
        </label>
        <button id="toggleRubyBtn" class="btn-round" style="background:#6c757d;">ふりがな表示切替</button>
    </div>
</div>

<div class="card-style" style="margin-bottom: 40px; border-left: 5px solid #4CAF50; padding: 20px;"> <p style="margin-bottom:10px; color:#444; font-size: 1.05em;">
        <strong>👤 <?php echo htmlspecialchars($userName); ?></strong> さんが調べた単語帳です。
    </p>
    <p style="font-size:0.85em; color:#888; line-height: 1.6;">
        ※ ✅を付けた単語は「習得済み」としてリストの下に移動し、復習の優先度が下がります。
    </p>
</div>     
        <?php if (!empty($records)): ?>
            <div style="overflow-x: auto;">
             <table class="history-table">
    <thead>
        <tr>
            <th style="width: 25%;">単語 (回数)</th>
            <th>意味</th>
            <th style="width: 20%;">最後に調べた時</th>
        </tr>
    </thead>
    <tbody>

<tbody>
  <?php foreach ($records as $row): ?>
    <?php 
        // データが空やNULLの場合に安全に処理する
        $jsonStr = $row['trans_json'] ?? ''; 
        $transArr = (!empty($jsonStr)) ? json_decode($jsonStr, true) : []; 
    ?>
    <tr class="<?php echo $row['mastered'] ? 'mastered-row' : ''; ?>" data-word="<?php echo htmlspecialchars($row['word']); ?>">
        <td class="ruby-target" style="vertical-align: top;">
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button class="btn-master" onclick="toggleMaster(this, '<?php echo addslashes($row['word']); ?>')">
                        <?php echo $row['mastered'] ? '✅' : '⬜'; ?>
                    </button>
                    
                    <span class="word-text" style="font-weight:bold; font-size:1.1em;">
                        <?php echo htmlspecialchars($row["word"]); ?>
                    </span>

                    <?php if ($row["search_count"] > 1): ?>
                        <span class="count-badge">🔥 <?php echo $row["search_count"]; ?></span>
                    <?php endif; ?>
                </div>

                <div class="trans-display" 
                     data-en="<?php echo htmlspecialchars($transArr['en'] ?? ''); ?>"
                     data-tl="<?php echo htmlspecialchars($transArr['tl'] ?? ''); ?>"
                     data-my="<?php echo htmlspecialchars($transArr['my'] ?? ''); ?>"
                     data-th="<?php echo htmlspecialchars($transArr['th'] ?? ''); ?>"
                     style="font-size: 0.85em; color: #1976d2; margin-left: 32px; min-height: 1.2em;">
                     <?php echo htmlspecialchars($transArr['en'] ?? ''); ?> 
                </div>
            </div>
        </td>
        <td class="ruby-target" style="line-height:1.6; font-size:0.95em; color:#444;">
            <?php echo htmlspecialchars($row["meaning"]); ?>
        </td>
        <td style="font-size: 0.85em; color: #888; white-space:nowrap; vertical-align: middle;">
            <?php echo formatRelativeTime($row["latest_at"]); ?>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>  
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:40px; color:#999;">
                <p>まだ単語履歴はありません。</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="flex-between" style="justify-content:center; gap:15px; margin-top:40px;">
        <a href="test.php" class="btn-round" style="background:#2196F3; padding:12px 25px;">◀ 試験画面へ戻る</a>
        <a href="review.php" class="btn-round" style="background:#d32f2f; padding:12px 25px;">📝 復習モードへ</a>
        <a href="history.php" class="btn-round" style="background:#6c757d; padding:12px 25px;">📊 学習履歴へ</a>
    </div>
</div>
<div style="overflow-x: auto; margin-bottom: 60px;"> <table class="history-table">
        ...
    </table>
</div>


<script>window.dictMap = <?php echo $dictJson; ?>;</script>
<script src="script.js"></script>

<script>
$(function() {
    // 1. ルビの適用（ページ読み込み時）
    if (typeof window.applyRuby === "function") {
        setTimeout(function() {
            window.applyRuby($('.ruby-target'));
            window.applyRubyVisibility($('.ruby-target'));
        }, 100);
    }

    // 2. 覚えた単語の表示・非表示切り替え
    $('#hideMasteredCsv').on('change', function() {
        const isHidden = $(this).is(':checked');
        if (isHidden) {
            $('.mastered-row').fadeOut(300);
        } else {
            $('.mastered-row').fadeIn(300);
        }
    });

    // 3. 翻訳言語の切り替え
    $('#history-lang-select').on('change', function() {
    const lang = $(this).val(); // 'en', 'tl', 'my', 'th', 'none'
    
    $('.trans-display').each(function() {
        if (lang === 'none') {
            $(this).text('').hide();
        } else {
            // .data(lang) より確実に属性から取得する .attr('data-' + lang) を使用
            const translation = $(this).attr('data-' + lang); 
            $(this).text(translation || '').show();
        }
    });
});

    // 4. toggleMaster関数を拡張（チェック中に✅を押したらその場で消す）
    const originalToggleMaster = window.toggleMaster;
    window.toggleMaster = function(btn, word) {
        if (typeof originalToggleMaster === "function") {
            originalToggleMaster(btn, word);
        }
        
        setTimeout(() => {
            if ($('#hideMasteredCsv').is(':checked') && $(btn).text().trim() === '✅') {
                $(btn).closest('tr').fadeOut(300);
            }
        }, 500);
    };
});
</script>
</body>
</html>