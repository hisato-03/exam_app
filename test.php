<?php
/**
 * test.php
 * 
 * 役割：
 * - 問題文・選択肢の表示
 * - 回答送信・解説表示
 * - 辞書ポップアップ生成（選択文字を「辞書で調べる」ボタンで検索）
 * - window.currentSubject を JS に渡す
 * 
 * 注意：
 * - 辞書ポップアップのクリックイベントはここで処理
 * - script.js 側には辞書関連コードは残さない
 */

require "auth.php";
// ▼ 科目パラメータを受け取る
$subject = $_GET['subject'] ?? '';
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

// ▼ 以上がAパート（準備、環境設定）ユーザー情報
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
    $selected = ($subject === $s) ? "selected" : "";
    echo "<option value='".htmlspecialchars($s)."' {$selected}>".htmlspecialchars($s)."</option>";
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
// ▼ トップページへのリンクを追加
echo '    <a href="/exam_app/index.php" class="btn btn-secondary no-ruby">🏠 トップページへ戻る</a>';
echo '  </div>';
// guest の場合はリンクを表示しない
echo '  </div>';
echo '</div>';

// Google Sheets API 設定
if (empty($subject)) {
    $subject = '人間の尊厳と自立'; // デフォルト科目
}

$client = new Google\Client();
$client->setApplicationName('ExamApp');
$client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);

// credentials.json の正しいパスを指定
$client->setAuthConfig(__DIR__ . '/credentials.json');


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
  echo "<script>const dictMap = {};</script>"; // ← 空オブジェクトで定義
}

// ▼ 問題取得（キャッシュ対応）
$cacheKey = "subject_" . $subject;

if (isset($_SESSION[$cacheKey]) && $_SESSION[$cacheKey]['expires'] > time()) {
    // キャッシュが有効ならそれを使う
    $values = $_SESSION[$cacheKey]['data'];
} else {
    try {
        $response = $service->spreadsheets_values->get(
            '1wBLqdju-BmXS--aPCMMC3PipvCpBFXmdVemT0X2rKew',
            "{$subject}!A2:M" // ← ここをLからMに
        );
        $values = $response->getValues();

        // キャッシュ保存（有効期限10分）
        $_SESSION[$cacheKey] = [
            'data' => $values,
            'expires' => time() + 600
        ];
    } catch (Exception $e) {
        echo '<p>Google API Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        $values = [];
    }
}

// ▼ ページング設定（5問ずつ表示）
$perPage = 5;
$page = max(1, intval($_GET['page'] ?? 1));
$total = is_array($values) ? count($values) : 0;
$start = ($page - 1) * $perPage;
$end = min($start + $perPage, $total);

