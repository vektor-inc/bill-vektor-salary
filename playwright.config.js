const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	timeout: 60000,
	// 複数 spec が `salary-template` の全体件数（publish/private/draft の合計）を
	// 前提にアサートしているため、ファイル間の並列実行を避けて衝突を防ぐ。
	// spec ごとの project 分離など根本的なテスト隔離設計は別 issue で対応予定。
	workers: 1,
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:5523',
		screenshot: 'on',
		trace: 'on-first-retry',
	},
	projects: [
		{
			name: 'chromium',
			use: { browserName: 'chromium' },
		},
	],
	reporter: [ [ 'list' ], [ 'html', { open: 'never' } ] ],
} );
