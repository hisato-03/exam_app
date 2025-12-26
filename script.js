//$(function() {
  console.log("Check: script.js loaded");
  console.log("Check: dictMap size ->", window.dictMap ? Object.keys(window.dictMap).length : 0);

  // --- 1. ルビ適用メイン関数 ---
  window.applyRuby = function(selectorOrElement) {
    const rawMap = window.dictMap || {};
    const entries = getSortedEntries(rawMap);

    if (entries.length === 0) return;

    $(selectorOrElement).each(function() {
      applyRubyToTextNodes(this, entries);
    });

    if (typeof window.applyRubyVisibility === "function") {
      window.applyRubyVisibility(selectorOrElement);
    }
  };

  // --- 2. テキストノードを走査してルビを振るロジック ---
  function applyRubyToTextNodes(rootEl, entries) {
    if (!rootEl) return;

    const walker = document.createTreeWalker(
      rootEl,
      NodeFilter.SHOW_TEXT,
      {
        acceptNode(node) {
          const parent = node.parentNode;
          if (!parent || parent.closest(".no-ruby") || parent.closest("ruby")) {
            return NodeFilter.FILTER_REJECT;
          }
          const tagName = parent.tagName ? parent.tagName.toLowerCase() : "";
          if (["script", "style", "textarea"].includes(tagName)) {
            return NodeFilter.FILTER_REJECT;
          }
          return NodeFilter.FILTER_ACCEPT;
        }
      }
    );

    const nodesToProcess = [];
    let current;
    while ((current = walker.nextNode())) {
      nodesToProcess.push(current);
    }

    nodesToProcess.forEach(node => {
      let remaining = node.nodeValue;
      if (!remaining || !remaining.trim()) return;

      const frag = document.createDocumentFragment();
      let replaced = false;

      while (remaining.length > 0) {
        let found = null;
        let firstIndex = -1;

        for (const [kanji, furigana] of entries) {
          const index = remaining.indexOf(kanji);
          if (index !== -1 && (firstIndex === -1 || index < firstIndex)) {
            found = [kanji, furigana];
            firstIndex = index;
          }
        }

        if (found) {
          const [kanji, furigana] = found;
          if (firstIndex > 0) {
            frag.appendChild(document.createTextNode(remaining.slice(0, firstIndex)));
          }

          // ★ポイント: クリック可能なルビ要素を作成
          const ruby = document.createElement("ruby");
          ruby.className = "clickable-ruby"; 
          ruby.setAttribute("data-word", kanji);
          ruby.innerHTML = `<rb>${kanji}</rb><rt>${furigana}</rt>`;
          frag.appendChild(ruby);

          remaining = remaining.slice(firstIndex + kanji.length);
          replaced = true;
        } else {
          frag.appendChild(document.createTextNode(remaining));
          break;
        }
      }

      if (replaced) {
        node.replaceWith(frag);
      }
    });
  }

  // 辞書順（長い単語優先）にソート
  function getSortedEntries(map) {
    return Object.entries(map).sort((a, b) => b[0].length - a[0].length);
  }

  // --- 3. ふりがな表示切り替えの制御 ---
  let isRubyVisible = localStorage.getItem("rubyVisible") !== "false";

  window.applyRubyVisibility = function(selector) {
    if (isRubyVisible) {
      $(selector).find("rt").show();
    } else {
      $(selector).find("rt").hide();
    }
  };

  $("#toggleRubyBtn").text(isRubyVisible ? "ふりがな非表示" : "ふりがな表示");

  $(document).on("click", "#toggleRubyBtn", function() {
    isRubyVisible = !isRubyVisible;
    localStorage.setItem("rubyVisible", isRubyVisible);
    $(this).text(isRubyVisible ? "ふりがな非表示" : "ふりがな表示");
    window.applyRubyVisibility("body");
  });

  // --- 4. 【新機能】ルビをクリックして辞書を引く ---
  $(document).on("click", ".clickable-ruby", function(e) {
    e.stopPropagation(); // 親要素のクリックイベント（解説トグル等）を止める
    const word = $(this).attr("data-word");
    const subject = window.currentSubject || "";
    const url = `dictionary.php?word=${encodeURIComponent(word)}&subject=${encodeURIComponent(subject)}`;
    
    // 別ウィンドウで開く（現在のページを維持するため）
    window.open(url, 'dictWin', 'width=600,height=800,scrollbars=yes');
  });

  // --- 5. マウス選択（ハイライト）による辞書検索 ---
  $(document).on("mouseup", function(e) {
    if ($(e.target).closest("#dictPopup, .clickable-ruby").length) return;
    
    const selection = window.getSelection();
    const selectedText = selection.toString().trim();
    $("#dictPopup").remove();

    if (selectedText.length > 0 && selectedText.length < 20) {
      $('<div id="dictPopup">辞書で調べる</div>')
        .css({
          position: "absolute", left: e.pageX + 10, top: e.pageY + 10,
          padding: "10px 20px", background: "#2196F3", color: "#fff",
          borderRadius: "6px", cursor: "pointer", zIndex: 9999, fontSize: "14px"
        })
        .appendTo("body")
        .on("click", function() {
          const url = `dictionary.php?word=${encodeURIComponent(selectedText)}&subject=${encodeURIComponent(window.currentSubject || "")}`;
          window.open(url, 'dictWin', 'width=600,height=800,scrollbars=yes');
          $(this).remove();
        });
    }
 });

// ページ読み込み完了時に、もし対象があれば実行する
  $(function() {
    window.applyRuby(".content-ruby");
    // dictionary.php 用に ruby-target も対象に含める
    window.applyRuby(".ruby-target");
  });  
//});