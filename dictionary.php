<?php
session_start();
require "auth.php";
require_once __DIR__ . '/load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');

require __DIR__ . '/vendor/autoload.php';

// ▼ .env 読み込み
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ▼ ユーザー情報・パラメータ取得
$userId = $_SESSION['user_id'] ?? 0;
$word = $_GET['word'] ?? '';
$subject = $_GET['subject'] ?? '';

use Google\Client;
use Google\Service\Sheets;

// ▼ 翻訳関数
function translateText($text, $targetLang = 'en') {
    $apiKey = $_ENV['GOOGLE_TRANSLATE_API_KEY'] ?? null;
    if (!$apiKey || empty($text)) return "";
    $url = "https://translation.googleapis.com/language/translate/v2?key={$apiKey}";
    $data = ['q' => $text, 'target' => $targetLang, 'format' => 'text'];
    $options = ['http' => [
        'header'  => "Content-type: application/json; charset=UTF-8\r\n",
        'method'  => 'POST',
        'content' => json_encode($data)
    ]];
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) return "Translation error.";
    $json = json_decode($result, true);
    return $json['data']['translations'][0]['translatedText'] ?? '';
}

// ▼ 変数初期化
$meaning = '';
$ruby = '';
$imageUrl = '';
$isFromCache = false;
$allDictData = []; // 全辞書データ格納用

// --- STEP 1: MySQLキャッシュを確認 ---
try {
    $pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4", $_ENV['DB_USER'], $_ENV['DB_PASS'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    $stmt = $pdo->prepare("SELECT * FROM dictionary_cache WHERE word = ? LIMIT 1");
    $stmt->execute([$word]);
    $cached = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cached) {
        $meaning = $cached['meaning'];
        $ruby = $cached['ruby'];
        $imageUrl = $cached['image_url'];
        $isFromCache = true;
    }
} catch (PDOException $e) { /* エラー時はスキップ */ }

// --- STEP 2: Google Sheets から全辞書データを取得（ルビ振り用） ---
$client = new Google\Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
$service = new Google\Service\Sheets($client);

try {
    $dictResponse = $service->spreadsheets_values->get('1LDr4Acf_4SE-Wzp-ypPxM6COZdOt2QYumak8hIVVdxo', 'dictionary_upload!A2:E');
    $dictValues = $dictResponse->getValues() ?? [];
    
    foreach ($dictValues as $row) {
        $w = $row[0] ?? '';
        $r = $row[1] ?? '';
        if ($w !== '') {
            $allDictData[$w] = $r; // JavaScriptのルビ振り用マップに追加
        }

        // キャッシュにない場合、現在の検索ワードの情報を抽出して保存
        if (!$isFromCache && $w === $word) {
            $ruby = $r;
            $meaning = $row[2] ?? '';
            $imageUrl = $row[4] ?? '';
            
            try {
                $ins = $pdo->prepare("INSERT IGNORE INTO dictionary_cache (word, ruby, meaning, image_url) VALUES (?, ?, ?, ?)");
                $ins->execute([$word, $ruby, $meaning, $imageUrl]);
            } catch (Exception $dbE) {}
        }
    }
} catch (Exception $e) { /* APIエラー時 */ }

// --- 追加：PHP側でスマート・ルビを生成する関数 ---
function formatSmartRuby($word, $reading) {
    if (empty($reading) || $word === $reading) {
        return htmlspecialchars($word);
    }

    // 文字数を取得
    $wordLen = mb_strlen($word);
    $readingLen = mb_strlen($reading);
    $okuriganaLen = 0;

    // 後ろから1文字ずつ比較して、一致する送り仮名の長さを調べる
    while ($okuriganaLen < $wordLen && $okuriganaLen < $readingLen) {
        $wChar = mb_substr($word, $wordLen - 1 - $okuriganaLen, 1);
        $rChar = mb_substr($reading, $readingLen - 1 - $okuriganaLen, 1);

        // ひらがなが一致する場合、送り仮名とみなす
        if ($wChar === $rChar) {
            $okuriganaLen++;
        } else {
            break;
        }
    }

    if ($okuriganaLen > 0 && $okuriganaLen < $wordLen) {
        // 送り仮名を分離してルビを振る
        // 例：「行い」「おこない」 → <ruby>行<rt>おこな</rt></ruby>い
        $baseKanji = mb_substr($word, 0, $wordLen - $okuriganaLen);
        $rubyPart  = mb_substr($reading, 0, $readingLen - $okuriganaLen);
        $okurigana = mb_substr($word, $wordLen - $okuriganaLen);

        return "<ruby>" . htmlspecialchars($baseKanji) . "<rt>" . htmlspecialchars($rubyPart) . "</rt></ruby>" . htmlspecialchars($okurigana);
    } else {
        // 送り仮名がない、または全て一致（ひらがなのみ等）の場合
        return "<ruby>" . htmlspecialchars($word) . "<rt>" . htmlspecialchars($reading) . "</rt></ruby>";
    }
}

