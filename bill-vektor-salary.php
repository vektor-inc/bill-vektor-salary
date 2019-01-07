<?php
/**
 * Plugin Name:     BillVektor Salary
 * Plugin URI:
 * Description:
 * Author:          Vektor,Inc.
 * Author URI:      https://billvektor.com/
 * Text Domain:     bill-vektor-salary
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         Bill_Vektor_Salary
 */

 /*
  ---------------------------------------------
	 updater
 --------------------------------------------- */
// require 'inc/plugin-update-checker/plugin-update-checker.php';
// $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
// 'https://lightning.nagoya/wp-content/vk-data-files/bill-vektor-salary-49308514/plugin-update-config.json',
// __FILE__,
// 'bill-vektor-salary'
// );
// Your code starts here.
require_once( 'inc/duplicate-doc.php' );
require_once( 'inc/staff/staff.php' );
require_once( 'inc/template-tags.php' );
require_once( 'inc/custom-field-salary/custom-field-salary-normal.php' );
require_once( 'inc/custom-field-salary/custom-field-salary-table.php' );


add_action( 'template_redirect', 'bvsl_doc_change_salary_archive' );
function bvsl_doc_change_salary_archive() {
	global $wp_query;
	$post_type = bill_get_post_type();
	if ( $post_type['slug'] == 'salary' && is_tax() ) {
		require_once( 'template-parts/doc/frame-salary-archive.php' );
		die();
	}
}

add_filter( 'bill-vektor-doc-change', 'bvsl_doc_change_salary' );
function bvsl_doc_change_salary( $doc_change ) {
	if ( get_post_type() == 'salary' ) {
		$doc_change = true;
	}
	return $doc_change;
}

add_action( 'bill-vektor-doc-frame', 'bvsl_doc_frame_salary' );
function bvsl_doc_frame_salary() {
	if ( get_post_type() == 'salary' ) {
		require_once( 'template-parts/doc/frame-salary.php' );
	}
}


	/*
	-------------------------------------------
	Add Post Type salary
	-------------------------------------------
	*/
add_action( 'init', 'bill_add_post_type_salaly', 0 );
function bill_add_post_type_salaly() {
	register_post_type(
		'salary',
		array(
			'labels'             => array(
				'name'         => '給与明細',
				'edit_item'    => '給与明細の編集',
				'add_new_item' => '給与明細の作成',
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'has_archive'        => true,
			'supports'           => array( 'title' ),
			'menu_icon'          => 'dashicons-media-spreadsheet',
			'menu_position'      => 7,
			// 'show_in_rest'       => true,
			// 'rest_base'          => 'salary',
		)
	);
	register_taxonomy(
		'salary-term',
		'salary',
		array(
			'hierarchical'          => true,
			'update_count_callback' => '_update_post_term_count',
			'label'                 => '支給分',
			'singular_label'        => '支給分',
			'public'                => true,
			'show_ui'               => true,
		)
	);
	register_taxonomy(
		'salary-cat',
		'salary',
		array(
			'hierarchical'          => true,
			'update_count_callback' => '_update_post_term_count',
			'label'                 => '給与明細カテゴリー',
			'singular_label'        => '給与明細カテゴリー',
			'public'                => true,
			'show_ui'               => true,
		)
	);
}

function bill_remove_meta_boxes_comment() {
	remove_meta_box( 'commentstatusdiv', 'salary', 'normal' );
}
add_action( 'admin_menu', 'bill_remove_meta_boxes_comment' );

function bill_vektor_post_types_custom_salay( $post_type_array ) {
		$post_type_array['salary'] = '給与明細';
		return $post_type_array;
}
add_filter( 'bill_vektor_post_types', 'bill_vektor_post_types_custom_salay' );

/**
 * タイトルにスタッフ名と支給期間を自動入力
 *
 * @var [type]
 */
add_action( 'save_post', 'bvsl_title_auto_save' );
function bvsl_title_auto_save() {
	if ( empty( $_POST['post_title'] ) ) {
		$terms              = get_the_terms( get_the_ID(), 'salary-term' );
		$title              = esc_html( get_the_title( $_POST['salary_staff'] ) . ' / ' . $terms[0]->name );
		$post['post_title'] = $title;
		$post['ID']         = get_the_ID();
		remove_action( 'save_post', 'bvsl_title_auto_save' );
		wp_update_post( $post );
		add_action( 'save_post', 'bvsl_title_auto_save' );
	}
}
