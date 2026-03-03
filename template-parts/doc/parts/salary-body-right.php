<?php
/**
 * 給与明細 - 右列（所得税・控除額・非課税・差引支給）テーブル。
 *
 * frame-salary.php / frame-salary-pdf.php 共通パーツ。
 *
 * @package Bill_Vektor_Salary
 */

global $post;
?>
<table class="table table-bordered table-bill">
<thead>
	<tr><th colspan="2">所得税</th></tr>
</thead>
<tbody>
	<tr>
		<th>課税対象額（ 課税支給合計 - 社会保険料合計 ）</th>
		<td class="price"><b><?php echo bvsl_format_print( bvsl_get_kazeisyotoku() ); ?></b></td>
	</tr>
	<tr>
		<th><b>所得税</b></th>
		<td class="price"><b><?php echo bvsl_format_print( $post->salary_syotokuzei ); ?></b></td>
	</tr>
</tbody>
</table>

<table class="table table-bordered table-bill">
<thead>
	<tr><th colspan="2">控除額</th></tr>
</thead>
<tbody>
	<tr>
		<th>社会保険料合計</th>
		<td class="price"><?php echo bvsl_format_print( bvsl_get_shakai_hoken_total() ); ?></td>
	</tr>
	<tr>
		<th>所得税</th>
		<td class="price"><?php echo bvsl_format_print( $post->salary_syotokuzei ); ?></td>
	</tr>
	<tr>
		<th>住民税</th>
		<td class="price"><?php echo bvsl_format_print( $post->salary_jyuuminzei ); ?></td>
	</tr>
</tbody>
<tfoot>
	<tr>
		<th>控除合計</th>
		<td class="price"><?php echo bvsl_format_print( bvsl_get_koujyo_total() ); ?></td>
	</tr>
</tfoot>
</table>

<?php
$custom_fields_array   = Salary_Table_Custom_Fields::custom_fields_hikazei_array();
$custom_fields_hikazei = VK_Custom_Field_Builder_Flexible_Table::get_view_table_body( $custom_fields_array );
if ( $custom_fields_hikazei ) :
?>
<table class="table table-bordered table-bill">
<thead>
	<tr><th colspan="2">その他支給・控除（非課税）</th></tr>
</thead>
<tbody>
	<?php echo $custom_fields_hikazei; ?>
</tbody>
<tfoot>
	<tr>
		<th>非課税支給・控除合計</th>
		<td class="price"><?php echo bvsl_format_print( bvsl_get_hikazei_additional_total() ); ?></td>
	</tr>
</tfoot>
</table>
<?php endif; ?>

<table class="table table-bordered table-bill">
<thead class="thead-dark">
	<tr><th colspan="2">差引支給</th></tr>
</thead>
<tbody>
	<tr>
		<th>課税支給合計</th>
		<td class="price"><?php echo bvsl_format_print( bvsl_get_total_pay( array( 'kazei' => true ) ) ); ?></td>
	</tr>
	<tr>
		<th>控除合計</th>
		<td class="price"><?php echo bvsl_format_print( bvsl_get_koujyo_total() ); ?></td>
	</tr>
	<tr>
		<th>非課税支給・控除合計</th>
		<td class="price"><?php echo bvsl_format_print( bvsl_get_hikazei_additional_total() ); ?></td>
	</tr>
</tbody>
<tfoot>
	<tr>
		<th><b>差引支給額</b></th>
		<td class="price"><b><?php echo bvsl_get_total_furikomi(); ?></b></td>
	</tr>
</tfoot>
</table>