// ▼ 翻訳実行
$translations = [
    'en' => translateText($word, 'en'),
    'tl' => translateText($word, 'tl'),
    'my' => translateText($word, 'my'),
    'th' => translateText($word, 'th')
];
$translationsJson = json_encode($translations, JSON_UNESCAPED_UNICODE);

// ▼ 履歴保存
if (!empty($word) && !empty($meaning) && $userId > 0) {
    try {
        $stmt = $pdo->prepare("INSERT INTO searched_words (user_id, word, meaning, subject, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $word, $meaning, $subject]);
    } catch (PDOException $e) { }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>辞書検索</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ルビの重なりや二重表示を防ぐためのスタイル */
        ruby { ruby-align: start; }
        rt { font-size: 0.6em; color: #666; font-weight: normal; }
        .content-ruby { line-height: 2.0; } /* ルビが入るため行間を広げる */
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        window.dictMap = <?php echo json_encode($allDictData, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="script.js"></script>
</head>
<body>
<div class="main-layout container">
    <div class="flex-between" style="margin-bottom:20px;">
        <h1>🔍 辞書検索</h1>
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($isFromCache): ?>
                <span style="font-size: 0.7em; background: #e0e0e0; padding: 2px 8px; border-radius: 10px; color: #666;">Cached</span>
            <?php endif; ?>
            <button id="toggleRubyBtn" class="btn-round" style="background:#6c757d;">ふりがな非表示</button>
        </div>
    </div>

    <div class="card-style" style="margin-bottom:25px; border-left: 5px solid #2196F3;">
        <div style="font-size:1.4em; margin-bottom:15px;">
            <strong>単語:</strong> <span class="no-ruby"><?php echo formatSmartRuby($word, $ruby); ?></span>
        </div>

        <?php if (!empty($imageUrl)): ?>
            <div class="dictionary-image-container" style="text-align:center; margin-bottom:20px;">
                <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                     style="max-width:100%; max-height:300px; border-radius:8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
            </div>
        <?php endif; ?>

        <div class="ruby-target">
            <strong>意味:</strong> 
            <div class="content-ruby" style="margin-top:10px; padding:15px; background:#f8f9fa; border-radius:8px; line-height:1.8;">
                <?php echo !empty($meaning) ? htmlspecialchars($meaning) : '辞書に登録されていません'; ?>
            </div>
        </div>
    </div>

    <div class="card-style" style="margin-bottom:30px; border-left: 5px solid #4CAF50;">
        <div style="margin-bottom:15px; display:flex; align-items:center; gap:10px;">
            <label for="lang-select"><strong>🌎 翻訳言語:</strong></label>
            <select id="lang-select" style="padding:8px; border-radius:6px; border:1px solid #ddd;">
                <option value="en">English</option>
                <option value="tl">Tagalog</option>
                <option value="my">Myanmar</option>
                <option value="th">Thai</option>
            </select>
        </div>
        <div id="translation-result" style="padding:15px; background:#e8f5e9; border-radius:8px; min-height:60px;">
            <div class="word"><strong>English:</strong></div>
            <div class="meaning" style="font-size:1.1em; margin-top:5px;">
                <?php echo htmlspecialchars($translations['en'] ?? ''); ?>
            </div>
        </div>
    </div>

    <div class="flex-between" style="justify-content:center; gap:15px;">
        <a href="dictionary_history.php" class="btn-round" style="background:#6c757d; padding:12px 25px;">📖 検索履歴</a>
        <a href="test.php?subject=<?php echo urlencode($subject); ?>" id="backLink" class="btn-round" style="background:#2196F3; padding:12px 25px;">◀ 試験画面へ戻る</a>
    </div>
</div>

<script>
$(function() {
    if (window.opener) { $("#backLink").hide(); }
    const translations = <?php echo $translationsJson; ?>;

    // script.js のルビ適用
    if (typeof window.applyRuby === "function") {
        window.applyRuby('.content-ruby');
        // visibilityも連動させる
        if (typeof window.applyRubyVisibility === "function") {
            window.applyRubyVisibility('.content-ruby');
        }
    }

    // 言語切り替えイベント
    $('#lang-select').on('change', function() {
        const lang = $(this).val();
        const labels = { 'en': 'English', 'tl': 'Tagalog', 'my': 'Myanmar', 'th': 'Thai' };
        $('#translation-result').html(
            '<div class="word"><strong>' + labels[lang] + ':</strong></div>' +
            '<div class="meaning" style="font-size:1.1em; margin-top:5px;">' + (translations[lang] || '---') + '</div>'
        );
    });
});
</script>
</body>
</html>