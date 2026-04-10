const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	timeout: 60000,
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
