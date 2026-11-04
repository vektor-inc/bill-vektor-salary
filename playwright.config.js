const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	globalSetup: require.resolve( './tests/e2e/global-setup' ),
	timeout: 60000,
	// 0 件検証は共有 DB のテンプレートを削除するため、通常テスト完了後に実行する。
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:5523',
		screenshot: 'on',
		trace: 'on-first-retry',
	},
	projects: [
		{
			name: 'chromium',
			testIgnore: '**/pr57-bulk-create-count.spec.js',
			use: { browserName: 'chromium' },
		},
		{
			name: 'bulk-count',
			testMatch: '**/pr57-bulk-create-count.spec.js',
			grepInvert: /05: 0 件時/,
			dependencies: [ 'chromium' ],
			use: { browserName: 'chromium' },
		},
		{
			name: 'empty-templates',
			testMatch: '**/pr57-bulk-create-count.spec.js',
			grep: /05: 0 件時/,
			dependencies: [ 'bulk-count' ],
			use: { browserName: 'chromium' },
		},
	],
	reporter: [ [ 'list' ], [ 'html', { open: 'never' } ] ],
} );
