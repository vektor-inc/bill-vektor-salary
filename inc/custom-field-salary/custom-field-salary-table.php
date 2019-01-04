<?php
/*
* 給与明細のカスタムフィールド（品目以外）
*/

class Salary_Table_Custom_Fields {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_metabox' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save_custom_fields' ), 10, 2 );
	}

	// add meta_box
	public static function add_metabox() {

		$id            = 'meta_box_bill_table';
		$title         = '給与明細項目';
		$callback      = array( __CLASS__, 'fields_form' );
		$screen        = 'salary';
		$context       = 'advanced';
		$priority      = 'high';
		$callback_args = '';

		add_meta_box( $id, $title, $callback, $screen, $context, $priority, $callback_args );

	}

	public static function fields_form() {
		global $post;

		$custom_fields_array = Salary_Table_Custom_Fields::custom_fields_array();
		$befor_custom_fields = '';
		VK_Custom_Field_Builder::form_table( $custom_fields_array, $befor_custom_fields );
	}

	public static function save_custom_fields() {
		$custom_fields_array = Salary_Table_Custom_Fields::custom_fields_array();
		VK_Custom_Field_Builder::save_cf_value( $custom_fields_array );
	}

	public static function custom_fields_array() {

		$custom_fields_array = array(
			'salary_role'           => array(
				'label'       => '役職',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_fuyou'          => array(
				'label'       => '扶養人数',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_base'           => array(
				'label'       => '基本給',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_overtime_total' => array(
				'label'       => '時間外賃金',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_part_total'     => array(
				'label'       => 'パート賃金',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_holiday_total'  => array(
				'label'       => '休日出勤賃金',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_kenkou'         => array(
				'label'       => '健康保険',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_nenkin'         => array(
				'label'       => '厚生年金',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_jyuuminzei'     => array(
				'label'       => '住民税',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
		);
		return $custom_fields_array;
	}

}
Salary_Table_Custom_Fields::init();
