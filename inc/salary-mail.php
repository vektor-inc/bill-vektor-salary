<?php
/**
 * 給与明細メール送信機能。
 *
 * @package Bill_Vektor_Salary
 */

/**
 * メール履歴保存用メタキー。
 */
define( 'BVSL_MAIL_HISTORY_META_KEY', 'bvsl_mail_history' );

/**
 * 直近送信ステータスメタキー。
 */
define( 'BVSL_LAST_MAIL_STATUS_META_KEY', 'bvsl_last_mail_status' );

/**
 * 直近送信日時メタキー。
 */
define( 'BVSL_LAST_MAIL_SENT_AT_META_KEY', 'bvsl_last_mail_sent_at' );

add_action( 'wp_ajax_bvsl_preview_salary_mail', 'bvsl_ajax_preview_salary_mail' );
add_action( 'wp_ajax_bvsl_send_salary_mail', 'bvsl_ajax_send_salary_mail' );

/**
 * 給与明細に紐づくスタッフ投稿IDを返す。
 *
 * @param int $post_id 給与明細投稿ID。
 * @return int スタッフ投稿ID。未設定時は0。
 */
function bvsl_get_salary_staff_post_id( $post_id ) {
	return (int) get_post_meta( $post_id, 'salary_staff', true );
}

/**
 * 給与明細に紐づくスタッフ名を返す。
 *
 * @param int $post_id 給与明細投稿ID。
 * @return string スタッフ名。
 */
function bvsl_get_salary_staff_name( $post_id ) {
	$staff_post_id = bvsl_get_salary_staff_post_id( $post_id );
	if ( ! $staff_post_id ) {
		return '';
	}

	return (string) get_the_title( $staff_post_id );
}

/**
 * 給与明細に紐づくスタッフのメールアドレスを返す。
 *
 * @param int $post_id 給与明細投稿ID。
 * @return string メールアドレス。未設定/不正時は空文字。
 */
function bvsl_get_salary_staff_email( $post_id ) {
	$staff_post_id = bvsl_get_salary_staff_post_id( $post_id );
	if ( ! $staff_post_id ) {
		return '';
	}

	$email = sanitize_email( (string) get_post_meta( $staff_post_id, 'salary_staff_email', true ) );
	if ( ! is_email( $email ) ) {
		return '';
	}

	return $email;
}

/**
 * 給与明細の支給分ターム名を返す。
 *
 * @param int $post_id 給与明細投稿ID。
 * @return string 支給分ターム名。未設定時は空文字。
 */
function bvsl_get_salary_term_name( $post_id ) {
	$terms = get_the_terms( $post_id, 'salary-term' );
	if ( is_wp_error( $terms ) || empty( $terms ) || ! is_array( $terms ) ) {
		return '';
	}

	$first_term = reset( $terms );
	if ( ! $first_term || empty( $first_term->name ) ) {
		return '';
	}

	return (string) $first_term->name;
}

/**
 * 給与明細メール件名を組み立てる。
 *
 * @param int $post_id 給与明細投稿ID。
 * @return string 件名。
 */
function bvsl_build_salary_mail_subject( $post_id ) {
	$parts = array( '【給与明細】' );

	$term_name = trim( bvsl_get_salary_term_name( $post_id ) );
	if ( '' !== $term_name ) {
		$parts[] = $term_name;
	}

	$staff_name = trim( bvsl_get_salary_staff_name( $post_id ) );
	if ( '' !== $staff_name ) {
		$parts[] = $staff_name . ' 様';
	}

	return implode( ' ', $parts );
}

/**
 * 給与明細メール本文を組み立てる。
 *
 * @param int $post_id 給与明細投稿ID。
 * @return string 本文。
 */
function bvsl_build_salary_mail_body( $post_id ) {
	$lines      = array();
	$staff_name = trim( bvsl_get_salary_staff_name( $post_id ) );
	$message    = (string) bvsl_build_salary_message( $post_id );

	if ( '' !== $staff_name ) {
		$lines[] = $staff_name . ' 様';
		$lines[] = '';
	}

	$lines[] = $message;

	return implode( "\n", $lines );
}

