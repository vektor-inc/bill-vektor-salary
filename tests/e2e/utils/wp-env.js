// Playwright の接続先と同じ wp-env コンテナを WP-CLI の操作対象にする。
// testsPort を指定した実行では、ブラウザだけ tests 環境を見て fixture が分離する事故を防ぐ。
function getWpEnvCliContainer() {
	const baseUrl = process.env.WP_BASE_URL;
	const testsPort = process.env.WP_ENV_TESTS_PORT;

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
