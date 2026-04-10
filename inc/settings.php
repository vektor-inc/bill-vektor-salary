<?php
/**
 * BillVektor Salary グローバル設定
 *
 * プラグイン全体で使用する設定を管理する設定ページ。
 *
 * @package Bill_Vektor_Salary
 */

/**
 * 設定ページを管理メニューに追加する。
 *
 * 給与明細メニューの下にサブメニューとして追加する。
 */
function bvsl_add_settings_page() {
	add_submenu_page(
		'edit.php?post_type=salary',
		'給与設定',
		'給与設定',
		'manage_options',
		'bvsl-settings',
		'bvsl_render_settings_page'
	);
}
add_action( 'admin_menu', 'bvsl_add_settings_page' );

/**
 * 設定を登録する。
 */
function bvsl_register_settings() {
	register_setting( 'bvsl_settings', 'bvsl_business_type', array( 'sanitize_callback' => 'sanitize_text_field' ) );

	add_settings_section(
		'bvsl_settings_section',
		'雇用保険',
		'__return_empty_string',
		'bvsl-settings'
	);

	add_settings_field(
		'bvsl_business_type',
		'事業の種類',
		'bvsl_render_business_type_field',
		'bvsl-settings',
		'bvsl_settings_section'
	);
}
add_action( 'admin_init', 'bvsl_register_settings' );

/**
 * 事業の種類フィールドを描画する。
 */
function bvsl_render_business_type_field() {
	$value   = get_option( 'bvsl_business_type', 'general' );
	$options = array(
		'general'      => '一般の事業',
		'agriculture'  => '農林水産・清酒製造の事業',
		'construction' => '建設の事業',
	);
	echo '<select name="bvsl_business_type" id="bvsl_business_type">';
	foreach ( $options as $key => $label ) {
		echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select>';
	echo '<p class="description">給与明細・スタッフで未設定の場合にこの値が適用されます。<a href="https://www.mhlw.go.jp/stf/seisakunitsuite/bunya/0000108634.html" target="_blank">業種の判断について（厚生労働省）</a></p>';
}

/**
 * 設定ページを描画する。
 */
function bvsl_render_settings_page() {
	?>
	<div class="wrap">
		<h1>給与設定</h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'bvsl_settings' );
			do_settings_sections( 'bvsl-settings' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}
