<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
global $wp_query;

if ( $wp_query->have_posts() ) {

	$total_earn   = 0;
	$total_pay    = 0;
	$koyou_hoken  = 0;
	$kenkou_hoken = 0;
	$nenkin       = 0;
	$kazeisyotoku = 0;
	$syotokuzei   = 0;
	$jyuuminzei   = 0;
	$koujyo       = 0;
	$sasihiki     = 0;

	while ( have_posts() ) :
		the_post();

		require 'frame-salary.php';
		$id    = '';
		$class = '';
		echo edit_post_link( '▲ 編集', '<div class="container"><div class="no-print text-right">[ ', ' ]</div></div>', $id, $class );

		$total_earn   = $total_earn + bvsl_get_total_earn();
		$total_pay    = $total_pay + bvsl_get_total_pay();
		$koyou_hoken  = $koyou_hoken + bvsl_get_koyou_hoken();
		$kenkou_hoken = $kenkou_hoken + bvsl_format_number( $post->salary_kenkou );
		$nenkin       = $nenkin + bvsl_format_number( $post->salary_nenkin );
		$kazeisyotoku = $kazeisyotoku + bvsl_get_kazeisyotoku();
		$syotokuzei   = $syotokuzei + bvsl_format_number( $post->salary_syotokuzei );
		$jyuuminzei   = $jyuuminzei + bvsl_format_number( $post->salary_jyuuminzei );
		$koujyo       = $koujyo + bvsl_get_koujyo_total();
		// ↓ここが1円ずれる事がある？
		$sasihiki = $sasihiki + bvsl_get_total_pay() - bvsl_get_koujyo_total();
	endwhile;
}
?>
<?php
/*
  合計
/*-------------------------------------------*/
?>
<div class="bill-wrap">
<div class="container">
<h1 class="bill-title">合計金額</h1>
<div class="row">
<div class="col-xs-6">
	<table class="table table-bordered table-striped table-bill">
		<tr>
			<th>支給額</th>
			<td class="text-right"><?php echo bvsl_format_print( $total_earn ); ?></td>
		</tr>
		<tr>
			<th>総支給額（交通費込）</th>
			<td class="text-right"><?php echo bvsl_format_print( $total_pay ); ?></td>
		</tr>
	</table>
</div>
<div class="col-xs-6">
	<table class="table table-bordered table-striped table-bill">
		<tr>
			<th>雇用保険合計</th>
			<td class="text-right"><?php echo bvsl_format_print( $koyou_hoken ); ?></td>
		</tr>
		<tr>
			<th>健康保険合計</th>
			<td class="text-right"><?php echo bvsl_format_print( $kenkou_hoken ); ?></td>
		</tr>
		<tr>
			<th>厚生年金合計</th>
			<td class="text-right"><?php echo bvsl_format_print( $nenkin ); ?></td>
		</tr>
		<tr>
			<th>課税対象合計</th>
			<td class="text-right"><?php echo bvsl_format_print( $kazeisyotoku ); ?></td>
		</tr>
		<tr>
			<th>所得税合計</th>
			<td class="text-right"><?php echo bvsl_format_print( $syotokuzei ); ?></td>
		</tr>
		<tr>
			<th>住民税合計</th>
			<td class="text-right"><?php echo bvsl_format_print( $jyuuminzei ); ?></td>
		</tr>
		<tr>
			<th>控除合計</th>
			<td class="text-right"><?php echo bvsl_format_print( $koujyo ); ?></td>
		</tr>
	</table>
<table class="table table-bordered table-striped table-bill">
	<tr>
		<th>差引支給合計</th>
		<td class="text-right"><?php echo bvsl_format_print( $sasihiki ); ?></td>
	</tr>
</table>
</div>
</div><!-- [ /.row ] -->
</div><!-- [ /.container ] -->
</div><!-- [ /.bill-wrap ] -->

<?php
/*
  振込情報
/*-------------------------------------------*/
?>
<div class="bill-wrap">
<div class="container">
<h1 class="bill-title">振込情報</h1>
<?php
if ( $wp_query->have_posts() ) {
	?>
	<table class="table table-bordered table-striped table-bill">
		<thead>
			<tr>
				<th>氏名</th>
				<th>振込金額</th>
				<th>振込銀行名</th>
				<th>支店名</th>
				<th>口座種類</th>
				<th>口座番号</th>
				<th class="no-print"></th>
			</tr>
		</thead>
	<?php
	while ( have_posts() ) :

		the_post();
			echo '<tr>';
			echo '<td>' . esc_html( get_the_title( $post->salary_staff ) ) . '</td>';
			echo '<td class="text-right">' . bvsl_format_print( bvsl_get_total_pay() - bvsl_get_koujyo_total() ) . '</td>';
			echo '<td>' . esc_html( get_post_meta( $post->salary_staff, 'salary_transfer_account_bank', true ) ) . '</td>';
			echo '<td>' . esc_html( get_post_meta( $post->salary_staff, 'salary_transfer_account_branch', true ) ) . '</td>';
			echo '<td>' . esc_html( get_post_meta( $post->salary_staff, 'salary_transfer_account_type', true ) ) . '</td>';
			echo '<td>' . esc_html( get_post_meta( $post->salary_staff, 'salary_transfer_account_number', true ) ) . '</td>';
			echo '<td class="no-print text-center">';
			echo edit_post_link( '編集', '', '' );
			echo '</td>';
		echo '</tr>';

		endwhile;
		echo '</table>';
}
?>
</div><!-- [ /.container ] -->
</div><!-- [ /.bill-wrap ] -->

<div class="bill-wrap">
<div class="container">
<h1 class="bill-title">振込情報</h1>
<?php
if ( $wp_query->have_posts() ) {
	while ( have_posts() ) :
		the_post();
			echo '[ ' . esc_html( get_the_title( $post->salary_staff ) ) . ' ] <br>';
			echo bvsl_format_print( bvsl_get_total_pay() - bvsl_get_koujyo_total() ) . '<br />';
			echo esc_html( get_post_meta( $post->salary_staff, 'salary_transfer_account_bank', true ) ) .  ' ' . esc_html( get_post_meta( $post->salary_staff, 'salary_transfer_account_branch', true ) ) . ' ' . esc_html( get_post_meta( $post->salary_staff, 'salary_transfer_account_type', true ) ) . ' ' . esc_html( get_post_meta( $post->salary_staff, 'salary_transfer_account_number', true ) ) . '<br />';
		echo '<br/>';
	endwhile;
}
?>
</div><!-- [ /.container ] -->
</div><!-- [ /.bill-wrap ] -->


<div class="bill-no-print">
<div class="container">
<p>このエリアは印刷されません。</p>
<div class="row">
<?php get_template_part( 'template-parts/breadcrumb' ); ?>
</div>
</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
