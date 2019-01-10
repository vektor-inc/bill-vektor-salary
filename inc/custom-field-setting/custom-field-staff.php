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
