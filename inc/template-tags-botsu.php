<?php

/**
 * 仮のボーナス金額
 *
 * @return [type] [description]
 */
function bvsl_get_bonus_tentative() {
	global $post;
	// 年間ボーナスレート
	$bonus_rate = apply_filters( 'bonus_rate', 3 );
	return bvsl_format_number( $post->salary_base ) * $bonus_rate;
}

/**
 * 仮の年収
 *
 * @return [type] [description]
 */
function bvsl_get_year_earn_bonus_tentative() {
	// 12月分 + 賞与
	$year_income = bvsl_get_total_earn() * 12 + bvsl_get_bonus_tentative();
	return $year_income;
}

/**
 * 給与所得控除
 *
 * @return [type] [description]
 */
function bvsl_get_kyuuyo_syotoku_koujyo() {
	// 12月分 + 賞与
	$year_income = bvsl_get_year_earn_bonus_tentative();
	// 平成29年度
	$rate_array = array(
		0        => array(
			'rate'       => 0.4,
			'lowlimit'   => 650000,
			'additional' => 0,
		),
		1800000  => array(
			'rate'       => 0.3,
			'lowlimit'   => null,
			'additional' => 180000,
		),
		3600000  => array(
			'rate'       => 0.2,
			'lowlimit'   => null,
			'additional' => 540000,
		),
		6600000  => array(
			'rate'       => 0.1,
			'lowlimit'   => null,
			'additional' => 1200000,
		),
		10000000 => array(
			'rate'       => 0,
			'lowlimit'   => null,
			'additional' => 2200000,
		),
	);
	foreach ( $rate_array as $key => $value ) {
		if ( $year_income > $key ) {
			$cal_array = $value;
		}
	}

	$kyuuyo_syotoku_koujyo = $cal_array['rate'] * $year_income + $cal_array['additional'];
	if ( $kyuuyo_syotoku_koujyo < $rate_array[0]['lowlimit'] ) {
		$kyuuyo_syotoku_koujyo = $rate_array[0]['lowlimit'];
	}
	return $kyuuyo_syotoku_koujyo;
}


/**
 * 所得税の計算
 *
 * @return [type] [description]
 */
function bvsl_get_income_tax() {
	// 所得税 ＝ 課税所得 × 税率－税額控除額
	// 年収 - 給与所得控除
	$year_income = bvsl_get_year_earn_bonus_tentative() - bvsl_get_kyuuyo_syotoku_koujyo();
	// 税率 _ 平成27年分以降
	$rate_array = array(
		0        => array(
			'rate'      => 0.05,
			'deduction' => 0,
		),
		1950001  => array(
			'rate'      => 0.1,
			'deduction' => 97500,
		),
		3300001  => array(
			'rate'      => 0.2,
			'deduction' => 427500,
		),
		6950001  => array(
			'rate'      => 0.23,
			'deduction' => 636000,
		),
		9000001  => array(
			'rate'      => 0.33,
			'deduction' => 1536000,
		),
		18000001 => array(
			'rate'      => 0.33,
			'deduction' => 1536000,
		),
		40000001 => array(
			'rate'      => 0.45,
			'deduction' => 4796000,
		),
	);
	foreach ( $rate_array as $key => $value ) {
		if ( $year_income > $key ) {
			$cal_array = $value;
		}
	}
	$income_tax = round( bvsl_get_total_pay() * $cal_array['rate'] ) - $cal_array['deduction'];
	return $income_tax;
}
