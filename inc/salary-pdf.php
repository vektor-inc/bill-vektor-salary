<?php
/**
 * 給与明細PDF発行・管理機能。
 *
 * @package Bill_Vektor_Salary
 */

/**
 * PDF履歴保存用メタキー。
 */
define( 'BVSL_PDF_HISTORY_META_KEY', 'bvsl_pdf_history' );

/**
 * PDF保存サブディレクトリ名。
 */
define( 'BVSL_PDF_SUBDIR', 'salary-pdf' );

add_action( 'wp_ajax_bvsl_generate_salary_pdf', 'bvsl_ajax_generate_salary_pdf' );
add_action( 'wp_ajax_bvsl_delete_salary_pdf', 'bvsl_ajax_delete_salary_pdf' );

/**
 * 給与明細PDFで使う日本語ゴシックフォントの候補パスを返す。
 *
 * @return string フォントファイルの絶対パス。未検出時は空文字。
 */
function bvsl_get_salary_pdf_sans_font_path() {
	$font_path = apply_filters( 'bvsl_salary_pdf_font_path', '' );
	if ( is_string( $font_path ) && '' !== $font_path && file_exists( $font_path ) && is_readable( $font_path ) ) {
		return $font_path;
	}

	// TTC は設定が煩雑になるため除外し、TTF/OTF を優先する。
	$candidates = array(
		'/usr/share/fonts/opentype/ipaexfont-gothic/ipaexg.ttf',
		'/usr/share/fonts/opentype/ipafont-gothic/ipag.ttf',
		'/usr/share/fonts/opentype/ipafont-gothic/ipagp.ttf',
		'/usr/share/fonts/truetype/ipafont-gothic/ipag.ttf',
		'/usr/share/fonts/truetype/fonts-japanese-gothic.ttf',
		'/usr/share/fonts/truetype/vlgothic/VL-Gothic-Regular.ttf',
		'/usr/share/fonts/truetype/noto/NotoSansJP-Regular.otf',
		'/usr/share/fonts/opentype/noto/NotoSansJP-Regular.otf',
		'/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.otf',
		'/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.otf',
		'/System/Library/Fonts/Supplemental/Arial Unicode.ttf',
		'/Library/Fonts/Arial Unicode.ttf',
	);

	foreach ( $candidates as $candidate ) {
		if ( file_exists( $candidate ) && is_readable( $candidate ) ) {
			return $candidate;
		}
	}

	return '';
}

/**
 * PDF発行 Ajax ハンドラ。
 *
 * @return void
 */
function bvsl_ajax_generate_salary_pdf() {
	check_ajax_referer( 'bvsl_generate_salary_pdf_nonce', 'nonce' );

	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
	}

	$result = bvsl_generate_salary_pdf( $post_id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( $result );
}

/**
 * PDF削除 Ajax ハンドラ。
 *
 * @return void
 */
