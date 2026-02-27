<?php
/**
 * Plugin Name:     BillVektor Salary
 * Plugin URI:      https://billvektor.com/
 * Description:     BillVektorで給与明細管理をするためのプラグインです。
 * Author:          Vektor,Inc.
 * Author URI:      https://billvektor.com/
 * Text Domain:     bill-vektor-salary
 * Domain Path:     /languages
 * Version:         0.10.0
 *
 * @package         Bill_Vektor_Salary
 */

/*
	テーマがBillVektorじゃない時は誤動作防止のために読み込ませない
--------------------------------------------- */
add_action(
	'after_setup_theme',
	function () {
		if ( ! function_exists( 'bill_get_post_type' ) ) {
			// 読み込まずに終了
			return;
		}
	}
);

/*
	---------------------------------------------
	updater
--------------------------------------------- */
require 'inc/plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/vektor-inc/bill-vektor-salary',
	__FILE__, // Full path to the main plugin file or functions.php.
	'bill-vektor-salary'
);
$myUpdateChecker->setBranch( 'master' );

/*
	給与明細編集画面：管理画面用スクリプトの読み込み
--------------------------------------------- */
add_action( 'admin_enqueue_scripts', 'bvsl_admin_enqueue_scripts' );
function bvsl_admin_enqueue_scripts( $hook ) {
	// 投稿編集画面（post.php / post-new.php）のみ対象
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	// salary 投稿タイプのみ対象
	$post_id   = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	$post_type = $post_id ? get_post_type( $post_id ) : ( isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '' );
	if ( 'salary' !== $post_type ) {
		return;
	}
	wp_enqueue_script(
		'bvsl-admin-salary',
		plugin_dir_url( __FILE__ ) . 'assets/js/admin-salary.js',
		array(),
		'1.0.0',
		true
	);
}

require_once 'inc/duplicate-doc.php';
require_once 'inc/staff/staff.php';
require_once 'inc/template-tags.php';
require_once 'inc/custom-field-setting/custom-field-salary-normal.php';
require_once 'inc/custom-field-setting/custom-field-salary-table.php';
require_once 'inc/custom-field-setting/custom-field-staff.php';

/*
	支給分アーカイブページのテンプレートを上書き
--------------------------------------------- */
add_action( 'template_redirect', 'bvsl_doc_change_salary_archive' );
function bvsl_doc_change_salary_archive() {
	global $wp_query;
	if ( function_exists( 'bill_get_post_type' ) ) {
		$post_type = bill_get_post_type();
		$post_type = $post_type['slug'];
	} else {
		$post_type = get_post_type();
	}
	if ( $post_type == 'salary' && is_tax() ) {
		require_once 'template-parts/doc/frame-salary-archive.php';
		die();
	}
}

/*
	詳細テンプレートを上書き
--------------------------------------------- */
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
		require_once 'template-parts/doc/frame-salary.php';
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
		'salary-tag',
		'salary',
		array(
			'hierarchical'          => false,
			'update_count_callback' => '_update_post_term_count',
			'label'                 => 'タグ',
			'singular_label'        => 'タグ',
			'public'                => true,
			'show_ui'               => true,
		)
	);
	register_taxonomy(
		'salary-type',
		'salary',
		array(
			'hierarchical'          => true,
			'update_count_callback' => '_update_post_term_count',
			'label'                 => '給与種別',
			'singular_label'        => '給与種別',
			'public'                => true,
			'show_ui'               => true,
			'capabilities'          => array(
				// 追加・編集・削除を不可にし、既存タームの割り当てのみ許可する。
				'manage_terms' => 'do_not_allow',
				'edit_terms'   => 'do_not_allow',
				'delete_terms' => 'do_not_allow',
				'assign_terms' => 'edit_posts',
			),
		)
	);

	bvsl_ensure_salary_type_terms();
}

/**
 * 給与種別タクソノミーの固定タームを作成する。
 *
 * @return void
 */
function bvsl_ensure_salary_type_terms() {
	$fixed_terms = array(
		'bvsl-salary' => '給与',
		'bvsl-bonus'  => '賞与',
	);

	foreach ( $fixed_terms as $slug => $name ) {
		$term = get_term_by( 'slug', $slug, 'salary-type' );
		if ( ! $term ) {
			wp_insert_term(
				$name,
				'salary-type',
				array(
					'slug' => $slug,
				)
			);
		}
	}
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
	if ( 'salary' == get_post_type() ) {
		if ( empty( $_POST['post_title'] ) ) {
			$terms = get_the_terms( get_the_ID(), 'salary-term' );
			if ( ! empty( $_POST['salary_staff'] ) ) {
				$title = esc_html( get_the_title( $_POST['salary_staff'] ) );
			} elseif ( isset( $terms[0]->name ) ) {
				$title .= esc_html( ' / ' . $terms[0]->name );
			} else {
				$title = '';
			}
			$post['post_title'] = $title;
			$post['ID']         = get_the_ID();
			remove_action( 'save_post', 'bvsl_title_auto_save' );
			wp_update_post( $post );
			add_action( 'save_post', 'bvsl_title_auto_save' );
		}
	}
}

/*
	支給分アーカイブで、スタッフ番号順になるように書き換え
--------------------------------------------- */
add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		// 支給分アーカイブで、スタッフ番号順 + 同じ番号なら日付が古い順にする
		if ( $query->is_tax( 'salary-term' ) ) {
			$query->set( 'meta_key', 'salary_staff_number' );
			$query->set(
				'orderby',
				array(
					'meta_value' => 'ASC',
					'date'       => 'ASC',
				)
			);
			$query->set( 'order', 'ASC' );
			$query->set( 'posts_per_page', -1 );
		}
		// タグアーカイブで、全件表示になるように書き換え
		if ( $query->is_tax( 'salary-tag' ) ) {
			$query->set( 'posts_per_page', -1 );
		}
	}
);