// ▼ 問題出力
if (empty($values)) {
    echo "<p>データが見つかりませんでした。</p>";
} else {
    // ページ情報と進捗バーはループ外で表示するほうが安全（必要ならこの位置に）
    $totalPages = ceil($total / $perPage);
    $progress = ($end / max(1, $total)) * 100;

    echo "<div class='page-info' style='text-align:center;margin:15px 0;'>";
    echo "ページ {$page} / {$totalPages} （全 {$total} 問）";
    echo "</div>";

    echo "<div class='progress-bar' style='width:80%;margin:10px auto;background:#eee;border-radius:6px;overflow:hidden;'>";
    echo "<div style='width:{$progress}%;background:#4CAF50;height:12px;'></div>";
    echo "</div>";

    for ($index = $start; $index < $end; $index++) {
    // M列まで安全にパディング（12→13に変更）
    $row = array_pad($values[$index], 13, '');
    $questionId   = $row[0] ?? '';
    $questionText = $row[1] ?? '';
    $choices      = array_slice($row, 2, 5);
    $correctIndex = intval($row[7] ?? 0);
    $explanation  = $row[8] ?? '';
    $examNumber   = $row[9] ?? '';
    $imageFile    = $row[12] ?? ''; // M列（ファイル名）

        echo "<div class='question-card'>";
        echo "<form class='qa-form' action='save_history.php' method='post'>";
 
        // 問題文
        echo "<div class='question-text content-ruby'><strong>問題:</strong> " . htmlspecialchars($questionText) . "</div>";
        // M列の画像ファイルがあれば表示
    if (!empty($imageFile)) {
        echo "<img src='/exam_app/images/" . htmlspecialchars($imageFile, ENT_QUOTES) . "' alt='問題画像' class='question-image'>";
    }

        
        // hiddenフィールド
        echo "<input type='hidden' name='question_id' value='" . htmlspecialchars($questionId) . "'>";
        echo "<input type='hidden' name='exam_number' value='" . htmlspecialchars($examNumber) . "'>";
        echo "<input type='hidden' name='correct' value='" . htmlspecialchars($correctIndex) . "'>";
        echo "<input type='hidden' name='subject' value='" . htmlspecialchars($subject) . "'>";

        // 選択肢
        echo "<ul class='choices content-ruby'>";
        for ($i = 1; $i <= 5; $i++) {
            $choiceTextRaw = $choices[$i-1] ?? '';
            if (!empty($choiceTextRaw)) {
                $cleanSentence = preg_replace('/^\d+\s*/', '', $choiceTextRaw);
                echo "<li>";
                echo "<label><input type='radio' name='answer' value='{$i}' required> " . htmlspecialchars($cleanSentence) . "</label>";
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
  
    // ▼ ページネーション（subject保持）
echo "<div class='btn-container' style='margin:20px 0;text-align:center;'>";

// 前のページリンク
if ($page > 1) {
    $prevUrl = "test.php?subject=" . urlencode($subject) . "&page=" . ($page - 1);
    echo "<a class='btn-nav' href='{$prevUrl}'>◀ 前の5問</a>";
} else {
    echo "<span class='btn-nav disabled'>◀ 前の5問</span>";
}

// 次のページリンク
if ($end < $total) {
    $nextUrl = "test.php?subject=" . urlencode($subject) . "&page=" . ($page + 1);
    echo "<a class='btn-nav' href='{$nextUrl}'>次の5問 ▶</a>";
} else {
    echo "<span class='btn-nav disabled'>次の5問 ▶</span>";
}

echo "</div>";

// ▼ ページ番号リンク
$totalPages = ceil($total / $perPage);
echo "<div class='pagination' style='text-align:center;margin:20px 0;'>";

for ($p = 1; $p <= $totalPages; $p++) {
    if ($p == $page) {
        // 現在ページは強調表示
        echo "<span style='margin:0 5px;font-weight:bold;color:#4CAF50;'>[{$p}]</span>";
    } else {
        $url = "test.php?subject=" . urlencode($subject) . "&page={$p}";
        echo "<a href='{$url}' style='margin:0 5px;'>{$p}</a>";
    }
}

echo "</div>";
}

// PHPの処理ここまで
?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).on("submit", ".qa-form", function(e) {
  e.preventDefault();

  const $form = $(this);

  $.ajax({
    url: $form.attr("action"),
    type: $form.attr("method"),
    data: $form.serialize(),
    dataType: "json",
    success: function(response) {
      if (response.message) {
        // ▼ ゲストの場合（保存なし）
        $form.find(".answer").html(
          `<p>
            判定: <span style="color:${response.judgement === '○' ? 'green' : 'red'};">
              ${response.judgement}
            </span><br>
            ${response.message}
          </p>`
        );
      } else {
        // ▼ ログインユーザーの場合（保存あり）
        $form.find(".answer").html(
          `<p>      
            正解: ${response.correct}<br>
            判定: <span style="color:${response.judgement === '○' ? 'green' : 'red'};">
              ${response.judgement}
            </span><br>
          </p>`
        );
      }
    },
    error: function() {
      $form.find(".answer").html("<p style='color:red;'>保存に失敗しました。</p>");
    }
  });
});
</script>



<?php
//以上がBパート画面表示（HTML+問題出力）
// ▼ 科目をJSに渡す
echo "<script>window.currentSubject = '" . htmlspecialchars($subject, ENT_QUOTES) . "';</script>";

// ▼ 辞書ポップアップスクリプト
echo <<<EOT
<script>
document.addEventListener("mouseup", function(e) {
  if (e.target.id === "dictPopup") return;
  const selectedText = window.getSelection().toString().trim();
  const oldPopup = document.getElementById("dictPopup");
  if (oldPopup) oldPopup.remove();
  if (selectedText.length > 0) {
    const popup = document.createElement("div");
    popup.id = "dictPopup";
    popup.innerText = "辞書で調べる";
    popup.style.position = "absolute";
    popup.style.left = (e.pageX + 10) + "px";
    popup.style.top = (e.pageY + 10) + "px";
    popup.style.padding = "10px 20px";
    popup.style.background = "#2196F3";
    popup.style.color = "#fff";
    popup.style.borderRadius = "6px";
    popup.style.cursor = "pointer";
    popup.style.zIndex = "9999999";
    popup.style.pointerEvents = "auto";
    popup.addEventListener("click", function(ev) {
      ev.stopPropagation();
      const subject = window.currentSubject || "";
      const url = "dictionary.php?word=" + encodeURIComponent(selectedText) + "&subject=" + encodeURIComponent(subject);
      window.location.href = url;
      popup.remove();
    });
    document.body.appendChild(popup);
  }
});
</script>
EOT;
?>
