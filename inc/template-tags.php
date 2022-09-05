<?php
/**
 * 計算用数字フォーマットに変換
 *
 * @param  integer $number [description]
 * @return [type]          [description]
 */
function bvsl_format_number( $number = 0 ) {
	// 全角を半額に変換
	$number = mb_convert_kana( $number, 'a' );
	// , が入ってたら除去
	$number = str_replace( ',', '', $number );
	if ( ! $number ) {
		$number = 0;
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
	$price = '¥ ' . number_format( $price, 0 );
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
 * 雇用保険料の計算
 *
 * @return [type] [description]
 */
function bvsl_get_koyou_hoken() {
	global $post;
	if ( is_array( $post->salary_koyouhoken ) ) {
		foreach ( $post->salary_koyouhoken as $key => $value ) {
			if ( $value == 'not_auto_cal' ) {
				return 0;
			}
		}
	}
	// 稼ぎの合計から雇用保険を引く
	$koyouhoken_taisyou = bvsl_get_total_earn() + bvsl_format_number( $post->salary_transportation_total );
	if ( '20221001_after' === $post->salary_target_term ){
		$rate = 0.005;
	} else {
		$rate = 0.003;
	}
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
 * @return [type] [description]
 */
function bvsl_get_koujyo_kazei() {
	global $post;
	$total_deduction = bvsl_get_koyou_hoken();
	$total_deduction = $total_deduction + bvsl_format_number( $post->salary_kenkou );
	$total_deduction = $total_deduction + bvsl_format_number( $post->salary_nenkin );
	return $total_deduction;
}
/**
 * 控除合計
 *
 * @return [type] [description]
 */
function bvsl_get_koujyo_total() {
	global $post;
	$total_deduction = bvsl_get_koujyo_kazei();
	$total_deduction = $total_deduction + bvsl_format_number( $post->salary_jyuuminzei );
	$total_deduction = $total_deduction + bvsl_format_number( $post->salary_syotokuzei );
	return $total_deduction;
}
