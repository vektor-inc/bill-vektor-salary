<?php
/**
 * 給与明細の複製リンクのテスト。
 *
 * @package Bill_Vektor_Salary
 */

/**
 * 複製リンクの表示と nonce の検証。
 */
class DuplicateDocTest extends WP_UnitTestCase {

	/**
	 * 投稿種別ごとのリンク表示と、投稿に対応する nonce を検証する。
	 *
	 * @return void
	 */
	public function test_bvsl_duplicate_salary() {
		global $post;
		$original_post = $post;
		$original_user = get_current_user_id();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$test_cases = array(
			array(
				'test_condition_name' => '公開済みの給与明細には有効な複製リンクを表示する',
				'post_type'           => 'salary',
				'post_status'         => 'publish',
				'expected'            => true,
			),
			array(
				'test_condition_name' => '下書きの給与明細にも有効な複製リンクを表示する',
				'post_type'           => 'salary',
				'post_status'         => 'draft',
				'expected'            => true,
			),
			array(
				'test_condition_name' => '通常の投稿には複製リンクを表示しない',
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'expected'            => false,
			),
		);

		try {
			foreach ( $test_cases as $case ) {
				// 編集対象を用意し、出力されたリンクを取り出す。
				$post = self::factory()->post->create_and_get(
					array(
						'post_type'   => $case['post_type'],
						'post_status' => $case['post_status'],
					)
				);
				ob_start();
				try {
					bvsl_duplicate_salary();
				} finally {
					$output = ob_get_clean();
				}
				$has_link = 1 === preg_match( '/href="([^"]+)"/', $output, $matches );
				$this->assertSame( $case['expected'], $has_link, $case['test_condition_name'] );
				if ( ! $has_link ) {
					continue;
				}

				// URL のパラメーターと、テーマ側が求める action の nonce を検証する。
				$url = html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' );
				$this->assertSame( admin_url( 'post-new.php' ), strtok( $url, '?' ) );
				parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query );
				$this->assertSame( (string) $post->ID, $query['master_id'] );
				$this->assertSame( 'salary', $query['post_type'] );
				$this->assertSame( 'all', $query['table_copy_type'] );
				$this->assertSame( 'full', $query['duplicate_type'] );
				$this->assertArrayHasKey( '_wpnonce', $query );
				$this->assertNotFalse( wp_verify_nonce( $query['_wpnonce'], 'bill_copy_' . $post->ID ) );
				$this->assertFalse( wp_verify_nonce( $query['_wpnonce'], 'bill_copy_' . ( $post->ID + 1 ) ) );
			}
		} finally {
			// グローバル状態を元に戻す。
			$post = $original_post;
			wp_set_current_user( $original_user );
		}
	}
}
