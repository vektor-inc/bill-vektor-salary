const { execFileSync } = require( 'child_process' );
const { getWpEnvCliContainer } = require( './utils/wp-env' );

// 開発用 wp-env に BillVektor テーマを用意し、サイト言語を ja に切り替える（元に戻さない）。
// WordPress コアの日本語表示を、既存環境と空の wp-env の両方で再現する。
// プラグイン固有の文言は日本語原文であり、追加の言語パックは不要。
module.exports = () => {
	const wpEnvCommand = [ 'wp-env', 'run', getWpEnvCliContainer(), 'wp' ];
	let isThemeInstalled = false;
	let isThemeActive = false;

	// 判定コマンドの失敗は未導入・無効として扱い、後続処理の案内付きエラーにまとめる。
	try {
		execFileSync( 'npx', [ ...wpEnvCommand, 'theme', 'is-installed', 'bill-vektor' ] );
		isThemeInstalled = true;
	} catch {
		isThemeInstalled = false;
	}

	if ( isThemeInstalled ) {
		try {
			execFileSync( 'npx', [ ...wpEnvCommand, 'theme', 'is-active', 'bill-vektor' ] );
			isThemeActive = true;
		} catch {
			isThemeActive = false;
		}
	}

	// プラグインが依存するテーマを、必要な場合だけ導入または有効化する。
	try {
		if ( ! isThemeInstalled ) {
			execFileSync(
				'npx',
				[
					...wpEnvCommand,
					'theme',
					'install',
					'https://github.com/vektor-inc/bill-vektor/releases/latest/download/bill-vektor.zip',
					'--activate',
				],
				{ encoding: 'utf8', stdio: 'inherit' }
			);
		} else if ( ! isThemeActive ) {
			execFileSync(
				'npx',
				[ ...wpEnvCommand, 'theme', 'activate', 'bill-vektor' ],
				{ encoding: 'utf8', stdio: 'inherit' }
			);
		}
	} catch ( error ) {
		throw new Error(
			'BillVektor テーマのインストールに失敗しました。ネットワーク接続や wp-env の起動状態を確認してください。',
			{ cause: error }
		);
	}

	let language = '';

	try {
		language = execFileSync(
			'npx',
			[ ...wpEnvCommand, 'option', 'get', 'WPLANG' ],
			{ encoding: 'utf8' }
		);
	} catch {
		// 空の環境では WPLANG が未登録のため、未設定として日本語化を続ける。
		language = '';
	}

	if ( /(^|\s)ja(\s|$)/.test( language ) ) {
		return;
	}

	try {
		execFileSync(
			'npx',
			[ ...wpEnvCommand, 'language', 'core', 'install', 'ja', '--activate' ],
			{ encoding: 'utf8', stdio: 'inherit' }
		);
	} catch ( error ) {
		throw new Error(
			'日本語言語パックのインストールに失敗しました。ネットワーク接続や wp-env の起動状態を確認してください。',
			{ cause: error }
		);
	}
};