function bvsl_ajax_delete_salary_pdf() {
	check_ajax_referer( 'bvsl_generate_salary_pdf_nonce', 'nonce' );

	$post_id       = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	$attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;

	if ( ! $post_id || ! $attachment_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
	}

	$result = bvsl_delete_salary_pdf_record( $post_id, $attachment_id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( array( 'deleted' => true ) );
}

/**
 * 給与明細PDFを生成してメディアライブラリに保存する。
 *
 * @param int $post_id 投稿ID。
 * @return array|WP_Error 成功時は { pdf_url, attachment_id, filename, issued_at } の連想配列。
 */
function bvsl_generate_salary_pdf( $post_id ) {
	if ( ! class_exists( 'Mpdf\Mpdf' ) ) {
		$autoload = plugin_dir_path( __DIR__ ) . 'vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		} else {
			return new WP_Error( 'mpdf_not_found', 'mPDF ライブラリが見つかりません。' );
		}
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'invalid_post', '投稿が見つかりません。' );
	}

	// HTML キャプチャ。
	$html = bvsl_render_salary_html_for_pdf( $post );

	// アップロードディレクトリ準備。
	$upload_dir = wp_upload_dir();
	$pdf_dir    = $upload_dir['basedir'] . '/' . BVSL_PDF_SUBDIR;

	if ( ! file_exists( $pdf_dir ) ) {
		wp_mkdir_p( $pdf_dir );
	}

	$issued_at = current_time( 'Y-m-d H:i:s' );
	$timestamp = current_time( 'YmdHis' );
	$filename  = 'salary-' . $post_id . '-' . $timestamp . '.pdf';
	$pdf_path  = $pdf_dir . '/' . $filename;
	$pdf_url   = $upload_dir['baseurl'] . '/' . BVSL_PDF_SUBDIR . '/' . $filename;

	// mPDF で PDF 生成。
	try {
		$config = array(
			'mode'             => 'utf-8',
			'format'           => 'A4',
			'orientation'      => 'P',
			'margin_top'       => 15,
			'margin_right'     => 10,
			'margin_bottom'    => 15,
			'margin_left'      => 10,
			'tempDir'          => sys_get_temp_dir(),
			'autoScriptToLang' => true,
			'autoLangToFont'   => true,
			// フォールバック（ゴシック未検出時）は従来設定。
			'default_font'     => 'sjis',
			'fonttrans'        => array(
				'kozminproregular' => 'sjis',
				'sun-exta'         => 'sjis',
				'kozgopromedium'   => 'sjis',
			),
		);

		// 利用可能な日本語ゴシックフォント（TTF/OTF）があれば、それを優先して使う。
		$sans_font_path = bvsl_get_salary_pdf_sans_font_path();
		if ( '' !== $sans_font_path ) {
			$font_dir  = dirname( $sans_font_path );
			$font_file = basename( $sans_font_path );

			$default_font_config = new \Mpdf\Config\FontVariables();
			$font_data           = $default_font_config->getDefaults()['fontdata'];
			$font_dirs           = $default_font_config->getDefaults()['fontDir'];

			$config['fontDir']  = array_merge( $font_dirs, array( $font_dir ) );
			$config['fontdata'] = $font_data + array(
				'bvsljapansans' => array(
					'R' => $font_file,
				),
			);
			$config['default_font']   = 'bvsljapansans';
			$config['autoLangToFont'] = false;
			$config['fonttrans']      = array(
				'kozminproregular' => 'bvsljapansans',
				'sun-exta'         => 'bvsljapansans',
				'kozgopromedium'   => 'bvsljapansans',
				'sjis'             => 'bvsljapansans',
			);
		}

		$mpdf = new \Mpdf\Mpdf( $config );
		$mpdf->WriteHTML( $html );
		$mpdf->Output( $pdf_path, \Mpdf\Output\Destination::FILE );
	} catch ( \Exception $e ) {
		return new WP_Error( 'mpdf_error', 'PDF生成に失敗しました: ' . $e->getMessage() );
	}

	if ( ! file_exists( $pdf_path ) ) {
		return new WP_Error( 'pdf_save_error', 'PDFファイルの保存に失敗しました。' );
	}

	// メディアライブラリに登録。
	$attachment = array(
		'post_mime_type' => 'application/pdf',
		'post_title'     => $filename,
		'post_content'   => '',
		'post_status'    => 'inherit',
		'post_parent'    => $post_id,
	);

	$attachment_id = wp_insert_attachment( $attachment, $pdf_path, $post_id );
	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$attach_data = wp_generate_attachment_metadata( $attachment_id, $pdf_path );
	wp_update_attachment_metadata( $attachment_id, $attach_data );

	// 履歴に追加（新しいものを先頭へ）。
	$record = array(
		'attachment_id' => $attachment_id,
		'filename'      => $filename,
		'pdf_url'       => $pdf_url,
		'issued_at'     => $issued_at,
	);

	$history = get_post_meta( $post_id, BVSL_PDF_HISTORY_META_KEY, true );
	if ( ! is_array( $history ) ) {
		$history = array();
	}
	array_unshift( $history, $record );
	update_post_meta( $post_id, BVSL_PDF_HISTORY_META_KEY, $history );

	return array(
		'pdf_url'       => $pdf_url,
		'attachment_id' => $attachment_id,
		'filename'      => $filename,
		'issued_at'     => $issued_at,
	);
}

