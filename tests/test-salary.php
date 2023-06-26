<?php
/**
 * Class SalaryTest
 *
 * @package Bill_Vektor_Salary
 */

 // テーマ側から 'npm run phpunit:salary' を実行する

class SalaryTest extends WP_UnitTestCase {

	/**
	 * @since 0.6.2
	 */
	function test_bvsl_format_number() {
		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'test_bvsl_format_number' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		$test_data = array(
			// null だったら 0 を返す
			array(
				'post_value' => null,
				'expected'   => 0,
			),
			// null だったら 0 を返す
			array(
				'post_value' => '',
				'expected'   => 0,
			),
			// , があったら削除
			array(
				'post_value' => '1,000',
				'expected'   => 1000,
			),
			// 全角文字は半角に変換
			array(
				'post_value' => '１，０００',
				'expected'   => 1000,
			),
			// 末尾に空白があったら削除
			array(
				'post_value' => '1000 ',
				'expected'   => 1000,
			),
			// 末尾に空白があったら削除
			array(
				'post_value' => '文字列',
				'expected'   => '文字列',
			),
		);

		foreach ( $test_data as $test_value ) {
			$actual = bvsl_format_number( $test_value['post_value'] );
			print PHP_EOL;
			print 'actual  :' . $actual . PHP_EOL;
			print 'expected :' . $test_value['expected'] . PHP_EOL;
			$this->assertEquals( $test_value['expected'], $actual );
		}
	}

	public function test_bvsl_get_koyou_hoken_rate() {
		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'test_bvsl_get_koyou_hoken_rate' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		$test_data = array(
			array(
				'post'    => array(
					'post_title'   => 'Salary Title',
					'post_content' => 'test',
					'post_type'    => 'salary',
				),
				'correct' => 0.003,
			),
			array(
				'post'      => array(
					'post_title'   => 'Salary Title',
					'post_content' => 'test',
					'post_type'    => 'salary',
				),
				'post_meta' => array(
					'salary_target_term' => '20221001_after',
				),
				'correct'   => 0.005,
			),
			array(
				'post'      => array(
					'post_title'   => 'Salary Title',
					'post_content' => 'test',
					'post_type'    => 'salary',
				),
				'post_meta' => array(
					'salary_target_term' => '20230401_after',
				),
				'correct'   => 0.006,
			),
		);
		// var_dump $this->$test_data;
		foreach ( $test_data as $test_value ) {
			$post_id = wp_insert_post( $test_value['post'] );
			if ( is_int( $post_id ) ) {
				if ( ! empty( $test_value['post_meta'] ) ) {
					foreach ( $test_value['post_meta'] as $meta_key => $meta_value ) {
						update_post_meta( $post_id, $meta_key, $meta_value );
					}
				}
			}
			global $post;
			$post = get_post( $post_id );
			setup_postdata( $post );
			$actual = bvsl_get_koyou_hoken_rate();
			print PHP_EOL;
			print 'return  :' . $actual . PHP_EOL;
			print 'correct :' . $test_value['correct'] . PHP_EOL;
			$this->assertEquals( $test_value['correct'], $actual );
			wp_delete_post( $post_id, true );
			$post_id = 0;
		}
	}

	function test_bvsl_format_print() {
		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'test_bvsl_format_print' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		$test_data = array(
			array(
				'post_value' => 0.4,
				'expected'   => '¥ ' . 0,
			),
			array(
				'post_value' => 0.5,
				'expected'   => '¥ ' . 1,
			),
			array(
				'post_value' => '文字列',
				'expected'   => '文字列',
			),
		);

		foreach ( $test_data as $test_value ) {
			$actual = bvsl_format_print( $test_value['post_value'] );
			print PHP_EOL;
			print 'actual  :' . $actual . PHP_EOL;
			print 'expected :' . $test_value['expected'] . PHP_EOL;
			$this->assertEquals( $test_value['expected'], $actual );
		}
	}

