<?php

/*
-------------------------------------------
Add Post Type Receipt
-------------------------------------------
*/
add_action( 'init', 'bill_add_post_type_staff', 0 );
function bill_add_post_type_staff() {
	register_post_type(
		'staff',
		array(
			'labels'             => array(
				'name'         => 'スタッフ',
				'edit_item'    => 'スタッフの編集',
				'add_new_item' => 'スタッフの作成',
			),
			'public'             => false,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'has_archive'        => true,
			'supports'           => array( 'title' ),
			'menu_icon'          => 'dashicons-media-spreadsheet',
			'menu_position'      => 7,
		// 'show_in_rest'       => true,
		// 'rest_base'          => 'staff',
		)
	);
	register_taxonomy(
		'staff-cat',
		'staff',
		array(
			'hierarchical'          => true,
			'update_count_callback' => '_update_post_term_count',
			'label'                 => 'スタッフカテゴリー',
			'singular_label'        => 'スタッフカテゴリー',
			'public'                => true,
			'show_ui'               => true,
		)
	);
}

add_filter( 'manage_staff_posts_columns', 'bill_staff_posts_columns' );
/**
 * スタッフ投稿一覧のカラムを調整する。
 *
 * 「タイトル」と「日付」の間にスタッフ関連の列を追加する。
 *
 * @param array $columns 投稿一覧に表示されるカラム配列。
 * @return array 追加後のカラム配列。
 */
function bill_staff_posts_columns( $columns ) {
	$new_columns = array();

	foreach ( $columns as $key => $label ) {
		$new_columns[ $key ] = $label;

		if ( 'title' === $key ) {
			$new_columns['salary_staff_status'] = 'スタッフステータス';
			$new_columns['salary_fuyou']        = '税扶養人数';
			$new_columns['salary_kenkou_hifuyousya'] = '健康保険被扶養人数';
			$new_columns['salary_birthday']     = '生年月日';
		}
	}

	return $new_columns;
}

add_action( 'manage_staff_posts_custom_column', 'bill_staff_posts_custom_column', 10, 2 );
/**
 * スタッフ投稿一覧のカスタムカラムに値を表示する。
 *
 * @param string $column_name 現在のカラム名。
 * @param int    $post_id     現在の投稿ID。
 * @return void
 */
function bill_staff_posts_custom_column( $column_name, $post_id ) {
	if ( 'salary_staff_status' === $column_name ) {
		$status = get_post_meta( $post_id, 'salary_staff_status', true );
		$labels = array(
			'employed'         => '勤務中',
			'retired'          => '退職',
			'leave_of_absence' => '休職',
		);

		if ( isset( $labels[ $status ] ) ) {
			echo esc_html( $labels[ $status ] );
		} else {
			echo '-';
		}

		echo '<span class="bill-staff-status-value" style="display:none;">' . esc_html( $status ) . '</span>';
		echo '<span class="bill-staff-kenkou-hifuyousya-value" style="display:none;">' . esc_html( get_post_meta( $post_id, 'salary_kenkou_hifuyousya', true ) ) . '</span>';
		echo '<span class="bill-staff-base-value" style="display:none;">' . esc_html( get_post_meta( $post_id, 'salary_base', true ) ) . '</span>';
		echo '<span class="bill-staff-transportation-value" style="display:none;">' . esc_html( get_post_meta( $post_id, 'salary_transportation_total', true ) ) . '</span>';
		$koyou_hoken = get_post_meta( $post_id, 'salary_koyouhoken', true );
		$koyou_hoken_checked = '0';
		if ( is_array( $koyou_hoken ) && in_array( 'not_auto_cal', $koyou_hoken, true ) ) {
			$koyou_hoken_checked = '1';
		}
		echo '<span class="bill-staff-koyouhoken-value" style="display:none;">' . esc_html( $koyou_hoken_checked ) . '</span>';
		return;
	}

	if ( 'salary_fuyou' === $column_name ) {
		$fuyou = get_post_meta( $post_id, 'salary_fuyou', true );
		if ( '' === $fuyou ) {
			echo '-';
		} else {
			echo esc_html( $fuyou );
		}

		echo '<span class="bill-staff-fuyou-value" style="display:none;">' . esc_html( $fuyou ) . '</span>';
		return;
	}

	if ( 'salary_kenkou_hifuyousya' === $column_name ) {
		$kenkou_hifuyousya = get_post_meta( $post_id, 'salary_kenkou_hifuyousya', true );
		if ( '' === $kenkou_hifuyousya ) {
			echo '-';
		} else {
			echo esc_html( $kenkou_hifuyousya );
		}
		return;
	}

	if ( 'salary_birthday' === $column_name ) {
		$salary_birthday = get_post_meta( $post_id, 'salary_birthday', true );
		if ( '' === $salary_birthday ) {
			echo '-';
		} else {
			echo esc_html( $salary_birthday );
		}
		return;
	}

	if ( 'salary_staff_status' !== $column_name && 'salary_fuyou' !== $column_name && 'salary_kenkou_hifuyousya' !== $column_name && 'salary_birthday' !== $column_name ) {
		echo '-';
	}
}

