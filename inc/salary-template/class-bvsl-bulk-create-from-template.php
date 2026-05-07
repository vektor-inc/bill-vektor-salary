<?php
/**
 * 給与テンプレートからの一括登録クラス。
 *
 * 給与明細（salary）一覧画面の上部に折りたたみ可能なパネルを描画し、
 * テンプレート × 支給分 × 対象スタッフ複数 の組み合わせで salary 投稿を下書きで一括生成する。
 *
 * vk-booking-manager-pro の class-shift-editor.php の bulk_create パターンを参考にしている。
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
	 * 一括登録の対象スタッフ数の上限。
	 *
	 * 上限を超えるリクエストは admin notice でエラー表示して拒否する。
	 * 大量生成による負荷・誤操作の被害範囲を抑える保険。
	 */
	const MAX_STAFF_COUNT = 500;

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
		// admin-post.php エンドポイント。
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
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
	 * 一括登録元として選択可能な salary-template の投稿一覧を取得する。
	 *
	 * 「下書き」のテンプレは未完成として誤配リスクがあるため除外し、
	 * publish / private のみを対象にする。
	 *
	 * @return WP_Post[] テンプレ投稿の配列。
	 */
	private static function get_template_posts() {
		return get_posts(
			array(
				'post_type'      => 'salary-template',
				// 指摘 F: draft は誤配リスクのため除外。publish / private のみ。
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * 一括登録の対象になりうるスタッフ投稿を取得する。
	 *
	 * client_hidden が立っているスタッフは除外する（プルダウン同様）。
	 *
	 * @return WP_Post[] スタッフ投稿の配列。
	 */
	private static function get_staff_posts() {
		$args  = array(
			'post_type'      => 'staff',
			'post_status'    => array( 'publish' ),
			'posts_per_page' => -1,
			'meta_key'       => 'salary_staff_number',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		);
		$posts = get_posts( $args );
		// salary_staff_number 未設定のスタッフが取りこぼされないよう、再取得して合流させる。
		$all   = get_posts(
			array(
				'post_type'      => 'staff',
				'post_status'    => array( 'publish' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$known = array();
		foreach ( $posts as $p ) {
			$known[ $p->ID ] = true;
		}
		foreach ( $all as $p ) {
			if ( ! isset( $known[ $p->ID ] ) ) {
				$posts[] = $p;
			}
		}
		// プルダウン非表示スタッフを除外。
		$visible = array();
		foreach ( $posts as $p ) {
			$hidden = get_post_meta( $p->ID, 'client_hidden', true );
			if ( $hidden ) {
				continue;
			}
			$visible[] = $p;
		}
		return $visible;
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
	 * 「結果通知が transient に存在する」または「補助導線から bvsl_template_id が渡された」ときは open。
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

		$templates = self::get_template_posts();
		$staff     = self::get_staff_posts();
		$terms     = self::get_salary_terms();

		// テンプレが1件もない場合は作成導線を出す。
		$has_templates = ! empty( $templates );
		$has_terms     = ! empty( $terms );
		$has_staff     = ! empty( $staff );

		// 補助導線（給与テンプレート編集画面の「このテンプレで一括登録」）から渡された選択済みテンプレID。
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 単なる初期選択値として読むだけのため nonce は不要。
		$preset_template_id = isset( $_GET['bvsl_template_id'] ) ? (int) $_GET['bvsl_template_id'] : 0;

		// 指摘 D: 折りたたみ状態の判定。
		// 結果 transient があるとき or 補助導線から来たとき or（運用上の親切として）
		// テンプレ未作成 / 支給分未登録 / スタッフ未登録のいずれかでガイダンスを見せたい場合は開いておく。
		$has_result_transient = (bool) get_transient( self::result_transient_key() );
		$is_open              = $has_result_transient
			|| $preset_template_id > 0
			|| ! $has_templates
			|| ! $has_terms
			|| ! $has_staff;

		$action_url = admin_url( 'admin-post.php' );
		?>
		<details class="bvsl-bulk-create" <?php echo $is_open ? 'open' : ''; ?> style="margin: 16px 0; padding: 12px 16px; background:#fff; border:1px solid #ccd0d4; border-left: 4px solid #2271b1;">
			<summary style="cursor:pointer; font-weight:600; font-size:14px; padding: 4px 0;">
				<?php echo esc_html__( '給与テンプレートから一括登録', 'bill-vektor-salary' ); ?>
			</summary>
			<div style="padding-top: 12px;">

			<?php if ( ! $has_templates ) : ?>
				<?php // 指摘 J: テンプレ0件時は他のフォーム要素を出さず、案内＋遷移ボタンのみ大きく出す。 ?>
				<p><?php echo esc_html__( '給与テンプレートがまだ登録されていません。先にテンプレートを作成してください。', 'bill-vektor-salary' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=salary-template' ) ); ?>" class="button button-primary button-hero">
						<?php echo esc_html__( '給与テンプレートを作成', 'bill-vektor-salary' ); ?>
					</a>
				</p>
			<?php elseif ( ! $has_terms ) : ?>
				<?php // 指摘 J: 支給分0件時はフォームを出さず、登録案内のみ。 ?>
				<p><?php echo esc_html__( '一括登録には「支給分」が必要です。先に支給分を登録してください。', 'bill-vektor-salary' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=salary-term&post_type=salary' ) ); ?>" class="button button-primary button-hero">
						<?php echo esc_html__( '支給分を登録する', 'bill-vektor-salary' ); ?>
					</a>
				</p>
			<?php elseif ( ! $has_staff ) : ?>
				<?php // 指摘 J: スタッフ0件時はフォームを出さず、登録案内のみ。 ?>
				<p><?php echo esc_html__( '一括登録の対象になるスタッフが登録されていません。先にスタッフを登録してください。', 'bill-vektor-salary' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=staff' ) ); ?>" class="button button-primary button-hero">
						<?php echo esc_html__( 'スタッフを登録する', 'bill-vektor-salary' ); ?>
					</a>
				</p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="bvsl-bulk-create__form" id="bvsl-bulk-create-form">
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="bvsl-bulk-template"><?php echo esc_html__( 'テンプレート', 'bill-vektor-salary' ); ?></label>
								</th>
								<td>
									<select id="bvsl-bulk-template" name="bvsl_template_id" required>
										<option value=""><?php echo esc_html__( '選択してください', 'bill-vektor-salary' ); ?></option>
										<?php foreach ( $templates as $tpl ) : ?>
											<option value="<?php echo esc_attr( $tpl->ID ); ?>" <?php selected( $preset_template_id, $tpl->ID ); ?>>
												<?php echo esc_html( get_the_title( $tpl ) ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
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
									<?php // 指摘 I: fieldset/legend でアクセシビリティ対応。 ?>
									<?php echo esc_html__( '対象スタッフ', 'bill-vektor-salary' ); ?>
								</th>
								<td>
									<fieldset>
										<legend class="screen-reader-text"><?php echo esc_html__( '対象スタッフ', 'bill-vektor-salary' ); ?></legend>
										<p style="margin-top:0;">
											<button type="button" class="button button-small" id="bvsl-bulk-staff-all" aria-controls="bvsl-bulk-staff-list">
												<?php echo esc_html__( 'すべて選択', 'bill-vektor-salary' ); ?>
											</button>
											<button type="button" class="button button-small" id="bvsl-bulk-staff-none" aria-controls="bvsl-bulk-staff-list">
												<?php echo esc_html__( 'すべて解除', 'bill-vektor-salary' ); ?>
											</button>
										</p>
										<div id="bvsl-bulk-staff-list" style="display:flex; flex-wrap:wrap; gap:6px 16px; max-height:160px; overflow-y:auto; padding:8px; border:1px solid #dcdcde; border-radius:4px;">
											<?php foreach ( $staff as $s ) : ?>
												<label style="display:inline-flex; align-items:center; gap:4px; min-width:180px;">
													<?php // 指摘 G: 初期は全員未チェック。明示的に「すべて選択」または個別チェックさせる。 ?>
													<input type="checkbox" class="bvsl-bulk-staff-checkbox" name="bvsl_staff_ids[]" value="<?php echo esc_attr( $s->ID ); ?>" />
													<span><?php echo esc_html( get_the_title( $s ) ); ?></span>
												</label>
											<?php endforeach; ?>
										</div>
										<p class="description" style="margin-top:6px;">
											<?php
											printf(
												/* translators: %d: 一括登録の上限件数 */
												esc_html__( '一度に登録できるのは最大 %d 名までです。', 'bill-vektor-salary' ),
												(int) self::MAX_STAFF_COUNT
											);
											?>
										</p>
									</fieldset>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php echo esc_html__( '生成件数の目安', 'bill-vektor-salary' ); ?>
								</th>
								<td>
									<p id="bvsl-bulk-summary" style="margin:0;">
										<?php echo esc_html__( '対象スタッフを選んでください。', 'bill-vektor-salary' ); ?>
									</p>
									<p style="margin:6px 0 0; color:#555;">
										<?php echo esc_html__( '生成された給与明細はすべて下書きとして登録されます。同一スタッフの同一支給分の明細が既にある場合は自動でスキップされます。', 'bill-vektor-salary' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>

					<p>
						<button type="submit" class="button button-primary" id="bvsl-bulk-submit">
							<?php echo esc_html__( '一括登録（下書きで作成）', 'bill-vektor-salary' ); ?>
						</button>
					</p>
				</form>

				<script>
				( function() {
					var form    = document.getElementById( 'bvsl-bulk-create-form' );
					if ( ! form ) {
						return;
					}
					var allBtn  = document.getElementById( 'bvsl-bulk-staff-all' );
					var noneBtn = document.getElementById( 'bvsl-bulk-staff-none' );
					var termSel = document.getElementById( 'bvsl-bulk-term' );
					var summary = document.getElementById( 'bvsl-bulk-summary' );

					// 翻訳テンプレート（指摘 E に対応して 1 文化したもの）。
					// %1$d / %2$s をフロントで sprintf 風に置換する。
					var i18n = {
						selectStaff:    <?php echo wp_json_encode( __( '対象スタッフを選んでください。', 'bill-vektor-salary' ) ); ?>,
						selectTerm:     <?php echo wp_json_encode( __( '%1$d 名選択中です。支給分を選んでください。', 'bill-vektor-salary' ) ); ?>,
						summary:        <?php echo wp_json_encode( __( '%1$d 名分の給与明細を「%2$s」で下書き作成します。', 'bill-vektor-salary' ) ); ?>,
						confirm:        <?php echo wp_json_encode( __( '%1$d 名分の給与明細を「%2$s」で下書き作成します。よろしいですか？', 'bill-vektor-salary' ) ); ?>,
						selectAtLeastOne: <?php echo wp_json_encode( __( '対象スタッフを少なくとも1名選択してください。', 'bill-vektor-salary' ) ); ?>,
						tooMany:        <?php echo wp_json_encode( sprintf( __( '対象スタッフが多すぎます（上限 %d 名）。選択を減らしてください。', 'bill-vektor-salary' ), (int) self::MAX_STAFF_COUNT ) ); ?>
					};
					var maxStaff = <?php echo (int) self::MAX_STAFF_COUNT; ?>;

					function format( tpl, n, term ) {
						return tpl.replace( '%1$d', n ).replace( '%2$s', term );
					}

					function checkboxes() {
						return form.querySelectorAll( '.bvsl-bulk-staff-checkbox' );
					}

					function termText() {
						if ( termSel && termSel.value && termSel.options[ termSel.selectedIndex ] ) {
							return termSel.options[ termSel.selectedIndex ].text;
						}
						return '';
					}

					// 件数表示と確認ダイアログ用の文言を更新する（指摘 E：1文化）。
					function updateSummary() {
						var checked = 0;
						checkboxes().forEach( function ( cb ) {
							if ( cb.checked ) { checked++; }
						} );
						var term = termText();
						if ( checked === 0 ) {
							summary.textContent = i18n.selectStaff;
						} else if ( '' === term ) {
							summary.textContent = format( i18n.selectTerm, checked, '' );
						} else {
							summary.textContent = format( i18n.summary, checked, term );
						}
					}

					if ( allBtn ) {
						allBtn.addEventListener( 'click', function () {
							checkboxes().forEach( function ( cb ) { cb.checked = true; } );
							updateSummary();
						} );
					}
					if ( noneBtn ) {
						noneBtn.addEventListener( 'click', function () {
							checkboxes().forEach( function ( cb ) { cb.checked = false; } );
							updateSummary();
						} );
					}
					checkboxes().forEach( function ( cb ) {
						cb.addEventListener( 'change', updateSummary );
					} );
					if ( termSel ) {
						termSel.addEventListener( 'change', updateSummary );
					}

					// 送信前の最終確認。誤操作の保険として件数を提示する。
					form.addEventListener( 'submit', function ( e ) {
						var checked = 0;
						checkboxes().forEach( function ( cb ) {
							if ( cb.checked ) { checked++; }
						} );
						if ( checked === 0 ) {
							e.preventDefault();
							window.alert( i18n.selectAtLeastOne );
							return;
						}
						if ( checked > maxStaff ) {
							e.preventDefault();
							window.alert( i18n.tooMany );
							return;
						}
						var term = termText();
						if ( '' === term ) {
							// 支給分未選択時は HTML 側の required で弾かれるはずだが念のため。
							return;
						}
						if ( ! window.confirm( format( i18n.confirm, checked, term ) ) ) {
							e.preventDefault();
						}
					} );

					updateSummary();
				} )();
				</script>
			<?php endif; ?>

			</div>
		</details>
		<?php
	}

	/**
	 * 一括登録の admin-post ハンドラ。
	 *
	 * 入力検証 → 件数上限チェック → 既存重複スキップ判定 → bill_copy_post でテンプレを複製 →
	 * salary_staff / salary_staff_number の上書き + salary-term の付与、を選択スタッフ分ループ。
	 * 結果は transient に格納し、リダイレクト先で表示・削除する。
	 *
	 * 注意: bill_copy_post() はテーマ側の関数で、テンプレ投稿の全 meta（_ で始まらないもの）を
	 * salary 投稿に複製する。将来テンプレ専用の機微 meta（編集者情報など、salary に持っていきたくないもの）
	 * が増える場合は、このルートでは情報漏洩のリスクがあるため、メタを allowlist 方式で
	 * 明示コピーする独自複製関数に切り替えること（指摘 K）。
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
		$template_id = isset( $_POST['bvsl_template_id'] ) ? (int) $_POST['bvsl_template_id'] : 0;
		$term_id     = isset( $_POST['bvsl_term_id'] ) ? (int) $_POST['bvsl_term_id'] : 0;
		$staff_ids   = array();
		if ( isset( $_POST['bvsl_staff_ids'] ) && is_array( $_POST['bvsl_staff_ids'] ) ) {
			foreach ( wp_unslash( $_POST['bvsl_staff_ids'] ) as $sid ) {
				$sid = (int) $sid;
				if ( $sid > 0 ) {
					$staff_ids[] = $sid;
				}
			}
			$staff_ids = array_values( array_unique( $staff_ids ) );
		}

		// 入力チェック。
		$errors = array();
		if ( $template_id <= 0 || 'salary-template' !== get_post_type( $template_id ) ) {
			$errors[] = 'invalid_template';
		}
		$term = $term_id > 0 ? get_term( $term_id, 'salary-term' ) : null;
		if ( ! $term || is_wp_error( $term ) ) {
			$errors[] = 'invalid_term';
		}
		if ( empty( $staff_ids ) ) {
			$errors[] = 'no_staff';
		}
		// 指摘 C: 件数上限チェック（500件）。負荷・誤操作の被害範囲を抑える保険。
		if ( count( $staff_ids ) > self::MAX_STAFF_COUNT ) {
			$errors[] = 'too_many_staff';
		}
		if ( ! empty( $errors ) ) {
			// エラーは結果 transient に積んでリダイレクト（クエリ汚染を避ける）。
			self::store_result(
				array(
					'error' => sanitize_key( $errors[0] ),
				)
			);
			wp_safe_redirect( $redirect_base );
			exit;
		}

		// テーマ側の bill_copy_post() がある前提。なければエラー。
		if ( ! function_exists( 'bill_copy_post' ) ) {
			self::store_result( array( 'error' => 'no_copy_function' ) );
			wp_safe_redirect( $redirect_base );
			exit;
		}

		$created             = 0;
		$skipped             = 0;
		$skipped_staff_names = array();

		foreach ( $staff_ids as $staff_id ) {
			// 同一スタッフ × 同一支給分の明細が既に存在する場合はスキップ（誤操作の保険）。
			if ( self::salary_exists_for_staff_and_term( $staff_id, $term_id ) ) {
				++$skipped;
				$skipped_staff_names[] = (string) get_the_title( $staff_id );
				continue;
			}

			// テンプレートを salary 投稿として複製する。
			// $table_copy_type = 'all' で品目テーブルもコピー。
			// $duplicate_type  = 'full' でカスタムフィールドとタクソノミーをまとめてコピー。
			// 注意: bill_copy_post() はテンプレの全 meta を salary に複製する（指摘 K のコメント参照）。
			$new_post_id = bill_copy_post( $template_id, 'salary', 'all', 'full' );
			if ( ! $new_post_id || is_wp_error( $new_post_id ) ) {
				++$skipped;
				$skipped_staff_names[] = (string) get_the_title( $staff_id );
				continue;
			}

			// 対象スタッフのメタ情報をテンプレ複製後に上書きする。
			update_post_meta( $new_post_id, 'salary_staff', (string) $staff_id );
			$staff_number = (string) get_post_meta( $staff_id, 'salary_staff_number', true );
			if ( '' !== $staff_number ) {
				update_post_meta( $new_post_id, 'salary_staff_number', $staff_number );
			}

			// 支給分（salary-term）を付与。テンプレ複製の段階で付いていない可能性があるため明示的に上書き。
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
				'created'             => $created,
				'skipped'             => $skipped,
				'skipped_staff_names' => $skipped_staff_names,
				'term_name'           => (string) $term->name,
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
		// 指摘 A: 結果通知も manage_options 権限を要求する。
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
				'invalid_template' => __( 'テンプレートが選択されていないか、無効です。', 'bill-vektor-salary' ),
				'invalid_term'     => __( '支給分が選択されていないか、無効です。', 'bill-vektor-salary' ),
				'no_staff'         => __( '対象スタッフを少なくとも1名選択してください。', 'bill-vektor-salary' ),
				'too_many_staff'   => sprintf(
					/* translators: %d: 上限件数 */
					__( '対象スタッフが多すぎます（上限 %d 名）。選択を減らしてください。', 'bill-vektor-salary' ),
					(int) self::MAX_STAFF_COUNT
				),
				'no_copy_function' => __( '親テーマ「BillVektor」が有効化されていないため、一括登録を実行できません。', 'bill-vektor-salary' ),
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
		$created             = isset( $result['created'] ) ? (int) $result['created'] : 0;
		$skipped             = isset( $result['skipped'] ) ? (int) $result['skipped'] : 0;
		$skipped_staff_names = isset( $result['skipped_staff_names'] ) && is_array( $result['skipped_staff_names'] )
			? array_values( array_filter( array_map( 'strval', $result['skipped_staff_names'] ) ) )
			: array();

		$message = sprintf(
			/* translators: 1: 生成件数 2: スキップ件数 */
			__( '給与明細を一括登録しました。生成: %1$d 件 / スキップ: %2$d 件', 'bill-vektor-salary' ),
			max( 0, $created ),
			max( 0, $skipped )
		);
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
			<?php if ( ! empty( $skipped_staff_names ) ) : ?>
				<?php
				// 指摘 B: スキップしたスタッフ名を列挙。長くなる場合に備えて details で折りたたみ。
				$names_text = implode( '、', $skipped_staff_names );
				?>
				<details style="margin: 4px 0 8px;">
					<summary style="cursor:pointer;">
						<?php echo esc_html__( 'スキップしたスタッフ（既に同一支給分の明細あり）', 'bill-vektor-salary' ); ?>
					</summary>
					<p style="margin: 6px 0 0;"><?php echo esc_html( $names_text ); ?></p>
				</details>
			<?php endif; ?>
		</div>
		<?php
	}
}
BVSL_Bulk_Create_From_Template::init();
