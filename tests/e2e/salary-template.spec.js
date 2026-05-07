const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );

/**
 * PR #48: 給与テンプレート機能 + 一括登録 機能の e2e テスト（仕様変更後）。
 *
 * 仕様変更（9eb8a27）の要点:
 *   - 給与テンプレートは「スタッフごとに作る雛形」に位置付け直し、テンプレ編集画面に
 *     「スタッフ」「Staff No.」フィールドが復活。
 *   - 一括登録 UI からテンプレ select / スタッフチェックボックス / 全選択ボタンを削除し、
 *     「支給分（年月）select + 件数プレビュー + 送信ボタン」のみに簡素化。
 *   - 公開状態のテンプレ全件を、指定した支給分で salary（draft）に展開する。
 *   - 補助導線「このテンプレで一括登録」ボタンは削除。
 *   - 結果通知は「生成 / 重複スキップ / スタッフ未設定スキップ / その他」の 4 カテゴリ。
 *
 * 確認観点:
 *   01. 給与テンプレ新規作成画面: メタボックスタイトル、スタッフ/Staff No. 復活、PDF 関連は非表示。
 *   02. テンプレ公開: スタッフ選択ありとスタッフ未選択を混在させて公開。
 *   03. 一括登録パネル UI: 支給分 select のみ、テンプレ select / スタッフ UI / 全選択ボタンが無いこと。
 *   04. 件数プレビュー: 「公開中のテンプレート N 件を「○○」で下書き作成します。」の文言。
 *   05. 一括登録実行: 公開テンプレ全件分の salary が draft で生成、タイトルが「スタッフ名 / 支給分」。
 *       通知に「生成 / 重複スキップ / スタッフ未設定スキップ」、details にテンプレ名が列挙。
 *   06. 重複スキップ: 同条件でもう一度送信 → 全件「重複スキップ」。
 *   07. テンプレ編集画面: 「このテンプレで一括登録」ボタンが存在しないこと。
 *   08. 0 件時の遷移ボタン（テンプレ未作成時 / 支給分未登録時）。
 *   09. 権限: salary_editor（manage_options なし）ではパネルが出ないこと。
 *   10. メニュー位置: 給与テンプレートが給与明細の直下に並ぶこと。
 *   11. デグレ: 既存給与明細の編集画面で salary_staff やメタボックスが正常に表示されること。
 */

// admin としてログインする共通処理。
async function loginAsAdmin( page ) {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( /wp-admin/ );
}

// edit_posts のみ持つ salary_editor ロールユーザーとしてログインする。
async function loginAsSalaryEditor( page ) {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'salary_user' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( /wp-admin/ );
}

// wp-cli を経由して shell コマンドを叩くヘルパ。
// テスト前後のデータ整備（テスト固有の salary / template の掃除と作成）に限定して使う。
// `wp db import / reset / export` は使わない（プロジェクトルールで禁止）。
function wpCli( args ) {
	return execSync( `npx wp-env run cli wp ${ args }`, { encoding: 'utf8' } );
}

// post_type を指定して、その post_type の投稿を全削除する。
function purgePostType( postType ) {
	const ids = wpCli( `post list --post_type=${ postType } --format=ids --posts_per_page=-1` )
		.replace( /ℹ.*\n/g, '' )
		.replace( /✔.*$/m, '' )
		.trim();
	if ( ids ) {
		// IDs はスペース区切りで返るため、そのまま渡せる。
		wpCli( `post delete ${ ids } --force` );
	}
}

