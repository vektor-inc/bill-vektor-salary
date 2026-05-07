<?php
/**
 * 給与テンプレート（salary-template）投稿タイプの登録。
 *
 * 給与明細（salary）の雛形となる投稿タイプ。
 * 一括登録 UI から、このテンプレートを元に各スタッフ向けの salary 投稿を生成する。
 *
 * @package Bill_Vektor_Salary
 */

defined( 'ABSPATH' ) || exit;

/**
 * 給与テンプレート投稿タイプを登録する。
 *
 * フロント表示はせず管理画面でのみ扱う。
 * `salary-type` タクソノミー（給与/賞与）はテンプレ側でも利用できるよう
 * register_post_type の object_type と register_taxonomy の objects 配列の両方で関連付けている。
 *
 * @return void
 */
function bvsl_register_salary_template_post_type() {
	register_post_type(
		'salary-template',
		array(
			'labels'             => array(
				'name'         => '給与テンプレート',
				'edit_item'    => '給与テンプレートの編集',
				'add_new_item' => '給与テンプレートの作成',
			),
			// 給与テンプレートはフロントには出さない。あくまで管理画面で雛形として使う。
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			// 「サブメニューじゃなくて通常で」との指示に従い、独立したメニューとして表示する。
			'show_in_menu'       => true,
			'has_archive'        => false,
			'supports'           => array( 'title' ),
			'menu_icon'          => 'dashicons-clipboard',
			// 給与明細（menu_position 7）と並べて表示するため、その直下に配置する。
			'menu_position'      => 8,
		)
	);
}
// salary 投稿タイプ登録（priority 0）の前に走らせて、
// salary-type タクソノミーが両 post_type に確実に関連付けられるようにする。
add_action( 'init', 'bvsl_register_salary_template_post_type', -10 );

/**
 * 給与テンプレート編集画面の publish メタボックス内に「このテンプレで一括登録」ボタンを表示する。
 *
 * 押下すると給与明細一覧画面（edit.php?post_type=salary）にテンプレIDをクエリで渡して遷移し、
 * 主導線パネルでテンプレ選択済みの状態にする。
 *
 * @param WP_Post $post 編集中の投稿。
 * @return void
 */
function bvsl_render_bulk_create_link_in_template_submitbox( $post ) {
	if ( ! ( $post instanceof WP_Post ) ) {
		return;
	}
	if ( 'salary-template' !== get_post_type( $post ) ) {
		return;
	}
	// 新規作成（未保存）状態では遷移先で意味を成さないので非表示。
	if ( 'auto-draft' === $post->post_status || 0 === (int) $post->ID ) {
		return;
	}

	$bulk_url = add_query_arg(
		array(
			'post_type'        => 'salary',
			'bvsl_template_id' => (int) $post->ID,
		),
		admin_url( 'edit.php' )
	);
	?>
	<div style="padding: 10px; border-top: 1px solid #dcdcde;">
		<a href="<?php echo esc_url( $bulk_url ); ?>" class="button button-primary button-large" style="width:100%; display:block; text-align:center;">
			<?php echo esc_html__( 'このテンプレで一括登録', 'bill-vektor-salary' ); ?>
		</a>
		<p style="margin-top:6px;color:#555;font-size:12px;">
			<?php echo esc_html__( '給与明細一覧の一括登録パネルへ移動します（このテンプレートが選択された状態になります）。', 'bill-vektor-salary' ); ?>
		</p>
	</div>
	<?php
}
add_action( 'post_submitbox_start', 'bvsl_render_bulk_create_link_in_template_submitbox', 30 );
