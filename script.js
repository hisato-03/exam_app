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

                    // --- 送り仮名を分離する処理の開始 ---
                    let rubyContainer = document.createElement("span"); 
                    
                    if (furiganaHTML.includes("<ruby>")) {
                        // 既にHTML化されている場合はそのまま
                        rubyContainer.innerHTML = furiganaHTML;
                    } else {
                        // スマート・ルビ・ロジック
                        let word = kanji;
                        let reading = furiganaHTML;
                        let wordLen = word.length;
                        let readingLen = reading.length;
                        let okuriganaLen = 0;

                        // 後ろから一致するひらがな（送り仮名）を探す
                        while (okuriganaLen < wordLen && okuriganaLen < readingLen) {
                            let wChar = word[wordLen - 1 - okuriganaLen];
                            let rChar = reading[readingLen - 1 - okuriganaLen];
                            // ひらがなが一致する場合、送り仮名とみなす
                            if (wChar === rChar && /[ぁ-ん]/.test(wChar)) {
                                okuriganaLen++;
                            } else {
                                break;
                            }
                        }

                        if (okuriganaLen > 0 && okuriganaLen < wordLen) {
                            // 送り仮名を分離してルビを振る
                            let baseKanji = word.substring(0, wordLen - okuriganaLen);
                            let rubyPart = reading.substring(0, readingLen - okuriganaLen);
                            let okurigana = word.substring(wordLen - okuriganaLen);
                            rubyContainer.innerHTML = `<ruby><rb>${baseKanji}</rb><rt>${rubyPart}</rt></ruby>${okurigana}`;
                        } else {
                            // 送り仮名がない場合
                            rubyContainer.innerHTML = `<ruby><rb>${word}</rb><rt>${reading}</rt></ruby>`;
                        }
                    }

                    // 属性（クリックイベント用クラス等）を ruby 要素に付与
                    const rubyElement = rubyContainer.querySelector("ruby") || rubyContainer;
                    rubyElement.classList.add("clickable-ruby");
                    if (window.meaningMap && window.meaningMap[kanji]) {
                        rubyElement.classList.add("has-meaning");
                    }
                    rubyElement.setAttribute("data-word", kanji);
                    
                    // 生成したノード（ruby + 送り仮名）を fragment に追加
                    while (rubyContainer.firstChild) {
                        frag.appendChild(rubyContainer.firstChild);
                    }
                    // --- 送り仮名処理の終了 ---
                    
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
        const $card = $form.closest('.question-card'); 
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
                $card.addClass('card-correct').removeClass('card-incorrect');
                statusHtml = '<div class="answer-status" style="color:#1976d2; font-weight:bold; font-size:1.3em; margin:15px 0;">⭕ 正解です！</div>';
            } else {
                $card.addClass('card-incorrect').removeClass('card-correct');
                statusHtml = '<div class="answer-status" style="color:#d32f2f; font-weight:bold; font-size:1.3em; margin:15px 0;">❌ 正解は [' + data.correct + '] です。</div>';
            }
            
            $resultDiv.html(statusHtml);

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
    
    // --- 6. 単語履歴の「覚えた！」ステータス切り替え (dictionary_history.php 用) ---
    window.toggleMaster = function(btn, word) {
        const $row = $(btn).closest('tr');
        const $btn = $(btn);
        // 現在のテキストが ⬜ なら、これから「覚えた(1)」にする
        const isNowMastered = $btn.text().trim() === '⬜' ? 1 : 0;

        $.ajax({
            url: 'update_word_status.php',
            type: 'POST',
            data: {
                word: word,
                status: isNowMastered
            },
            dataType: 'json'
        })
        .done(function(response) {
            if (response.success) {
                if (isNowMastered) {
                    $btn.text('✅');
                    $row.addClass('mastered-row');
                } else {
                    $btn.text('⬜');
                    $row.removeClass('mastered-row');
                }
            } else {
                alert('エラーが発生しました。');
            }
        })
        .fail(function() {
            alert('通信に失敗しました。');
        });
    };
    
    // 初期実行
    window.applyRuby(".content-ruby");
    window.applyRuby(".ruby-target");
});