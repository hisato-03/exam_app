/* --------------------------------------------------------------------
Script Name:
Profaim Ruby AutoApply / フリガナ自動適用スクリプト
prfmRubyList
Version:
1.0.0 - 2013/09/25
1.0.1 - 2013/09/26 HTML記号のエスケープを追加実装

著作権表示:
Copyright 2013 profaim.jp (http://www.profaim.jp/)
Twitter   @PRFM_JP

使用許諾表示:
Released under the MIT license
本ソフトウェアは MITライセンス です。

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, 

本ソフトウェアやソフトウェアドキュメントの複製を取得した者は誰でも、
その使用権や、変更や流用、配布や二次ライセンス、販売を行う等のあらゆる権利が、
制限なく無償で与えられます。

本ソフトウェアの使用者がソフトウェアを提供する際にも、
同様の権利を主張する事が許可されます。

subject to the following conditions:
The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

本ソフトウェアの使用にあたり、本ソフトウェアの著作権表示と使用許諾表示を
本ソフトウェアを使用するソフトウェアの全てのコピーか重要な部分に明記して下さい。


THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

本ソフトウェアは無保証です。
現状のまま使用しても、明記されていない不具合であっても、一切保証されません。
商品性、適合性、権利を侵害していないことを始め、その他全てにおいて無保証です。

作者または著作権者は、契約行為、不法行為、またはそれ以外であろうと、
ソフトウェアに起因または関連し、あるいはソフトウェアの使用または
その他の扱いによって生じる一切の請求、損害、その他の義務について
何らの責任も負わないものとします。

=== Run with JQuery ===========================================================
JQuery
The MIT License

Copyright 2013 jQuery Foundation and other contributors
http://jquery.com/

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

----------------------------------------------------------------------- */
function prfmRubyAutoApply() {

	var prfmRubilist = {};	// ルビのリストを管理します。

	// --------------------------------------------------
	// コンテンツを解析し、ルビを付与します。
	// node: ルビを付与するルートノード（JQueryオブジェクト）
	// --------------------------------------------------
	function contentsTrace(node){
		var contents = node.contents();	// ノード中のコンテンツを取得する。
	
		// ----------------------------------------------
		// 指定ノードの全コンテンツについてルビの処理を行う。
		// ----------------------------------------------
		for (var i=0; i < contents.length; i++) {
			var htmlNode = contents[i];		// 処理対象のノードオブジェクトを取得する。
	
			// 処理対象のノード種別に応じて処理を分けます。
			switch (htmlNode.nodeType) {
				case 1:
					// ---------------------------------
					//  要素ノードに対する処理
					// ---------------------------------
					
					// 要素ノードの下層コンテンツをトレースする。
					contentsTrace(contents.eq(i));
					break;
				case 3:
					// ---------------------------------
					// テキストノードに対する処理
					// ---------------------------------
					
					// テキストノードの値を取得する。
					var nodeText = contents[i].nodeValue;
					
					// エスケープ対象文字列を宣言
					var character = {
      					'&' : '&amp;',
      					'<' : '&lt;' ,
      					'>' : '&gt;' ,
      					'"': '&quot;'
   					};
					// 取得した値の HTML記号 をエスケープする。
   					nodeText = nodeText.replace(/[&<>"]/g, function(chr) {
      												return character[chr];
   												});
	
					// ルビ適用を行ったノードかを評価するための変数。初期値は「ルビ適用なし」。
					var isRubyExists = false;
					
					// ルビリストに登録された全てのエントリを軸にルビ適用を行う。
					jQuery.each(prfmRubilist, function(i, key) {
	
						// ルビ適用対象を検索するための正規表現を作成する。スペースは１つ以上の任意空白とする。
						var regWord = key.getAttribute('base').replace(/\s+/, '\\s+');
	
						// テキストノードの値がルビ適用対象を含んでいるかを検索する。
						if(nodeText.match (new RegExp (regWord) )) {
							
							// ---------------------------------
							// ルビタグの適用情報を整理する。
							// ---------------------------------
							// ルビベースのリストを取得する。半角スペース区切りが語の区切りと判断する。
							var rubiBaseAttrs = null;
							if (key.getAttribute('base') != null) {
								rubiBaseAttrs = key.getAttribute('base').split(' ');
							}
	
							// ルビテキストのリストを取得する。半角スペース区切りが語の区切りと判断する。
							var rubiTextAttrs = null;
							if (key.getAttribute('text') != null) {
								rubiTextAttrs = key.getAttribute('text').split(' ');
							}
							
							// ルビ非対応ブラウザ用の記号を取得する。デフォルトは「(と)」。
							var rubiBP = key.getAttribute('left')==null?'(':key.getAttribute('left');
							var rubiEP = key.getAttribute('right')==null?')':key.getAttribute('right');
	
							// ---------------------------------
							// ルビタグを適用する。
							// ---------------------------------
	
							// ルビテキストが１つ以上定義されている場合はルビ適用を行う。
							// ※ルビテキストを定義しなければルビ適用をキャンセルできる。
							if (rubiTextAttrs != null) {
								
									// 親ノードがルビ関係のタグであった場合は、既に適用済みとして処理する。
									switch(node.get(0).tagName) {
										case 'RUBY':
										case 'RB':
										case 'RT':
										case 'RP':
											break;
										default:
	
											// ------------------------------------------------
											// ルビを適用する。ルビベースのリストを全て変換する。
											// ------------------------------------------------
											for (var repIdx = 0; repIdx < rubiBaseAttrs.length; repIdx++) {
	
												// 対応するルビベースとルビテキストを取得する。
												var rubiBase = rubiBaseAttrs[repIdx];
												var rubiText = rubiTextAttrs[repIdx];
										
												// ルビタグを適用したテキストを作成する。
												var rubiString = '<ruby><rb>' + rubiBase + '</rb>' +
												'<rp>' + rubiBP + '</rp><rt>' + rubiText + '</rt><rp>' + rubiEP + '</rp>' + '</ruby>';
	
												// ルビベースをルビタグを適用したテキストで置き換えるための文字列を作成する。
												nodeText = nodeText.replace(rubiBase, rubiString);
												
												// 「ルビ適用あり」とマークする。
												isRubyExists = true;
											}
											
											break;
									}
							}
							// ルビは先頭にのみ適用するため、１度適用したルビ情報は除去する。
							delete prfmRubilist[key.getAttribute('base')];
						}
					});
				
					// 「ルビ適用あり」の場合はノードテキストを置き換える。
					if(isRubyExists) {
						contents[i].nodeValue = '';
						contents.eq(i).after(nodeText);
					}
					break;
				
				default:
					// 備忘録：8がコメント 行かな?←FireFoxがコメントを引っ掛けた
					break;	
			}
		}
	};

	// --------------------------------------------------
	// ルビ付与の処理を開始します。
	// --------------------------------------------------
	function doRubyProcess(){
		
		// -------------------------------------------------------------
		// 変数を初期化します。
		// -------------------------------------------------------------
		var rubiTarget = null;	// ルビ付与をどの領域に適用するかを指定する。JQueryで要素を選択できる文字列を設定する。
		var rubiListCount = 0;	// いくつルビを適用するかを保持する変数です。
		prfmRubilist = {};		// 適用するルビをリストで保持する変数です。
		
		// -------------------------------------------------------------
		// ルビリストを準備します。HTMLからルビ定義を抽出してリスト化します。
		// -------------------------------------------------------------
		jQuery.each($('meta[name=profaim-ruby]').get(), function(i, val){
			// 同一のルビ適用対象（ルビベース）が定義されていた場合、最初の定義を採用します。
			if(prfmRubilist[val.getAttribute('base')] == undefined) {
				// ルビ適用対象（ルビベース）をキーに、ルビ定義オブジェクトを保持します。
				prfmRubilist[val.getAttribute('base')] = val;
				rubiListCount++;
			}
		});
		// 適用完了したルビ定義を削除します。
		$('meta[name=profaim-ruby]').remove();
		
		// -------------------------------------------------------------
		// ルビ対象範囲の特定
		// -------------------------------------------------------------
		jQuery.each($('meta[name=profaim-ruby-area]').get(), function(i, val) {
			// ルビ対象範囲が複数定義されていた場合、最初の定義を採用します。
			if(rubiTarget == null) {
				rubiTarget = val.getAttribute('target');
			}
		});
		
		// ルビ対象範囲が定義されていなかった場合、<BODY>をルビ対象範囲とします。
		if (rubiTarget == null) {
			rubiTarget = 'BODY';
		}
		// ルビ対象範囲の指定を削除します。
		$('meta[name=profaim-ruby-area]').remove();
		
		// -------------------------------------------------------------
		// ルビ処理だけで必要な情報を削除
		// -------------------------------------------------------------
		// 外部読み込み用領域の削除
		$('profaim-rubi-config').remove();
	
		// -------------------------------------------------------------
		// 適用するルビが１つでも定義されていればコンテンツ解析へ移る。
		// -------------------------------------------------------------
		if (rubiListCount > 0){
			contentsTrace($(rubiTarget));
		}
	};

	// --------------------------------------------------
	// フリガナ自動適用処理 開始
	// --------------------------------------------------
	var rubiList = $('meta[name=profaim-ruby-list]');	// 外部参照リストの取得
	if (rubiList.length > 0) {
		// ------------------------------------------------------
		// 外部参照リストが１つでも存在する場合
		// それぞれの外部参照リストについて解析を行いルビを適用する。
		// ------------------------------------------------------
		jQuery.each(rubiList.get(), function(i, listInfo) {
		
			var url = listInfo.getAttribute('url');  // 外部参照リストのURL情報を取得する。

			// cache:falseにすべし
			$.ajax({
				url:url,
				type:'get',
				async:false,
				dataType:'html',
				timeout:1000,
				cache:false,
				success:function(response, textStatus) {
					// 外部参照リストをヘッダに展開する。
					$('HEAD').append('<profaim-rubi-config>' + response + '</profaim-rubi-config>');
					// ルビの解析処理を実行する。
					doRubyProcess();
				},
				error:function(response, textStatus) {
				}
			});
		
		});
		
		$('meta[name=profaim-ruby-list]').remove();
		
	} else {
		// ルビの解析処理を実行する。
		doRubyProcess();
	}
}