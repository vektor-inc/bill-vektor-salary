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
 * 設計方針:
 * - 役割としては「管理画面用 CPT」（フロントには露出させない雛形）。
 * - ただし `public => false` にすると、`get_post_types( array( 'public' => true, 'show_ui' => true ) )`
 *   で投稿タイプ一覧を引いている系のプラグイン（例: Post Type Switcher）から salary-template が
 *   除外され、salary 投稿の投稿タイプ変更先候補に出てこなくなる。
 *   そのため `public => true` にした上で、フロントの露出経路（クエリ・検索・アーカイブ・
 *   ナビメニュー）を個別に全て無効化する WordPress 界隈で一般的な「管理画面用 CPT」パターンを採用する。
 * - `salary-type` タクソノミー（給与/賞与）はテンプレ側でも利用できるよう、
 *   register_post_type の object_type と register_taxonomy の objects 配列の両方で関連付けている。
 *
 * @return void
 */
function bvsl_register_salary_template_post_type() {
	register_post_type(
		'salary-template',
		array(
			'labels'              => array(
				'name'         => '給与テンプレート',
				'edit_item'    => '給与テンプレートの編集',
				'add_new_item' => '給与テンプレートの作成',
			),
			// public は true。Post Type Switcher など、
			// public CPT を引くプラグインから取得対象になるようにする。
			'public'              => true,
			// ただしフロント露出は全部無効化する（実態としては管理画面用 CPT）。
			'publicly_queryable'  => false, // /?post_type=salary-template などは 404。
			'exclude_from_search' => true,  // 検索結果に出さない。
			'has_archive'         => false, // アーカイブページなし。
			'show_in_nav_menus'   => false, // ナビメニュー設定画面の候補に出さない。
			'show_ui'             => true,
			// 「サブメニューじゃなくて通常で」との指示に従い、独立したメニューとして表示する。
			'show_in_menu'        => true,
			'supports'            => array( 'title' ),
			'menu_icon'           => 'dashicons-clipboard',
			// menu_position 自体は他プラグインとの競合で揺れる前提。
			// 確実に給与明細の直下に並べるため、bvsl_force_salary_template_menu_order() で
			// 明示的に並び替えを行う。
			'menu_position'       => 8,
		)
	);
}
// salary 投稿タイプ登録（priority 0）の前に走らせて、
// salary-type タクソノミーが両 post_type に確実に関連付けられるようにする。
add_action( 'init', 'bvsl_register_salary_template_post_type', -10 );

/**
 * 管理画面メニューの並びをカスタマイズ可能にする。
 *
 * `menu_order` フィルタを有効化するための受け皿。
 * Lightning など他プラグインが既にこのフィルタを true にしている環境では多重 true が入るが、
 * `__return_true` は副作用なく true を返すだけなので競合は発生しない。
 *
 * @return bool 常に true。
 */
add_filter( 'custom_menu_order', '__return_true' );

/**
 * 「給与テンプレート」を「給与明細」のすぐ直下に並べる。
 *
 * `menu_position` の整数値だけでは、他プラグインと衝突したときに
 * 給与明細と給与テンプレートの間に他のメニューが割り込む可能性があるため、
 * `menu_order` フィルタで明示的に並び替える。
 *
 * @param string[] $menu_order 現在のトップレベルメニューの順序を表す配列。
 * @return string[] 並び替え後の配列。
 */
function bvsl_force_salary_template_menu_order( $menu_order ) {
	if ( ! is_array( $menu_order ) || empty( $menu_order ) ) {
		return $menu_order;
	}

	$salary_key   = 'edit.php?post_type=salary';
	$template_key = 'edit.php?post_type=salary-template';

	$salary_idx   = array_search( $salary_key, $menu_order, true );
	$template_idx = array_search( $template_key, $menu_order, true );

	// 両方が存在するときだけ並び替える（片方だけのときは何もしない）。
	if ( false === $salary_idx || false === $template_idx ) {
		return $menu_order;
	}

	// 既に給与明細の直後に給与テンプレートがあるなら何もしない。
	if ( $template_idx === $salary_idx + 1 ) {
		return $menu_order;
	}

	// テンプレートを一旦取り除き、配列をリインデックスしてから給与明細の次に挿入する。
	unset( $menu_order[ $template_idx ] );
	$menu_order = array_values( $menu_order );

	// テンプレートを抜いた後の salary の位置を取り直す（テンプレが salary より前にあった場合に位置がズレるため）。
	$salary_idx = array_search( $salary_key, $menu_order, true );
	if ( false === $salary_idx ) {
		// 念のため。salary が消えるケースは通常ないが、その場合は末尾に追加して終了。
		$menu_order[] = $template_key;
		return $menu_order;
	}

	array_splice( $menu_order, $salary_idx + 1, 0, $template_key );

	return $menu_order;
}
add_filter( 'menu_order', 'bvsl_force_salary_template_menu_order' );

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