/**
 * 添付IDが給与明細のPDF履歴に含まれるかを判定する。
 *
 * @param int $post_id       給与明細投稿ID。
 * @param int $attachment_id 添付ID。
 * @return bool 含まれる場合はtrue。
 */
function bvsl_is_salary_pdf_history_attachment( $post_id, $attachment_id ) {
	$history = get_post_meta( $post_id, BVSL_PDF_HISTORY_META_KEY, true );
	if ( empty( $history ) || ! is_array( $history ) ) {
		return false;
	}

	foreach ( $history as $record ) {
		$history_attachment_id = isset( $record['attachment_id'] ) ? (int) $record['attachment_id'] : 0;
		if ( $history_attachment_id === (int) $attachment_id ) {
			return true;
		}
	}

	return false;
}

/**
 * 指定添付IDがメール送信に利用可能な給与PDFかを判定する。
 *
 * 判定条件:
 * - attachment 投稿であること
 * - MIMEタイプが application/pdf であること
 * - ファイルが実在すること
 * - 対象給与明細との関連があること（post_parent 一致 or PDF履歴に存在）
 *
 * @param int $post_id       給与明細投稿ID。
 * @param int $attachment_id 添付ID。
 * @return bool 利用可能な場合はtrue。
 */
function bvsl_is_valid_salary_mail_attachment( $post_id, $attachment_id ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id <= 0 ) {
		return false;
	}

	$attachment_post = get_post( $attachment_id );
	if ( ! $attachment_post || 'attachment' !== $attachment_post->post_type ) {
		return false;
	}

	if ( 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
		return false;
	}

	$file_path = get_attached_file( $attachment_id );
	if ( ! $file_path || ! file_exists( $file_path ) ) {
		return false;
	}

	$has_relation = ( (int) $attachment_post->post_parent === (int) $post_id ) || bvsl_is_salary_pdf_history_attachment( $post_id, $attachment_id );
	if ( ! $has_relation ) {
		return false;
	}

	return true;
}

/**
 * PDF履歴から最新の有効なattachment_idを返す。
 *
 * @param int $post_id 給与明細投稿ID。
 * @return int attachment_id。見つからない場合は0。
 */
function bvsl_get_latest_valid_salary_pdf_attachment_id( $post_id ) {
	$history = get_post_meta( $post_id, BVSL_PDF_HISTORY_META_KEY, true );
	if ( empty( $history ) || ! is_array( $history ) ) {
		return 0;
	}

	foreach ( $history as $record ) {
		$attachment_id = isset( $record['attachment_id'] ) ? (int) $record['attachment_id'] : 0;
		if ( ! $attachment_id ) {
			continue;
		}

		if ( bvsl_is_valid_salary_mail_attachment( $post_id, $attachment_id ) ) {
			return $attachment_id;
		}
	}

	return 0;
}

/**
 * 添付PDFのattachment_idを解決する。
 * 指定がない、または指定PDFが無効な場合は最新PDFを探し、それも無ければ自動発行する。
 *
 * @param int $post_id       給与明細投稿ID。
 * @param int $attachment_id 指定attachment_id。
 * @return int|WP_Error 有効なattachment_id。失敗時はWP_Error。
 */
function bvsl_resolve_salary_mail_attachment_id( $post_id, $attachment_id = 0 ) {
	$attachment_id = (int) $attachment_id;

	if ( $attachment_id > 0 ) {
		if ( bvsl_is_valid_salary_mail_attachment( $post_id, $attachment_id ) ) {
			return $attachment_id;
		}
	}

	$latest_attachment_id = bvsl_get_latest_valid_salary_pdf_attachment_id( $post_id );
	if ( $latest_attachment_id > 0 ) {
		return $latest_attachment_id;
	}

	if ( ! function_exists( 'bvsl_generate_salary_pdf' ) ) {
		return new WP_Error( 'pdf_generate_function_missing', 'PDF生成関数が見つかりません。' );
	}

	$pdf_result = bvsl_generate_salary_pdf( $post_id );
	if ( is_wp_error( $pdf_result ) ) {
		return $pdf_result;
	}

	$new_attachment_id = isset( $pdf_result['attachment_id'] ) ? (int) $pdf_result['attachment_id'] : 0;
	if ( ! $new_attachment_id ) {
		return new WP_Error( 'pdf_generate_failed', 'PDF自動発行に失敗しました。' );
	}

	return $new_attachment_id;
}

