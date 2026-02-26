/**
 * 給与明細編集画面 - 雇用保険・社会保険料合計のリアルタイム表示
 *
 * PHP の bvsl_get_koyou_hoken() / bvsl_get_shakai_hoken_total() と
 * 同じ計算式を JavaScript で再現し、以下の2行をDOMに挿入する。
 *
 *   厚生年金
 *   ↓ ここに挿入
 *   雇用保険（自動計算）
 *   社会保険料合計（健康保険 ＋ 厚生年金 ＋ 雇用保険）
 *   ↓
 *   所得税
 */
( function () {
	'use strict';

	/**
	 * カンマ・全角数字・前後空白を除去して数値に変換する
	 * PHP の bvsl_format_number() に対応
	 *
	 * @param {string} value 入力値
	 * @return {number} 数値（変換不能な場合は 0）
	 */
	function parseAmount( value ) {
		// 全角数字を半角に変換
		var cleaned = String( value ).replace( /[０-９]/g, function ( s ) {
			return String.fromCharCode( s.charCodeAt( 0 ) - 0xFEE0 );
		} );
		cleaned = cleaned.replace( /,/g, '' ).trim();
		var num = parseFloat( cleaned );
		return isNaN( num ) ? 0 : num;
	}

	/**
	 * 雇用保険料率を取得する
	 * PHP の bvsl_get_koyou_hoken_rate() に対応
	 *
	 * @param {string} term salary_target_term の value
	 * @return {number} 料率
	 */
	function getKoyouHokenRate( term ) {
		if ( '20250401_after' === term ) {
			return 5.5 / 1000;
		} else if ( '20230401_after' === term ) {
			return 6 / 1000;
		} else if ( '20221001_after' === term ) {
			return 5 / 1000;
		}
		return 3 / 1000;
	}

	/**
	 * 課税対象支給（その他）の合計を取得する
	 * kazei_additional[n][price] の合計
	 * PHP の bvsl_get_total_earn() 内の kazei_additional 加算部分に対応
	 *
	 * @return {number}
	 */
	function getKazeiAdditionalTotal() {
		var total = 0;
		var inputs = document.querySelectorAll( 'input[name$="[price]"][id^="kazei_additional"]' );
		inputs.forEach( function ( input ) {
			total += parseAmount( input.value );
		} );
		return total;
	}

	/**
	 * 雇用保険の対象となる総支給額（稼ぎの合計 ＋ 交通費）を取得する
	 * PHP の bvsl_get_total_earn() + salary_transportation_total に対応
	 *
	 * @return {number}
	 */
	function getKoyouHokenTaisyou() {
		var base        = parseAmount( getVal( 'salary_base' ) );
		var overtime    = parseAmount( getVal( 'salary_overtime_total' ) );
		var part        = parseAmount( getVal( 'salary_part_total' ) );
		var holiday     = parseAmount( getVal( 'salary_holiday_total' ) );
		var transport   = parseAmount( getVal( 'salary_transportation_total' ) );
		var kazeiAdditional = getKazeiAdditionalTotal();
		return base + overtime + part + holiday + kazeiAdditional + transport;
	}

	/**
	 * 雇用保険料を計算する
	 * PHP の bvsl_get_koyou_hoken() に対応
	 *
	 * @return {number}
	 */
	function calcKoyouHoken() {
		// 「自動計算しない」チェックがある場合は 0
		var checkbox = document.getElementById( 'salary_koyouhoken' );
		if ( checkbox && checkbox.checked ) {
			return 0;
		}
		var term = getVal( 'salary_target_term' );
		var rate = getKoyouHokenRate( term );
		return Math.round( getKoyouHokenTaisyou() * rate );
	}

	/**
	 * input/select の値を id で取得するヘルパー
	 *
	 * @param {string} id
	 * @return {string}
	 */
	function getVal( id ) {
		var el = document.getElementById( id );
		return el ? el.value : '';
	}

	/**
	 * 各表示を更新する
	 */
	function updateAll() {
		var koyouDisplay = document.getElementById( 'bvsl_koyouhoken_display' );
		var totalDisplay = document.getElementById( 'bvsl_shakaihoken_total_display' );

		if ( ! koyouDisplay || ! totalDisplay ) {
			return;
		}

		var kenkou = parseAmount( getVal( 'salary_kenkou' ) );
		var nenkin = parseAmount( getVal( 'salary_nenkin' ) );
		var koyou  = calcKoyouHoken();
		var total  = kenkou + nenkin + koyou;

		koyouDisplay.textContent = koyou.toLocaleString( 'ja-JP' );
		totalDisplay.textContent = total.toLocaleString( 'ja-JP' );
	}

	/**
	 * 雇用保険行・社会保険料合計行を厚生年金 <tr> の直後に挿入する
	 */
	function insertRows() {
		var nenkinInput = document.getElementById( 'salary_nenkin' );
		if ( ! nenkinInput ) {
			return;
		}

		// 既に挿入済みの場合はスキップ
		if ( document.getElementById( 'bvsl_koyouhoken_row' ) ) {
			return;
		}

		var nenkinTr = nenkinInput.closest( 'tr.cf_item' );
		if ( ! nenkinTr ) {
			return;
		}

		var noteStyle = 'font-size: 0.85em; color: #666; margin-left: 4px;';

		// --- 雇用保険行 ---
		var koyouTr = document.createElement( 'tr' );
		koyouTr.id        = 'bvsl_koyouhoken_row';
		koyouTr.className = 'cf_item bvsl-koyouhoken';
		koyouTr.innerHTML =
			'<th class="text-nowrap"><label>雇用保険</label></th>' +
			'<td>' +
				'<span id="bvsl_koyouhoken_display" class="bvsl-calc-amount">0</span>' +
				'<span class="bvsl-calc-unit"> 円</span>' +
				'<span class="bvsl-calc-note">（自動計算）</span>' +
			'</td>';
		koyouTr.querySelector( '.bvsl-calc-note' ).style.cssText = noteStyle;

		// --- 社会保険料合計行 ---
		var totalTr = document.createElement( 'tr' );
		totalTr.id        = 'bvsl_shakaihoken_total_row';
		totalTr.className = 'cf_item bvsl-shakaihoken-total';
		totalTr.innerHTML =
			'<th class="text-nowrap"><label>社会保険料合計</label></th>' +
			'<td>' +
				'<span id="bvsl_shakaihoken_total_display" class="bvsl-total-amount">0</span>' +
				'<span class="bvsl-total-unit"> 円</span>' +
				'<span class="bvsl-total-note">（健康保険 ＋ 厚生年金 ＋ 雇用保険）</span>' +
			'</td>';
		totalTr.querySelector( '.bvsl-total-note' ).style.cssText = noteStyle;

		// 厚生年金 <tr> の次に挿入（雇用保険 → 社会保険料合計の順）
		nenkinTr.parentNode.insertBefore( koyouTr, nenkinTr.nextSibling );
		nenkinTr.parentNode.insertBefore( totalTr, koyouTr.nextSibling );

		// 初期値を計算して表示
		updateAll();

		// イベントリスナーを設定（雇用保険に関係する全フィールド）
		var watchIds = [
			'salary_kenkou',
			'salary_nenkin',
			'salary_base',
			'salary_overtime_total',
			'salary_part_total',
			'salary_holiday_total',
			'salary_transportation_total',
			'salary_target_term',
		];
		watchIds.forEach( function ( id ) {
			var el = document.getElementById( id );
			if ( el ) {
				el.addEventListener( 'input', updateAll );
				el.addEventListener( 'change', updateAll );
			}
		} );

		// 雇用保険チェックボックス
		var checkbox = document.getElementById( 'salary_koyouhoken' );
		if ( checkbox ) {
			checkbox.addEventListener( 'change', updateAll );
		}

		// kazei_additional の price フィールド（動的行にも対応するためtable全体を監視）
		var kazeiTable = document.querySelector( 'input[id^="kazei_additional"]' );
		if ( kazeiTable ) {
			var tbody = kazeiTable.closest( 'tbody' );
			if ( tbody ) {
				tbody.addEventListener( 'input', function ( e ) {
					if ( e.target && e.target.name && e.target.name.indexOf( '[price]' ) !== -1 ) {
						updateAll();
					}
				} );
			}
		}
	}

	/**
	 * DOMContentLoaded 後に実行
	 */
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', insertRows );
	} else {
		insertRows();
	}
}() );
