<?php
/*
* 給与明細のカスタムフィールド（品目以外）
*/

class Salary_Normal_Custom_Fields {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_metabox' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save_custom_fields' ), 10, 2 );
	}

	// add meta_box
	public static function add_metabox() {

		$id            = 'meta_box_bill_normal';
		$title         = '給与明細基本項目';
		$callback      = array( __CLASS__, 'fields_form' );
		$screen        = 'salary';
		$context       = 'advanced';
		$priority      = 'high';
		$callback_args = '';

		add_meta_box( $id, $title, $callback, $screen, $context, $priority, $callback_args );

	}

	public static function fields_form() {
		global $post;

		$custom_fields_array = Salary_Normal_Custom_Fields::custom_fields_array();
		$befor_custom_fields = '';
		VK_Custom_Field_Builder::form_table( $custom_fields_array, $befor_custom_fields );
	}

	public static function save_custom_fields() {
		$custom_fields_array = Salary_Normal_Custom_Fields::custom_fields_array();
		// $custom_fields_array_no_cf_builder = arra();
		// $custom_fields_all_array = array_merge(  $custom_fields_array, $custom_fields_array_no_cf_builder );
		VK_Custom_Field_Builder::save_cf_value( $custom_fields_array );
	}

	public static function custom_fields_array() {

		$args        = array(
			'post_type'      => 'staff',
			'posts_per_page' => -1,
			'order'          => 'ASC',
			'orderby'        => 'title',
		);
		$staff_posts = get_posts( $args );
		if ( $staff_posts ) {
			$staff = array( '' => '選択してください' );
			foreach ( $staff_posts as $key => $post ) {
				// プルダウンに表示するかしないかの情報を取得
				$staff_hidden = get_post_meta( $post->ID, 'client_hidden', true );
				// プルダウン非表示にチェックが入っていない項目だけ出力
				if ( ! $staff_hidden ) {
						$staff[ $post->ID ] = $post->post_title;
				}
			}
		} else {
			$staff = array( '0' => 'スタッフが登録されていません' );
		}

		$custom_fields_array = array(
			'salary_document_name' => array(
				'label'       => '書類の表記',
				'type'        => 'text',
				'description' => '',
				'required'    => false,
				'description' => '※未記入の場合は「給与明細」になります。',
			),
			'salary_staff'         => array(
				'label'       => 'スタッフ',
				'type'        => 'select',
				'description' => 'スタッフは<a href="' . admin_url( '/post-new.php?post_type=staff' ) . '" target="_blank">こちら</a>から登録してください。',
				'required'    => true,
				'options'     => $staff,
			),
			'salary_staff_number'  => array(
				'label'       => 'Staff No.',
				'type'        => 'text',
				'description' => '支給分一覧ではこの値が小さい順に表示されます。',
				'required'    => false,
			),
			'salary_message'       => array(
				'label'       => 'メッセージ',
				'type'        => 'textarea',
				'description' => '※未記入の場合は「今月もお疲れ様でした。」になります。',
				'required'    => false,
			),
			'salary_remarks'       => array(
				'label'       => '備考',
				'type'        => 'textarea',
				'description' => '',
				'required'    => false,
			),
			'salary_memo'          => array(
				'label'       => 'メモ',
				'type'        => 'textarea',
				'description' => 'この項目は印刷されません。',
				'required'    => false,
			),
			'salary_send_pdf'      => array(
				'label'       => '発行済PDF',
				'type'        => 'file',
				'description' => '発行したPDFファイルを保存しておく場合に登録してください。',
				'hidden'      => true,
			),
		// 'event_image_main' => array(
		// 'label' => __('メインイメージ','bill-vektor'),
		// 'type' => 'image',
		// 'description' => '',
		// 'hidden' => true,
		// ),
		);
		return $custom_fields_array;
	}

}
Salary_Normal_Custom_Fields::init();
