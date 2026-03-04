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

		var nenkinTr = nenkinInput.closest( 'tr.cf_item' ) || nenkinInput.closest( 'tr' );
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

/**
 * 給与明細編集画面 - メール送信プレビュー・送信
 */
( function () {
	'use strict';

	/**
	 * 文字列をHTMLエスケープする。
	 *
	 * @param {string} str
	 * @return {string}
	 */
	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	/**
	 * 送信日時文字列（Y-m-d H:i:s）を Y/m/d H:i 形式に変換する。
	 *
	 * @param {string} sentAt
	 * @return {string}
	 */
	function formatSentAt( sentAt ) {
		if ( ! sentAt ) {
			return '';
		}
		return sentAt.replace( /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}).*$/, '$1/$2/$3 $4:$5' );
	}

	/**
	 * メール履歴テーブルの tbody を取得し、なければ作成する。
	 *
	 * @return {Element|null}
	 */
	function ensureMailHistoryTable() {
		var tbody = document.getElementById( 'bvsl-mail-history-tbody' );
		if ( tbody ) {
			return tbody;
		}

		var wrap = document.getElementById( 'bvsl-mail-history-wrap' );
		if ( ! wrap ) {
			var metaBox = document.getElementById( 'meta_box_bill_normal' );
			if ( ! metaBox ) {
				return null;
			}
			var insideEl = metaBox.querySelector( '.inside' ) || metaBox;

			wrap = document.createElement( 'div' );
			wrap.id = 'bvsl-mail-history-wrap';
			wrap.style.marginTop = '16px';
			wrap.innerHTML =
				'<h4 style="margin-bottom:6px;">メール送信履歴</h4>' +
				'<table class="widefat striped">' +
					'<thead><tr>' +
						'<th>送信日時</th>' +
						'<th>送信先</th>' +
						'<th>件名</th>' +
						'<th>添付PDF</th>' +
						'<th>結果</th>' +
						'<th>失敗理由</th>' +
					'</tr></thead>' +
					'<tbody id="bvsl-mail-history-tbody"></tbody>' +
				'</table>';
			insideEl.appendChild( wrap );
		}

		return document.getElementById( 'bvsl-mail-history-tbody' );
	}

	/**
	 * メール履歴テーブルに1行追加する。
	 *
	 * @param {Object} data 送信結果データ
	 * @return {void}
	 */
	function prependMailHistoryRow( data ) {
		var tbody = ensureMailHistoryTable();
		if ( ! tbody ) {
			return;
		}

		var tr = document.createElement( 'tr' );
		tr.innerHTML =
			'<td>' + escapeHtml( formatSentAt( data.sent_at ) ) + '</td>' +
			'<td>' + escapeHtml( data.to || '' ) + '</td>' +
			'<td>' + escapeHtml( data.subject || '' ) + '</td>' +
			'<td>' + escapeHtml( data.attachment_name || '' ) + '</td>' +
			'<td>成功</td>' +
			'<td></td>';

		tbody.insertBefore( tr, tbody.firstChild );
	}

	/**
	 * 送信プレビューモーダルDOMを返す。なければ作成する。
	 *
	 * @return {Object}
	 */
	function ensurePreviewModal() {
		var overlay = document.getElementById( 'bvsl-mail-preview-modal' );
		if ( ! overlay ) {
			overlay = document.createElement( 'div' );
			overlay.id = 'bvsl-mail-preview-modal';
			overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;z-index:100000;';
			overlay.innerHTML =
				'<div style="max-width:800px;width:calc(100% - 40px);margin:40px auto;background:#fff;border-radius:4px;padding:16px;max-height:calc(100vh - 80px);overflow:auto;">' +
					'<h2 style="margin-top:0;">メール送信プレビュー</h2>' +
					'<p style="margin:0 0 8px;"><strong>件名</strong></p>' +
					'<div id="bvsl-mail-preview-subject" style="border:1px solid #dcdcde;padding:8px;background:#fff;margin-bottom:12px;"></div>' +
					'<p style="margin:0 0 8px;"><strong>本文</strong></p>' +
					'<pre id="bvsl-mail-preview-body" style="border:1px solid #dcdcde;padding:8px;background:#fff;white-space:pre-wrap;word-break:break-word;min-height:180px;"></pre>' +
					'<p style="margin:12px 0 8px;">この内容でメールを送信しますか</p>' +
					'<div style="display:flex;gap:8px;align-items:center;">' +
						'<button type="button" id="bvsl-mail-send-confirm-btn" class="button button-primary">メールを送信する</button>' +
						'<button type="button" id="bvsl-mail-send-cancel-btn" class="button">キャンセル</button>' +
						'<span id="bvsl-mail-send-confirm-spinner" class="spinner" style="float:none;display:none;margin:0;"></span>' +
					'</div>' +
				'</div>';
			document.body.appendChild( overlay );
		}

		return {
			overlay: overlay,
			subject: document.getElementById( 'bvsl-mail-preview-subject' ),
			body: document.getElementById( 'bvsl-mail-preview-body' ),
			confirmBtn: document.getElementById( 'bvsl-mail-send-confirm-btn' ),
			cancelBtn: document.getElementById( 'bvsl-mail-send-cancel-btn' ),
			confirmSpinner: document.getElementById( 'bvsl-mail-send-confirm-spinner' ),
		};
	}

	/**
	 * 送信メッセージ表示を更新する。
	 *
	 * @param {string} text テキスト
	 * @param {string} color 色
	 * @return {void}
	 */
	function setSendMessage( text, color ) {
		var message = document.getElementById( 'bvsl-mail-send-message' );
		if ( ! message ) {
			return;
		}
		message.style.color = color;
		message.textContent = text;
	}

	/**
	 * プレビュー取得リクエストを送る。
	 *
	 * @return {Promise}
	 */
	function fetchPreview() {
		var formData = new window.FormData();
		formData.append( 'action', 'bvsl_preview_salary_mail' );
		formData.append( 'nonce', window.bvslAdminSalary.mailNonce );
		formData.append( 'post_id', window.bvslAdminSalary.postId );

		return window.fetch( window.bvslAdminSalary.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} ).then( function ( res ) {
			return res.json();
		} );
	}

	/**
	 * メール送信リクエストを送る。
	 *
	 * @param {string} subject 件名
	 * @return {Promise}
	 */
	function sendMail( subject ) {
		var formData = new window.FormData();
		formData.append( 'action', 'bvsl_send_salary_mail' );
		formData.append( 'nonce', window.bvslAdminSalary.mailNonce );
		formData.append( 'post_id', window.bvslAdminSalary.postId );
		formData.append( 'subject', subject );

		return window.fetch( window.bvslAdminSalary.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} ).then( function ( res ) {
			return res.json();
		} );
	}

	/**
	 * 送信プレビューの初期化。
	 *
	 * @return {void}
	 */
	function initMailPreview() {
		var previewBtn = document.getElementById( 'bvsl-mail-preview-btn' );
		var spinner    = document.getElementById( 'bvsl-mail-send-spinner' );

		if ( ! previewBtn || ! window.bvslAdminSalary || ! window.bvslAdminSalary.mailNonce ) {
			return;
		}

		var modal = ensurePreviewModal();
		var latestPreview = null;

		previewBtn.addEventListener( 'click', function () {
			previewBtn.disabled = true;
			if ( spinner ) {
				spinner.style.display = 'inline-block';
			}
			setSendMessage( '', '#333' );

			fetchPreview()
				.then( function ( json ) {
					if ( json && json.success && json.data ) {
						latestPreview = json.data;
						modal.subject.textContent = json.data.subject || '';
						modal.body.textContent = json.data.body || '';
						modal.overlay.style.display = 'block';
						return;
					}

					var errorMessage = ( json && json.data && json.data.message ) ? json.data.message : 'メールプレビューの取得に失敗しました。';
					setSendMessage( errorMessage, '#dc3232' );
				} )
				.catch( function () {
					setSendMessage( '通信エラーが発生しました。', '#dc3232' );
				} )
				.finally( function () {
					previewBtn.disabled = false;
					if ( spinner ) {
						spinner.style.display = 'none';
					}
				} );
		} );

		modal.cancelBtn.addEventListener( 'click', function () {
			modal.overlay.style.display = 'none';
		} );

		modal.overlay.addEventListener( 'click', function ( event ) {
			if ( event.target === modal.overlay ) {
				modal.overlay.style.display = 'none';
			}
		} );

		modal.confirmBtn.addEventListener( 'click', function () {
			if ( ! latestPreview ) {
				return;
			}

			modal.confirmBtn.disabled = true;
			if ( modal.confirmSpinner ) {
				modal.confirmSpinner.style.display = 'inline-block';
			}

			sendMail( latestPreview.subject || '' )
				.then( function ( json ) {
					if ( json && json.success && json.data ) {
						prependMailHistoryRow( json.data );
						setSendMessage( 'メールを送信しました。', '#46b450' );
						modal.overlay.style.display = 'none';
						return;
					}

					var errorMessage = ( json && json.data && json.data.message ) ? json.data.message : 'メール送信に失敗しました。';
					setSendMessage( errorMessage, '#dc3232' );
				} )
				.catch( function () {
					setSendMessage( '通信エラーが発生しました。', '#dc3232' );
				} )
				.finally( function () {
					modal.confirmBtn.disabled = false;
					if ( modal.confirmSpinner ) {
						modal.confirmSpinner.style.display = 'none';
					}
				} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initMailPreview );
	} else {
		initMailPreview();
	}
}() );

