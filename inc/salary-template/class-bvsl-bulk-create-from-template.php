<?php
/**
 * 給与テンプレートからの一括登録クラス。
 *
 * 給与明細（salary）一覧画面の上部に折りたたみ可能なパネルを描画し、
 * **支給分（salary-term）を 1 つ指定すると、処理対象の給与テンプレ全件**（公開 / 非公開、下書きは除外）を
 * 各スタッフ向けの salary 投稿として下書きで一括生成する。
 *
 * 運用イメージ:
 * - 給与テンプレートはスタッフごとに作成する雛形。
 * - 毎月（支給分ごとに）一括登録パネルで支給分を選んで実行 → 処理対象の給与テンプレ全件が salary に展開される。
 * - vk-booking-manager-pro の class-shift-editor.php の bulk_create パターンに揃えた、
 *   「テンプレ全件を支給分指定で一気に展開する」シンプルな運用。
 *
 * @package Bill_Vektor_Salary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BVSL_Bulk_Create_From_Template
 */
class BVSL_Bulk_Create_From_Template {

	/**
	 * admin-post.php に渡す action 名。
	 */
	const ACTION = 'bvsl_bulk_create_from_template';

	/**
	 * nonce アクション名。
	 */
	const NONCE_ACTION = 'bvsl_bulk_create_from_template_nonce';

	/**
	 * nonce フィールド名。
	 */
	const NONCE_NAME = '_bvsl_bulk_create_nonce';

	/**
	 * 1 リクエストで処理できる対象テンプレ数の上限。
	 *
	 * 大量生成による負荷・誤操作の被害範囲を抑える保険。
	 * 処理対象の給与テンプレ（公開 / 非公開）が上限を超える場合は admin notice でエラー表示して拒否する。
	 */
	const MAX_TEMPLATE_COUNT = 500;

	/**
	 * 結果通知用 transient のキープレフィックス。
	 *
	 * 実際のキーは self::result_transient_key() で生成する（ユーザーごとに分離）。
	 */
	const RESULT_TRANSIENT_PREFIX = 'bvsl_bulk_result_';

	/**
	 * 結果通知用 transient の有効秒数。
	 */
	const RESULT_TRANSIENT_TTL = 60;

