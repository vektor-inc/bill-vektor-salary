<?php
/**
 * 給与明細メッセージ関連機能。
 *
 * @package Bill_Vektor_Salary
 */

/**
 * 支給分タクソノミーの共通メッセージ保存キー。
 */
define( 'BVSL_SALARY_TERM_COMMON_MESSAGE_META_KEY', 'bvsl_salary_term_common_message' );

/**
 * 給与明細投稿のメッセージ構成保存キー。
 */
define( 'BVSL_SALARY_MESSAGE_STRUCTURE_META_KEY', 'salary_message_structure' );
define( 'BVSL_SALARY_MESSAGE_STRUCTURE_MESSAGE_OR_COMMON', 'message_or_common' );
define( 'BVSL_SALARY_MESSAGE_STRUCTURE_COMMON_THEN_MESSAGE', 'common_then_message' );
define( 'BVSL_SALARY_MESSAGE_STRUCTURE_MESSAGE_THEN_COMMON', 'message_then_common' );

add_action( 'salary-term_add_form_fields', 'bvsl_salary_term_add_common_message_field' );
add_action( 'salary-term_edit_form_fields', 'bvsl_salary_term_edit_common_message_field' );
add_action( 'created_salary-term', 'bvsl_save_salary_term_common_message' );
add_action( 'edited_salary-term', 'bvsl_save_salary_term_common_message' );
add_action( 'wp_ajax_bvsl_get_salary_term_common_message', 'bvsl_ajax_get_salary_term_common_message' );

/**
 * メッセージが空白のみかを判定する。
 *
 * 半角/全角スペースや改行・タブのみの場合は空扱いにする。
 *
 * @param string $message 判定対象メッセージ。
 * @return bool
 */
function bvsl_is_blank_message( $message ) {
	$normalized = preg_replace( '/[\p{Z}\s]+/u', '', (string) $message );
	if ( null === $normalized ) {
		$normalized = trim( (string) $message );
	}

	return '' === $normalized;
}

/**
 * 支給分タームIDをキーにした共通メッセージ一覧を返す。
 *
 * @return array<string, string>
 */
function bvsl_get_salary_term_common_message_map() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'salary-term',
			'hide_empty' => false,
		)
	);

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return array();
	}

	$messages = array();
	foreach ( $terms as $term ) {
		$common_message = (string) get_term_meta( $term->term_id, BVSL_SALARY_TERM_COMMON_MESSAGE_META_KEY, true );
		if ( bvsl_is_blank_message( $common_message ) ) {
			continue;
		}
		$messages[ (string) $term->term_id ] = $common_message;
	}

	return $messages;
}

/**
 * 支給分ターム新規作成フォームに共通メッセージ入力欄を表示する。
 *
 * @return void
 */
function bvsl_salary_term_add_common_message_field() {
	?>
	<div class="form-field term-common-message-wrap">
		<label for="bvsl_salary_term_common_message"><?php esc_html_e( '共通メッセージ', 'bill-vektor-salary' ); ?></label>
		<textarea
			name="bvsl_salary_term_common_message"
			id="bvsl_salary_term_common_message"
			rows="5"
			cols="40"
		></textarea>
	</div>
	<?php
}

/**
 * 支給分ターム編集フォームに共通メッセージ入力欄を表示する。
 *
 * @param WP_Term $term 編集対象ターム。
 * @return void
 */
function bvsl_salary_term_edit_common_message_field( $term ) {
	$common_message = get_term_meta( $term->term_id, BVSL_SALARY_TERM_COMMON_MESSAGE_META_KEY, true );
	?>
	<tr class="form-field term-common-message-wrap">
		<th scope="row">
			<label for="bvsl_salary_term_common_message"><?php esc_html_e( '共通メッセージ', 'bill-vektor-salary' ); ?></label>
		</th>
		<td>
			<textarea
				name="bvsl_salary_term_common_message"
				id="bvsl_salary_term_common_message"
				rows="5"
				cols="50"
			><?php echo esc_textarea( $common_message ); ?></textarea>
		</td>
	</tr>
	<?php
}

/**
 * 支給分タームの共通メッセージを保存する。
 *
 * @param int $term_id タームID。
 * @return void
 */
function bvsl_save_salary_term_common_message( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	if ( ! isset( $_POST['bvsl_salary_term_common_message'] ) ) {
		return;
	}

	$common_message = wp_kses_post( wp_unslash( $_POST['bvsl_salary_term_common_message'] ) );
	update_term_meta( $term_id, BVSL_SALARY_TERM_COMMON_MESSAGE_META_KEY, $common_message );
}

