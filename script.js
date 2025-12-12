/**
 * script.js
 * 
 * 役割：
 * - Aパート：ふりがな処理（dictMapを使ってテキストにルビを付与）
 * - Bパート：UI操作（ふりがな表示切替、解説開閉、スコア表示）
 * 
 * 注意：
 * - 辞書ポップアップ（ハイライト検索）は test.php 側に移動済み
 * - このファイルは学習UI専用として管理する
 */


$(function(){
  console.log(dictMap);

  function getSortedEntries(map) {
    return Object.entries(map).map(([kanji, val]) => {
      const furigana = typeof val === "string" ? val : val.furigana;
      return [kanji, furigana];
    }).sort((a, b) => b[0].length - a[0].length);
  }

  function applyRubyToTextNodes(rootEl, entries) {
    const walker = document.createTreeWalker(
      rootEl,
      NodeFilter.SHOW_TEXT,
      {
        acceptNode(node) {
          if (node.parentNode.closest(".no-ruby")) return NodeFilter.FILTER_REJECT;
          if (node.parentNode.closest("ruby")) return NodeFilter.FILTER_REJECT;
          return NodeFilter.FILTER_ACCEPT;
        }
      }
    );

    let current;
    while ((current = walker.nextNode())) {
      let text = current.nodeValue;
      if (!text) continue;

      let replaced = false;
      const frag = document.createDocumentFragment();
      let remaining = text;

      while (remaining.length > 0) {
        let found = null;
        let index = -1;

        // ▼ 正規表現検索に変更
        for (const [kanji, furigana] of entries) {
          const escaped = kanji.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); // 正規表現用にエスケープ
          const regex = new RegExp(escaped);
          const match = regex.exec(remaining);
          if (match && (index === -1 || match.index < index)) {
            found = [kanji, furigana];
            index = match.index;
          }
        }

        if (found) {
          const [kanji, furigana] = found;
          if (index > 0) {
            frag.appendChild(document.createTextNode(remaining.slice(0, index)));
          }
          const ruby = document.createElement("ruby");
          const rb = document.createElement("rb");
          const rt = document.createElement("rt");
          rb.textContent = kanji;
          rt.textContent = furigana;
          ruby.appendChild(rb);
          ruby.appendChild(rt);
          frag.appendChild(ruby);

          remaining = remaining.slice(index + kanji.length);
          replaced = true;
        } else {
          frag.appendChild(document.createTextNode(remaining));
          break;
        }
      }

      if (replaced) {
        current.replaceWith(frag);
      }
    }
  }

  function applyRuby(selector) {
    const entries = getSortedEntries(dictMap);
    $(selector).each(function(){
      applyRubyToTextNodes(this, entries);
    });
  }

  function applyRubyVisibility(scope = document) {
    const hidden = localStorage.getItem("rubyHidden") === "true";
    const $scope = $(scope);
    if (hidden) {
      $scope.find("ruby rt").hide();
      $("#toggleRubyBtn").text("ふりがな表示");
    } else {
      $scope.find("ruby rt").show();
      $("#toggleRubyBtn").text("ふりがな非表示");
    }
  }


  // ▼ 以下がBパート（初期適用とUI操作）初期表示時：問題文・選択肢・解説文・単語詳細・意味すべてにルビ適用
  applyRuby([
  ".question-card",
  ".question-card *",
  ".explanation",
  ".explanation *",
  ".word-detail",
  ".word-detail *",
  ".word-meaning",
  ".word-meaning *",
  ".meaning-text",
  ".meaning-text *"
].join(", "));

applyRubyVisibility();


  // ▼ UI要素（no-ruby クラス付き）はふりがなを削除して固定表示
  $(".no-ruby ruby").each(function(){
    const text = $(this).find("rb").text() || $(this).text();
    $(this).replaceWith(text);
  });

  // ▼ ふりがな表示切り替え（表示／非表示のみ）
  $("#toggleRubyBtn").on("click", function(e){
    e.preventDefault();
    $("ruby rt").toggle();
    const nowHidden = $("ruby rt").is(":hidden");
    $(this).text(nowHidden ? "ふりがな表示" : "ふりがな非表示");
    localStorage.setItem("rubyHidden", nowHidden ? "true" : "false");
  });

  // ▼ グローバル変数初期化
  window.correctCount = 0;
  window.totalAnswered = 0;
  window.subjectStats = {};

  // ▼ 解説を表示（開閉のみ）
  $(document).ready(function() {
    $(".btn-explanation").on("click", function() {
      const idx = $(this).data("index");
      $("#explanation" + idx).toggle();  // 開閉のみ、ふりがなは既に付いている
    });
  });

  // ▼ 正解率表示
  window.updateScore = function(){
    const scoreDiv = document.getElementById("score");
    const rate = window.totalAnswered > 0 ? Math.round((window.correctCount / window.totalAnswered) * 100) : 0;
    scoreDiv.textContent = `正解数: ${window.correctCount} / ${window.totalAnswered}　正解率: ${rate}%`;
  };

  window.updateSubjectStats = function(){
    const statsDiv = document.getElementById("subjectStats");
    const stats = window.subjectStats || {};
    let html = "<strong>科目別正解率:</strong><ul>";
    for (const subject in stats) {
      const s = stats[subject];
      const rate = s.total > 0 ? Math.round((s.correct / s.total) * 100) : 0;
      html += `<li>${subject}: ${s.correct} / ${s.total}（${rate}%）</li>`;
    }
    html += "</ul>";
    statsDiv.innerHTML = html;
  };
});

