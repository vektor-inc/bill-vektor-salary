<?php
/*
* 給与明細のカスタムフィールド（品目以外）
*/

class Staff_Custom_Fields {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_metabox' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save_custom_fields' ), 10, 2 );
	}

	// add meta_box
	public static function add_metabox() {

		$id            = 'meta_box_bill_normal';
		$title         = 'スタッフ基本項目';
		$callback      = array( __CLASS__, 'fields_form' );
		$screen        = 'staff';
		$context       = 'advanced';
		$priority      = 'high';
		$callback_args = '';

		add_meta_box( $id, $title, $callback, $screen, $context, $priority, $callback_args );

	}

	public static function fields_form() {
		global $post;

		$custom_fields_array = Staff_Custom_Fields::custom_fields_array();
		$befor_custom_fields = '';
		VK_Custom_Field_Builder::form_table( $custom_fields_array, $befor_custom_fields );
	}

	public static function save_custom_fields() {
		$custom_fields_array = Staff_Custom_Fields::custom_fields_array();
		// $custom_fields_array_no_cf_builder = arra();
		// $custom_fields_all_array = array_merge(  $custom_fields_array, $custom_fields_array_no_cf_builder );
		VK_Custom_Field_Builder::save_cf_value( $custom_fields_array );
	}

	public static function custom_fields_array() {

		$custom_fields_array = array(
			'salary_staff_number'            => array(
				'label'       => 'Staff No.',
				'type'        => 'text',
				'description' => '※各明細で別途手入力する必要があります。',
				'required'    => false,
				'sanitize'    => 'sanitize_text_field',
			),
			'salary_staff_status'            => array(
				'label'       => 'スタッフステータス',
				'type'        => 'select',
				'description' => '',
				'required'    => false,
				'options'     => array(
					'employed'         => '勤務中',
					'retired'          => '退職',
					'leave_of_absence' => '休職',
				),
				'sanitize'    => 'sanitize_text_field',
			),
			'salary_fuyou'                   => array(
				'label'       => '税扶養人数',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
				'sanitize'    => 'sanitize_text_field',
			),
			'salary_kenkou_hifuyousya'       => array(
				'label'       => '健康保険被扶養人数',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
				'sanitize'    => 'sanitize_text_field',
			),
			'salary_birthday'                => array(
				'label'       => '生年月日',
				'type'        => 'text',
				'description' => 'YYYY-MM-DD 形式で入力してください。',
				'required'    => false,
				'sanitize'    => 'bvsl_sanitize_staff_birthday',
			),
			'salary_base'                    => array(
				'label'       => '基本給',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
				'sanitize'    => 'bvsl_sanitize_staff_amount',
			),
			'salary_transportation_total'    => array(
				'label'       => '交通費',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
				'sanitize'    => 'bvsl_sanitize_staff_amount',
			),
			'salary_koyouhoken'              => array(
				'label'       => '雇用保険',
				'type'        => 'checkbox',
				'description' => '未チェックの場合は自動計算されます。雇用保険対象外の場合はチェックしてください。',
				'options'     => array(
					'not_auto_cal' => '自動計算しない',
				),
				'required'    => false,
			),
			'salary_transfer_account_bank'   => array(
				'label'       => '振込銀行名',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
				'sanitize'    => 'sanitize_text_field',
			),
			'salary_transfer_account_branch' => array(
				'label'       => '支店名',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
				'sanitize'    => 'sanitize_text_field',
			),
			'salary_transfer_account_type'   => array(
				'label'       => '口座種類',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
				'sanitize'    => 'sanitize_text_field',
			),
			'salary_transfer_account_number' => array(
				'label'       => '口座番号',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
				'sanitize'    => 'sanitize_text_field',
			),
		);
		return $custom_fields_array;
	}

}
Staff_Custom_Fields::init();

/**
 * スタッフ生年月日を年齢計算しやすい YYYY-MM-DD 形式に整える。
 *
 * @param string $value 入力値。
 * @return string YYYY-MM-DD 形式の値。無効な値は空文字を返す。
 */
function bvsl_sanitize_staff_birthday( $value ) {
	$value = sanitize_text_field( $value );

	if ( '' === $value ) {
		return '';
	}

	// 生年月日は年齢計算で扱えるよう YYYY-MM-DD のみ許可する。
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
		return '';
	}

	list( $year, $month, $day ) = array_map( 'intval', explode( '-', $value ) );
	if ( ! checkdate( $month, $day, $year ) ) {
		return '';
	}

	return sprintf( '%04d-%02d-%02d', $year, $month, $day );
}

/**
 * スタッフ金額項目を数値文字列に整える。
 *
 * @param string $value 入力値。
 * @return string 正規化した数値文字列。無効な値は空文字を返す。
 */
function bvsl_sanitize_staff_amount( $value ) {
	$value = sanitize_text_field( $value );
	$value = str_replace( array( ',', ' ' ), '', $value );

	if ( '' === $value ) {
		return '';
	}

	if ( ! is_numeric( $value ) ) {
		return '';
	}

	$amount = (float) $value;
	if ( $amount < 0 ) {
		return '';
	}

	if ( (float) (int) $amount === $amount ) {
		return (string) (int) $amount;
	}

	return rtrim( rtrim( sprintf( '%.10F', $amount ), '0' ), '.' );
}
