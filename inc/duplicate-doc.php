<?php
/*
  編集画面 _ 領収書発行のボタン追加
/*-------------------------------------------*/
add_action( 'post_submitbox_start', 'bvsl_duplicate_salary' );
function bvsl_duplicate_salary() {
	global $post;
	$links = admin_url() . 'post-new.php?master_id=' . $post->ID;
	if ( get_post_type() == 'salary' ) {
	?>

	<div class="duplicate-section">

	<a href="<?php echo esc_url( $links ) . '&post_type=salary&table_copy_type=all&duplicate_type=full'; ?>" class="button button-default button-block">この書類を複製</a>

	</div><!-- [ / #duplicate-section ] -->
	<?php } ?>
	<?php
}
