<?php
/**
 * 給与明細 PDF 用テンプレート。
 *
 * mPDF 向けに最適化した HTML テーブルレイアウト。
 * データ表示の共通パーツは template-parts/doc/parts/ 以下を使用。
 * salary-footer.php では $bvsl_pdf_mode を参照してロゴ出力を切り替える。
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
	font-family: bvsljapansans, sjis, sans-serif;
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
	padding-right: 8px;
}
.pdf-header-right {
	width: 45%;
}

h1.bill-title {
	display: block;
	width: 100%;
	font-size: 16pt;
	font-weight: bold;
	margin: 0 0 20pt 0;
	padding: 0 0 3pt 0;
}
h2.bill-destination {
	display: block;
	width: 100%;
	font-size: 12pt;
	margin: 0 0 20pt 0;
	padding: 0 0 3pt 0;
}
.bill-message {
	font-size: 9pt;
	margin-top: 8pt;
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

/* ===== メインコンテンツ 2 カラム ===== */
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

<!-- ===== ヘッダー部分 ===== -->
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
			<div style="height: 1em; line-height: 1em; font-size: 1em;">&nbsp;</div>

		<?php if ( $post->salary_staff ) : ?>
		<h2 class="bill-destination">
			<?php echo esc_html( get_the_title( $post->salary_staff ) ); ?> 様
		</h2>
			<div style="height: 1em; line-height: 1em; font-size: 1em;">&nbsp;</div>
		<?php endif; ?>

		<div class="bill-message">
		<?php
		$message = bvsl_build_salary_message( get_the_ID() );
		echo apply_filters( 'the_content', $message );
		?>
		</div>
	</td>
	<td class="pdf-header-right">
		<?php require __DIR__ . '/parts/salary-info-table.php'; ?>
	</td>
</tr>
</tbody>
</table>

<!-- ===== メイン 2 カラム ===== -->
<table class="pdf-body-table">
<tbody>
<tr>
	<td class="pdf-col-left">
		<?php require __DIR__ . '/parts/salary-body-left.php'; ?>
	</td>
	<td class="pdf-col-right">
		<?php require __DIR__ . '/parts/salary-body-right.php'; ?>
	</td>
</tr>
</tbody>
</table>

<?php
// salary-footer.php でロゴをファイルパス参照にするためのフラグ。
$bvsl_pdf_mode = true;
require __DIR__ . '/parts/salary-footer.php';
?>

</body>
</html>
