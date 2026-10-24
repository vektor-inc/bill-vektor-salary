// ログインフォームへ認証情報をまとめて設定し、送信と遷移を同時に待つ。
// 個別の入力中に画面更新が挟まり、入力先がずれる不安定さを避ける。
//
// @param {import('@playwright/test').Page} page - 操作対象ページ。
// @param {string} userLogin - ユーザー名。
async function login( page, userLogin ) {
	await page.goto( '/wp-login.php' );
	await Promise.all( [
		page.waitForURL( /wp-admin/ ),
		page.locator( '#loginform' ).evaluate( ( form, credentials ) => {
			form.elements.log.value = credentials.userLogin;
			form.elements.pwd.value = credentials.password;
			form.requestSubmit();
		}, { userLogin, password: 'password' } ),
	] );
}

module.exports = { login };
