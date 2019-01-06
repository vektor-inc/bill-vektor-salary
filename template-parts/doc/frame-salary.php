<?php global $post; ?>
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
<span class="bill-destination-honorific">
<?php
$client_honorific = esc_html( get_post_meta( $post->salary_staff, 'client_honorific', true ) );
if ( $client_honorific ) {
	echo $client_honorific;
} else {
	echo '様';
}
?>
</span>
</h2>
<?php endif; ?>

<div class="bill-message">
<?php
if ( $post->salary_message ) {
	$message = $post->salary_message;
} else {
	$message = '今月もお疲れ様でした。';
}

echo apply_filters( 'the_content', $message );
?>
</div>


</div><!-- [ /.col-xs-6 ] -->

<div class="col-xs-5 col-xs-offset-1">
<table class="bill-info-table">
<tr>
<th>支給分</th>
<td>
<?php
$terms = get_the_terms( get_the_ID(), 'salary-term' );
echo esc_html( $terms[0]->name );
?>
 </td>
</tr>
<tr>
<th>発行日</th>
<td><?php the_date(); ?></td>
</tr>
</table>

<div class="bill-address-own">
<?php $options = get_option( 'bill-setting', Bill_Admin::options_default() ); ?>
<h4><?php echo esc_html( $options['own-name'] ); ?></h4>
<div class="bill-address"><?php echo nl2br( esc_textarea( $options['own-address'] ) ); ?></div>
<?php
if ( isset( $options['own-seal'] ) && $options['own-seal'] ) {
	$attr = array(
		'id'    => 'bill-seal',
		'class' => 'bill-seal',
		'alt'   => trim( strip_tags( get_post_meta( $options['own-seal'], '_wp_attachment_image_alt', true ) ) ),
	);
	echo wp_get_attachment_image( $options['own-seal'], 'medium', false, $attr );
}
?>
</div><!-- [ /.address-own ] -->
</div><!-- [ /.col-xs-5 col-xs-offset-1 ] -->
</div><!-- [ /.row ] -->
</div><!-- [ /.container ] -->

<div class="container">
	<div class="row">
		<div class="col-sm-6">
			<!--
	<table class="table table-bordered table-striped table-bill">
	<tbody>
		<tr>
			<th>役職</th>
			<td><?php echo esc_html( $post->salary_role ); ?></td>
		</tr>
		<tr>
			<th>扶養人数</th>
			<td class="text-right"><?php echo esc_html( $post->salary_fuyou ); ?></td>
		</tr>
	</tbody>
	</table>
-->
	<table class="table table-bordered table-striped table-bill">
	<thead>
		<th colspan="2">支給額</th>
	</thead>
	<tbody>
		<tr>
			<th>基本給</th>
			<td class="text-right"><?php echo bvsl_format_print( $post->salary_base ); ?></td>
		</tr>
		<tr>
			<th>時間外賃金</th>
			<td class="text-right"><?php echo bvsl_format_print( $post->salary_overtime_total ); ?></td>
		</tr>
		<tr>
			<th>パート賃金</th>
			<td class="text-right"><?php echo bvsl_format_print( $post->salary_part_total ); ?></td>
		</tr>
		<tr>
			<th>休日出勤賃金</th>
			<td class="text-right"><?php echo bvsl_format_print( $post->salary_holiday_total ); ?></td>
		</tr>
		<tr>
			<th>交通費</th>
			<td class="text-right"><?php echo bvsl_format_print( $post->salary_transportation_total ); ?></td>
		</tr>

<?php
$custom_fields_array = Salary_Table_Custom_Fields::custom_fields_kazei_array();
echo VK_Custom_Field_Builder_Flexible_Table::get_view_table_body( $custom_fields_array );

$custom_fields_array = Salary_Table_Custom_Fields::custom_fields_hikazei_array();
echo VK_Custom_Field_Builder_Flexible_Table::get_view_table_body( $custom_fields_array );
?>

		</tbody>
		<tfoot>
		<tr>
			<th>支給合計</th>
			<td class="text-right"><?php echo bvsl_format_print( bvsl_get_total_pay() ); ?></td>
		</tr>
	</tfoot>
	</table>
</div><!-- [ /.col-sm-6 ] -->

<?php
/*
  右列
/*-------------------------------------------*/
?>
<div class="col-sm-6">
<?php
// include( 'test-display.php' );
?>
<table class="table table-bordered table-striped table-bill">
	<thead>
		<th colspan="2">控除額</th>
	</thead>
<tbody>
	<tr>
		<th>雇用保険</th>
		<td class="text-right"><?php echo bvsl_format_print( bvsl_get_koyou_hoken() ); ?></td>
	</tr>
	<tr>
		<th>健康保険</th>
		<td class="text-right"><?php echo bvsl_format_print( $post->salary_kenkou ); ?></td>
	</tr>
	<tr>
		<th>厚生年金</th>
		<td class="text-right"><?php echo bvsl_format_print( $post->salary_nenkin ); ?></td>
	</tr>

	<tr>
		<th><b>課税対象額<b></th>
		<td class="text-right"><b><?php echo bvsl_format_print( bvsl_get_kazeisyotoku() ); ?></b></td>
	</tr>
	<tr>
		<th>所得税</th>
		<td class="text-right"><?php echo bvsl_format_print( $post->salary_syotokuzei ); ?></td>
	</tr>
	<tr>
		<th>住民税</th>
		<td class="text-right"><?php echo bvsl_format_print( $post->salary_jyuuminzei ); ?></td>
	</tr>
	<tfoot>
	<tr>
		<th>控除合計</th>
		<td class="text-right"><?php echo bvsl_format_print( bvsl_get_koujyo_total() ); ?></td>
	</tr>
	</tfoot>
</tbody>
</table>

<table class="table table-bordered table-striped table-bill">
<tbody>
	<tr>
		<th><b>差引支給額</b></th>
		<td class="text-right"><b><?php echo bvsl_format_print( bvsl_get_total_pay() - bvsl_get_koujyo_total() ); ?></b></td>
	</tr>
</tbody>
</table>

</div><!-- [ /.col-sm-6 ] -->
</div>
<?php if ( $post->salary_remarks ) : ?>
<dl class="bill-remarks">
<dt>備考</dt>
<dd>
<?php echo apply_filters( 'the_content', $post->salary_remarks ); ?>
</dd>
</dl>
<?php endif; ?>
</div><!-- [ /.container ] -->
</div><!-- [ /.bill-wrap ] -->
