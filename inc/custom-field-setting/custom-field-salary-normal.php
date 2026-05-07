<?php
/**
 * 給与明細 / 給与テンプレートのカスタムフィールド（品目以外）。
 *
 * salary（給与明細）と salary-template（給与テンプレート）で同じカスタムフィールド構成を共有する。
 * 表示する/しないの差分は $context（投稿タイプ）で切り替える。
 *
 * @package Bill_Vektor_Salary
 */

class Salary_Normal_Custom_Fields {

	/**
	 * このカスタムフィールド群を適用する投稿タイプ。
	 *
	 * salary（給与明細）と salary-template（給与テンプレート）で同一構成を再利用する。
	 *
	 * @var string[]
	 */
	protected static $post_types = array( 'salary', 'salary-template' );

	/**
	 * フックを登録する。
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_metabox' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save_custom_fields' ), 10, 2 );
	}

	/**
	 * 対象投稿タイプそれぞれにメタボックスを追加する。
	 *
	 * 投稿タイプごとにタイトルを切り替えて、給与明細とテンプレートでどちらを編集中か分かるようにする。
	 *
	 * @return void
	 */
	public static function add_metabox() {
		foreach ( self::$post_types as $post_type ) {
			// 指摘 H: post_type ごとにメタボックスのタイトルを分岐させる。
			$title = ( 'salary-template' === $post_type )
				? '給与テンプレート基本項目'
				: '給与明細基本項目';

			add_meta_box(
				'meta_box_bill_normal',
				$title,
				array( __CLASS__, 'fields_form' ),
				$post_type,
				'advanced',
				'high'
			);
		}
	}

	/**
	 * メタボックス内のフォームを描画する。
	 *
	 * 現在編集中の投稿タイプに応じて、表示する項目（フィールド・履歴テーブル）を切り替える。
	 *
	 * @return void
	 */
	public static function fields_form() {
		global $post;

		$context             = $post ? get_post_type( $post ) : 'salary';
		$custom_fields_array = self::custom_fields_array( $context );
		$befor_custom_fields = '';
		VK_Custom_Field_Builder::form_table( $custom_fields_array, $befor_custom_fields );

		// PDF / メール履歴は salary でのみ表示。テンプレでは履歴自体が存在しないため非表示。
		if ( 'salary' === $context ) {
			// PDF履歴テーブルをフォームテーブル直後に追加。
			if ( function_exists( 'bvsl_render_pdf_history_table' ) && $post && $post->ID ) {
				bvsl_render_pdf_history_table( $post->ID );
			}

			// メール送信履歴テーブルをフォームテーブル直後に追加。
			if ( function_exists( 'bvsl_render_mail_history_table' ) && $post && $post->ID ) {
				bvsl_render_mail_history_table( $post->ID );
			}
		}
	}

	/**
	 * カスタムフィールドを保存する。
	 *
	 * @param int $post_id 保存対象の投稿ID。
	 * @return void
	 */
	public static function save_custom_fields( $post_id = 0 ) {
		// 対象投稿タイプ以外の保存処理では何もしない。
		if ( ! in_array( get_post_type( $post_id ), self::$post_types, true ) ) {
			return;
		}

		$context             = get_post_type( $post_id );
		$custom_fields_array = self::custom_fields_array( $context );
		VK_Custom_Field_Builder::save_cf_value( $custom_fields_array );

		// 新規作成時など未選択の場合でも、メッセージ構成は既定値を保存する。
		$message_structure = (string) get_post_meta( $post_id, 'salary_message_structure', true );
		if ( '' === $message_structure ) {
			update_post_meta( $post_id, 'salary_message_structure', BVSL_SALARY_MESSAGE_STRUCTURE_MESSAGE_OR_COMMON );
		}
	}

	/**
	 * カスタムフィールド定義配列を返す。
	 *
	 * $context（投稿タイプ）に応じて、給与テンプレートでは不要なフィールド（スタッフ選択・PDF履歴用フィールド）を除外する。
	 *
	 * @param string $context 投稿タイプ（'salary' または 'salary-template'）。未指定時は salary 想定。
	 * @return array<string, array<string, mixed>> カスタムフィールド定義の連想配列。
	 */
	public static function custom_fields_array( $context = 'salary' ) {

		// スタッフ選択肢の構築は salary 用のみ必要。テンプレでは使用しないがコードの単純化のため共通で組み立てておく。
		$args        = array(
			'post_type'      => 'staff',
			'posts_per_page' => -1,
			'meta_key'       => 'salary_staff_number',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
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
				'description' => '※ 共通メッセージもメッセージも両方未記入の場合は、「今月もお疲れでした」になります。',
				'required'    => false,
			),
			'salary_message_structure' => array(
				'label'       => 'メッセージ構成',
				'type'        => 'radio',
				'description' => '',
				'options'     => array(
					BVSL_SALARY_MESSAGE_STRUCTURE_MESSAGE_OR_COMMON => 'メッセージの内容を反映。メッセージ が空の場合はタクソノミー「支給分」の「共通メッセージ」の内容を反映。',
					BVSL_SALARY_MESSAGE_STRUCTURE_COMMON_THEN_MESSAGE => '共通メッセージ + メッセージ',
					BVSL_SALARY_MESSAGE_STRUCTURE_MESSAGE_THEN_COMMON => 'メッセージ + 共通メッセージ',
				),
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
				'label'       => '発行済PDF（非推奨）',
				'type'        => 'file',
				'description' => '手動登録されたPDF URL。上記の「PDF発行」機能をご利用ください。',
				'hidden'      => true,
			),
		);

		// 給与テンプレートでは「対象スタッフ」は一括登録時に指定するため、テンプレ自体には不要。
		// PDF発行履歴用の salary_send_pdf もテンプレでは表示しない。
		if ( 'salary-template' === $context ) {
			unset( $custom_fields_array['salary_staff'] );
			unset( $custom_fields_array['salary_staff_number'] );
			unset( $custom_fields_array['salary_send_pdf'] );
		}

		return $custom_fields_array;
	}

}
Salary_Normal_Custom_Fields::init();
