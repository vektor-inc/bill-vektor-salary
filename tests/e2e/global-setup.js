const { execFileSync } = require( 'child_process' );

// 開発用 wp-env のサイト言語を ja に切り替える（元に戻さない）。
// WordPress コアの日本語表示を、既存環境と空の wp-env の両方で再現する。
// プラグイン固有の文言は日本語原文であり、追加の言語パックは不要。
module.exports = () => {
	let language = '';

	try {
		language = execFileSync(
			'npx',
			[ 'wp-env', 'run', 'cli', 'wp', 'option', 'get', 'WPLANG' ],
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
			[ 'wp-env', 'run', 'cli', 'wp', 'language', 'core', 'install', 'ja', '--activate' ],
			{ encoding: 'utf8', stdio: 'inherit' }
		);
	} catch ( error ) {
		throw new Error(
			'日本語言語パックのインストールに失敗しました。ネットワーク接続や wp-env の起動状態を確認してください。',
			{ cause: error }
		);
	}
};
