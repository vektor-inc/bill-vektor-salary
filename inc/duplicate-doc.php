<?php
/*
  編集画面 _ 領収書発行のボタン追加
/*-------------------------------------------*/
add_action( 'post_submitbox_start', 'bvsl_duplicate_salary' );
/**
 * 給与明細の編集画面に複製リンクを出力する。
 *
 * @return void
 */
function bvsl_duplicate_salary() {
	global $post;
	// テーマ側の複製処理での検証を通過できるよう、元の投稿に対応する nonce を付ける。
	$links = wp_nonce_url(
		add_query_arg(
			array(
				'master_id'       => $post->ID,
				'post_type'       => 'salary',
				'table_copy_type' => 'all',
				'duplicate_type'  => 'full',
			),
			admin_url( 'post-new.php' )
		),
		'bill_copy_' . $post->ID
	);
	if ( get_post_type() == 'salary' ) {
	?>

	<div class="duplicate-section">

	<a href="<?php echo esc_url( $links ); ?>" class="button button-default button-block">この書類を複製</a>

	</div><!-- [ / #duplicate-section ] -->
	<?php } ?>
	<?php
}
