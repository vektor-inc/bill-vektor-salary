<?php
/**
 * Plugin Name: BVSL E2E Helper (TEST ONLY)
 * Description: e2e テスト専用 mu-plugin。一括登録パネルの 0 件状態を再現するためだけに利用する。本番環境には絶対に含めないこと。`.wp-env.json` の mappings 経由でのみ wp-env コンテナに配置される。
 * Author: Vektor,Inc.
 * Version: 1.0.0
 *
 * @package Bill_Vektor_Salary
 */

/*
 * このファイルは e2e テスト専用の mu-plugin です。
 *
 * 目的:
 *   tests/e2e/salary-template.spec.js の test '08-b'（支給分未登録時の UI 確認）で、
 *   salary-term taxonomy を物理削除せずに「支給分 0 件」状態を再現するためのフック。
 *
 * 配布物への混入禁止:
 *   - dist パッケージ（gulpfile.js / .distignore で `tests/` を除外済み）には含まれない。
 *   - 本ファイルは `.wp-env.json` の `mappings` 経由で、wp-env コンテナの
 *     `wp-content/mu-plugins/bvsl-e2e-helper.php` にマッピングされる前提。
 *   - 本番環境（プラグインの zip 配布物 / WordPress.org / 顧客サイトなど）に絶対に含めないこと。
 *
 * 発火条件（二重ガード）:
 *   1. WP_DEBUG === true であること（本番環境では原則 false のため、本番では発火しない）
 *   2. リクエストに `bvsl_e2e_force_no_terms=1` という cookie が付与されていること
 *
 * 上記の両方を満たす場合に限り、`bvsl_bulk_create_panel_terms` フィルターで空配列を返し、
 * 一括登録パネルを「支給分未登録」状態として描画させる。
 * cookie が無いセッションには一切影響しない。
 */

// ABSPATH ガード（WordPress 文脈でのみ動作させる）。
defined( 'ABSPATH' ) || exit;

// ガード 1: WP_DEBUG が有効でなければ何もしない（本番環境での誤発火防止）。
if ( ! defined( 'WP_DEBUG' ) || true !== WP_DEBUG ) {
	return;
}

// ガード 2: 専用 cookie が付いていなければ何もしない。
// $_COOKIE は WordPress の入力値全般と同じく信用できないが、
// ここで使う唯一の用途は「e2e で空配列を強制するか否かの 1 ビット判定」のみ。
// 値の比較は厳密な文字列一致で行い、意図しない truthy 値で発火しないようにする。
if ( empty( $_COOKIE['bvsl_e2e_force_no_terms'] ) || '1' !== (string) wp_unslash( $_COOKIE['bvsl_e2e_force_no_terms'] ) ) {
	return;
}

// 上記 2 つのガードを通過した場合のみフィルターを登録。
// 一括登録パネルで対象とする支給分タームを強制的に空配列にする。
add_filter(
	'bvsl_bulk_create_panel_terms',
	static function ( $terms ) {
		// 0 件状態を強制的に再現する（元の $terms は無視）。
		return array();
	}
);
