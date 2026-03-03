<?php
/**
 * 給与明細 Web 表示テンプレート。
 *
 * ブラウザ向けレイアウト（Bootstrap グリッド）。
 * データ表示の共通パーツは template-parts/doc/parts/ 以下を使用。
 *
 * @package Bill_Vektor_Salary
 */

global $post;
?>
<div class="bill-wrap">
<div class="container">
<div class="row">

	<div class="col-xs-6">
		<h1 class="bill-title">
		<?php
		if ( $post->salary_document_name ) {
			echo esc_html( $post->salary_document_name );
		} else {
			echo '給与明細';
		}
		?>
		</h1>

		<?php if ( $post->salary_staff ) : ?>
		<h2 class="bill-destination">
			<span class="bill-destination-client">
				<?php echo esc_html( get_the_title( $post->salary_staff ) ); ?>
			</span>
			<span class="bill-destination-honorific">様</span>
		</h2>
		<?php endif; ?>

		<div class="bill-message">
		<?php
		$message = bvsl_build_salary_message( get_the_ID() );
		echo apply_filters( 'the_content', $message );
		?>
		</div>
	</div><!-- /.col-xs-6 -->

	<div class="col-xs-6">
		<?php require __DIR__ . '/parts/salary-info-table.php'; ?>
	</div><!-- /.col-xs-6 -->

</div><!-- /.row -->
</div><!-- /.container -->

<div class="container">
	<div class="row">
		<div class="col-xs-6">
			<?php require __DIR__ . '/parts/salary-body-left.php'; ?>
		</div><!-- /.col-xs-6 -->
		<div class="col-xs-6">
			<?php require __DIR__ . '/parts/salary-body-right.php'; ?>
		</div><!-- /.col-xs-6 -->
	</div><!-- /.row -->
</div><!-- /.container -->

<?php require __DIR__ . '/parts/salary-footer.php'; ?>

</div><!-- /.bill-wrap -->