// 既存テストデータを掃除し、新仕様で必要な状態を作る。
//   - テスト用に作っていた salary と salary-template を全削除（force）。
//   - スタッフ A / B / C と salary-term 「2026年5月分」「2026年6月分」は前提として残す。
//   - 「麗美標準テンプレ（スタッフA向け）」「麗美標準テンプレB（スタッフB向け）」「麗美未設定テンプレ（スタッフ未選択）」を
//     新規作成し、それぞれ salary_staff メタを設定する。
function setupTestData() {
	// 旧テストで残った salary を全削除（trash → force）。
	purgePostType( 'salary' );
	// 旧テストで残った salary-template を全削除。
	purgePostType( 'salary-template' );

	// スタッフ A / B の ID を取得（CLI の出力からフィルタ）。
	function getStaffIdByTitle( title ) {
		// post list の出力にはアイコン付きのプログレス行が混じるので、ID 行のみ抽出する。
		const out = wpCli(
			`post list --post_type=staff --post_status=publish --posts_per_page=-1 --format=csv --fields=ID,post_title`
		);
		const lines = out.split( '\n' );
		for ( const line of lines ) {
			// 形式: ID,post_title
			const cols = line.split( ',' );
			if ( cols.length >= 2 && cols[ 1 ].includes( title ) ) {
				return cols[ 0 ];
			}
		}
		return '';
	}
	const staffA = getStaffIdByTitle( '麗美テストスタッフA' );
	const staffB = getStaffIdByTitle( '麗美テストスタッフB' );

	// 「麗美標準テンプレ（スタッフA）」: スタッフあり。
	const tplA = wpCli(
		`post create --post_type=salary-template --post_status=publish --post_title=麗美標準テンプレA --porcelain`
	).trim();
	wpCli( `post meta update ${ tplA } salary_staff ${ staffA }` );
	wpCli( `post meta update ${ tplA } salary_staff_number 001` );
	wpCli( `post meta update ${ tplA } salary_base 300000` );

	// 「麗美標準テンプレB（スタッフB）」: スタッフあり。
	const tplB = wpCli(
		`post create --post_type=salary-template --post_status=publish --post_title=麗美標準テンプレB --porcelain`
	).trim();
	wpCli( `post meta update ${ tplB } salary_staff ${ staffB }` );
	wpCli( `post meta update ${ tplB } salary_staff_number 002` );
	wpCli( `post meta update ${ tplB } salary_base 280000` );

	// 「麗美未設定テンプレ」: スタッフ未設定（salary_staff メタを設定しない）。
	wpCli(
		`post create --post_type=salary-template --post_status=publish --post_title=麗美未設定テンプレ --porcelain`
	).trim();

	return { tplA, tplB };
}

// テスト全体の前にデータを整える。
test.beforeAll( () => {
	setupTestData();
} );

