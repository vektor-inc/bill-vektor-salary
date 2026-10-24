// Playwright の接続先と同じ wp-env コンテナを WP-CLI の操作対象にする。
// testsPort を指定した実行では、ブラウザだけ tests 環境を見て fixture が分離する事故を防ぐ。
function getWpEnvCliContainer() {
	const explicitContainer = process.env.WP_ENV_CLI_CONTAINER;
	const baseUrl = process.env.WP_BASE_URL;
	const testsPort = process.env.WP_ENV_TESTS_PORT;
	const allowedContainers = [ 'cli', 'tests-cli' ];

	// 明示指定は固定の許可値だけを返し、外部入力を WP-CLI の引数へ直接渡さない。
	if ( allowedContainers.includes( explicitContainer ) ) {
		return explicitContainer;
	}

	if ( explicitContainer ) {
		console.warn( 'WP_ENV_CLI_CONTAINER は cli または tests-cli を指定してください。自動判定へ戻します。' );
	}

	if ( baseUrl && testsPort ) {
		try {
			if ( new URL( baseUrl ).port === testsPort ) {
				return 'tests-cli';
			}
		} catch {
			// URL の妥当性は Playwright に任せ、従来どおり開発環境を使う。
		}
	}

	return 'cli';
}

module.exports = { getWpEnvCliContainer };