/**
 * メール履歴を投稿メタへ保存する。
 *
 * @param int   $post_id 給与明細投稿ID。
 * @param array $record  履歴1件分のデータ。
 * @return void
 */
function bvsl_add_salary_mail_history_record( $post_id, $record ) {
	$history = get_post_meta( $post_id, BVSL_MAIL_HISTORY_META_KEY, true );
	if ( ! is_array( $history ) ) {
		$history = array();
	}

	array_unshift( $history, $record );

	update_post_meta( $post_id, BVSL_MAIL_HISTORY_META_KEY, $history );
	update_post_meta( $post_id, BVSL_LAST_MAIL_STATUS_META_KEY, $record['status'] );
	update_post_meta( $post_id, BVSL_LAST_MAIL_SENT_AT_META_KEY, $record['sent_at'] );
}

/**
 * メール送信失敗履歴を投稿メタへ保存する。
 *
 * @param int    $post_id          給与明細投稿ID。
 * @param string $to               宛先メールアドレス。
 * @param string $subject          件名。
 * @param int    $attachment_id    添付ID。
 * @param string $attachment_name  添付ファイル名。
 * @param string $error_message    失敗理由。
 * @return void
 */
function bvsl_add_salary_mail_failure_history_record( $post_id, $to, $subject, $attachment_id, $attachment_name, $error_message ) {
	$record = array(
		'sent_at'         => current_time( 'Y-m-d H:i:s' ),
		'status'          => 'failed',
		'to'              => (string) $to,
		'subject'         => (string) $subject,
		'attachment_id'   => (int) $attachment_id,
		'attachment_name' => (string) $attachment_name,
		'error_message'   => (string) $error_message,
	);

	bvsl_add_salary_mail_history_record( $post_id, $record );
}

/**
 * 給与明細メールを送信する。
 *
 * @param int   $post_id 給与明細投稿ID。
 * @param array $args {
 *     @type string $subject       件名。未指定時は自動生成。
 *     @type int    $attachment_id 添付PDFのattachment_id。
 * }
 * @return array|WP_Error 成功時は送信結果。
 */
function bvsl_send_salary_mail( $post_id, $args = array() ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );

	if ( ! $post || 'salary' !== $post->post_type ) {
		return new WP_Error( 'invalid_post', '給与明細投稿が見つかりません。' );
	}

	$subject = isset( $args['subject'] ) ? sanitize_text_field( (string) $args['subject'] ) : '';
	if ( '' === $subject ) {
		$subject = bvsl_build_salary_mail_subject( $post_id );
	}

	$to = bvsl_get_salary_staff_email( $post_id );
	if ( '' === $to ) {
		$error = new WP_Error( 'email_not_found', 'スタッフのメールアドレスが未設定、または不正です。' );
		bvsl_add_salary_mail_failure_history_record( $post_id, '', $subject, 0, '', $error->get_error_message() );
		return $error;
	}

	$message = bvsl_build_salary_mail_body( $post_id );

	$requested_attachment_id = isset( $args['attachment_id'] ) ? (int) $args['attachment_id'] : 0;
	$attachment_id           = bvsl_resolve_salary_mail_attachment_id( $post_id, $requested_attachment_id );
	if ( is_wp_error( $attachment_id ) ) {
		bvsl_add_salary_mail_failure_history_record( $post_id, $to, $subject, $requested_attachment_id, '', $attachment_id->get_error_message() );
		return $attachment_id;
	}

	$attachment_path = get_attached_file( $attachment_id );
	if ( ! $attachment_path || ! file_exists( $attachment_path ) ) {
		$error = new WP_Error( 'attachment_not_found', '添付PDFファイルが見つかりません。' );
		bvsl_add_salary_mail_failure_history_record( $post_id, $to, $subject, $attachment_id, '', $error->get_error_message() );
		return $error;
	}

	$headers = array();
	$sent_at = current_time( 'Y-m-d H:i:s' );

	$record = array(
		'sent_at'         => $sent_at,
		'status'          => 'failed',
		'to'              => $to,
		'subject'         => $subject,
		'attachment_id'   => (int) $attachment_id,
		'attachment_name' => basename( $attachment_path ),
		'error_message'   => '',
	);

	$result = wp_mail( $to, $subject, $message, $headers, array( $attachment_path ) );
	if ( ! $result ) {
		$record['error_message'] = 'メール送信に失敗しました。';
		bvsl_add_salary_mail_history_record( $post_id, $record );
		return new WP_Error( 'mail_send_failed', 'メール送信に失敗しました。' );
	}

	$record['status'] = 'success';
	bvsl_add_salary_mail_history_record( $post_id, $record );

	return array(
		'sent'            => true,
		'sent_at'         => $sent_at,
		'to'              => $to,
		'subject'         => $subject,
		'attachment_id'   => (int) $attachment_id,
		'attachment_name' => basename( $attachment_path ),
	);
}