/**
 * PDF履歴から指定のattachment_idを削除し、メディアライブラリからもファイルを消す。
 *
 * @param int $post_id       投稿ID。
 * @param int $attachment_id 削除対象のattachment ID。
 * @return true|WP_Error
 */
function bvsl_delete_salary_pdf_record( $post_id, $attachment_id ) {
	// メディアライブラリから削除（ファイルも含む）。
	$deleted = wp_delete_attachment( $attachment_id, true );
	if ( false === $deleted || null === $deleted ) {
		return new WP_Error( 'delete_error', 'PDFファイルの削除に失敗しました。' );
	}

	// 履歴から該当レコードを除去。
	$history = get_post_meta( $post_id, BVSL_PDF_HISTORY_META_KEY, true );
	if ( is_array( $history ) ) {
		$history = array_values(
			array_filter(
				$history,
				function ( $record ) use ( $attachment_id ) {
					return (int) $record['attachment_id'] !== $attachment_id;
				}
			)
		);
		update_post_meta( $post_id, BVSL_PDF_HISTORY_META_KEY, $history );
	}

	return true;
}

/**
 * PDF生成用のHTMLを組み立てて返す。
 *
 * @param WP_Post $post_to_render 投稿オブジェクト。
 * @return string HTML文字列。
 */
function bvsl_render_salary_html_for_pdf( $post_to_render ) {
	// グローバル $post を退避し、対象投稿に差し替えてからテンプレートをレンダリングする。
	// 引数名を $post_to_render にしているのは、global $post; 宣言と衝突しないようにするため。
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	global $post;
	$original_post = $post;

	// グローバルに対象投稿をセット。
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$post = $post_to_render;
	setup_postdata( $post );

	ob_start();
	require plugin_dir_path( __DIR__ ) . 'template-parts/doc/frame-salary-pdf.php';
	$html = ob_get_clean();

	wp_reset_postdata();
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$post = $original_post;

	return $html;
}


/**
 * 給与明細PDF管理テーブルのHTMLを出力する。
 *
 * @param int $post_id 投稿ID。
 * @return void
 */
function bvsl_render_pdf_history_table( $post_id ) {
	$history = get_post_meta( $post_id, BVSL_PDF_HISTORY_META_KEY, true );
	if ( empty( $history ) || ! is_array( $history ) ) {
		return;
	}
	?>
	<div id="bvsl-pdf-history-wrap" style="margin-top: 16px;">
		<h4 style="margin-bottom:6px;"><?php esc_html_e( '発行済みPDF履歴', 'bill-vektor-salary' ); ?></h4>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( '発行日時', 'bill-vektor-salary' ); ?></th>
					<th><?php esc_html_e( 'ファイル名', 'bill-vektor-salary' ); ?></th>
					<th><?php esc_html_e( '操作', 'bill-vektor-salary' ); ?></th>
				</tr>
			</thead>
			<tbody id="bvsl-pdf-history-tbody">
			<?php foreach ( $history as $record ) : ?>
				<?php
				$attachment_id = (int) ( $record['attachment_id'] ?? 0 );
				$filename      = esc_html( $record['filename'] ?? '' );
				$pdf_url       = esc_url( $record['pdf_url'] ?? '' );
				$issued_at     = esc_html(
					isset( $record['issued_at'] )
						? date_i18n( 'Y/m/d H:i', strtotime( $record['issued_at'] ) )
						: ''
				);
				?>
				<tr data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>">
					<td><?php echo $issued_at; ?></td>
					<td><?php echo $filename; ?></td>
					<td>
						<button type="button"
							class="button button-small bvsl-pdf-delete-btn"
							data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>"
						><?php esc_html_e( '削除', 'bill-vektor-salary' ); ?></button>
						<?php if ( $pdf_url ) : ?>
						<a href="<?php echo $pdf_url; ?>"
							target="_blank"
							class="button button-small"
							style="margin-left:4px;"
						><?php esc_html_e( 'プレビュー', 'bill-vektor-salary' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
