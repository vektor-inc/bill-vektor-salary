<?php
/**
 * Class SalaryTest
 *
 * @package Bill_Vektor_Salary
 */

class SalaryTest extends WP_UnitTestCase {

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
}
