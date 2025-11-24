<?php
require "auth.php";

$dictJson = '{}';
require 'vendor/autoload.php';
use Google\Client;
use Google\Service\Sheets;

echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>トップページ</title>';
echo '<link rel="stylesheet" href="style.css">';

$metaPath = __DIR__ . "/ruby_meta_tags.html";
echo file_exists($metaPath) ? file_get_contents($metaPath) : "<!-- ruby_meta_tags.html not found -->";

echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
echo '<script src="script.js"></script>';
echo '<style>
.btn-container {
  text-align: center;
  margin-top: 10px;
}
.btn-container button {
  margin: 0 8px;
}
</style>';
echo '</head><body>';

// ▼ ユーザー情報
$user = $_SESSION["user"] ?? "guest";

// ▼ ダッシュボード風の冒頭メッセージ
echo '<div class="dashboard" style="max-width:600px;margin:30px auto;padding:20px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.15);text-align:center;">';

if ($user === "guest") {
    echo "<h2>ようこそ、ゲストさん！</h2>";
    echo "<p>お試し利用は可能ですが、学習履歴は保存されません。</p>";
    echo "<p>履歴を残したい場合はログインしてください。</p>";
    echo "<a href='login.php' style='display:block;width:calc(100% - 40px);margin:0 auto;padding:12px;background-color:#2196F3;color:#fff;text-decoration:none;border-radius:6px;font-size:1em;font-weight:bold;box-sizing:border-box;'>ログイン画面へ</a>";
} else {
    echo "<h2>ようこそ、" . htmlspecialchars($user) . " さん！</h2>";
    echo "<p>ここから科目を選んで学習を始められます。</p>";
}

// ▼ 辞書機能の案内を追加
echo "<p style='margin-top:20px;color:#333;font-size:0.95em;'>
      わからない単語はマウスで選択すると「辞書で調べる」ボタンが表示されます。
      </p>";

echo '</div>';



// ▼ ツールバー（科目切り替え＋ふりがな表示＋ユーザー情報＋リンク群）
echo '<div class="toolbar">';

// 左側：科目選択フォーム＋ふりがな表示
echo '  <form method="GET" id="subjectForm" class="no-ruby toolbar-form">';
echo '    <label for="subject" class="no-ruby">科目を選択:</label>';
echo '    <select name="subject" id="subject" class="subject-select no-ruby">';
$subjects = [
  "人間の尊厳と自立", "人間関係とコミュニケーション", "社会の理解", "こころとからだ",
  "発達と老化の理解", "認知症の理解", "障害の理解", "医療的ケア", "介護の基本",
  "コミュニケーション技術", "生活支援技術", "介護過程", "総合問題"
];
foreach ($subjects as $s) {
  $selected = ($_GET["subject"] ?? "") === $s ? "selected" : "";
  echo "<option value='{$s}' {$selected}>{$s}</option>";
}
echo '    </select>';
echo '    <button type="submit" class="btn btn-subject no-ruby">科目切り替え</button>';
echo '    <button type="button" id="toggleRubyBtn" class="btn btn-ruby no-ruby">ふりがな表示</button>';
echo '  </form>';

// 右側：ユーザー名＋リンク群
echo '  <div class="toolbar-links">';
echo "    <span class='user-label no-ruby'>現在のユーザー: " . htmlspecialchars($user) . "</span>";
if ($user !== "guest") {
    echo '    <a href="history.php" class="btn btn-history no-ruby">学習履歴を見る</a>';
    echo '    <a href="logout.php" class="btn btn-logout no-ruby">ログアウト</a>';
}
// guest の場合はリンクを表示しない
echo '  </div>';
echo '</div>';

// ▼ Google Sheets API 設定
$subject = $_GET['subject'] ?? '人間の尊厳と自立';
$client = new Google\Client();
$client->setApplicationName('ExamApp');
$client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);

// 環境変数からパスを取得
$client->setAuthConfig(getenv('GOOGLE_APPLICATION_CREDENTIALS'));

$client->setAccessType('offline');
$service = new Google\Service\Sheets($client);