add_action( 'quick_edit_custom_box', 'bill_staff_quick_edit_custom_box', 10, 2 );
/**
 * スタッフ投稿一覧のクイック編集欄を追加する。
 *
 * @param string $column_name 現在のカラム名。
 * @param string $post_type   投稿タイプ。
 * @return void
 */
function bill_staff_quick_edit_custom_box( $column_name, $post_type ) {
	if ( 'staff' !== $post_type || 'salary_staff_status' !== $column_name ) {
		return;
	}
	?>
	<fieldset class="inline-edit-col-right">
		<div class="inline-edit-col">
			<label class="inline-edit-group">
				<span class="title">スタッフステータス</span>
				<select name="salary_staff_status">
					<option value="">選択してください</option>
					<option value="employed">勤務中</option>
					<option value="retired">退職</option>
					<option value="leave_of_absence">休職</option>
				</select>
			</label>
			<label class="inline-edit-group">
				<span class="title">税扶養人数</span>
				<input type="number" min="0" step="1" name="salary_fuyou" value="" />
			</label>
			<label class="inline-edit-group">
				<span class="title">健康保険被扶養人数</span>
				<input type="number" min="0" step="1" name="salary_kenkou_hifuyousya" value="" />
			</label>
			<label class="inline-edit-group">
				<span class="title">基本給</span>
				<input type="text" name="salary_base" value="" />
			</label>
			<label class="inline-edit-group">
				<span class="title">交通費</span>
				<input type="text" name="salary_transportation_total" value="" />
			</label>
			<label class="inline-edit-group">
				<input type="checkbox" name="salary_koyouhoken[]" value="not_auto_cal" />
				<span class="checkbox-title">雇用保険: 自動計算しない</span>
			</label>
		</div>
	</fieldset>
	<?php
}

add_action( 'admin_footer-edit.php', 'bill_staff_quick_edit_script' );
/**
 * クイック編集画面を開いたときに現在のスタッフステータスをセットする。
 *
 * @return void
 */
function bill_staff_quick_edit_script() {
	global $typenow;

	if ( 'staff' !== $typenow ) {
		return;
	}
	?>
	<script>
	(function($){
		var originalEdit = inlineEditPost.edit;
		inlineEditPost.edit = function(id){
			originalEdit.apply(this, arguments);

			var postId = 0;
			if (typeof id === 'object') {
				postId = parseInt(this.getId(id), 10);
			}

			if (!postId) {
				return;
			}

			var $postRow = $('#post-' + postId);
			var $editRow = $('#edit-' + postId);
			var status = $postRow.find('.column-salary_staff_status .bill-staff-status-value').text();
			var fuyou = $postRow.find('.column-salary_fuyou .bill-staff-fuyou-value').text();
			var kenkouHifuyousya = $postRow.find('.column-salary_staff_status .bill-staff-kenkou-hifuyousya-value').text();
			var salaryBase = $postRow.find('.column-salary_staff_status .bill-staff-base-value').text();
			var salaryTransportationTotal = $postRow.find('.column-salary_staff_status .bill-staff-transportation-value').text();
			var koyouHoken = $postRow.find('.column-salary_staff_status .bill-staff-koyouhoken-value').text();

			$editRow.find('select[name=\"salary_staff_status\"]').val(status);
			$editRow.find('input[name=\"salary_fuyou\"]').val(fuyou);
			$editRow.find('input[name=\"salary_kenkou_hifuyousya\"]').val(kenkouHifuyousya);
			$editRow.find('input[name=\"salary_base\"]').val(salaryBase);
			$editRow.find('input[name=\"salary_transportation_total\"]').val(salaryTransportationTotal);
			$editRow.find('input[name=\"salary_koyouhoken[]\"]').prop('checked', koyouHoken === '1');
		};
	})(jQuery);
	</script>
	<?php
}

