const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	globalSetup: require.resolve( './tests/e2e/global-setup' ),
	timeout: 60000,
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:5523',
		screenshot: 'on',
		trace: 'on-first-retry',
	},
	// 0 件検証は対象テンプレートを一時的に下書きへ変更するため、通常テスト完了後に実行する。
	// pr57 を単独指定しても依存先の全 spec が走る。並行した DB 操作を避けるため --no-deps は使わない。
	// chromium が1件でも失敗すると bulk-count と empty-templates は連鎖スキップされるが、失敗は全体の失敗として表示されるので見落とさない。
	projects: [
		{
			name: 'chromium',
			testIgnore: '**/pr57-bulk-create-count.spec.js',
			use: { browserName: 'chromium' },
		},
		{
			name: 'bulk-count',
			testMatch: '**/pr57-bulk-create-count.spec.js',
			grepInvert: /@empty-templates/,
			dependencies: [ 'chromium' ],
			use: { browserName: 'chromium' },
		},
		{
			name: 'empty-templates',
			testMatch: '**/pr57-bulk-create-count.spec.js',
			grep: /@empty-templates/,
			dependencies: [ 'bulk-count' ],
			use: { browserName: 'chromium' },
		},
	],
	reporter: [ [ 'list' ], [ 'html', { open: 'never' } ] ],
} );
