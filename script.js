
$(function() {
    // 1. メタタグから辞書を読み込み、window.dictMap に統合する
    window.dictMap = window.dictMap || {};
    $('meta[name="profaim-ruby"]').each(function() {
        const b = $(this).attr('base');
        const t = $(this).attr('text');
        if (b && t) window.dictMap[b] = t;
    });

    // すべての辞書（スプレッドシート＋メタタグ）を統合して長い順にソート
    const sortedEntries = Object.entries(window.dictMap).sort((a, b) => b[0].length - a[0].length);

    console.log("Check: script.js loaded with Smart Ruby Support. Dictionary size:", sortedEntries.length);

    // --- ルビ適用メイン関数 (1つにまとめます) ---
    window.applyRuby = function(selectorOrElement) {
        if (sortedEntries.length === 0) return;

        $(selectorOrElement).each(function() {
            // ソート済みの共通リストを使って処理
            applyRubyToTextNodes(this, sortedEntries);
        });

        if (typeof window.applyRubyVisibility === "function") {
            window.applyRubyVisibility(selectorOrElement);
        }
    };
    // --- 2. テキストノードを走査してルビを振るロジック ---
    function applyRubyToTextNodes(rootEl, entries) {
        if (!rootEl) return;
        const walker = document.createTreeWalker(rootEl, NodeFilter.SHOW_TEXT, {
            acceptNode(node) {
                const parent = node.parentNode;
                // すでにrubyタグの中だったり、no-rubyクラスがある場合はスキップ
                if (!parent || parent.closest(".no-ruby") || parent.closest("ruby")) return NodeFilter.FILTER_REJECT;
                const tagName = parent.tagName ? parent.tagName.toLowerCase() : "";
                if (["script", "style", "textarea"].includes(tagName)) return NodeFilter.FILTER_REJECT;
                return NodeFilter.FILTER_ACCEPT;
            }
        });

        const nodesToProcess = [];
        let current;
        while ((current = walker.nextNode())) { nodesToProcess.push(current); }

        nodesToProcess.forEach(node => {
            let remaining = node.nodeValue;
            if (!remaining || !remaining.trim()) return;
            const frag = document.createDocumentFragment();
            let replaced = false;

            while (remaining.length > 0) {
                let found = null;
                let firstIndex = -1;

                for (const [kanji, furiganaHTML] of entries) {
                    const index = remaining.indexOf(kanji);
                    if (index !== -1 && (firstIndex === -1 || index < firstIndex)) {
                        found = [kanji, furiganaHTML];
                        firstIndex = index;
                    }
                }

                if (found) {
                    const [kanji, furiganaHTML] = found;
                    // 見つかった場所までのテキストを追加
                    if (firstIndex > 0) frag.appendChild(document.createTextNode(remaining.slice(0, firstIndex)));

                    // --- 【重要】スマート・ルビ対応の要素作成 ---
                    let rubyElement;

                    if (furiganaHTML.includes("<ruby>")) {
                        // パターンA: Python側で既にタグ化されている場合 (例: <ruby>関<rt>かん</rt></ruby>する)
                        // spanで包んで、中のHTMLとしてそのまま流し込む
                        rubyElement = document.createElement("span");
                        rubyElement.innerHTML = furiganaHTML;
                    } else {
                        // パターンB: 通常のふりがなテキストのみの場合
                        rubyElement = document.createElement("ruby");
                        rubyElement.innerHTML = `<rb>${kanji}</rb><rt>${furiganaHTML}</rt>`;
                    }

                    // 共通のクラスと属性を付与
                    rubyElement.classList.add("clickable-ruby");
                    if (window.meaningMap && window.meaningMap[kanji]) {
                        rubyElement.classList.add("has-meaning");
                    }
                    rubyElement.setAttribute("data-word", kanji);
                    
                    frag.appendChild(rubyElement);

                    remaining = remaining.slice(firstIndex + kanji.length);
                    replaced = true;
                } else {
                    frag.appendChild(document.createTextNode(remaining));
                    break;
                }
            }
            if (replaced) node.replaceWith(frag);
        });
    }

    // --- 3. ふりがな表示切り替え ---
    let isRubyVisible = localStorage.getItem("rubyVisible") !== "false";
    window.applyRubyVisibility = function(selector) {
        // rtタグを直接制御
        if (isRubyVisible) { $(selector).find("rt").show(); } 
        else { $(selector).find("rt").hide(); }
    };

    $("#toggleRubyBtn").text(isRubyVisible ? "ふりがな非表示" : "ふりがな表示");
    $(document).on("click", "#toggleRubyBtn", function() {
        isRubyVisible = !isRubyVisible;
        localStorage.setItem("rubyVisible", isRubyVisible);
        $(this).text(isRubyVisible ? "ふりがな非表示" : "ふりがな表示");
        window.applyRubyVisibility("body");
    });

    // --- 4. クリックイベント（意味がある単語のみ） ---
    $(document).on("click", ".clickable-ruby.has-meaning", function(e) {
        e.preventDefault();
        e.stopPropagation();
        const word = $(this).attr("data-word");
        const url = `dictionary.php?word=${encodeURIComponent(word)}`;
        window.open(url, 'dictWin', 'width=600,height=800,scrollbars=yes');
    });

    // 読み込み時に実行
    window.applyRuby(".content-ruby");
    window.applyRuby(".ruby-target");
});