/**
 * 給与明細編集画面 - 支給分の共通メッセージ表示
 */
( function () {
	'use strict';
	var commonMessageRequestGeneration = 0;

	/**
	 * 共通メッセージ表示の行IDを返す。
	 *
	 * @return {string}
	 */
	function getCommonMessageRowId() {
		if ( window.bvslAdminSalary && window.bvslAdminSalary.commonMessageId ) {
			return window.bvslAdminSalary.commonMessageId;
		}
		return 'bvsl-common-message-row';
	}

	/**
	 * 支給分チェックのタームID配列を DOM 順で取得する。
	 *
	 * @return {Array}
	 */
	function getCheckedTermIds() {
		var checkboxes = document.querySelectorAll( '#taxonomy-salary-term input[type="checkbox"][name^="tax_input[salary-term]"]' );
		var termIds = [];

		checkboxes.forEach( function ( checkbox ) {
			if ( checkbox.checked ) {
				termIds.push( checkbox.value );
			}
		} );

		return termIds;
	}

	/**
	 * 共通メッセージ表示行のDOM要素を取得する。
	 *
	 * @return {Element|null}
	 */
	function getCommonMessageRow() {
		return document.getElementById( getCommonMessageRowId() );
	}

	/**
	 * メッセージが空白のみかを判定する。
	 *
	 * @param {string} message 判定対象
	 * @return {boolean}
	 */
	function isBlankMessage( message ) {
		if ( null === message || typeof message === 'undefined' ) {
			return true;
		}
		return String( message ).replace( /[\s\u3000]+/g, '' ) === '';
	}

	/**
	 * メッセージ入力欄の行要素を取得する。
	 *
	 * @return {Element|null}
	 */
	function findMessageRow() {
		var messageField = document.getElementById( 'salary_message' ) ||
			document.querySelector( 'textarea[name="salary_message"]' ) ||
			document.querySelector( 'textarea[id*="salary_message"]' );

		if ( messageField ) {
			return messageField.closest( 'tr.cf_item' ) || messageField.closest( 'tr' );
		}

		// フィールドID差異があるケースに備えて、ラベル文字列でも補助探索する。
		var labels = document.querySelectorAll( '.postbox th, .postbox label' );
		for ( var i = 0; i < labels.length; i++ ) {
			if ( labels[ i ].textContent && labels[ i ].textContent.replace( /\s+/g, '' ) === 'メッセージ' ) {
				return labels[ i ].closest( 'tr' );
			}
		}

		return null;
	}

	/**
	 * ローカライズ済みの共通メッセージから、先頭の非空メッセージを取得する。
	 *
	 * @param {Array} termIds チェック済みタームID配列
	 * @return {string}
	 */
	function getLocalizedCommonMessage( termIds ) {
		if ( ! window.bvslAdminSalary || ! window.bvslAdminSalary.termMessages ) {
			return '';
		}

		var termMessages = window.bvslAdminSalary.termMessages;
		for ( var i = 0; i < termIds.length; i++ ) {
			var termId = String( termIds[ i ] );
			if ( termMessages[ termId ] && ! isBlankMessage( termMessages[ termId ] ) ) {
				return String( termMessages[ termId ] );
			}
		}

		return '';
	}

	/**
	 * 共通メッセージ表示行を、メッセージ入力欄の直前に追加する。
	 *
	 * @return {Element|null}
	 */
	function ensureCommonMessageRow() {
		var existing = getCommonMessageRow();
		if ( existing ) {
			return existing;
		}

		var messageTr = findMessageRow();
		if ( ! messageTr || ! messageTr.parentNode ) {
			return null;
		}

		var row = document.createElement( 'tr' );
		row.id = getCommonMessageRowId();
		row.className = 'cf_item';
		row.style.display = 'none';
		row.innerHTML =
			'<th class="text-nowrap"><label>共通メッセージ（支給分）</label></th>' +
			'<td><div id="bvsl-common-message-content" style="white-space: pre-wrap;"></div></td>';

		messageTr.parentNode.insertBefore( row, messageTr );
		return row;
	}

	/**
	 * 共通メッセージを画面へ反映する。
	 *
	 * @param {string} commonMessage 表示メッセージ
	 * @return {void}
	 */
	function renderCommonMessage( commonMessage ) {
		var row = ensureCommonMessageRow();
		if ( ! row ) {
			return;
		}

		var content = row.querySelector( '#bvsl-common-message-content' );
		if ( ! content ) {
			return;
		}

		if ( ! isBlankMessage( commonMessage ) ) {
			content.textContent = commonMessage;
			row.style.display = '';
			return;
		}

		content.textContent = '';
		row.style.display = 'none';
	}

	/**
	 * Ajaxで共通メッセージを取得する。
	 *
	 * @param {Array} termIds チェック済みタームID配列
	 * @return {Promise<string>}
	 */
	function fetchCommonMessage( termIds ) {
		if ( ! window.bvslAdminSalary || ! window.bvslAdminSalary.ajaxUrl || ! window.bvslAdminSalary.nonce ) {
			return Promise.resolve( '' );
		}

		var formData = new window.FormData();
		formData.append( 'action', 'bvsl_get_salary_term_common_message' );
		formData.append( 'nonce', window.bvslAdminSalary.nonce );
		termIds.forEach( function ( termId ) {
			formData.append( 'term_ids[]', termId );
		} );

		return window.fetch( window.bvslAdminSalary.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success || ! json.data ) {
					return '';
				}
				return json.data.common_message || '';
			} )
			.catch( function () {
				return '';
			} );
	}

	/**
	 * 共通メッセージ表示を最新状態に更新する。
	 *
	 * @return {void}
	 */
	function updateCommonMessage() {
		var termIds = getCheckedTermIds();
		if ( ! termIds.length ) {
			commonMessageRequestGeneration++;
			renderCommonMessage( '' );
			return;
		}

		var localizedCommonMessage = getLocalizedCommonMessage( termIds );
		if ( ! isBlankMessage( localizedCommonMessage ) ) {
			renderCommonMessage( localizedCommonMessage );
		}

		commonMessageRequestGeneration++;
		var requestGeneration = commonMessageRequestGeneration;

		fetchCommonMessage( termIds ).then( function ( commonMessage ) {
			if ( requestGeneration !== commonMessageRequestGeneration ) {
				return;
			}

			if ( ! isBlankMessage( commonMessage ) ) {
				renderCommonMessage( commonMessage );
				return;
			}
			renderCommonMessage( localizedCommonMessage );
		} );
	}

	/**
	 * イベントを設定する。
	 *
	 * @return {void}
	 */
	function initCommonMessage() {
		ensureCommonMessageRow();
		updateCommonMessage();

		// 動的UI更新にも追従できるようイベント委譲で監視する。
		document.addEventListener( 'change', function ( event ) {
			var target = event.target;
			if ( target && target.matches( '#taxonomy-salary-term input[type="checkbox"][name^="tax_input[salary-term]"]' ) ) {
				updateCommonMessage();
			}
		} );

		// 画面描画タイミング差分に備えて初回だけ再試行する。
		window.setTimeout( function () {
			ensureCommonMessageRow();
			updateCommonMessage();
		}, 300 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initCommonMessage );
	} else {
		initCommonMessage();
	}
}() );

