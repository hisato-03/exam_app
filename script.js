$(function(){
  console.log(dictMap);

  function getSortedEntries(map) {
    return Object.entries(map).sort((a, b) => b[0].length - a[0].length);
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

        for (const [kanji, furigana] of entries) {
          const pos = remaining.indexOf(kanji);
          if (pos !== -1 && (index === -1 || pos < index)) {
            found = [kanji, furigana];
            index = pos;
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

  // ▼ 初期表示時：カード全体にふりがな適用
  applyRuby(".question-card");
  applyRuby(".question-card *");
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

  // ▼ 答えを確認（イベント委譲）
  /*$(document).on("submit", ".qa-form", function(e){
    e.preventDefault();
    const form = $(this);
    const index = parseInt(form.data("index"), 10);
    const correct = parseInt(form.data("correct"), 10);
    const subject = $("#subject").val();

    const selected = form.find(`input[name="answer${index}"]:checked`).val();
    const resultDiv = document.getElementById(`result${index}`);

    if (!selected) {
      resultDiv.textContent = "選択肢を選んでください。";
      resultDiv.style.color = "orange";
      return false;
    }

    if (form.data("answered") === true) {
      resultDiv.textContent = "この問題はすでに回答済みです。";
      resultDiv.style.color = "gray";
      return false;
    }

    const userAnswer = parseInt(selected, 10);

    if (userAnswer === correct) {
      resultDiv.textContent = "正解です！";
      resultDiv.style.color = "#2196f3";
      window.correctCount++;
      window.subjectStats[subject] = window.subjectStats[subject] || { correct: 0, total: 0 };
      window.subjectStats[subject].correct++;
    } else {
      resultDiv.textContent = `不正解です。正解は 選択肢${correct} です。`;
      resultDiv.style.color = "red";
      window.subjectStats[subject] = window.subjectStats[subject] || { correct: 0, total: 0 };
    }

    window.totalAnswered++;
    window.subjectStats[subject].total++;
    form.data("answered", true);

    updateScore();
    updateSubjectStats();
    return false;
  });
  */
 
  // ▼ 解説を表示（イベント委譲）
  $(document).ready(function() {
  // 解説ボタンのクリックイベント
  $(".btn-explanation").on("click", function() {
    const idx = $(this).data("index");   // ボタンに埋め込んだ data-index を取得
    $("#explanation" + idx).toggle();    // idで直接指定して開閉
  });

  // ふりがな表示切り替え（既存の処理がある場合はそのまま）
  $("#toggleRubyBtn").on("click", function() {
    $(".content-ruby").toggleClass("show-ruby");
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
