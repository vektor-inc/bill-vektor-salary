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

add_action( 'salary-term_add_form_fields', 'bvsl_salary_term_add_common_message_field' );
add_action( 'salary-term_edit_form_fields', 'bvsl_salary_term_edit_common_message_field' );
add_action( 'created_salary-term', 'bvsl_save_salary_term_common_message' );
add_action( 'edited_salary-term', 'bvsl_save_salary_term_common_message' );
add_action( 'wp_ajax_bvsl_get_salary_term_common_message', 'bvsl_ajax_get_salary_term_common_message' );

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
 * @return string `1` / `2` / `3`
 */
function bvsl_get_salary_message_structure( $post_id ) {
	$structure = (string) get_post_meta( $post_id, BVSL_SALARY_MESSAGE_STRUCTURE_META_KEY, true );

	if ( ! in_array( $structure, array( '1', '2', '3' ), true ) ) {
		return '1';
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
		$common_message = trim( $common_message );

		if ( '' !== $common_message ) {
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
		$common_message = trim( $common_message );

		if ( '' !== $common_message ) {
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
	$post_message   = trim( (string) get_post_meta( $post_id, 'salary_message', true ) );
	$common_message = bvsl_get_salary_term_common_message_by_post( $post_id );
	$structure      = bvsl_get_salary_message_structure( $post_id );
	$message        = '';

	if ( '2' === $structure ) {
		$parts = array_filter(
			array(
				$common_message,
				$post_message,
			),
			'strlen'
		);
		$message = implode( "\n", $parts );
	} elseif ( '3' === $structure ) {
		$parts = array_filter(
			array(
				$post_message,
				$common_message,
			),
			'strlen'
		);
		$message = implode( "\n", $parts );
	} else {
		$message = '' !== $post_message ? $post_message : $common_message;
	}

	if ( '' === trim( $message ) ) {
		$message = '今月もお疲れでした';
	}

	return $message;
}