	/**
	 * フックを登録する。
	 *
	 * @return void
	 */
	public static function init() {
		// 給与明細一覧画面の上部にパネルを描画。
		add_action( 'admin_notices', array( __CLASS__, 'render_panel' ), 1 );
		// パネル送信後の結果通知。
		add_action( 'admin_notices', array( __CLASS__, 'render_result_notice' ) );
		// 一括登録パネル用の CSS / JS を給与明細一覧画面でのみ読み込む。
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		// admin-post.php エンドポイント。
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * 一括登録パネル用の CSS / JS を読み込む。
	 *
	 * 給与明細（salary）一覧画面でのみ読み込む。
	 * admin_enqueue_scripts に渡される $hook_suffix は pagenow ベース（'edit.php' など）で、
	 * post_type の情報を含まないため、現在のスクリーン ID（'edit-salary'）でも判定する。
	 * ファイルの更新時刻をバージョンに使うことで、ブラウザキャッシュの破棄を自動化する。
	 *
	 * @param string $hook_suffix 現在の管理画面の hook suffix（'edit.php' など）。
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		// 投稿一覧系の画面（edit.php）以外は早期 return。
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}
		// $hook_suffix は post_type を区別しないため、スクリーン ID で salary 一覧かどうか判定する。
		// admin_enqueue_scripts は current_screen フック以降に発火するため get_current_screen() が利用可能。
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-salary' !== $screen->id ) {
			return;
		}
		// 念のため画面判定でもガード（権限のないユーザーには読ませない）。
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// プラグインのルート（bill-vektor-salary.php がある場所）を基準にパスを組み立てる。
		// __DIR__ は inc/salary-template/ なので、2 階層上がプラグインのルート。
		$plugin_dir   = dirname( __DIR__, 2 );
		$plugin_file  = $plugin_dir . '/bill-vektor-salary.php';
		$css_rel_path = 'assets/css/admin-bulk-create-panel.css';
		$js_rel_path  = 'assets/js/admin-bulk-create-panel.js';
		$css_abs_path = $plugin_dir . '/' . $css_rel_path;
		$js_abs_path  = $plugin_dir . '/' . $js_rel_path;

		// バージョン文字列はファイルの mtime を使う（既存 enqueue 実装と揃える）。
		// ファイル不在時のフォールバックも既存 bvsl_admin_enqueue_scripts() に合わせて '1.0.1' を使う。
		// false を渡すと WP コアのバージョンが付与されてしまう副作用があるため文字列固定にする。
		$css_version = file_exists( $css_abs_path ) ? (string) filemtime( $css_abs_path ) : '1.0.1';
		$js_version  = file_exists( $js_abs_path ) ? (string) filemtime( $js_abs_path ) : '1.0.1';

		wp_enqueue_style(
			'bvsl-admin-bulk-create-panel',
			plugins_url( $css_rel_path, $plugin_file ),
			array(),
			$css_version
		);

		wp_enqueue_script(
			'bvsl-admin-bulk-create-panel',
			plugins_url( $js_rel_path, $plugin_file ),
			array(),
			$js_version,
			true
		);

		// 旧インラインスクリプトで PHP から JS に渡していた値を localize で渡す。
		// 対象テンプレ件数はパネル描画時に確定するため、軽量な wp_count_posts() ベースのヘルパーで件数のみ取得する
		// （実配列が不要な箇所では get_posts() で全件ロードしないように切り替えた）。
		$template_count = self::get_target_template_count();
		wp_localize_script(
			'bvsl-admin-bulk-create-panel',
			'bvslBulkCreatePanel',
			array(
				'templateCount' => $template_count,
				'i18n'          => array(
					'selectTerm' => __( '支給分を選んでください。', 'bill-vektor-salary' ),
					'summary'    => __( '対象テンプレート %1$d 件を「%2$s」で下書き作成します。', 'bill-vektor-salary' ),
					'confirm'    => __( '対象テンプレート %1$d 件を「%2$s」で下書き作成します。よろしいですか？', 'bill-vektor-salary' ),
				),
			)
		);
	}

	/**
	 * 結果通知用 transient のキーを返す（ログインユーザーごと）。
	 *
	 * @return string transient キー。
	 */
	private static function result_transient_key() {
		return self::RESULT_TRANSIENT_PREFIX . (int) get_current_user_id();
	}

	/**
	 * 給与明細一覧画面かどうか判定する。
	 *
	 * @return bool 給与明細の一覧画面なら true。
	 */
	private static function is_salary_list_screen() {
		if ( ! is_admin() ) {
			return false;
		}
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		// edit.php?post_type=salary（投稿タイプ一覧）でのみ表示。
		return ( 'edit' === $screen->base && 'salary' === $screen->post_type );
	}

	/**
	 * 一括登録の処理対象になる給与テンプレ投稿一覧を取得する。
	 *
	 * 公開（publish）/ 非公開（private）のテンプレが処理対象。
	 * draft のテンプレは未完成として誤配リスクがあるため除外する。
	 *
	 * @return WP_Post[] 給与テンプレ投稿の配列。
	 */
	private static function get_target_templates() {
		return get_posts(
			array(
				'post_type'      => 'salary-template',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * 一括登録の処理対象（公開 / 非公開）の給与テンプレ件数のみを軽量に取得する。
	 *
	 * 件数だけが欲しい箇所（パネル描画・JS への localize）で get_target_templates() を呼ぶと
	 * WP_Post 全件をメモリにロードしてしまうため、wp_count_posts() の集計値だけを使う。
	 * wp_count_posts() の戻り値は post_status をプロパティに持つ stdClass で、
	 * 該当 status が 0 件のときは publish / private プロパティが未定義になりうるため
	 * isset チェック + (int) キャストで防御する。
	 *
	 * @return int publish + private の合計件数。
	 */
	private static function get_target_template_count() {
		$counts = wp_count_posts( 'salary-template' );
		// wp_count_posts は通常 stdClass を返すが、想定外の戻り値（false など）に備えて型ガード。
		if ( ! is_object( $counts ) ) {
			return 0;
		}
		// publish / private が未定義のケースに備えて isset チェック + キャスト。
		$publish = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$private = isset( $counts->private ) ? (int) $counts->private : 0;
		return $publish + $private;
	}

	/**
	 * 支給分（salary-term）タクソノミーのターム一覧を取得する。
	 *
	 * @return WP_Term[] ターム配列。
	 */
	private static function get_salary_terms() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'salary-term',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return $terms;
	}

	/**
	 * 給与明細一覧画面の上部に一括登録パネルを描画する。
	 *
	 * 通常は <details> で折りたたまれた状態。
	 * 「結果通知が transient に存在する」または「前提（テンプレ / 支給分）が未整備」のときは open。
	 *
	 * @return void
	 */
	public static function render_panel() {
		if ( ! self::is_salary_list_screen() ) {
			return;
		}
		// 一括登録は管理者相当のみ許可。
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// パネル描画では実配列を使わないため、wp_count_posts() ベースの軽量カウントのみで判定する。
		// 実配列が必要なのは admin-post の handle() だけ。
		$terms          = self::get_salary_terms();
		$template_count = self::get_target_template_count();
		$has_templates  = $template_count > 0;
		$has_terms      = ! empty( $terms );

		// 折りたたみ状態の判定:
		// - 結果 transient があるとき（直近の操作結果を見せる）
		// - テンプレ / 支給分のいずれかが未整備のときはガイダンスを見せたいので open。
		$has_result_transient = (bool) get_transient( self::result_transient_key() );
		$is_open              = $has_result_transient || ! $has_templates || ! $has_terms;

		$action_url = admin_url( 'admin-post.php' );
		?>
		<details class="bvsl-bulk-create" <?php echo $is_open ? 'open' : ''; ?>>
			<summary>
				<?php echo esc_html__( '給与テンプレートから一括登録', 'bill-vektor-salary' ); ?>
			</summary>
			<div class="bvsl-bulk-create__body">

			<?php if ( ! $has_templates ) : ?>
				<?php // テンプレ 0 件時はフォームを出さず、登録案内のみ大きく出す。 ?>
				<p>
					<?php echo esc_html__( '給与テンプレートがまだ登録されていません。先にスタッフごとの給与テンプレートを作成してください。', 'bill-vektor-salary' ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=salary-template' ) ); ?>" class="button button-primary button-hero">
						<?php echo esc_html__( '給与テンプレートを作成', 'bill-vektor-salary' ); ?>
					</a>
				</p>
			<?php elseif ( ! $has_terms ) : ?>
				<?php // 支給分 0 件時はフォームを出さず、登録案内のみ。 ?>
				<p><?php echo esc_html__( '一括登録には「支給分」が必要です。先に支給分を登録してください。', 'bill-vektor-salary' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=salary-term&post_type=salary' ) ); ?>" class="button button-primary button-hero">
						<?php echo esc_html__( '支給分を登録する', 'bill-vektor-salary' ); ?>
					</a>
				</p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="bvsl-bulk-create__form" id="bvsl-bulk-create-form">
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />

					<p class="bvsl-bulk-create__lead">
						<?php
						printf(
							/* translators: %d: 一括展開の対象となる給与テンプレ件数（公開・非公開を含み、下書きは除外） */
							esc_html__( '一括展開の対象となる給与テンプレートは %d 件です（公開・非公開を含み、下書きは除外）。指定した支給分で全件を給与明細（下書き）として展開します。', 'bill-vektor-salary' ),
							(int) $template_count
						);
						?>
					</p>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="bvsl-bulk-term"><?php echo esc_html__( '支給分', 'bill-vektor-salary' ); ?></label>
								</th>
								<td>
									<select id="bvsl-bulk-term" name="bvsl_term_id" required>
										<option value=""><?php echo esc_html__( '選択してください', 'bill-vektor-salary' ); ?></option>
										<?php foreach ( $terms as $term ) : ?>
											<option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php echo esc_html__( '生成件数の目安', 'bill-vektor-salary' ); ?>
								</th>
								<td>
									<p id="bvsl-bulk-summary" class="bvsl-bulk-create__summary">
										<?php echo esc_html__( '支給分を選んでください。', 'bill-vektor-salary' ); ?>
									</p>
									<p class="bvsl-bulk-create__note">
										<?php echo esc_html__( '生成された給与明細はすべて下書きとして登録されます。同一スタッフの同一支給分の明細が既にある場合は自動でスキップされます。スタッフ未設定のテンプレートもスキップされます。', 'bill-vektor-salary' ); ?>
									</p>
									<?php if ( $template_count > self::MAX_TEMPLATE_COUNT ) : ?>
										<p class="bvsl-bulk-create__warning">
											<?php
											printf(
												/* translators: 1: 上限件数 2: 対象の給与テンプレ件数 */
												esc_html__( '一度に処理できるテンプレートは最大 %1$d 件までです（現在 %2$d 件）。', 'bill-vektor-salary' ),
												(int) self::MAX_TEMPLATE_COUNT,
												(int) $template_count
											);
											?>
										</p>
									<?php endif; ?>
								</td>
							</tr>
						</tbody>
					</table>

					<p>
						<button type="submit" class="button button-primary" id="bvsl-bulk-submit" <?php echo ( $template_count > self::MAX_TEMPLATE_COUNT ) ? 'disabled' : ''; ?>>
							<?php echo esc_html__( '一括登録（下書きで作成）', 'bill-vektor-salary' ); ?>
						</button>
					</p>
				</form>
			<?php endif; ?>

			</div>
		</details>
		<?php
	}

	/**
	 * 一括登録の admin-post ハンドラ。
	 *
	 * 入力検証 → 件数上限チェック → 処理対象テンプレ（公開 / 非公開）全件をループしながら
	 * 各テンプレを bill_copy_post で salary に複製 → スタッフ・支給分メタを上書き → タイトル整形。
	 * スキップ理由は重複・スタッフ未設定の 2 種類で分けてカウントする。
	 * 結果は transient に格納し、リダイレクト先で表示・削除する。
	 *
	 * 注意: bill_copy_post() はテーマ側の関数で、テンプレ投稿の全 meta（_ で始まらないもの）を
	 * salary 投稿に複製する。将来テンプレ専用の機微 meta（編集者情報など、salary に持っていきたくないもの）
	 * が増える場合は、このルートでは情報漏洩のリスクがあるため、メタを allowlist 方式で
	 * 明示コピーする独自複製関数に切り替えること。
	 *
	 * @return void
	 */
	public static function handle() {
		// 権限チェック。
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '一括登録の実行権限がありません。', 'bill-vektor-salary' ) );
		}
		// nonce 検証。
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$redirect_base = add_query_arg(
			array( 'post_type' => 'salary' ),
			admin_url( 'edit.php' )
		);

		// 入力サニタイズ。
		$term_id = isset( $_POST['bvsl_term_id'] ) ? (int) $_POST['bvsl_term_id'] : 0;

		// 入力チェック。
		$term = $term_id > 0 ? get_term( $term_id, 'salary-term' ) : null;
		if ( ! $term || is_wp_error( $term ) ) {
			self::store_result( array( 'error' => 'invalid_term' ) );
			wp_safe_redirect( $redirect_base );
			exit;
		}

		// 処理対象テンプレ（公開 / 非公開）を全件取得。
		// ここはループで実体（WP_Post）が必要なので wp_count_posts() ベースのカウントには置き換えない。
		$templates = self::get_target_templates();
		if ( empty( $templates ) ) {
			self::store_result( array( 'error' => 'no_templates' ) );
			wp_safe_redirect( $redirect_base );
			exit;
		}

		// 件数上限チェック（対象テンプレ件数で判定）。
		if ( count( $templates ) > self::MAX_TEMPLATE_COUNT ) {
			self::store_result( array( 'error' => 'too_many_templates' ) );
			wp_safe_redirect( $redirect_base );
			exit;
		}

		// テーマ側の bill_copy_post() がある前提。なければエラー。
		if ( ! function_exists( 'bill_copy_post' ) ) {
			self::store_result( array( 'error' => 'no_copy_function' ) );
			wp_safe_redirect( $redirect_base );
			exit;
		}

		$created               = 0;
		$skipped_duplicate     = 0;
		$skipped_no_staff      = 0;
		$skipped_other         = 0;
		$skipped_dup_titles    = array();
		$skipped_no_staff_tpls = array();

		foreach ( $templates as $template ) {
			$template_id = (int) $template->ID;
			if ( $template_id <= 0 ) {
				continue;
			}

			// テンプレからスタッフ ID を取得。
			$staff_id = (int) get_post_meta( $template_id, 'salary_staff', true );
			if ( $staff_id <= 0 ) {
				// スタッフ未設定のテンプレはスキップ（カテゴリ別カウント）。
				++$skipped_no_staff;
				$skipped_no_staff_tpls[] = (string) get_the_title( $template );
				continue;
			}

			// 同一スタッフ × 同一支給分の明細が既に存在する場合はスキップ。
			if ( self::salary_exists_for_staff_and_term( $staff_id, $term_id ) ) {
				++$skipped_duplicate;
				$skipped_dup_titles[] = (string) get_the_title( $staff_id );
				continue;
			}

			// テンプレを salary 投稿として複製する。
			// $table_copy_type = 'all' で品目テーブルもコピー。
			// $duplicate_type  = 'full' でカスタムフィールドとタクソノミーをまとめてコピー。
			// 注意: bill_copy_post() はテンプレの全 meta を salary に複製する（クラス PHPDoc 参照）。
			$new_post_id = bill_copy_post( $template_id, 'salary', 'all', 'full' );
			if ( ! $new_post_id || is_wp_error( $new_post_id ) ) {
				++$skipped_other;
				continue;
			}

			// スタッフメタを念のため明示的に上書き（テンプレ複製の段階で入っているはずだが防御的に）。
			update_post_meta( $new_post_id, 'salary_staff', (string) $staff_id );
			$staff_number = (string) get_post_meta( $template_id, 'salary_staff_number', true );
			if ( '' === $staff_number ) {
				// テンプレ側に Staff No. が無ければスタッフ投稿側から引く。
				$staff_number = (string) get_post_meta( $staff_id, 'salary_staff_number', true );
			}
			if ( '' !== $staff_number ) {
				update_post_meta( $new_post_id, 'salary_staff_number', $staff_number );
			}

			// 支給分（salary-term）を付与。テンプレ複製の段階では付いていないため明示的に上書き。
			wp_set_object_terms( $new_post_id, array( (int) $term_id ), 'salary-term', false );

			// タイトルを「スタッフ名 / 支給分」で揃え、同時に post_status を draft に固定。
			//
			// 注意（タイトル二重更新の回避）:
			// save_post に紐づく bvsl_title_auto_save() は $_POST['post_title'] が空の場合のみ動作する。
			// admin-post 経由のこのハンドラでは post_title は POST に乗らないため、
			// このまま wp_update_post を呼ぶと bvsl_title_auto_save が割り込んで
			// salary_staff からタイトルを再生成してしまい、「/ 支給分」部分が落ちる現象が起こり得る。
			// それを避けるため、wp_update_post の前後で bvsl_title_auto_save を一時的に外し、
			// 1リクエスト内のタイトル更新を 1 回に集約している。
			//
			// bvsl_title_auto_save はデフォルト優先度（10）で登録されているので、
			// remove/add は明示的に 10 を指定する。has_action の戻り値に依存せず意図を明確にする。
			$title                 = trim( get_the_title( $staff_id ) . ' / ' . $term->name, ' /' );
			$title_hook_registered = ( false !== has_action( 'save_post', 'bvsl_title_auto_save' ) );
			if ( $title_hook_registered ) {
				remove_action( 'save_post', 'bvsl_title_auto_save', 10 );
			}
			wp_update_post(
				array(
					'ID'          => $new_post_id,
					'post_status' => 'draft',
					'post_title'  => '' !== $title ? $title : get_the_title( $new_post_id ),
				)
			);
			if ( $title_hook_registered ) {
				add_action( 'save_post', 'bvsl_title_auto_save', 10 );
			}

			++$created;
		}

		// 結果を transient に格納（クエリ汚染を避け、偽通知を踏ませない）。
		self::store_result(
			array(
				'created'               => $created,
				'skipped_duplicate'     => $skipped_duplicate,
				'skipped_no_staff'      => $skipped_no_staff,
				'skipped_other'         => $skipped_other,
				'skipped_dup_titles'    => $skipped_dup_titles,
				'skipped_no_staff_tpls' => $skipped_no_staff_tpls,
				'term_name'             => (string) $term->name,
			)
		);

		wp_safe_redirect( $redirect_base );
		exit;
	}

	/**
	 * 結果通知用の値を transient に格納する。
	 *
	 * @param array<string, mixed> $payload 通知に使う値。
	 * @return void
	 */
	private static function store_result( array $payload ) {
		set_transient( self::result_transient_key(), $payload, self::RESULT_TRANSIENT_TTL );
	}

	/**
	 * 同一スタッフ × 同一支給分の salary 投稿が既にあるか判定する。
	 *
	 * @param int $staff_id スタッフ投稿ID（salary_staff メタ）。
	 * @param int $term_id  salary-term の term_id。
	 * @return bool 既にあれば true。
	 */
	private static function salary_exists_for_staff_and_term( $staff_id, $term_id ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'salary',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => 'salary_staff',
						'value' => (string) $staff_id,
					),
				),
				'tax_query'      => array(
					array(
						'taxonomy' => 'salary-term',
						'field'    => 'term_id',
						'terms'    => array( (int) $term_id ),
					),
				),
			)
		);
		return $query->have_posts();
	}

	/**
	 * 一括登録結果の管理画面通知を描画する。
	 *
	 * 結果は transient から取得し、表示後すぐに削除する（使い捨て）。
	 * 任意ユーザーがクエリパラメータ偽装で通知を踏むことを防ぐ。
	 *
	 * @return void
	 */
	public static function render_result_notice() {
		if ( ! self::is_salary_list_screen() ) {
			return;
		}
		// 結果通知も manage_options 権限を要求する。
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$key    = self::result_transient_key();
		$result = get_transient( $key );
		if ( false === $result || ! is_array( $result ) ) {
			return;
		}
		// 使い捨て。
		delete_transient( $key );

		// エラー系。
		if ( ! empty( $result['error'] ) ) {
			$messages = array(
				'invalid_term'       => __( '支給分が選択されていないか、無効です。', 'bill-vektor-salary' ),
				'no_templates'       => __( '対象となる給与テンプレートが見つかりません。', 'bill-vektor-salary' ),
				'too_many_templates' => sprintf(
					/* translators: %d: 上限件数 */
					__( '対象テンプレートが多すぎます（上限 %d 件）。', 'bill-vektor-salary' ),
					(int) self::MAX_TEMPLATE_COUNT
				),
				'no_copy_function'   => __( '親テーマ「BillVektor」が有効化されていないため、一括登録を実行できません。', 'bill-vektor-salary' ),
			);
			$err = (string) $result['error'];
			$msg = isset( $messages[ $err ] ) ? $messages[ $err ] : __( '一括登録に失敗しました。', 'bill-vektor-salary' );
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $msg ); ?></p>
			</div>
			<?php
			return;
		}

		// 成功系。
		$created           = isset( $result['created'] ) ? (int) $result['created'] : 0;
		$skipped_duplicate = isset( $result['skipped_duplicate'] ) ? (int) $result['skipped_duplicate'] : 0;
		$skipped_no_staff  = isset( $result['skipped_no_staff'] ) ? (int) $result['skipped_no_staff'] : 0;
		$skipped_other     = isset( $result['skipped_other'] ) ? (int) $result['skipped_other'] : 0;

		$dup_titles = isset( $result['skipped_dup_titles'] ) && is_array( $result['skipped_dup_titles'] )
			? array_values( array_filter( array_map( 'strval', $result['skipped_dup_titles'] ) ) )
			: array();
		$no_staff_titles = isset( $result['skipped_no_staff_tpls'] ) && is_array( $result['skipped_no_staff_tpls'] )
			? array_values( array_filter( array_map( 'strval', $result['skipped_no_staff_tpls'] ) ) )
			: array();

		$message = sprintf(
			/* translators: 1: 生成件数 2: 重複スキップ件数 3: スタッフ未設定スキップ件数 */
			__( '給与明細を一括登録しました。生成: %1$d 件 / 重複スキップ: %2$d 件 / スタッフ未設定スキップ: %3$d 件', 'bill-vektor-salary' ),
			max( 0, $created ),
			max( 0, $skipped_duplicate ),
			max( 0, $skipped_no_staff )
		);
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>

			<?php if ( ! empty( $dup_titles ) ) : ?>
				<details style="margin: 4px 0 8px;">
					<summary style="cursor:pointer;">
						<?php echo esc_html__( '重複でスキップしたスタッフ（既に同一支給分の明細あり）', 'bill-vektor-salary' ); ?>
					</summary>
					<p style="margin: 6px 0 0;"><?php echo esc_html( implode( '、', $dup_titles ) ); ?></p>
				</details>
			<?php endif; ?>

			<?php if ( ! empty( $no_staff_titles ) ) : ?>
				<details style="margin: 4px 0 8px;">
					<summary style="cursor:pointer;">
						<?php echo esc_html__( 'スタッフ未設定でスキップしたテンプレート', 'bill-vektor-salary' ); ?>
					</summary>
					<p style="margin: 6px 0 0;"><?php echo esc_html( implode( '、', $no_staff_titles ) ); ?></p>
				</details>
			<?php endif; ?>

			<?php if ( $skipped_other > 0 ) : ?>
				<p style="margin: 4px 0 0; color:#555;">
					<?php
					printf(
						/* translators: %d: その他のエラー件数 */
						esc_html__( 'その他の理由で %d 件スキップされました（複製処理に失敗）。', 'bill-vektor-salary' ),
						(int) $skipped_other
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
BVSL_Bulk_Create_From_Template::init();
