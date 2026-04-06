<?php
/**
 * 計算用数字フォーマットに変換
 *
 * @param  integer $number 変換する値
 * @return number          変換後の値
 */
function bvsl_format_number( $number = 0 ) {
	// 全角を半額に変換
	$number = mb_convert_kana( $number, 'a' );
	// , が入ってたら除去
	$number = str_replace( ',', '', $number );
	// 前後に空白などがあったら除去
	$number = trim( $number );
	if ( ! $number ) {
		$number = (int) 0;
	}
	return $number;
}

/**
 * 表示フォーマットに変換
 *
 * @param  [type] $price [description]
 * @return [type]        [description]
 */
function bvsl_format_print( $price ) {
	$price = bvsl_format_number( $price );
	if ( is_numeric( $price ) ) {
		$price = '¥ ' . number_format( $price, 0 );
	}
	return $price;
}

/**
 * 稼いだ総額
 *
 * @param  string $post [description]
 * @return [type]       [description]
 */
function bvsl_get_total_earn( $post = '' ) {
	if ( ! $post ) {
		global $post;
	}
	$total_earn = bvsl_format_number( $post->salary_base );
	$total_earn = $total_earn + bvsl_format_number( $post->salary_overtime_total );
	$total_earn = $total_earn + bvsl_format_number( $post->salary_part_total );
	$total_earn = $total_earn + bvsl_format_number( $post->salary_holiday_total );

	if ( is_array( $post->kazei_additional ) ) {
		foreach ( $post->kazei_additional as $key => $value ) {
			if ( ! empty( $value['price'] ) ) {
				$total_earn = $total_earn + bvsl_format_number( $value['price'] );
			}
		}
	}

	return $total_earn;
}

/**
 * 交通費込の総支給額
 * @param  string $args = [
 * 	'kazei' => true, // 課税のみの場合
 * ]
 * @return [type] [description]
 */
function bvsl_get_total_pay( $args = array() ) {
	global $post;
	$total_pay = bvsl_get_total_earn( $post );
	$total_pay = $total_pay + bvsl_format_number( $post->salary_transportation_total );

	// 非課税も含める場合（古い仕様）
	if ( empty( $args[ 'kazei' ] ) ){
		$total_pay = $total_pay + bvsl_get_hikazei_additional_total();
	}
	return $total_pay;
}

/**
 * 非課税支給の合計
 * 
 * @since 0.8.0
 * @return number
 */
function bvsl_get_hikazei_additional_total() {
	$hikazei_additional_total = 0;
	global $post;
	if ( is_array( $post->hikazei_additional ) ) {
		foreach ( $post->hikazei_additional as $key => $value ) {
			if ( ! empty( $value['price'] ) ) {
				$hikazei_additional_total = $hikazei_additional_total + bvsl_format_number( $value['price'] );
			}
		}
	}
	return $hikazei_additional_total;
}

/**
 * 雇用保険料率テーブル
 *
 * 事業の種類と給与対象時期に基づく労働者負担の雇用保険料率を返す。
 * 料率は 事業の種類 => 給与対象時期 => 料率 の形式で定義。
 *
 * @return array 料率テーブル
 */
function bvsl_get_koyou_hoken_rate_table() {
	return array(
		'general'      => array(
			'20260401_after'  => 5 / 1000,
			'20250401_after'  => 5.5 / 1000,
			'20230401_after'  => 6 / 1000,
			'20221001_after'  => 5 / 1000,
			'20220930_before' => 3 / 1000,
		),
		'agriculture'  => array(
			'20260401_after'  => 6 / 1000,
			'20250401_after'  => 6.5 / 1000,
			'20230401_after'  => 7 / 1000,
			'20221001_after'  => 6 / 1000,
			'20220930_before' => 4 / 1000,
		),
		'construction' => array(
			'20260401_after'  => 6 / 1000,
			'20250401_after'  => 6.5 / 1000,
			'20230401_after'  => 7 / 1000,
			'20221001_after'  => 6 / 1000,
			'20220930_before' => 4 / 1000,
		),
	);
}

/**
 * 事業の種類を取得する
 *
 * 優先度: 給与明細 > スタッフ > グローバル設定。
 * いずれも未設定の場合は 'general'（一般の事業）を返す。
 *
 * @return string 事業の種類（general / agriculture / construction）
 */
function bvsl_get_business_type() {
	global $post;
	$valid_types = array( 'general', 'agriculture', 'construction' );

	// 1. 給与明細の設定を確認する。
	if ( isset( $post->ID ) ) {
		$salary_type = get_post_meta( $post->ID, 'salary_business_type', true );
		if ( in_array( $salary_type, $valid_types, true ) ) {
			return $salary_type;
		}
	}

	// 2. スタッフの設定を確認する。
	$staff_id = isset( $post->ID ) ? (int) get_post_meta( $post->ID, 'salary_staff', true ) : 0;
	if ( $staff_id ) {
		$staff_type = get_post_meta( $staff_id, 'salary_business_type', true );
		if ( in_array( $staff_type, $valid_types, true ) ) {
			return $staff_type;
		}
	}

	// 3. グローバル設定を確認する。
	$global_type = get_option( 'bvsl_business_type', 'general' );
	if ( in_array( $global_type, $valid_types, true ) ) {
		return $global_type;
	}

	return 'general';
}

