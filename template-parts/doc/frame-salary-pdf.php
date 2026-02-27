<?php
/**
 * 給与明細PDF用テンプレート。
 *
 * mPDF 向けに最適化した HTML テーブルレイアウト。
 * データ取得ロジックは frame-salary.php と同一。
 *
 * @package Bill_Vektor_Salary
 */

global $post;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* {
	box-sizing: border-box;
}
body {
	font-family: sans-serif;
	font-size: 9.5pt;
	color: #222;
	margin: 0;
	padding: 0;
}

/* ===== ヘッダー ===== */
.pdf-header-table {
	width: 100%;
	border-collapse: collapse;
	margin-bottom: 10px;
}
.pdf-header-table td {
	vertical-align: top;
	padding: 0;
}
.pdf-header-left {
	width: 55%;
}
.pdf-header-right {
	width: 45%;
}

h1.bill-title {
	font-size: 16pt;
	font-weight: bold;
	margin: 0 0 4px 0;
	padding: 0;
}
h2.bill-destination {
	font-size: 12pt;
	margin: 0 0 6px 0;
	padding: 0;
}
.bill-message {
	font-size: 9pt;
	margin-bottom: 4px;
}
.bill-message p {
	margin: 0 0 4px 0;
}

/* ===== 情報テーブル（支給分・発行日等）===== */
.bill-info-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 9pt;
}
.bill-info-table th,
.bill-info-table td {
	border: 1px solid #aaa;
	padding: 3px 6px;
	text-align: left;
}
.bill-info-table th {
	background: #f0f0f0;
	white-space: nowrap;
	width: 40%;
}

/* ===== メインコンテンツ2カラム ===== */
.pdf-body-table {
	width: 100%;
	border-collapse: collapse;
}
.pdf-body-table > tbody > tr > td {
	vertical-align: top;
	padding: 0;
}
.pdf-col-left {
	width: 49%;
	padding-right: 4px;
}
.pdf-col-right {
	width: 49%;
	padding-left: 4px;
}

/* ===== 明細テーブル共通 ===== */
.table-bill {
	width: 100%;
	border-collapse: collapse;
	margin-bottom: 6px;
	font-size: 9pt;
}
.table-bill th,
.table-bill td {
	border: 1px solid #aaa;
	padding: 3px 5px;
	text-align: left;
}
.table-bill thead th {
	background: #e8e8e8;
	text-align: center;
	font-weight: bold;
}
.table-bill tfoot th,
.table-bill tfoot td {
	background: #f5f5f5;
	font-weight: bold;
}
.table-bill .thead-dark th {
	background: #555;
	color: #fff;
	text-align: center;
}
.table-bill td.price {
	text-align: right;
	white-space: nowrap;
}
.table-bill th {
	width: 55%;
}

/* ===== 備考・フッター ===== */
.bill-remarks {
	margin: 8px 0 4px 0;
	font-size: 9pt;
}
.bill-remarks dt {
	font-weight: bold;
	margin-bottom: 2px;
}
.bill-remarks dd {
	margin: 0;
}
.bill-footer {
	text-align: right;
	margin-top: 8px;
	font-size: 9pt;
}
.bill-footer h4 {
	font-size: 11pt;
	margin: 0;
}
.bill-footer-logo {
	max-height: 40px;
}
</style>
</head>
<body>

<!-- ===== ヘッダー部分（タイトル・宛先・メッセージ ／ 支給分・発行日等） ===== -->
<table class="pdf-header-table">
<tbody>
<tr>
	<td class="pdf-header-left">
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
			<?php echo esc_html( get_the_title( $post->salary_staff ) ); ?> 様
		</h2>
		<?php endif; ?>

		<div class="bill-message">
		<?php
		$message = bvsl_build_salary_message( get_the_ID() );
		echo apply_filters( 'the_content', $message );
		?>
		</div>
	</td>
	<td class="pdf-header-right">
		<table class="bill-info-table">
		<tbody>
			<tr>
				<th>支給分</th>
				<td>
				<?php
				$terms = get_the_terms( get_the_ID(), 'salary-term' );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					echo esc_html( $terms[0]->name );
				}
				?>
				</td>
			</tr>
			<tr>
				<th>発行日</th>
				<td><?php echo get_the_date(); ?></td>
			</tr>
			<?php if ( $post->salary_staff_number ) : ?>
			<tr>
				<th>Staff No.</th>
				<td><?php echo esc_html( $post->salary_staff_number ); ?></td>
			</tr>
			<?php endif; ?>
		</tbody>
		</table>
	</td>
</tr>
</tbody>
</table>

<!-- ===== メイン2カラム（左：支給額・社会保険料 ／ 右：所得税・控除・差引支給） ===== -->
<table class="pdf-body-table">
<tbody>
<tr>
	<!-- 左列 -->
	<td class="pdf-col-left">

		<!-- 支給額 -->
		<table class="table-bill">
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
			echo '<thead class="thead-dark">';
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

		<!-- 社会保険料 -->
		<table class="table-bill">
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

	</td><!-- /.pdf-col-left -->

	<!-- 右列 -->
	<td class="pdf-col-right">

		<!-- 所得税 -->
		<table class="table-bill">
		<thead>
			<tr><th colspan="2">所得税</th></tr>
		</thead>
		<tbody>
			<tr>
				<th>課税対象額<br>（課税支給合計 - 社会保険料合計）</th>
				<td class="price"><b><?php echo bvsl_format_print( bvsl_get_kazeisyotoku() ); ?></b></td>
			</tr>
			<tr>
				<th><b>所得税</b></th>
				<td class="price"><b><?php echo bvsl_format_print( $post->salary_syotokuzei ); ?></b></td>
			</tr>
		</tbody>
		</table>

		<!-- 控除額 -->
		<table class="table-bill">
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

		<!-- 非課税支給・控除 -->
		<?php
		$custom_fields_array   = Salary_Table_Custom_Fields::custom_fields_hikazei_array();
		$custom_fields_hikazei = VK_Custom_Field_Builder_Flexible_Table::get_view_table_body( $custom_fields_array );
		if ( $custom_fields_hikazei ) :
		?>
		<table class="table-bill">
		<thead class="thead-dark">
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

		<!-- 差引支給 -->
		<table class="table-bill">
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

	</td><!-- /.pdf-col-right -->
</tr>
</tbody>
</table>

<!-- ===== 備考 ===== -->
<?php if ( $post->salary_remarks ) : ?>
<dl class="bill-remarks">
	<dt>備考</dt>
	<dd><?php echo apply_filters( 'the_content', $post->salary_remarks ); ?></dd>
</dl>
<?php endif; ?>

<!-- ===== フッター（会社名・ロゴ） ===== -->
<div class="bill-footer">
<?php
$options = get_option( 'bill-setting', Bill_Admin::options_default() );
if ( isset( $options['own-logo'] ) && $options['own-logo'] ) {
	$logo_path = get_attached_file( $options['own-logo'] );
	if ( $logo_path && file_exists( $logo_path ) ) {
		// mPDF はローカルファイルパスで画像を読み込む
		echo '<img src="' . esc_attr( $logo_path ) . '" class="bill-footer-logo" alt="">';
	}
} else {
	echo '<h4>' . esc_html( $options['own-name'] ) . '</h4>';
}
?>
</div>

</body>
</html>
