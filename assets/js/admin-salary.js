/**
 * 給与明細編集画面 - 社会保険料合計のリアルタイム表示
 *
 * 健康保険（#salary_kenkou）と厚生年金（#salary_nenkin）の入力欄の
 * 後ろに社会保険料合計行をDOM挿入し、値の変更に応じてリアルタイムで更新する。
 */
( function () {
	'use strict';

	/**
	 * カンマや空白を除去して数値に変換する
	 *
	 * @param {string} value 入力値
	 * @return {number} 数値（変換不能な場合は 0）
	 */
	function parseAmount( value ) {
		var cleaned = String( value ).replace( /,/g, '' ).trim();
		var num = parseFloat( cleaned );
		return isNaN( num ) ? 0 : num;
	}

	/**
	 * 合計金額表示を更新する
	 */
	function updateTotal() {
		var kenkouInput = document.getElementById( 'salary_kenkou' );
		var nenkinInput = document.getElementById( 'salary_nenkin' );
		var totalDisplay = document.getElementById( 'bvsl_shakaihoken_total_display' );

		if ( ! kenkouInput || ! nenkinInput || ! totalDisplay ) {
			return;
		}

		var total = parseAmount( kenkouInput.value ) + parseAmount( nenkinInput.value );
		totalDisplay.textContent = total.toLocaleString( 'ja-JP' );
	}

	/**
	 * 合計行を厚生年金の <tr> の直後に挿入する
	 */
	function insertTotalRow() {
		var nenkinInput = document.getElementById( 'salary_nenkin' );
		if ( ! nenkinInput ) {
			return;
		}

		// 既に挿入済みの場合はスキップ
		if ( document.getElementById( 'bvsl_shakaihoken_total_row' ) ) {
			return;
		}

		// 厚生年金 input の親 <tr> を取得
		var nenkinTr = nenkinInput.closest( 'tr.cf_item' );
		if ( ! nenkinTr ) {
			return;
		}

		// 合計行の <tr> を生成
		var totalTr = document.createElement( 'tr' );
		totalTr.id = 'bvsl_shakaihoken_total_row';
		totalTr.className = 'cf_item bvsl-shakaihoken-total';
		totalTr.innerHTML =
			'<th class="text-nowrap"><label>社会保険料合計</label></th>' +
			'<td>' +
				'<span id="bvsl_shakaihoken_total_display" class="bvsl-total-amount">0</span>' +
				'<span class="bvsl-total-unit"> 円</span>' +
				'<span class="bvsl-total-note">（健康保険 ＋ 厚生年金）</span>' +
			'</td>';

		// インラインスタイル（管理画面用）
		totalTr.querySelector( '.bvsl-total-note' ).style.cssText =
			'font-size: 0.85em; color: #666; margin-left: 4px;';

		// 厚生年金 <tr> の次に挿入
		nenkinTr.parentNode.insertBefore( totalTr, nenkinTr.nextSibling );

		// 初期値を計算して表示
		updateTotal();

		// イベントリスナーを設定
		var kenkouInput = document.getElementById( 'salary_kenkou' );
		if ( kenkouInput ) {
			kenkouInput.addEventListener( 'input', updateTotal );
			kenkouInput.addEventListener( 'change', updateTotal );
		}
		nenkinInput.addEventListener( 'input', updateTotal );
		nenkinInput.addEventListener( 'change', updateTotal );
	}

	/**
	 * DOMContentLoaded 後に実行
	 */
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', insertTotalRow );
	} else {
		insertTotalRow();
	}
}() );