// ▼ 辞書取得
try {
  $dictResponse = $service->spreadsheets_values->get(
    '1LDr4Acf_4SE-Wzp-ypPxM6COZdOt2QYumak8hIVVdxo',
    'dictionary_upload!A2:B'
  );
  $dictValues = $dictResponse->getValues() ?? [];
  $dictMap = [];
  foreach ($dictValues as $row) {
    $kanji = $row[0] ?? '';
    $furigana = $row[1] ?? '';
    if ($kanji && $furigana) $dictMap[$kanji] = $furigana;
  }
  $dictJson = json_encode($dictMap, JSON_UNESCAPED_UNICODE);
  echo "<script>const dictMap = {$dictJson};</script>";
} catch (Exception $e) {
  echo '<!-- 辞書取得失敗: ' . htmlspecialchars($e->getMessage()) . ' -->';
}

// ▼ 問題取得
try {
  $response = $service->spreadsheets_values->get(
    '1wBLqdju-BmXS--aPCMMC3PipvCpBFXmdVemT0X2rKew',
    "{$subject}!A2:J"
  );
  $values = $response->getValues();
  if (empty($values)) {
    echo "<p>データが見つかりませんでした。</p>";
  } else {
    foreach ($values as $index => $row) {
      $questionId   = $row[0] ?? '';
      $questionText = $row[1] ?? '';
      $choices      = array_slice($row, 2, 5);
      $correctIndex = intval($row[7] ?? 0);
      $explanation  = $row[8] ?? '';
      $examNumber   = $row[9] ?? '';

      echo "<div class='question-card'>";
echo "<form class='qa-form' action='save_history.php' method='post'>";

// 問題文
echo "<div class='question-text content-ruby'><strong>問題:</strong> " . htmlspecialchars($questionText) . "</div>";


// hiddenフィールド（必須データ）
echo "<input type='hidden' name='question_id' value='" . htmlspecialchars($questionId) . "'>";
echo "<input type='hidden' name='exam_number' value='" . htmlspecialchars($examNumber) . "'>";
echo "<input type='hidden' name='correct' value='" . htmlspecialchars($correctIndex) . "'>";
echo "<input type='hidden' name='subject' value='" . htmlspecialchars($subject) . "'>";  

// 選択肢
echo "<ul class='choices content-ruby'>";
for ($i = 1; $i <= 5; $i++) {
    $choiceTextRaw = $choices[$i-1] ?? '';
    $choiceText = htmlspecialchars($choiceTextRaw);
    if ($choiceText !== '') {
        // 番号を削除した文章（表示用）
        $cleanSentence = preg_replace('/^\d+\s*/', '', $choiceTextRaw);

        echo "<li>";
        echo "<label><input type='radio' name='answer' value='{$i}' required> {$cleanSentence}</label>";
        echo "</li>";
    }
}
echo "</ul>";


// ボタンコンテナ
echo "<div class='btn-container'>";
echo "<button type='submit' class='btn-answer no-ruby'>回答を送信</button>";
echo "<button type='button' class='btn-explanation no-ruby' data-index='{$index}'>解説を表示</button>";
echo "</div>";

// 解説
echo "<div class='answer'></div>";
echo "<div id='explanation{$index}' class='explanation content-ruby' style='display:none;'><strong>解説:</strong> " . htmlspecialchars($explanation) . "</div>";

echo "</form></div>";

    }
  }
} catch (Exception $e) {
  echo '<p>Google API Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
// ▼ ハイライト辞書検索用スクリプトを追加
echo '<script>
document.addEventListener("mouseup", function() {
    const selectedText = window.getSelection().toString().trim();
    if (selectedText.length > 0) {
        const oldPopup = document.getElementById("dictPopup");
        if (oldPopup) oldPopup.remove();

        const popup = document.createElement("div");
        popup.id = "dictPopup";
        popup.style.position = "absolute";
        popup.style.background = "#2196F3";
        popup.style.color = "#fff";
        popup.style.padding = "6px 10px";
        popup.style.borderRadius = "4px";
        popup.style.cursor = "pointer";
        popup.style.zIndex = "9999";
        popup.innerText = "辞書で調べる";

        popup.onclick = function() {
            window.open("dictionary.php?word=" + encodeURIComponent(selectedText), "_blank");
        };

        const rect = window.getSelection().getRangeAt(0).getBoundingClientRect();
        popup.style.left = (rect.left + window.scrollX) + "px";
        popup.style.top = (rect.top + window.scrollY - 30) + "px";

        document.body.appendChild(popup);

        setTimeout(() => {
            if (popup) popup.remove();
        }, 5000);
    }
});
</script>';


echo '</body></html>';