	/**
	 * @since 0.6.2
	 */
	function test_bvsl_get_koujyo_total() {
		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'bvsl_get_koujyo_total' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;

		$dummy_post = array(
			'post_title'   => 'Salary Title',
			'post_content' => 'test',
			'post_type'    => 'salary',
		);

		$test_data = array(
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'salary_target_term' => '20221001_after',
				),
				// 300000 * 0.005 = 1500
				'expected'  => 1500,
			),
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'salary_target_term' => '20221001_after',
					'salary_jyuuminzei'  => null,
					'salary_syotokuzei'  => null,
				),
				// 300000 * 0.005 = 11500
				'expected'  => 1500,
			),
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'salary_target_term' => '20221001_after',
					'salary_jyuuminzei'  => 10000,
					'salary_syotokuzei'  => null,
				),
				// 300000 * 0.005 + 10000 = 11500
				'expected'  => 11500,
			),
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'salary_target_term' => '20221001_after',
					'salary_jyuuminzei'  => null,
					'salary_syotokuzei'  => 10000,
				),
				// 300000 * 0.005 + 10000 = 11500
				'expected'  => 11500,
			),
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'salary_target_term' => '20230401_after',
					'salary_jyuuminzei'  => null,
					'salary_syotokuzei'  => 10000,
				),
				// 300000 * 0.006 + 10000 = 11800
				'expected'  => 11800,
			),
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'salary_target_term' => '20221001_after',
					'salary_jyuuminzei'  => '文字列を入れられてしまった',
					'salary_syotokuzei'  => null,
				),
				'expected'  => '住民税は数字を入力してください。',
			),
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'salary_target_term' => '20221001_after',
					'salary_jyuuminzei'  => null,
					'salary_syotokuzei'  => '文字列を入れられてしまった',
				),
				'expected'  => '所得税は数字を入力してください。',
			),
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'salary_target_term' => '20221001_after',
					'salary_jyuuminzei'  => '文字列を入れられてしまった',
					'salary_syotokuzei'  => '文字列を入れられてしまった',
				),
				'expected'  => '住民税は数字を入力してください。<br />所得税は数字を入力してください。',
			),
		);
		// var_dump $this->$test_data;
		foreach ( $test_data as $test_value ) {
			$post_id = wp_insert_post( $test_value['post'] );
			if ( is_int( $post_id ) ) {
				if ( ! empty( $test_value['post_meta'] ) ) {
					foreach ( $test_value['post_meta'] as $meta_key => $meta_value ) {
						update_post_meta( $post_id, $meta_key, $meta_value );
					}
				}
			}
			global $post;
			$post = get_post( $post_id );
			setup_postdata( $post );
			$actual = bvsl_get_koujyo_total();
			print PHP_EOL;
			print 'actual  :' . $actual . PHP_EOL;
			print 'expected :' . $test_value['expected'] . PHP_EOL;
			$this->assertEquals( $test_value['expected'], $actual );
			wp_delete_post( $post_id, true );
			$post_id = 0;
		}
	}

	/**
	 * @since 0.6.2
	 */
	function test_bvsl_get_return_array() {
		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'bvsl_get_return_array' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;

		$test_data = array(
			array(
				'value'      => 1000,
				'error' => false,
				'expected'  => 1000,
			),
			array(
				'value'      => 1000,
				'error' => array(
					'エラーA',
					'エラーB',
				),
				'expected'  => 'エラーA<br />エラーB',
			),
		);
		// var_dump $this->$test_data;
		foreach ( $test_data as $test_value ) {
			$actual = bvsl_get_return_array(  $test_value );
			print PHP_EOL;
			print 'actual  :' . $actual . PHP_EOL;
			print 'expected :' . $test_value['expected'] . PHP_EOL;
			$this->assertEquals( $test_value['expected'], $actual );
		}
	}
}
