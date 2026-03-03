<?php
/**
 * 給与明細 - 左列（支給額・社会保険料）テーブル。
 *
 * frame-salary.php / frame-salary-pdf.php 共通パーツ。
 *
 * @package Bill_Vektor_Salary
 */

global $post;
?>
<table class="table table-bordered table-bill">
<thead>
	<tr><th colspan="2">支給額</th></tr>
</thead>
<tbody>
	<tr>
		<th>基本給</th>
		<td class="price"><?php echo bvsl_format_print( $post->salary_base ); ?></td>
	</tr>
	<tr>
		<th>時間外賃金</th>
		<td class="price"><?php echo bvsl_format_print( $post->salary_overtime_total ); ?></td>
	</tr>
	<tr>
		<th>パート賃金</th>
		<td class="price"><?php echo bvsl_format_print( $post->salary_part_total ); ?></td>
	</tr>
	<tr>
		<th>休日出勤賃金</th>
		<td class="price"><?php echo bvsl_format_print( $post->salary_holiday_total ); ?></td>
	</tr>
	<tr>
		<th>通勤費</th>
		<td class="price"><?php echo bvsl_format_print( $post->salary_transportation_total ); ?></td>
	</tr>
</tbody>
<?php
$custom_fields_array = Salary_Table_Custom_Fields::custom_fields_kazei_array();
$custom_fields_kazei = VK_Custom_Field_Builder_Flexible_Table::get_view_table_body( $custom_fields_array );
if ( $custom_fields_kazei ) {
	echo '<thead>';
	echo '<tr><th colspan="2">その他支給・控除（課税対象）</th></tr>';
	echo '</thead>';
	echo '<tbody>';
	echo $custom_fields_kazei;
	echo '</tbody>';
}
?>
<tfoot>
	<tr>
		<th>課税支給合計</th>
		<td class="price"><?php echo bvsl_format_print( bvsl_get_total_pay( array( 'kazei' => true ) ) ); ?></td>
	</tr>
</tfoot>
</table>

<table class="table table-bordered table-bill">
<thead>
	<tr><th colspan="2">社会保険料</th></tr>
</thead>
<tbody>
	<tr>
		<th>健康保険</th>
		<td class="price"><?php echo bvsl_format_print( $post->salary_kenkou ); ?></td>
	</tr>
	<tr>
		<th>厚生年金</th>
		<td class="price"><?php echo bvsl_format_print( $post->salary_nenkin ); ?></td>
	</tr>
	<tr>
		<th>雇用保険</th>
		<td class="price"><?php echo bvsl_format_print( bvsl_get_koyou_hoken() ); ?></td>
	</tr>
</tbody>
<tfoot>
	<tr>
		<th>社会保険料合計</th>
		<td class="price"><?php echo bvsl_format_print( bvsl_get_shakai_hoken_total() ); ?></td>
	</tr>
</tfoot>
</table>