add_action( 'save_post_staff', 'bill_staff_save_quick_edit_status', 10, 2 );
/**
 * クイック編集からスタッフステータスを保存する。
 *
 * @param int     $post_id 投稿ID。
 * @param WP_Post $post    投稿オブジェクト。
 * @return void
 */
function bill_staff_save_quick_edit_status( $post_id, $post ) {
	if ( ! isset( $_POST['_inline_edit'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['_inline_edit'] ) );
	if ( ! wp_verify_nonce( $nonce, 'inlineeditnonce' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( 'staff' !== $post->post_type ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$status = '';
	if ( isset( $_POST['salary_staff_status'] ) ) {
		$status = sanitize_text_field( wp_unslash( $_POST['salary_staff_status'] ) );
	}

	$allowed_status = array( 'employed', 'retired', 'leave_of_absence' );
	if ( '' !== $status && ! in_array( $status, $allowed_status, true ) ) {
		return;
	}

	if ( '' === $status ) {
		delete_post_meta( $post_id, 'salary_staff_status' );
	} else {
		update_post_meta( $post_id, 'salary_staff_status', $status );
	}

	$fuyou = '';
	if ( isset( $_POST['salary_fuyou'] ) ) {
		$fuyou = sanitize_text_field( wp_unslash( $_POST['salary_fuyou'] ) );
	}

	if ( '' !== $fuyou && ! preg_match( '/^\d+$/', $fuyou ) ) {
		return;
	}

	if ( '' === $fuyou ) {
		delete_post_meta( $post_id, 'salary_fuyou' );
	} else {
		update_post_meta( $post_id, 'salary_fuyou', $fuyou );
	}

	$kenkou_hifuyousya = '';
	if ( isset( $_POST['salary_kenkou_hifuyousya'] ) ) {
		$kenkou_hifuyousya = sanitize_text_field( wp_unslash( $_POST['salary_kenkou_hifuyousya'] ) );
	}

	if ( '' !== $kenkou_hifuyousya && ! preg_match( '/^\d+$/', $kenkou_hifuyousya ) ) {
		return;
	}

	if ( '' === $kenkou_hifuyousya ) {
		delete_post_meta( $post_id, 'salary_kenkou_hifuyousya' );
	} else {
		update_post_meta( $post_id, 'salary_kenkou_hifuyousya', $kenkou_hifuyousya );
	}

	$salary_base = '';
	if ( isset( $_POST['salary_base'] ) ) {
		$salary_base = sanitize_text_field( wp_unslash( $_POST['salary_base'] ) );
	}

	if ( '' === $salary_base ) {
		delete_post_meta( $post_id, 'salary_base' );
	} else {
		update_post_meta( $post_id, 'salary_base', $salary_base );
	}

	$salary_transportation_total = '';
	if ( isset( $_POST['salary_transportation_total'] ) ) {
		$salary_transportation_total = sanitize_text_field( wp_unslash( $_POST['salary_transportation_total'] ) );
	}

	if ( '' === $salary_transportation_total ) {
		delete_post_meta( $post_id, 'salary_transportation_total' );
	} else {
		update_post_meta( $post_id, 'salary_transportation_total', $salary_transportation_total );
	}

	$koyou_hoken = array();
	if ( isset( $_POST['salary_koyouhoken'] ) && is_array( $_POST['salary_koyouhoken'] ) ) {
		$koyou_hoken = array_map( 'sanitize_text_field', wp_unslash( $_POST['salary_koyouhoken'] ) );
	}

	if ( in_array( 'not_auto_cal', $koyou_hoken, true ) ) {
		update_post_meta( $post_id, 'salary_koyouhoken', array( 'not_auto_cal' ) );
		return;
	}

	delete_post_meta( $post_id, 'salary_koyouhoken' );
}