/**
 * 雇用保険料の料率
 *
 * 給与対象時期とスタッフの事業の種類に基づいて料率を返す。
 *
 * @return float 雇用保険料率
 */
function bvsl_get_koyou_hoken_rate() {
	global $post;
	$business_type = bvsl_get_business_type();
	$term          = isset( $post->salary_target_term ) ? $post->salary_target_term : '20220930_before';
	$table         = bvsl_get_koyou_hoken_rate_table();

	if ( isset( $table[ $business_type ][ $term ] ) ) {
		return $table[ $business_type ][ $term ];
	}

	// 未知の時期の場合はデフォルト（一般・〜令和4年9月）を返す。
	return 3 / 1000;
}

/**
 * 雇用保険料の計算
 * 雇用保険 = ( 稼ぎの合計 + 通勤交通費 ) * 雇用保険料率
 *
 * @return [type] [description]
 */
function bvsl_get_koyou_hoken() {
	global $post;
	if ( is_array( $post->salary_koyouhoken ) ) {
		foreach ( $post->salary_koyouhoken as $key => $value ) {
			if ( 'not_auto_cal' === $value ) {
				return 0;
			}
		}
	}
	$koyouhoken_taisyou = bvsl_get_total_earn() + bvsl_format_number( $post->salary_transportation_total );
	$rate               = bvsl_get_koyou_hoken_rate();
	return $koyou_hoken = round( $koyouhoken_taisyou * $rate );
}
/**
 * 課税所得
 *
 * @return [type] [description]
 */
function bvsl_get_kazeisyotoku() {
	/*
	課税所得 : 通勤手当や旅費などを除く収入の全額から、社会保険料や労働保険料、配偶者控除、寄付金控除などの所得控除を差し引いたあとの所得額
	課税所得 ＝ 総支給額（基本給・残業代・手当）－ 非課税の手当 － 所得控除
	※ bvsl_get_total_earn() には非課税の手当は含まれていないので引かなくて良い
	*/
	$kazeisyotoku = bvsl_get_total_earn() - bvsl_get_koujyo_kazei();
	if ( $kazeisyotoku < 0 ) {
		$kazeisyotoku = 0;
	}
	return $kazeisyotoku;
}

/**
 * 課税控除
 *
 * @return number 雇用保険 + 健康保険 + 厚生年金
 */
function bvsl_get_koujyo_kazei() {
	global $post;
	$total_deduction = bvsl_get_koyou_hoken();
	$total_deduction = $total_deduction + bvsl_format_number( $post->salary_kenkou );
	$total_deduction = $total_deduction + bvsl_format_number( $post->salary_nenkin );
	return $total_deduction;
}

/**
 * 値が正常なら値を、エラーがあればエラーを返す
 *
 * @param array $args 値とエラーの配列
 * @since 0.6.2
 * @return string|number $return
 */
function bvsl_get_return_array( $args ) {
	if ( ! empty( $args['error'] ) && is_array( $args['error'] ) ) {
		$return = '';
		$count  = 0;
		foreach ( $args['error'] as $value ) {
			if ( $count ) {
				$return .= '<br />';
			}
			$return .= $value;
			$count++;
		}
		return $return;
	} else {
		return $args['value'];
	}
}

/**
 * 控除合計
 *
 * @return number $total_deduction : 課税控除 + 住民税 + 所得税
 */
function bvsl_get_koujyo_total() {
	global $post;
	$args = array(
		'value' => bvsl_get_koujyo_kazei(),
		'error' => false,
	);
	if ( is_numeric( bvsl_format_number( $post->salary_jyuuminzei ) ) ) {
		$args['value'] = (int) $args['value'] + bvsl_format_number( $post->salary_jyuuminzei );
	} else {
		$args['error'][] = '住民税は数字を入力してください。';
	}
	if ( is_numeric( bvsl_format_number( $post->salary_syotokuzei ) ) ) {
		$args['value'] = (int) $args['value'] + bvsl_format_number( $post->salary_syotokuzei );
	} else {
		$args['error'][] = '所得税は数字を入力してください。';
	}

	return bvsl_get_return_array( $args );
}

/**
 * 社会保険料合計
 * 健康保険（介護保険含む）+ 厚生年金保険 + 雇用保険（労災含む）
 */
function bvsl_get_shakai_hoken_total(){
	global $post;
	$shakai_hoken_total = bvsl_format_number( $post->salary_kenkou ) + bvsl_format_number( $post->salary_nenkin ) + bvsl_get_koyou_hoken();
	return $shakai_hoken_total;
}

/**
 * 差引支給額
 *
 * @return number| array
 */
function bvsl_get_total_furikomi() {
	$args = array(
		'value' => false,
		'error' => false,
	);
	if ( is_numeric( bvsl_get_total_pay() ) && is_numeric( bvsl_get_koujyo_total() ) ) {
		$args['value'] = bvsl_format_print( bvsl_get_total_pay() - bvsl_get_koujyo_total() );

	}
	if ( ! is_numeric( bvsl_get_total_pay() ) ) {
		$args['error'][] = '支給合計が数字でないため算出できません。';
	}
	if ( ! is_numeric( bvsl_get_koujyo_total() ) ) {
		$args['error'][] = '控除合計が数字でないため算出できません。';
	}
	return bvsl_get_return_array( $args );
}