/**
 * 給与明細のメッセージ構成値を取得する。
 *
 * @param int $post_id 投稿ID。
 * @return string
 */
function bvsl_get_salary_message_structure( $post_id ) {
	$structure = (string) get_post_meta( $post_id, BVSL_SALARY_MESSAGE_STRUCTURE_META_KEY, true );

	$allowed = array(
		BVSL_SALARY_MESSAGE_STRUCTURE_MESSAGE_OR_COMMON,
		BVSL_SALARY_MESSAGE_STRUCTURE_COMMON_THEN_MESSAGE,
		BVSL_SALARY_MESSAGE_STRUCTURE_MESSAGE_THEN_COMMON,
	);
	if ( ! in_array( $structure, $allowed, true ) ) {
		return BVSL_SALARY_MESSAGE_STRUCTURE_MESSAGE_OR_COMMON;
	}

	return $structure;
}

/**
 * 給与明細投稿に紐づく支給分から、最初に空でない共通メッセージを取得する。
 *
 * @param int $post_id 投稿ID。
 * @return string
 */
function bvsl_get_salary_term_common_message_by_post( $post_id ) {
	$terms = get_the_terms( $post_id, 'salary-term' );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return '';
	}

	foreach ( $terms as $term ) {
		$common_message = (string) get_term_meta( $term->term_id, BVSL_SALARY_TERM_COMMON_MESSAGE_META_KEY, true );
		if ( ! bvsl_is_blank_message( $common_message ) ) {
			return $common_message;
		}
	}

	return '';
}

/**
 * 指定タームID配列から、最初に空でない共通メッセージを取得する。
 *
 * @param int[] $term_ids タームID配列。
 * @return string
 */
function bvsl_get_salary_term_common_message_by_term_ids( $term_ids ) {
	if ( empty( $term_ids ) || ! is_array( $term_ids ) ) {
		return '';
	}

	foreach ( $term_ids as $term_id ) {
		$common_message = (string) get_term_meta( (int) $term_id, BVSL_SALARY_TERM_COMMON_MESSAGE_META_KEY, true );
		if ( ! bvsl_is_blank_message( $common_message ) ) {
			return $common_message;
		}
	}

	return '';
}

/**
 * 支給分チェック状態に応じた共通メッセージをAjaxで返す。
 *
 * @return void
 */
function bvsl_ajax_get_salary_term_common_message() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	check_ajax_referer( 'bvsl_salary_admin_nonce', 'nonce' );

	$term_ids = isset( $_POST['term_ids'] ) ? wp_unslash( $_POST['term_ids'] ) : array();
	if ( ! is_array( $term_ids ) ) {
		$term_ids = array();
	}
	$term_ids = array_map( 'intval', $term_ids );

	$common_message = bvsl_get_salary_term_common_message_by_term_ids( $term_ids );

	wp_send_json_success(
		array(
			'common_message' => $common_message,
		)
	);
}

/**
 * 給与明細の最終メッセージを組み立てる。
 *
 * @param int $post_id 投稿ID。
 * @return string
 */
function bvsl_build_salary_message( $post_id ) {
	$post_message   = (string) get_post_meta( $post_id, 'salary_message', true );
	$common_message = bvsl_get_salary_term_common_message_by_post( $post_id );
	$structure      = bvsl_get_salary_message_structure( $post_id );
	$message        = '';

	if ( BVSL_SALARY_MESSAGE_STRUCTURE_COMMON_THEN_MESSAGE === $structure ) {
		$parts = array_filter(
			array(
				$common_message,
				$post_message,
			),
			function ( $value ) {
				return ! bvsl_is_blank_message( $value );
			}
		);
		$message = implode( "\n", $parts );
	} elseif ( BVSL_SALARY_MESSAGE_STRUCTURE_MESSAGE_THEN_COMMON === $structure ) {
		$parts = array_filter(
			array(
				$post_message,
				$common_message,
			),
			function ( $value ) {
				return ! bvsl_is_blank_message( $value );
			}
		);
		$message = implode( "\n", $parts );
	} else {
		$message = ! bvsl_is_blank_message( $post_message ) ? $post_message : $common_message;
	}

	if ( bvsl_is_blank_message( $message ) ) {
		$message = '今月もお疲れでした';
	}

	return $message;
}
