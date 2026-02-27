<?php
/**
 * 給与明細 - 備考・フッター（会社名・ロゴ）。
 *
 * frame-salary.php / frame-salary-pdf.php 共通パーツ。
 * PDF用テンプレートから呼ぶ場合は $bvsl_pdf_mode = true を事前にセットすること。
 * PDF モード時はロゴ画像をファイルパスで読み込む（mPDF は URL 参照不可のため）。
 *
 * @package Bill_Vektor_Salary
 */

global $post;
?>
<?php if ( $post->salary_remarks ) : ?>
<dl class="bill-remarks">
	<dt>備考</dt>
	<dd>
		<?php echo apply_filters( 'the_content', $post->salary_remarks ); ?>
	</dd>
</dl>
<?php endif; ?>

<div class="bill-footer">
<?php
$options = get_option( 'bill-setting', Bill_Admin::options_default() );

if ( isset( $options['own-logo'] ) && $options['own-logo'] ) {
	if ( ! empty( $bvsl_pdf_mode ) ) {
		// PDF モード：mPDF はローカルファイルパスが必要。
		$logo_path = get_attached_file( $options['own-logo'] );
		if ( $logo_path && file_exists( $logo_path ) ) {
			echo '<img src="' . esc_attr( $logo_path ) . '" class="bill-footer-logo" alt="">';
		}
	} else {
		// Web 表示モード：WordPress 標準の attachment 画像タグを使用。
		$attr = array(
			'id'    => '',
			'class' => 'bill-footer-logo',
			'alt'   => trim( strip_tags( get_post_meta( $options['own-logo'], '_wp_attachment_image_alt', true ) ) ),
		);
		echo wp_get_attachment_image( $options['own-logo'], 'medium', false, $attr );
	}
} else {
	echo '<h4>' . esc_html( $options['own-name'] ) . '</h4>';
}
?>
</div>
