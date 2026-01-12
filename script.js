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

    // --- ルビ適用メイン関数 ---
    window.applyRuby = function(selectorOrElement) {
        if (sortedEntries.length === 0) return;

        $(selectorOrElement).each(function() {
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
                    if (firstIndex > 0) frag.appendChild(document.createTextNode(remaining.slice(0, firstIndex)));

                    let rubyElement;
                    if (furiganaHTML.includes("<ruby>")) {
                        rubyElement = document.createElement("span");
                        rubyElement.innerHTML = furiganaHTML;
                    } else {
                        rubyElement = document.createElement("ruby");
                        rubyElement.innerHTML = `<rb>${kanji}</rb><rt>${furiganaHTML}</rt>`;
                    }

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

    // --- 3. ふりがな表示切り替え & ボタン外観更新 ---
    let isRubyVisible = localStorage.getItem("rubyVisible") !== "false";

    function updateRubyButtonVisuals($btn, visible) {
        if (visible) {
            $btn.addClass('active');
            $btn.html('<span>🔓</span> ふりがな非表示');
            $btn.css('background', '#FF9800'); 
        } else {
            $btn.removeClass('active');
            $btn.html('<span>🔒</span> ふりがな表示');
            $btn.css('background', '#6c757d');
        }
    }

    window.applyRubyVisibility = function(selector) {
        if (isRubyVisible) { 
            $(selector).find("rt").show(); 
        } else { 
            $(selector).find("rt").hide(); 
        }
    };

    const $rubyBtn = $("#toggleRubyBtn");
    updateRubyButtonVisuals($rubyBtn, isRubyVisible);

    $(document).on("click", "#toggleRubyBtn", function() {
        isRubyVisible = !isRubyVisible;
        localStorage.setItem("rubyVisible", isRubyVisible);
        updateRubyButtonVisuals($(this), isRubyVisible);
        window.applyRubyVisibility("body");
    });

    // --- 4. 回答送信（Ajax）処理 & カード演出 ---
    $('.qa-form').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $card = $form.closest('.question-card'); // 親カードを取得
        const $resultDiv = $form.find('.answer');
        const $explanation = $form.find('.explanation');
        const $submitBtn = $form.find('.btn-answer');

        $submitBtn.prop('disabled', true).text('送信中...');

        $.ajax({
            url: 'save_history.php',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json'
        })
        .done(function(data) {
            let statusHtml = '';
            if (data.is_correct) {
                // 正解：カードを青くする
                $card.addClass('card-correct').removeClass('card-incorrect');
                statusHtml = '<div class="answer-status" style="color:#1976d2; font-weight:bold; font-size:1.3em; margin:15px 0;">⭕ 正解です！</div>';
            } else {
                // 不正解：カードを赤くする
                $card.addClass('card-incorrect').removeClass('card-correct');
                statusHtml = '<div class="answer-status" style="color:#d32f2f; font-weight:bold; font-size:1.3em; margin:15px 0;">❌ 正解は [' + data.correct + '] です。</div>';
            }
            
            $resultDiv.html(statusHtml);

            // 新しく表示されたテキストにルビを適用
            if (typeof window.applyRuby === "function") {
                window.applyRuby($resultDiv[0]);
                window.applyRuby($explanation[0]);
                window.applyRubyVisibility('.content-ruby');
            }
            
            $explanation.slideDown(400);
            $submitBtn.text('回答済み').css({'background':'#ccc','cursor':'default','box-shadow':'none'});
        });
    });

    // --- 5. クリックイベント（辞書ポップアップなど） ---
    $(document).on("click", ".clickable-ruby.has-meaning", function(e) {
        e.preventDefault();
        e.stopPropagation();
        const word = $(this).attr("data-word");
        const url = `dictionary.php?word=${encodeURIComponent(word)}`;
        window.open(url, 'dictWin', 'width=600,height=800,scrollbars=yes');
    });

    // 初期実行
    window.applyRuby(".content-ruby");
    window.applyRuby(".ruby-target");
});