// Playwright の接続先と同じ wp-env コンテナを WP-CLI の操作対象にする。
// testsPort を指定した実行では、ブラウザだけ tests 環境を見て fixture が分離する事故を防ぐ。
let wpEnvCliContainer;

function getWpEnvCliContainer() {
	// 環境変数の判定と不正値の警告は、テストプロセスごとに最初の1回だけ行う。
	if ( wpEnvCliContainer ) {
		return wpEnvCliContainer;
	}

	const explicitContainer = process.env.WP_ENV_CLI_CONTAINER;
	const baseUrl = process.env.WP_BASE_URL;
	const testsPort = process.env.WP_ENV_TESTS_PORT;
	const allowedContainers = [ 'cli', 'tests-cli' ];

	// 明示指定は固定の許可値だけを返し、外部入力を WP-CLI の引数へ直接渡さない。
	if ( allowedContainers.includes( explicitContainer ) ) {
		wpEnvCliContainer = explicitContainer;
		return wpEnvCliContainer;
	}

	if ( explicitContainer ) {
		console.warn( 'WP_ENV_CLI_CONTAINER は cli または tests-cli を指定してください。自動判定へ戻します。' );
	}

	if ( baseUrl && testsPort ) {
		try {
			if ( new URL( baseUrl ).port === testsPort ) {
				wpEnvCliContainer = 'tests-cli';
				return wpEnvCliContainer;
			}
		} catch {
			// URL の妥当性は Playwright に任せ、従来どおり開発環境を使う。
		}
	}

	wpEnvCliContainer = 'cli';
	return wpEnvCliContainer;
}

module.exports = { getWpEnvCliContainer };