test.describe.serial( 'PR #48: 給与テンプレートと一括登録（仕様変更後）', () => {

	test( '01. 給与テンプレート新規作成画面: メタボックスタイトル / スタッフ・StaffNo. 復活 / PDF 関連は非表示', async ( { page } ) => {
		await loginAsAdmin( page );
		// 給与テンプレートの新規追加画面を開く。
		await page.goto( '/wp-admin/post-new.php?post_type=salary-template' );
		await page.waitForLoadState( 'networkidle' );

		// メタボックスのタイトルが「給与テンプレート基本項目」「給与テンプレート給与テーブル」になっていることを確認。
		const headers = await page.locator( '#poststuff .postbox > .postbox-header h2, #poststuff .postbox > h2.hndle' ).allTextContents();
		const headersJoined = headers.join( ' / ' );
		expect( headersJoined ).toContain( '給与テンプレート基本項目' );
		expect( headersJoined ).toContain( '給与テンプレート給与テーブル' );

		// 給与明細用のメタボックスタイトルが出ていないこと。
		expect( headersJoined ).not.toContain( '給与明細基本項目' );

		// 仕様変更でスタッフ・Staff No. はテンプレ画面でも表示されるようになった。
		await expect( page.locator( '#salary_staff' ) ).toHaveCount( 1 );
		await expect( page.locator( '#salary_staff_number' ) ).toHaveCount( 1 );

		// 発行済 PDF（salary_send_pdf）はテンプレでは引き続き非表示。
		await expect( page.locator( '#salary_send_pdf' ) ).toHaveCount( 0 );

		await page.screenshot( { path: 'tests/e2e/screenshots/pr48/01-template-new.png', fullPage: true } );
	} );

	test( '02. テンプレ一覧: スタッフあり 2 件 + スタッフ未設定 1 件が公開状態で並ぶ', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/edit.php?post_type=salary-template' );
		await page.waitForLoadState( 'networkidle' );

		// 3 件とも一覧に並ぶこと。
		await expect( page.locator( '.wp-list-table tbody .row-title', { hasText: '麗美標準テンプレA' } ) ).toBeVisible();
		await expect( page.locator( '.wp-list-table tbody .row-title', { hasText: '麗美標準テンプレB' } ) ).toBeVisible();
		await expect( page.locator( '.wp-list-table tbody .row-title', { hasText: '麗美未設定テンプレ' } ) ).toBeVisible();

		await page.screenshot( { path: 'tests/e2e/screenshots/pr48/02-template-list.png', fullPage: true } );
	} );

	test( '03. 一括登録パネル: 支給分 select のみ表示。テンプレ select / スタッフ UI / 全選択ボタンが存在しないこと', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/edit.php?post_type=salary' );
		await page.waitForLoadState( 'networkidle' );

		// パネルが存在し、summary に「給与テンプレートから一括登録」。
		const panel = page.locator( 'details.bvsl-bulk-create' );
		await expect( panel ).toHaveCount( 1 );
		await expect( panel.locator( 'summary' ) ).toContainText( '給与テンプレートから一括登録' );

		// テンプレと支給分の両方が用意済みなので、初期状態は閉じている。
		const isOpen = await panel.evaluate( ( el ) => el.hasAttribute( 'open' ) );
		expect( isOpen ).toBe( false );

		// summary をクリックして開く。
		await panel.locator( 'summary' ).click();

		// 仕様変更後の UI: 支給分 select のみ存在する。
		await expect( page.locator( '#bvsl-bulk-term' ) ).toBeVisible();

		// 旧仕様にあった UI が削除されていること。
		await expect( page.locator( '#bvsl-bulk-template' ) ).toHaveCount( 0 );
		await expect( page.locator( '#bvsl-bulk-staff-list' ) ).toHaveCount( 0 );
		await expect( page.locator( '#bvsl-bulk-staff-all' ) ).toHaveCount( 0 );
		await expect( page.locator( '.bvsl-bulk-staff-checkbox' ) ).toHaveCount( 0 );

		// 公開中のテンプレ件数の説明文が出ていること（テンプレ 3 件公開中）。
		const formText = await page.locator( '.bvsl-bulk-create__form' ).textContent();
		expect( formText ).toContain( '公開中の給与テンプレートは 3 件です' );

		await page.screenshot( { path: 'tests/e2e/screenshots/pr48/03-panel-open.png', fullPage: true } );
	} );

	test( '04. 件数プレビュー: 「公開中のテンプレート N 件を「○○」で下書き作成します。」の文言が表示される', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/edit.php?post_type=salary' );
		await page.waitForLoadState( 'networkidle' );
		await page.locator( 'details.bvsl-bulk-create > summary' ).click();

		// 初期は「支給分を選んでください。」。
		const initialText = ( await page.locator( '#bvsl-bulk-summary' ).textContent() ).trim();
		expect( initialText ).toContain( '支給分を選んでください' );

		// 支給分を選択。
		await page.locator( '#bvsl-bulk-term' ).selectOption( { label: '2026年5月分' } );

		// 件数プレビューが「公開中のテンプレート 3 件を「2026年5月分」で下書き作成します。」になること。
		const summaryText = ( await page.locator( '#bvsl-bulk-summary' ).textContent() ).trim();
		expect( summaryText ).toMatch( /公開中のテンプレート\s*3\s*件を「2026年5月分」で下書き作成します/ );

		await page.screenshot( { path: 'tests/e2e/screenshots/pr48/04-summary-preview.png', fullPage: true } );
	} );

	test( '05. 一括登録の実行: 公開テンプレ全件 → 生成 2 / 重複 0 / スタッフ未設定 1 / details にテンプレ名', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/edit.php?post_type=salary' );
		await page.waitForLoadState( 'networkidle' );
		await page.locator( 'details.bvsl-bulk-create > summary' ).click();

		// 支給分を選択。
		await page.locator( '#bvsl-bulk-term' ).selectOption( { label: '2026年5月分' } );

		// 確認ダイアログを accept。
		let confirmShown = false;
		page.once( 'dialog', async ( dialog ) => {
			confirmShown = ( dialog.type() === 'confirm' );
			expect( dialog.message() ).toMatch( /公開中のテンプレート\s*3\s*件を「2026年5月分」で下書き作成します/ );
			await dialog.accept();
		} );

		// 送信。
		await Promise.all( [
			page.waitForURL( /edit\.php\?post_type=salary/, { timeout: 30000 } ),
			page.locator( '#bvsl-bulk-submit' ).click(),
		] );

		expect( confirmShown, '送信前の confirm ダイアログが出ていない' ).toBe( true );

		// 成功通知の検証。
		const notice = page.locator( '.notice-success' );
		await expect( notice ).toBeVisible();
		await expect( notice ).toContainText( '一括登録しました' );
		// 生成: 2（A / B のスタッフあり）/ 重複: 0 / スタッフ未設定: 1（麗美未設定テンプレ）。
		await expect( notice ).toContainText( '生成: 2 件' );
		await expect( notice ).toContainText( '重複スキップ: 0 件' );
		await expect( notice ).toContainText( 'スタッフ未設定スキップ: 1 件' );

		// スタッフ未設定 details が存在し、開くと「麗美未設定テンプレ」が含まれる。
		const noStaffDetails = notice.locator( 'details', { hasText: 'スタッフ未設定' } );
		await expect( noStaffDetails ).toBeVisible();
		await noStaffDetails.locator( 'summary' ).click();
		const noStaffText = await noStaffDetails.locator( 'p' ).textContent();
		expect( noStaffText ).toContain( '麗美未設定テンプレ' );

		// 重複 details はこの段階では存在しない（生成 2 / 重複 0 のため）。
		await expect( notice.locator( 'details', { hasText: '重複でスキップ' } ) ).toHaveCount( 0 );

		await page.screenshot( { path: 'tests/e2e/screenshots/pr48/05-result-success.png', fullPage: true } );

		// 一覧上の draft タイトルを検証。「スタッフ名 / 支給分」形式。
		const draftTitles = await page.locator( '.wp-list-table tbody tr .row-title' ).allTextContents();
		// スタッフA / 2026年5月分 と スタッフB / 2026年5月分 が存在すること。
		expect( draftTitles.some( ( t ) => /麗美テストスタッフA\s*\/\s*2026年5月分/.test( t ) ) ).toBe( true );
		expect( draftTitles.some( ( t ) => /麗美テストスタッフB\s*\/\s*2026年5月分/.test( t ) ) ).toBe( true );
	} );

	test( '06. 重複スキップ: 同じ支給分でもう一度送信 → 生成 0 / 重複 2 / スタッフ未設定 1', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/edit.php?post_type=salary' );
		await page.waitForLoadState( 'networkidle' );
		await page.locator( 'details.bvsl-bulk-create > summary' ).click();

		await page.locator( '#bvsl-bulk-term' ).selectOption( { label: '2026年5月分' } );

		page.once( 'dialog', async ( dialog ) => {
			await dialog.accept();
		} );

		await Promise.all( [
			page.waitForURL( /edit\.php\?post_type=salary/, { timeout: 30000 } ),
			page.locator( '#bvsl-bulk-submit' ).click(),
		] );

		const notice = page.locator( '.notice-success' );
		await expect( notice ).toBeVisible();
		await expect( notice ).toContainText( '生成: 0 件' );
		await expect( notice ).toContainText( '重複スキップ: 2 件' );
		await expect( notice ).toContainText( 'スタッフ未設定スキップ: 1 件' );

		// 重複 details が存在し、スタッフ A / B 名が含まれる。
		const dupDetails = notice.locator( 'details', { hasText: '重複でスキップ' } );
		await expect( dupDetails ).toBeVisible();
		await dupDetails.locator( 'summary' ).click();
		const dupText = await dupDetails.locator( 'p' ).textContent();
		expect( dupText ).toContain( '麗美テストスタッフA' );
		expect( dupText ).toContain( '麗美テストスタッフB' );

		await page.screenshot( { path: 'tests/e2e/screenshots/pr48/06-skip-notice.png', fullPage: true } );
	} );

	test( '07. テンプレ編集画面: 「このテンプレで一括登録」ボタンが存在しない（補助導線削除済み）', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/edit.php?post_type=salary-template' );
		await page.waitForLoadState( 'networkidle' );

		// 一覧から「麗美標準テンプレA」を編集。
		const link = page.locator( '.wp-list-table tbody .row-title', { hasText: '麗美標準テンプレA' } ).first();
		await expect( link ).toBeVisible();
		await link.click();
		await page.waitForLoadState( 'networkidle' );

		// メタボックスタイトル: 「給与テンプレート基本項目」「給与テンプレート給与テーブル」が維持されている。
		const headers = await page.locator( '#poststuff .postbox > .postbox-header h2, #poststuff .postbox > h2.hndle' ).allTextContents();
		const headersJoined = headers.join( ' / ' );
		expect( headersJoined ).toContain( '給与テンプレート基本項目' );
		expect( headersJoined ).toContain( '給与テンプレート給与テーブル' );

		// publish メタボックス内に「このテンプレで一括登録」ボタンが無いこと。
		await expect( page.locator( '#submitdiv', { hasText: 'このテンプレで一括登録' } ) ).toHaveCount( 0 );
		// ページ全体でも当該文言のリンク・ボタンが無いこと。
		await expect( page.getByRole( 'link', { name: 'このテンプレで一括登録' } ) ).toHaveCount( 0 );
		await expect( page.getByRole( 'button', { name: 'このテンプレで一括登録' } ) ).toHaveCount( 0 );

		// スタッフ・Staff No. フィールドが表示されている（仕様変更で復活）。
		await expect( page.locator( '#salary_staff' ) ).toHaveCount( 1 );
		await expect( page.locator( '#salary_staff_number' ) ).toHaveCount( 1 );

		await page.screenshot( { path: 'tests/e2e/screenshots/pr48/07-template-edit-no-button.png', fullPage: true } );
	} );

	test( '08-a. 0 件時: テンプレ未作成 → 「給与テンプレートを作成」遷移ボタン表示', async ( { page } ) => {
		// 一時的にテンプレを全削除する。
		purgePostType( 'salary-template' );

		try {
			await loginAsAdmin( page );
			await page.goto( '/wp-admin/edit.php?post_type=salary' );
			await page.waitForLoadState( 'networkidle' );

			const panel = page.locator( 'details.bvsl-bulk-create' );
			await expect( panel ).toBeVisible();

			// テンプレ 0 件時は自動で open。
			const isOpen = await panel.evaluate( ( el ) => el.hasAttribute( 'open' ) );
			expect( isOpen ).toBe( true );

			// 案内文と遷移ボタンが表示される。
			await expect( panel ).toContainText( '給与テンプレートがまだ登録されていません' );
			const createBtn = panel.getByRole( 'link', { name: '給与テンプレートを作成' } );
			await expect( createBtn ).toBeVisible();
			expect( await createBtn.getAttribute( 'href' ) ).toContain( 'post-new.php?post_type=salary-template' );

			// フォーム自体は出ない（送信ボタン無し）。
			await expect( panel.locator( '#bvsl-bulk-submit' ) ).toHaveCount( 0 );
			await expect( panel.locator( '#bvsl-bulk-term' ) ).toHaveCount( 0 );

			await page.screenshot( { path: 'tests/e2e/screenshots/pr48/08a-no-templates.png', fullPage: true } );
		} finally {
			// テンプレを再構築。
			setupTestData();
		}
	} );

	test( '08-b. 0 件時: 支給分未登録 → 「支給分を登録する」遷移ボタン表示', async ( { page } ) => {
		// 支給分タームを一時退避（削除）して、復元用に名前を保持する。
		const csv = wpCli( 'term list salary-term --format=csv --fields=term_id,name' );
		const termNames = [];
		const termIdList = [];
		csv.split( '\n' ).forEach( ( line ) => {
			const cols = line.split( ',' );
			// 数字 ID で始まる行のみ採用（progress 行や header をスキップ）。
			if ( cols.length >= 2 && /^\d+$/.test( cols[ 0 ] ) ) {
				termIdList.push( cols[ 0 ] );
				termNames.push( { id: cols[ 0 ], name: cols[ 1 ] } );
			}
		} );
		if ( termIdList.length > 0 ) {
			wpCli( `term delete salary-term ${ termIdList.join( ' ' ) }` );
		}

		try {
			await loginAsAdmin( page );
			await page.goto( '/wp-admin/edit.php?post_type=salary' );
			await page.waitForLoadState( 'networkidle' );

			const panel = page.locator( 'details.bvsl-bulk-create' );
			await expect( panel ).toBeVisible();
			const isOpen = await panel.evaluate( ( el ) => el.hasAttribute( 'open' ) );
			expect( isOpen ).toBe( true );

			await expect( panel ).toContainText( '一括登録には「支給分」が必要です' );
			const link = panel.getByRole( 'link', { name: '支給分を登録する' } );
			await expect( link ).toBeVisible();
			expect( await link.getAttribute( 'href' ) ).toContain( 'edit-tags.php?taxonomy=salary-term' );

			// フォーム自体は出ない。
			await expect( panel.locator( '#bvsl-bulk-submit' ) ).toHaveCount( 0 );
			await expect( panel.locator( '#bvsl-bulk-term' ) ).toHaveCount( 0 );

			await page.screenshot( { path: 'tests/e2e/screenshots/pr48/08b-no-terms.png', fullPage: true } );
		} finally {
			// ターム復元。
			for ( const t of termNames ) {
				try {
					wpCli( `term create salary-term "${ t.name }"` );
				} catch ( e ) {
					// 既に存在していたらスキップ。
				}
			}
		}
	} );

	test( '09. 権限: edit_posts のみの salary_editor ではパネルが表示されない', async ( { browser } ) => {
		const context = await browser.newContext();
		const page = await context.newPage();
		try {
			await loginAsSalaryEditor( page );
			await page.goto( '/wp-admin/edit.php?post_type=salary' );
			await page.waitForLoadState( 'networkidle' );

			// パネル自体が描画されない（current_user_can('manage_options') ガード）。
			await expect( page.locator( 'details.bvsl-bulk-create' ) ).toHaveCount( 0 );

			await page.screenshot( { path: 'tests/e2e/screenshots/pr48/09-no-panel-for-editor.png', fullPage: true } );
		} finally {
			await context.close();
		}
	} );

	test( '10. メニュー: 給与テンプレートが給与明細の直下に並ぶ', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/index.php' );
		await page.waitForLoadState( 'networkidle' );

		// 管理メニュー（#adminmenu）の li の順序を取得し、salary の直後に salary-template があることを確認。
		const menuKeys = await page.evaluate( () => {
			const items = Array.from( document.querySelectorAll( '#adminmenu > li.menu-top' ) );
			return items.map( ( li ) => {
				// メインリンクの href から post_type 部分を抽出。
				const a = li.querySelector( 'a.menu-top' );
				return a ? a.getAttribute( 'href' ) || '' : '';
			} );
		} );

		const salaryIdx = menuKeys.findIndex( ( h ) => h && h.includes( 'edit.php?post_type=salary' ) && ! h.includes( 'salary-template' ) );
		const tplIdx = menuKeys.findIndex( ( h ) => h && h.includes( 'edit.php?post_type=salary-template' ) );

		expect( salaryIdx, '給与明細メニューが存在する' ).toBeGreaterThanOrEqual( 0 );
		expect( tplIdx, '給与テンプレートメニューが存在する' ).toBeGreaterThanOrEqual( 0 );
		// 給与テンプレートが給与明細の直下（インデックス +1）に並んでいる。
		expect( tplIdx ).toBe( salaryIdx + 1 );

		await page.screenshot( { path: 'tests/e2e/screenshots/pr48/10-menu-order.png', fullPage: true } );
	} );

	test( '11. デグレ: 既存給与明細の編集画面でメタボックス・PDF/メール履歴 UI が正常表示', async ( { page } ) => {
		// 08-a / 08-b の影響で salary が空になっている可能性があるため、
		// このテスト用に 1 件作って編集画面で確認する。
		const staffOut = wpCli(
			'post list --post_type=staff --post_status=publish --posts_per_page=1 --format=csv --fields=ID'
		);
		let staffId = '';
		staffOut.split( '\n' ).forEach( ( line ) => {
			if ( /^\d+$/.test( line.trim() ) ) {
				staffId = line.trim();
			}
		} );
		expect( staffId, 'スタッフが 1 件以上登録されている' ).not.toBe( '' );

		const salaryId = wpCli(
			`post create --post_type=salary --post_status=draft --post_title=デグレ確認用 --porcelain`
		).trim().split( '\n' ).filter( ( l ) => /^\d+$/.test( l.trim() ) )[ 0 ];
		wpCli( `post meta update ${ salaryId } salary_staff ${ staffId }` );

		try {
			await loginAsAdmin( page );
			await page.goto( `/wp-admin/post.php?post=${ salaryId }&action=edit` );
			await page.waitForLoadState( 'networkidle' );

			// 給与明細編集画面のメタボックスタイトル。
			const headers = await page.locator( '#poststuff .postbox > .postbox-header h2, #poststuff .postbox > h2.hndle' ).allTextContents();
			const headersJoined = headers.join( ' / ' );
			expect( headersJoined ).toContain( '給与明細基本項目' );
			// salary 投稿側のテーブルメタボックスタイトルが「給与明細給与テーブル」または「給与明細項目」のどちらかで実装される。
			expect( headersJoined ).toMatch( /給与明細(給与テーブル|項目)/ );

			// salary_staff フィールドが存在し、値が入っている。
			const staffField = page.locator( '#salary_staff' );
			await expect( staffField ).toHaveCount( 1 );
			const staffValue = await staffField.inputValue();
			expect( staffValue ).toBe( staffId );

			// 発行済 PDF（salary_send_pdf）は salary 側でのみ存在する（hidden 属性で出ている）。
			await expect( page.locator( 'input[name="salary_send_pdf"]' ).first() ).toHaveCount( 1 );

			// 致命的なエラーが出ていない。
			await expect( page.locator( '#wpbody-content' ) ).not.toContainText( '致命的なエラー' );
			await expect( page.locator( '#wpbody-content' ) ).not.toContainText( 'Fatal error' );

			await page.screenshot( { path: 'tests/e2e/screenshots/pr48/11-salary-edit.png', fullPage: true } );
		} finally {
			// テスト用 salary を削除。
			wpCli( `post delete ${ salaryId } --force` );
		}
	} );
} );
