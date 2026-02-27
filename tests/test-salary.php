<?php
/**
 * Class SalaryTest
 *
 * @package Bill_Vektor_Salary
 */

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
					'salary_target_term' => '20250401_after',
					'salary_jyuuminzei'  => null,
					'salary_syotokuzei'  => 10000,
				),
				// 300000 * ( 5.5 / 1000 ) + 10000 = 11650
				'expected'  => 11650,
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
				'value'    => 1000,
				'error'    => false,
				'expected' => 1000,
			),
			array(
				'value'    => 1000,
				'error'    => array(
					'エラーA',
					'エラーB',
				),
				'expected' => 'エラーA<br />エラーB',
			),
		);
		// var_dump $this->$test_data;
		foreach ( $test_data as $test_value ) {
			$actual = bvsl_get_return_array( $test_value );
			print PHP_EOL;
			print 'actual  :' . $actual . PHP_EOL;
			print 'expected :' . $test_value['expected'] . PHP_EOL;
			$this->assertEquals( $test_value['expected'], $actual );
		}
	}

	/**
	 * @since 0.8.0
	 */
	function test_bvsl_get_shakai_hoken_total() {
		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'bvsl_get_shakai_hoken_total' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;

		$dummy_post = array(
			'post_title'   => 'Salary Title',
			'post_content' => 'test',
			'post_type'    => 'salary',
		);
		$test_data  = array(
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'salary_target_term' => '20230401_after',
					'salary_kenkou'      => '20000',
					'salary_nenkin'      => '20000',
				),
				// 300000 * 0.006 = 1800
				'expected'  => 40000 + 1800,
			),
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'salary_target_term' => '20230401_after',
					'salary_kenkou'      => '20000',
					'salary_nenkin'      => null,
				),
				// 300000 * 0.006 = 1800
				'expected'  => 20000 + 1800,
			),
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'salary_target_term' => '20230401_after',
					'salary_kenkou'      => '20000',
					'salary_nenkin'      => '２００００',
				),
				// 300000 * 0.006 = 1800
				'expected'  => 40000 + 1800,
			),
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'                 => '300000',
					'salary_target_term'          => '20230401_after',
					'salary_kenkou'               => '20000',
					'salary_nenkin'               => '２００００',
					'salary_transportation_total' => '１０００００',
				),
				// 400000 * 0.006 = 2400
				'expected'  => 40000 + 2400,
			),

		);

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
			$actual = bvsl_get_shakai_hoken_total();
			print PHP_EOL;

			print 'actual  :' . $actual . PHP_EOL;
			print 'expected :' . $test_value['expected'] . PHP_EOL;
			$this->assertEquals( $test_value['expected'], $actual );
			wp_delete_post( $post_id, true );
			$post_id = 0;
		}
	}


	/**
	 * @since 0.8.0
	 */
	function test_bvsl_get_hikazei_additional_total() {
		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'bvsl_get_hikazei_additional_total' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;

		$dummy_post = array(
			'post_title'   => 'Salary Title',
			'post_content' => 'test',
			'post_type'    => 'salary',
		);
		$test_data  = array(
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'hikazei_additional' => array(
						array(
							'name'  => 'test',
							'price' => '1000',
						),
						array(
							'name'  => 'test',
							'price' => '2000',
						),
					),
				),
				'expected'  => 3000,
			),
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'hikazei_additional' => array(
						array(
							'name'  => 'test',
							'price' => '1000',
						),
						array(
							'name'  => 'test',
							'price' => '３，000',
						),
					),
				),
				'expected'  => 4000,
			),
		);

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
			$actual = bvsl_get_hikazei_additional_total();
			print PHP_EOL;

			print 'actual  :' . $actual . PHP_EOL;
			print 'expected :' . $test_value['expected'] . PHP_EOL;
			$this->assertEquals( $test_value['expected'], $actual );
			wp_delete_post( $post_id, true );
			$post_id = 0;
		}
	}

	/**
	 * @since 0.8.0
	 */
	function test_bvsl_get_total_pay() {
		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'bvsl_get_total_pay' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;

		$dummy_post = array(
			'post_title'   => 'Salary Title',
			'post_content' => 'test',
			'post_type'    => 'salary',
		);
		$test_data  = array(
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'kazei_additional' => array(
						array(
							'name'  => 'test',
							'price' => '1000',
						),
					),
				),
				'expected'  => 301000,
			),
			// 通常非課税も含まれる（初期構築時はこの仕様だった）
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'kazei_additional' => array(
						array(
							'name'  => 'test',
							'price' => '1000',
						),
					),
					'hikazei_additional' => array(
						array(
							'name'  => 'test',
							'price' => '1000',
						),
					),
				),
				'expected'  => 302000,
			),
			// kazei を true にすると非課税を含めない
			array(
				'post'      => $dummy_post,
				'post_meta' => array(
					'salary_base'        => '300000',
					'kazei_additional' => array(
						array(
							'name'  => 'test',
							'price' => '1000',
						),
					),
					'hikazei_additional' => array(
						array(
							'name'  => 'test',
							'price' => '1000',
						),
					),
				),
				'kazei' => true,
				'expected'  => 301000,
			),
		);
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
			$args = array();
			if ( ! empty ( $test_value['kazei'] ) ){
				$args['kazei'] = true;
			}
			$actual = bvsl_get_total_pay( $args );
			print PHP_EOL;

			print 'actual  :' . $actual . PHP_EOL;
			print 'expected :' . $test_value['expected'] . PHP_EOL;
			$this->assertEquals( $test_value['expected'], $actual );
			wp_delete_post( $post_id, true );
			$post_id = 0;
		}
	}

	/**
	 * @since 0.10.0
	 */
	function test_bvsl_build_salary_message() {
		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'bvsl_build_salary_message' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;

		$salary_post_id = self::factory()->post->create(
			array(
				'post_title' => 'Salary for message test',
				'post_type'  => 'salary',
			)
		);

		$empty_term = wp_insert_term(
			'2026年01月分 空',
			'salary-term',
			array(
				'slug' => 'salary-term-empty-message',
			)
		);
		$filled_term = wp_insert_term(
			'2026年01月分 有',
			'salary-term',
			array(
				'slug' => 'salary-term-filled-message',
			)
		);

		$this->assertFalse( is_wp_error( $empty_term ) );
		$this->assertFalse( is_wp_error( $filled_term ) );

		update_term_meta( $empty_term['term_id'], BVSL_SALARY_TERM_COMMON_MESSAGE_META_KEY, "　 \n\t" );
		update_term_meta( $filled_term['term_id'], BVSL_SALARY_TERM_COMMON_MESSAGE_META_KEY, '共通メッセージ本文' );

		wp_set_object_terms(
			$salary_post_id,
			array( (int) $empty_term['term_id'], (int) $filled_term['term_id'] ),
			'salary-term'
		);

		$test_data = array(
			array(
				'message_structure' => '1',
				'post_message'      => '投稿メッセージ本文',
				'expected'          => '投稿メッセージ本文',
			),
			array(
				'message_structure' => '1',
				'post_message'      => " \n\t　",
				'expected'          => '共通メッセージ本文',
			),
			array(
				'message_structure' => '2',
				'post_message'      => '投稿メッセージ本文',
				'expected'          => "共通メッセージ本文\n投稿メッセージ本文",
			),
			array(
				'message_structure' => '2',
				'post_message'      => "\n\t　",
				'expected'          => '共通メッセージ本文',
			),
			array(
				'message_structure' => '3',
				'post_message'      => '投稿メッセージ本文',
				'expected'          => "投稿メッセージ本文\n共通メッセージ本文",
			),
			array(
				'message_structure' => '3',
				'post_message'      => "\n\t　",
				'expected'          => '共通メッセージ本文',
			),
		);

		foreach ( $test_data as $test_value ) {
			update_post_meta( $salary_post_id, 'salary_message_structure', $test_value['message_structure'] );
			update_post_meta( $salary_post_id, 'salary_message', $test_value['post_message'] );

			$actual = bvsl_build_salary_message( $salary_post_id );
			print PHP_EOL;
			print 'actual  :' . $actual . PHP_EOL;
			print 'expected :' . $test_value['expected'] . PHP_EOL;
			$this->assertSame( $test_value['expected'], $actual );
		}

		// 共通メッセージも投稿メッセージも空白のみの場合はデフォルト文言を返す。
		update_term_meta( $filled_term['term_id'], BVSL_SALARY_TERM_COMMON_MESSAGE_META_KEY, "\n\t　" );
		update_post_meta( $salary_post_id, 'salary_message_structure', '1' );
		update_post_meta( $salary_post_id, 'salary_message', " \n\t　" );
		$this->assertSame( '今月もお疲れでした', bvsl_build_salary_message( $salary_post_id ) );
	}

}
