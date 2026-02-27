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
 * Requires PHP:    8.0
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

	$script_path    = plugin_dir_path( __FILE__ ) . 'assets/js/admin-salary.js';
	$script_version = file_exists( $script_path ) ? (string) filemtime( $script_path ) : '1.0.1';

	$term_messages = array();
	if ( function_exists( 'bvsl_get_salary_term_common_message_map' ) ) {
		$term_messages = bvsl_get_salary_term_common_message_map();
	}

	wp_enqueue_script(
		'bvsl-admin-salary',
		plugin_dir_url( __FILE__ ) . 'assets/js/admin-salary.js',
		array(),
		$script_version,
		true
	);

	wp_localize_script(
		'bvsl-admin-salary',
		'bvslAdminSalary',
		array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'bvsl_salary_admin_nonce' ),
			'pdfNonce'        => wp_create_nonce( 'bvsl_generate_salary_pdf_nonce' ),
			'commonMessageId' => 'bvsl-common-message-row',
			'termMessages'    => $term_messages,
			'postId'          => $post_id,
		)
	);
}

require_once 'inc/duplicate-doc.php';
require_once 'inc/staff/staff.php';
require_once 'inc/template-tags.php';
require_once 'inc/salary-message.php';
require_once 'inc/salary-pdf.php';
require_once 'inc/custom-field-setting/custom-field-salary-normal.php';
require_once 'inc/custom-field-setting/custom-field-salary-table.php';
require_once 'inc/custom-field-setting/custom-field-staff.php';

/*
	PDFテンプレート ブラウザプレビュー
	?bvsl_pdf_preview=1&post_id={post_id} で管理者がブラウザで HTML を確認できる。
	DevTools で CSS を調整してから frame-salary-pdf.php に反映する用途。
--------------------------------------------- */
add_action(
	'template_redirect',
	function () {
		if ( ! isset( $_GET['bvsl_pdf_preview'] ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Permission denied.' );
		}
		$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
		if ( ! $post_id ) {
			wp_die( 'post_id が指定されていません。' );
		}
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		global $post;
		$post = get_post( $post_id );
		if ( ! $post || 'salary' !== $post->post_type ) {
			wp_die( '指定された投稿が見つかりません。' );
		}
		setup_postdata( $post );
		require plugin_dir_path( __FILE__ ) . 'template-parts/doc/frame-salary-pdf.php';
		exit;
	}
);

/*
	PDF発行ボタン（公開メタボックス内）
--------------------------------------------- */
add_action( 'post_submitbox_start', 'bvsl_render_pdf_issue_in_submitbox', 20 );
function bvsl_render_pdf_issue_in_submitbox( $post ) {
	if ( 'salary' !== get_post_type( $post ) ) {
		return;
	}
	$is_new = ( 'auto-draft' === $post->post_status || 0 === $post->ID );
	?>
	<div id="bvsl-pdf-issue-wrap" style="padding: 10px; border-top: 1px solid #dcdcde;">
		<button
			type="button"
			id="bvsl-pdf-issue-btn"
			class="button button-primary button-large"
			<?php echo $is_new ? 'disabled' : ''; ?>
			style="width: 100%; display: block; text-align: center;"
		>PDF発行</button>
		<?php if ( $is_new ) : ?>
		<p style="margin-top:6px;color:#555;font-size:12px;">先に保存してから発行できます。</p>
		<?php endif; ?>
		<div style="display:flex; align-items:center; margin-top:6px;">
			<span id="bvsl-pdf-issue-spinner" class="spinner" style="float:none; display:none; margin:0 8px 0 0;"></span>
			<p id="bvsl-pdf-issue-message" style="margin:0; text-align:left;"></p>
		</div>
	</div>
	<?php
}

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
			$inserted_term = wp_insert_term(
				$name,
				'salary-type',
				array(
					'slug' => $slug,
				)
			);

			if ( is_wp_error( $inserted_term ) ) {
				// ターム作成失敗時に原因を追跡できるようにログへ出力する。
				error_log( sprintf( 'bvsl_ensure_salary_type_terms failed. slug: %1$s, message: %2$s', $slug, $inserted_term->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
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
