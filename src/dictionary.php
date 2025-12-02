<?php
session_start();
require "auth.php";
require __DIR__ . '/vendor/autoload.php';
// ▼ ユーザー情報
$userId = $_SESSION['user_id'] ?? 0;
$userName = $_SESSION['user'] ?? "guest";

use Google\Client;
use Google\Service\Sheets;

// ▼ .env 読み込み
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ▼ 翻訳関数（必ず定義しておく）
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
$client->setAuthConfig($_ENV['GOOGLE_APPLICATION_CREDENTIALS'] ?? null);
$client->setAccessType('offline');
$service = new Google\Service\Sheets($client);

// ▼ 辞書データ取得（ここで $dictValues を定義）
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

// ▼ dictMap を JS に渡す（script.js より前に）
echo "<script>const dictMap = {$dictMapJson};</script>";

echo '<script src="script.js"></script>';
echo '</head><body>';

// ▼ 単語と科目と意味を取得（完全一致）
$word = $_GET['word'] ?? '';
$subject = $_GET['subject'] ?? '';  // ← ここを追加

$meaning = '';
foreach ($dictValues as $row) {
    $dictWord = $row[0] ?? '';
    if ($dictWord && $word === $dictWord) {
        $meaning = $row[2] ?? '';  // C列（意味文）
        break;
    }
}


// ▼ 表示部分（単語は必ず表示）
echo "<div class='word-detail'><strong>単語:</strong> " . htmlspecialchars($word) . "</div>";

if (!empty($meaning)) {
    // 意味文は content-ruby クラス付きで1回だけ表示（ルビは JSに任せる）
    echo "<div class='word-meaning content-ruby'><strong>意味:</strong> <span class='meaning-text'>" . htmlspecialchars($meaning) . "</span></div>";
} else {
    echo "<div class='word-meaning'><strong>意味:</strong> 辞書に登録されていません</div>";
}

// ▼ 翻訳APIで多言語に変換（単語を翻訳対象にする）
$translations = [
    'en' => translateText($word, 'en'),
    'tl' => translateText($word, 'tl'),
    'my' => translateText($word, 'my'),
    'th' => translateText($word, 'th')
];
$translationsJson = json_encode($translations, JSON_UNESCAPED_UNICODE);

// ▼ 調べた単語を履歴に保存
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


//▼ 以下がBパート（HTML,JavaScript）ドロップダウンと表示領域
echo <<<HTML
<div>
  <label for="lang-select"><strong>翻訳言語:</strong></label>
  <select id="lang-select">
    <option value="en">English</option>
    <option value="tl">Tagalog</option>
    <option value="my">Myanmar</option>
    <option value="th">Thai</option>
  </select>
</div>
<div id="translation-result"><strong>English:</strong> {$translations['en']}</div>

<script>
const translations = {$translationsJson};
$('#lang-select').on('change', function() {
  const lang = $(this).val();
  let label = '';
  switch(lang) {
    case 'en': label = 'English'; break;
    case 'tl': label = 'Tagalog'; break;
    case 'my': label = 'Myanmar'; break;
    case 'th': label = 'Thai'; break;
  }
  $('#translation-result').html('<strong>' + label + ':</strong> ' + translations[lang]);
});
</script>
HTML;

?>
<!-- ▼ 履歴リンクと戻るリンク -->
<div class="links">
  <a href="dictionary_history.php">調べた単語履歴を見る</a> |
  <a href="test.php?subject=<?php echo urlencode($subject); ?>" id="backLink">試験画面へ戻る</a>
</div>

<script>
// test.phpからwindow.openで開かれた場合は「戻るリンク」を非表示にする
if (window.opener) {
  document.getElementById("backLink").style.display = "none";
}
</script>

</body>
</html>