/**
 * 給与明細編集画面 - PDF発行・削除
 */
( function () {
	'use strict';

	/**
	 * 日時文字列（Y-m-d H:i:s）を Y/m/d H:i 形式に変換する。
	 *
	 * @param {string} issuedAt
	 * @return {string}
	 */
	function formatIssuedAt( issuedAt ) {
		if ( ! issuedAt ) {
			return '';
		}
		// "2026-02-27 16:00:00" → "2026/02/27 16:00"
		return issuedAt.replace( /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}).*$/, '$1/$2/$3 $4:$5' );
	}

	/**
	 * PDF履歴テーブルの tbody を取得、なければ作成する。
	 *
	 * @return {Element|null}
	 */
	function ensurePdfHistoryTable() {
		var tbody = document.getElementById( 'bvsl-pdf-history-tbody' );
		if ( tbody ) {
			return tbody;
		}

		// テーブルがない場合は新規作成
		var wrap = document.getElementById( 'bvsl-pdf-history-wrap' );
		if ( ! wrap ) {
			var metaBox = document.getElementById( 'meta_box_bill_normal' );
			if ( ! metaBox ) {
				return null;
			}
			var insideEl = metaBox.querySelector( '.inside' ) || metaBox;

			wrap = document.createElement( 'div' );
			wrap.id = 'bvsl-pdf-history-wrap';
			wrap.style.marginTop = '16px';
			wrap.innerHTML =
				'<h4 style="margin-bottom:6px;">発行済みPDF履歴</h4>' +
				'<table class="widefat striped">' +
					'<thead><tr>' +
						'<th>発行日時</th>' +
						'<th>ファイル名</th>' +
						'<th>操作</th>' +
					'</tr></thead>' +
					'<tbody id="bvsl-pdf-history-tbody"></tbody>' +
				'</table>';
			insideEl.appendChild( wrap );
		}

		return document.getElementById( 'bvsl-pdf-history-tbody' );
	}

	/**
	 * 発行済みPDF履歴テーブルに新しい行を先頭に追加する。
	 *
	 * @param {Object} data { pdf_url, attachment_id, filename, issued_at }
	 * @return {void}
	 */
	function prependPdfHistoryRow( data ) {
		var tbody = ensurePdfHistoryTable();
		if ( ! tbody ) {
			return;
		}

		var tr = document.createElement( 'tr' );
		tr.dataset.attachmentId = String( data.attachment_id );
		tr.innerHTML =
			'<td>' + escapeHtml( formatIssuedAt( data.issued_at ) ) + '</td>' +
			'<td>' + escapeHtml( data.filename ) + '</td>' +
			'<td>' +
				'<button type="button" class="button button-small bvsl-pdf-delete-btn" data-attachment-id="' + data.attachment_id + '">削除</button>' +
				'<a href="' + escapeHtml( data.pdf_url ) + '" target="_blank" rel="noopener noreferrer" class="button button-small" style="margin-left:4px;">プレビュー</a>' +
			'</td>';

		tbody.insertBefore( tr, tbody.firstChild );
	}

	/**
	 * 文字列をHTMLエスケープする。
	 *
	 * @param {string} str
	 * @return {string}
	 */
	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	/**
	 * PDF発行ボタンのクリックハンドラ。
	 *
	 * @return {void}
	 */
	function handlePdfIssue() {
		var btn     = document.getElementById( 'bvsl-pdf-issue-btn' );
		var spinner = document.getElementById( 'bvsl-pdf-issue-spinner' );
		var message = document.getElementById( 'bvsl-pdf-issue-message' );

		if ( ! btn ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			if ( ! window.bvslAdminSalary || ! window.bvslAdminSalary.pdfNonce ) {
				return;
			}

			var postId = window.bvslAdminSalary.postId;
			if ( ! postId ) {
				return;
			}

			btn.disabled = true;
			if ( spinner ) {
				spinner.style.display = 'inline-block';
			}
			if ( message ) {
				message.textContent = '';
			}

			var formData = new window.FormData();
			formData.append( 'action', 'bvsl_generate_salary_pdf' );
			formData.append( 'nonce', window.bvslAdminSalary.pdfNonce );
			formData.append( 'post_id', postId );

			window.fetch( window.bvslAdminSalary.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( json ) {
					if ( json && json.success && json.data ) {
						prependPdfHistoryRow( json.data );
						if ( message ) {
							message.style.color = '#46b450';
							message.textContent = 'PDFを発行しました。';
						}
					} else {
						var errMsg = ( json && json.data && json.data.message ) ? json.data.message : 'PDF発行に失敗しました。';
						if ( message ) {
							message.style.color = '#dc3232';
							message.textContent = errMsg;
						}
					}
				} )
				.catch( function () {
					if ( message ) {
						message.style.color = '#dc3232';
						message.textContent = '通信エラーが発生しました。';
					}
				} )
				.finally( function () {
					btn.disabled = false;
					if ( spinner ) {
						spinner.style.display = 'none';
					}
				} );
		} );
	}

	/**
	 * PDF削除ボタンのクリックハンドラ（イベント委譲）。
	 *
	 * @return {void}
	 */
	function handlePdfDelete() {
		document.addEventListener( 'click', function ( event ) {
			var target = event.target;
			if ( ! target || ! target.classList.contains( 'bvsl-pdf-delete-btn' ) ) {
				return;
			}

			if ( ! window.confirm( 'このPDFを削除しますか？この操作は元に戻せません。' ) ) {
				return;
			}

			if ( ! window.bvslAdminSalary || ! window.bvslAdminSalary.pdfNonce ) {
				return;
			}

			var attachmentId = target.dataset.attachmentId;
			var postId       = window.bvslAdminSalary.postId;
			var tr           = target.closest( 'tr' );

			target.disabled = true;

			var formData = new window.FormData();
			formData.append( 'action', 'bvsl_delete_salary_pdf' );
			formData.append( 'nonce', window.bvslAdminSalary.pdfNonce );
			formData.append( 'post_id', postId );
			formData.append( 'attachment_id', attachmentId );

			window.fetch( window.bvslAdminSalary.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( json ) {
					if ( json && json.success ) {
						if ( tr ) {
							tr.parentNode.removeChild( tr );
						}
					} else {
						window.alert( 'PDFの削除に失敗しました。' );
						target.disabled = false;
					}
				} )
				.catch( function () {
					window.alert( '通信エラーが発生しました。' );
					target.disabled = false;
				} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			handlePdfIssue();
			handlePdfDelete();
		} );
	} else {
		handlePdfIssue();
		handlePdfDelete();
	}
}() );
