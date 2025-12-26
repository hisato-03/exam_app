<?php
session_start();
require "auth.php";
require_once __DIR__ . '/load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');

require __DIR__ . '/vendor/autoload.php';

// ▼ ユーザー情報
$userId = $_SESSION['user_id'] ?? 0;
$userName = $_SESSION['user'] ?? "guest";

use Google\Client;
use Google\Service\Sheets;

// ▼ .env 読み込み
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ▼ 翻訳関数
function translateText($text, $targetLang = 'en') {
    $apiKey = $_ENV['GOOGLE_TRANSLATE_API_KEY'] ?? null;
    if (!$apiKey) {
        return "Translation API key not set.";
    }

    $url = "https://translation.googleapis.com/language/translate/v2?key={$apiKey}";
    $data = ['q' => $text, 'target' => $targetLang, 'format' => 'text'];
    $options = ['http' => [
        'header'  => "Content-type: application/json; charset=UTF-8\r\n",
        'method'  => 'POST',
        'content' => json_encode($data)
    ]];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === false) return "Translation request failed.";
    $json = json_decode($result, true);
    return $json['data']['translations'][0]['translatedText'] ?? '';
}

// ▼ Google Sheets API クライアント設定
$client = new Google\Client();
$client->setApplicationName('ExamApp');
$client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->setAccessType('offline');
$service = new Google\Service\Sheets($client);

// ▼ 辞書データ取得
try {
  $dictResponse = $service->spreadsheets_values->get(
    '1LDr4Acf_4SE-Wzp-ypPxM6COZdOt2QYumak8hIVVdxo',
    'dictionary_upload!A2:C'
  );
  $dictValues = $dictResponse->getValues() ?? [];
} catch (Exception $e) {
  $dictValues = [];
}

// ▼ dictMap を生成（単語 => ルビ）
$dictMap = [];
foreach ($dictValues as $row) {
    $dictWord = $row[0] ?? '';
    $ruby = $row[1] ?? '';
    if ($dictWord && $ruby) {
        $dictMap[$dictWord] = $ruby;
    }
}
$dictMapJson = json_encode($dictMap, JSON_UNESCAPED_UNICODE);

// ▼ HTMLヘッダ
echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>辞書ページ</title>';
echo '<link rel="stylesheet" href="style.css">';
echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
echo "<script>window.dictMap = {$dictMapJson};</script>";
echo '<script src="script.js"></script>';
echo '</head><body>';

// ▼ 単語と科目と意味を取得
$word = $_GET['word'] ?? '';
$subject = $_GET['subject'] ?? '';

$meaning = '';
foreach ($dictValues as $row) {
    $dictWord = $row[0] ?? '';
    if ($dictWord && $word === $dictWord) {
        $meaning = $row[2] ?? '';  // C列（意味文）
        break;
    }
}

// ▼ 翻訳APIで多言語に変換
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
        $pdo = new PDO(
            "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->prepare("
            INSERT INTO searched_words (user_id, word, meaning, subject, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $word, $meaning, $subject]);
    } catch (PDOException $e) {
        echo "<p>❌ 履歴保存失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// ▼ 表示部分（カード風に復活）
echo <<<HTML
<!-- ▼ ふりがな表示切替ボタン -->
  <div class="toggle-ruby">
    <button id="toggleRubyBtn" class="btn-secondary">ふりがな非表示</button>
  </div>
</div>
<div class="dictionary-container">
  <h1>辞書検索</h1>
 
  <div class="result-card">
    <div class="word-detail"><strong>単語:</strong> {$word}</div>
HTML;

if (!empty($meaning)) {
    echo "<div class='word-meaning ruby-target'><strong>意味:</strong> <span class='meaning-text'>" . htmlspecialchars($meaning) . "</span></div>";
}else {
    echo "<div class='word-meaning'><strong>意味:</strong> 辞書に登録されていません</div>";
}

echo <<<HTML
  </div>
  <div class="search-bar">
    <label for="lang-select"><strong>翻訳言語:</strong></label>
    <select id="lang-select">
      <option value="en">English</option>
      <option value="tl">Tagalog</option>
      <option value="my">Myanmar</option>
      <option value="th">Thai</option>
    </select>
  </div>

  <div id="translation-result" class="result-card">
    <div class="word"><strong>English:</strong></div>
    <div class="meaning">{$translations['en']}</div>
  </div>

  <div class="links">
    <a href="dictionary_history.php" class="btn-history">調べた単語履歴を見る</a>
    <a href="test.php?subject={$subject}" id="backLink" class="btn-secondary">試験画面へ戻る</a>
  </div>

  
<script>
// 1. 親ウィンドウ（試験画面）から開かれた場合の処理
if (window.opener) {
  document.getElementById("backLink").style.display = "none";
}

// 2. 翻訳データの定義
const translations = {$translationsJson};

// 3. ページ読み込み完了時の処理
$(function() {
    // --- 【追加】初期表示の日本語（意味文）にルビを適用 ---
    if (typeof window.applyRuby === "function") {
      window.applyRuby('.ruby-target');
    }
    // 4. 言語切り替え時の処理（既存のものをjQueryの作法で整理）
    $('#lang-select').on('change', function() {
        const lang = $(this).val();
        let label = '';
        switch(lang) {
            case 'en': label = 'English'; break;
            case 'tl': label = 'Tagalog'; break;
            case 'my': label = 'Myanmar'; break;
            case 'th': label = 'Thai'; break;
        }
        
        // 翻訳結果の書き換え
        $('#translation-result').html(
            '<div class="word"><strong>' + label + ':</strong></div>' +
            '<div class="meaning">' + translations[lang] + '</div>'
        );
    });
});
</script>

</body>
</html>
HTML;
