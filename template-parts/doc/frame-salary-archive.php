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
	while ( have_posts() ) :
		the_post();

		require( 'frame-salary.php' );

		$total_earn   = $total_earn + bvsl_get_total_earn();
		$total_pay    = $total_pay + bvsl_get_total_pay();
		$koyou_hoken  = $koyou_hoken + bvsl_get_koyou_hoken();
		$kenkou_hoken = $kenkou_hoken + bvsl_format_number( $post->salary_kenkou );
		$nenkin       = $nenkin + bvsl_format_number( $post->salary_nenkin );
		$kazeisyotoku = $kazeisyotoku + bvsl_get_kazeisyotoku();
		$syotokuzei   = $syotokuzei + bvsl_format_number( $post->salary_syotokuzei );
		$jyuuminzei   = $jyuuminzei + bvsl_format_number( $post->salary_jyuuminzei );
		$koujyo       = $koujyo + bvsl_get_koujyo_total();
		$sasihiki     = $sasihiki + bvsl_get_total_pay() - bvsl_get_koujyo_total();
	endwhile;
}
?>

<div class="bill-wrap">
<div class="container">
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
