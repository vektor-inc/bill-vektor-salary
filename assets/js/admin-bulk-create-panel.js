/**
 * 給与明細一覧画面の「給与テンプレートから一括登録」パネル用スクリプト。
 *
 * - 支給分セレクトの選択値に応じて生成件数の目安テキストを更新する。
 * - フォーム送信時に最終確認のダイアログを出す。
 *
 * PHP 側からは bvslBulkCreatePanel として以下を渡す（wp_localize_script）:
 *   - templateCount: 公開中の給与テンプレ件数（数値）
 *   - i18n: { selectTerm, summary, confirm } の翻訳済み文字列
 *
 * 元は class-bvsl-bulk-create-from-template.php にインラインで記述していたものを
 * 外部ファイルへ分離したもの。挙動は変えていない。
 */
( function () {
	'use strict';

	var form = document.getElementById( 'bvsl-bulk-create-form' );
	if ( ! form ) {
		return;
	}

	var termSel = document.getElementById( 'bvsl-bulk-term' );
	var summary = document.getElementById( 'bvsl-bulk-summary' );

	// PHP から渡された設定を取り出す。未定義時は安全側に倒してフォールバック。
	var data  = ( typeof window.bvslBulkCreatePanel !== 'undefined' ) ? window.bvslBulkCreatePanel : {};
	var i18n  = data.i18n || { selectTerm: '', summary: '', confirm: '' };
	// 公開テンプレ件数は PHP 側で確定済みの値を埋め込む。
	var templateCount = parseInt( data.templateCount, 10 );
	if ( isNaN( templateCount ) ) {
		templateCount = 0;
	}

	/**
	 * 翻訳テンプレートの %1$d / %2$s を順に置換する（sprintf 風）。
	 *
	 * @param {string} tpl  テンプレート文字列。
	 * @param {number} n    %1$d に入れる件数。
	 * @param {string} term %2$s に入れる支給分名。
	 * @return {string} 置換後の文字列。
	 */
	function format( tpl, n, term ) {
		return tpl.replace( '%1$d', n ).replace( '%2$s', term );
	}

	/**
	 * 現在選択されている支給分の表示テキストを返す。
	 *
	 * @return {string} 選択中なら option のテキスト、未選択なら空文字。
	 */
	function termText() {
		if ( termSel && termSel.value && termSel.options[ termSel.selectedIndex ] ) {
			return termSel.options[ termSel.selectedIndex ].text;
		}
		return '';
	}

	// 件数表示の更新。テンプレ件数は固定で、支給分を選んだら文言を 1 文に揃える。
	function updateSummary() {
		var term = termText();
		if ( '' === term ) {
			summary.textContent = i18n.selectTerm;
		} else {
			summary.textContent = format( i18n.summary, templateCount, term );
		}
	}

	if ( termSel ) {
		termSel.addEventListener( 'change', updateSummary );
	}

	// 送信前の最終確認。
	form.addEventListener( 'submit', function ( e ) {
		var term = termText();
		if ( '' === term ) {
			// 支給分未選択は HTML 側の required で弾かれるはずだが念のため。
			return;
		}
		if ( ! window.confirm( format( i18n.confirm, templateCount, term ) ) ) {
			e.preventDefault();
		}
	} );

	updateSummary();
} )();
