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
 *
 * @return [type] [description]
 */
function bvsl_get_total_pay() {
	global $post;
	$total_pay = bvsl_get_total_earn( $post );
	$total_pay = $total_pay + bvsl_format_number( $post->salary_transportation_total );

	if ( is_array( $post->hikazei_additional ) ) {
		foreach ( $post->hikazei_additional as $key => $value ) {
			if ( ! empty( $value['price'] ) ) {
				$total_pay = $total_pay + bvsl_format_number( $value['price'] );
			}
		}
	}

	return $total_pay;
}

/**
 * 雇用保険料の料率
 *
 * @return float [description]
 */
function bvsl_get_koyou_hoken_rate() {
	global $post;
	if ( '20230401_after' === $post->salary_target_term ) {
		$rate = 0.006;
	} elseif ( '20221001_after' === $post->salary_target_term ) {
		$rate = 0.005;
	} else {
		$rate = 0.003;
	}
	return $rate;
}

/**
 * 雇用保険料の計算
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
	// 稼ぎの合計から雇用保険を引く
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
