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

		$custom_fields_array = self::custom_fields_array();
		$befor_custom_fields = '';
		VK_Custom_Field_Builder::form_table( $custom_fields_array, $befor_custom_fields );

		echo '<h4>その他 課税対象支給</h4>';

		$custom_fields_array = self::custom_fields_kazei_array();
		VK_Custom_Field_Builder_Flexible_Table::form_table_flexible( $custom_fields_array );

		echo '<h4>その他 非課税支給</h4>';

		$custom_fields_array = self::custom_fields_hikazei_array();
		VK_Custom_Field_Builder_Flexible_Table::form_table_flexible( $custom_fields_array );

		// echo '<h4>その他 課税控除</h4>';
		//
		// $custom_fields_array = Salary_Table_Custom_Fields::custom_fields_koujyo_kazei_array();
		// VK_Custom_Field_Builder_Flexible_Table::form_table_flexible( $custom_fields_array );
		//
		// echo '<h4>その他 非課税控除</h4>';
		//
		// $custom_fields_array = Salary_Table_Custom_Fields::custom_fields_koujyo_hikazei_array();
		// VK_Custom_Field_Builder_Flexible_Table::form_table_flexible( $custom_fields_array );
	}

	public static function save_custom_fields() {

		$custom_fields_array = self::custom_fields_array();
		VK_Custom_Field_Builder::save_cf_value( $custom_fields_array );

		$custom_fields_array = self::custom_fields_kazei_array();
		VK_Custom_Field_Builder_Flexible_Table::save_cf_value( $custom_fields_array );

		$custom_fields_array = self::custom_fields_hikazei_array();
		VK_Custom_Field_Builder_Flexible_Table::save_cf_value( $custom_fields_array );

		// $custom_fields_array = Salary_Table_Custom_Fields::custom_fields_koujyo_kazei_array();
		// VK_Custom_Field_Builder_Flexible_Table::save_cf_value( $custom_fields_array );
		//
		// $custom_fields_array = Salary_Table_Custom_Fields::custom_fields_koujyo_hikazei_array();
		// VK_Custom_Field_Builder_Flexible_Table::save_cf_value( $custom_fields_array );
	}

	public static function custom_fields_kazei_array() {
		$custom_fields_array = array(
			'field_name'        => 'kazei_additional',
			'row_default'       => 3,
			'row_empty_display' => false,
			'items'             => array(
				'name'  => array(
					'type'             => 'text',
					'label'            => '項目',
					'align'            => 'left',
					'sanitize'         => 'wp_filter_post_kses',
					'display_callback' => '',
				),
				'price' => array(
					'type'             => 'text',
					'label'            => '金額',
					'align'            => 'right',
					'class'            => 'price',
					'sanitize'         => 'wp_filter_post_kses',
					'display_callback' => 'bvsl_format_print',
				),
			),
		);
		return $custom_fields_array;
	}

	public static function custom_fields_hikazei_array() {
		$custom_fields_array = array(
			'field_name'        => 'hikazei_additional',
			'row_default'       => 3,
			'row_empty_display' => false,
			'items'             => array(
				'name'  => array(
					'type'             => 'text',
					'label'            => '項目',
					'align'            => 'left',
					'sanitize'         => 'wp_filter_post_kses',
					'display_callback' => '',
				),
				'price' => array(
					'type'             => 'text',
					'label'            => '金額',
					'align'            => 'right',
					'class'            => 'price',
					'sanitize'         => 'wp_filter_post_kses',
					'display_callback' => 'bvsl_format_print',
				),
			),
		);
		return $custom_fields_array;
	}

	public static function custom_fields_koujyo_kazei_array() {
		$custom_fields_array = array(
			'field_name'        => 'kazei_koujyo',
			'row_default'       => 3,
			'row_empty_display' => false,
			'items'             => array(
				'name'  => array(
					'type'             => 'text',
					'label'            => '項目',
					'align'            => 'left',
					'sanitize'         => 'wp_filter_post_kses',
					'display_callback' => '',
				),
				'price' => array(
					'type'             => 'text',
					'label'            => '金額',
					'align'            => 'right',
					'sanitize'         => 'wp_filter_post_kses',
					'display_callback' => 'bvsl_format_print',
				),
			),
		);
		return $custom_fields_array;
	}

	public static function custom_fields_koujyo_hikazei_array() {
		$custom_fields_array = array(
			'field_name'        => 'hikazei_koujyo',
			'row_default'       => 3,
			'row_empty_display' => false,
			'items'             => array(
				'name'  => array(
					'type'             => 'text',
					'label'            => '項目',
					'align'            => 'left',
					'sanitize'         => 'wp_filter_post_kses',
					'display_callback' => '',
				),
				'price' => array(
					'type'             => 'text',
					'label'            => '金額',
					'align'            => 'right',
					'sanitize'         => 'wp_filter_post_kses',
					'display_callback' => 'bvsl_format_print',
				),
			),
		);
		return $custom_fields_array;
	}



	public static function custom_fields_array() {

		$custom_fields_array = array(
			'salary_role'                 => array(
				'label'       => '役職',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_fuyou'                => array(
				'label'       => '扶養人数',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_base'                 => array(
				'label'       => '基本給',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_overtime_total'       => array(
				'label'       => '時間外賃金',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_part_total'           => array(
				'label'       => 'パート賃金',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_holiday_total'        => array(
				'label'       => '休日出勤賃金',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_transportation_total' => array(
				'label'       => '交通費',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_koyouhoken'           => array(
				'label'       => '雇用保険',
				'type'        => 'checkbox',
				'description' => '未チェックの場合は自動計算されます。雇用保険対象外の場合はチェックしてください。',
				'options'     => array(
					'not_auto_cal' => '自動計算しない',
				),
				'required'    => false,
			),
			'salary_target_term'          => array(
				'label'       => '給与対象時期',
				'type'        => 'select',
				'description' => '選択時期によって雇用保険料が変わります。よく確認して選択してください。',
				'options'     => array(
					'20220930_before' => '〜令和4年9月30日',
					'20221001_after' => '令和4年10月1日〜',
					'20230401_after' => '令和5年4月1日〜',
					'20250401_after' => '令和7年4月1日〜',
				),
				'required'    => true,
			),
			'salary_kenkou'               => array(
				'label'       => '健康保険',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_nenkin'               => array(
				'label'       => '厚生年金',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
			),
			'salary_syotokuzei'           => array(
				'label'       => '所得税',
				'type'        => 'text',
				'description' => '課税対象額をもとに<a href="https://keisan.casio.jp/exec/system/1527476109" target="_blank">給与所得の源泉徴収税額計算サイト</a>などで算出する',
				'required'    => false,
			),
			'salary_jyuuminzei'           => array(
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
