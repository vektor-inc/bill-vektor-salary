const { execFileSync } = require( 'child_process' );

// WordPress コアの日本語表示を、既存環境と空の wp-env の両方で再現する。
// プラグイン固有の文言は日本語原文であり、追加の言語パックは不要。
module.exports = () => {
	const language = execFileSync(
		'npx',
		[ 'wp-env', 'run', 'cli', 'wp', 'option', 'get', 'WPLANG' ],
		{ encoding: 'utf8' }
	);

	if ( /(^|\s)ja(\s|$)/.test( language ) ) {
		return;
	}

	execFileSync(
		'npx',
		[ 'wp-env', 'run', 'cli', 'wp', 'language', 'core', 'install', 'ja', '--activate' ],
		{ encoding: 'utf8', stdio: 'inherit' }
	);
};