/**
 * メールプレビューAjaxハンドラ。
 *
 * @return void
 */
function bvsl_ajax_preview_salary_mail() {
	check_ajax_referer( 'bvsl_send_salary_mail_nonce', 'nonce' );

	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
	}

	$to = bvsl_get_salary_staff_email( $post_id );
	if ( '' === $to ) {
		wp_send_json_error( array( 'message' => 'スタッフのメールアドレスが未設定、または不正です。' ) );
	}

	$subject = bvsl_build_salary_mail_subject( $post_id );
	$body    = bvsl_build_salary_mail_body( $post_id );

	wp_send_json_success(
		array(
			'to'      => $to,
			'subject' => $subject,
			'body'    => $body,
		)
	);
}

/**
 * メール送信Ajaxハンドラ。
 *
 * @return void
 */
function bvsl_ajax_send_salary_mail() {
	check_ajax_referer( 'bvsl_send_salary_mail_nonce', 'nonce' );

	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
	}

	$args = array(
		'subject'       => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
		'attachment_id' => isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0,
	);

	$result = bvsl_send_salary_mail( $post_id, $args );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( $result );
}

/**
 * 給与明細メール送信履歴テーブルを出力する。
 *
 * @param int $post_id 投稿ID。
 * @return void
 */
function bvsl_render_mail_history_table( $post_id ) {
	$history = get_post_meta( $post_id, BVSL_MAIL_HISTORY_META_KEY, true );
	if ( empty( $history ) || ! is_array( $history ) ) {
		return;
	}
	?>
	<div id="bvsl-mail-history-wrap" style="margin-top: 16px;">
		<h4 style="margin-bottom:6px;"><?php esc_html_e( 'メール送信履歴', 'bill-vektor-salary' ); ?></h4>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( '送信日時', 'bill-vektor-salary' ); ?></th>
					<th><?php esc_html_e( '送信先', 'bill-vektor-salary' ); ?></th>
					<th><?php esc_html_e( '件名', 'bill-vektor-salary' ); ?></th>
					<th><?php esc_html_e( '添付PDF', 'bill-vektor-salary' ); ?></th>
					<th><?php esc_html_e( '結果', 'bill-vektor-salary' ); ?></th>
					<th><?php esc_html_e( '失敗理由', 'bill-vektor-salary' ); ?></th>
				</tr>
			</thead>
			<tbody id="bvsl-mail-history-tbody">
				<?php foreach ( $history as $record ) : ?>
					<?php
					$sent_at      = isset( $record['sent_at'] ) ? date_i18n( 'Y/m/d H:i', strtotime( $record['sent_at'] ) ) : '';
					$to           = isset( $record['to'] ) ? (string) $record['to'] : '';
					$subject      = isset( $record['subject'] ) ? (string) $record['subject'] : '';
					$attach_name  = isset( $record['attachment_name'] ) ? (string) $record['attachment_name'] : '';
					$status       = ( isset( $record['status'] ) && 'success' === $record['status'] ) ? '成功' : '失敗';
					$error_message = isset( $record['error_message'] ) ? (string) $record['error_message'] : '';
					?>
					<tr>
						<td><?php echo esc_html( $sent_at ); ?></td>
						<td><?php echo esc_html( $to ); ?></td>
						<td><?php echo esc_html( $subject ); ?></td>
						<td><?php echo esc_html( $attach_name ); ?></td>
						<td><?php echo esc_html( $status ); ?></td>
						<td><?php echo esc_html( $error_message ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
