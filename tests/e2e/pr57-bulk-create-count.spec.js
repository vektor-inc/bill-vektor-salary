const { test, expect } = require( '@playwright/test' );
const { execFileSync } = require( 'child_process' );

/**
 * PR #57: 給与テンプレ一括登録パネルの件数取得を wp_count_posts() ベース化 + 文言中立化の e2e テスト。
 *
 * 確認観点:
 *   01. パネルの lead 文に「対象テンプレート N 件」相当の表記が出ていて、
 *       N が publish + private の合計と一致し、draft が除外されていること。
 *   02. JS 側 i18n の summary / confirm 文言が「対象テンプレート」表記になっていること
 *       （旧「公開中のテンプレート」が残っていないこと）。
 *   03. パネルの localize された templateCount が wp_count_posts() ベースの件数であること。
 *   04. 一括登録の実行が従来通り動作すること（デグレ確認）。
 *   05. 0 件時のガイダンス文言・empty 状態の遷移確認（draft しか無い状態）。
 *
 * 並列実行・他 spec との干渉を避けるため、本 spec 専用の fixture meta を付ける。
 */

const FIXTURE_META_KEY = '_e2e_fixture_pr57';
const FIXTURE_META_VALUE = 'pr57-bulk-create-count-spec';

// admin としてログイン。
async function loginAsAdmin( page ) {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( /wp-admin/ );
}

// wp-cli 呼び出しヘルパ（execFileSync ベースで shell 経由を避ける）。
function wpCli( argsArray ) {
	if ( ! Array.isArray( argsArray ) ) {
		throw new TypeError( 'wpCli: argsArray must be an array of tokens' );
	}
	return execFileSync(
		'npx',
		[ 'wp-env', 'run', 'cli', 'wp', ...argsArray ],
		{ encoding: 'utf8' }
	);
}

// wp-cli の JSON 出力を装飾行や前後ノイズを除去してから parse する。
function parseWpJson( raw, fallback ) {
	const normalized = raw
		.replace( /ℹ.*$/gm, '' )
		.replace( /✔.*$/gm, '' )
		.replace( /^[^[{]*/s, '' )
		.trim();
	try {
		return JSON.parse( normalized );
	} catch {
		return fallback;
	}
}

// 本 spec の fixture meta が付いた指定 post_type 投稿のみ削除する。
function purgePostFixtures( postType ) {
	const raw = wpCli( [
		'post',
		'list',
		`--post_type=${ postType }`,
		'--post_status=any',
		`--meta_key=${ FIXTURE_META_KEY }`,
		`--meta_value=${ FIXTURE_META_VALUE }`,
		'--format=ids',
	] );
	const ids = raw
		.replace( /ℹ.*$/gm, '' )
		.replace( /✔.*$/gm, '' )
		.trim()
		.split( /\s+/ )
		.filter( ( s ) => /^\d+$/.test( s ) );
	for ( const id of ids ) {
		wpCli( [ 'post', 'delete', id, '--force' ] );
	}
}

// 本 spec のタイトル prefix で残った投稿（fixture meta が引き継がれていない salary draft 等）を併せて削除する。
function purgePostsByTitlePrefix( postType, titlePrefix ) {
	const raw = wpCli( [
		'post',
		'list',
		`--post_type=${ postType }`,
		'--post_status=any',
		'--format=json',
		'--fields=ID,post_title',
	] );
	const list = parseWpJson( raw, [] );
	for ( const p of list ) {
		if (
			typeof p.post_title === 'string' &&
			p.post_title.startsWith( titlePrefix )
		) {
			wpCli( [ 'post', 'delete', String( p.ID ), '--force' ] );
		}
	}
}

// PR57 prefix で作ったタームのみ削除する。
function purgeTermFixtures( taxonomy ) {
	const raw = wpCli( [
		'term',
		'list',
		taxonomy,
		'--format=json',
		'--fields=term_id,slug',
	] );
	const terms = parseWpJson( raw, [] );
	for ( const t of terms ) {
		if ( typeof t.slug === 'string' && t.slug.startsWith( 'pr57-' ) ) {
			try {
				wpCli( [ 'term', 'delete', taxonomy, String( t.term_id ) ] );
			} catch ( e ) {
				// noop
			}
		}
	}
}

function trimWpOut( raw ) {
	return raw.replace( /ℹ.*$/gm, '' ).replace( /✔.*$/gm, '' ).trim();
}

// fixture を作成する。publish 2 件、private 1 件、draft 1 件のテンプレを作り、
// それぞれに salary_staff メタを設定する。支給分タームも 1 つ作成する。
function setupFixtures() {
	// 1) スタッフ 3 人作成。
	const staffIds = [];
	for ( let i = 1; i <= 3; i++ ) {
		const out = wpCli( [
			'post',
			'create',
			'--post_type=salary-staff',
			`--post_title=PR57_Staff_${ i }`,
			'--post_status=publish',
			'--porcelain',
		] );
		const id = trimWpOut( out );
		wpCli( [ 'post', 'meta', 'update', id, FIXTURE_META_KEY, FIXTURE_META_VALUE ] );
		staffIds.push( id );
	}

	// 2) salary-term タームを作成。
	const termOut = wpCli( [
		'term',
		'create',
		'salary-term',
		'PR57_Term_2026_05',
		'--slug=pr57-2026-05',
		'--porcelain',
	] );
	const termId = trimWpOut( termOut )
		.split( /\s+/ )
		.find( ( s ) => /^\d+$/.test( s ) );

	// 3) テンプレ 4 件作成（publish 2 / private 1 / draft 1）。
	function createTpl( title, status, staffId ) {
		const out = wpCli( [
			'post',
			'create',
			'--post_type=salary-template',
			`--post_title=${ title }`,
			`--post_status=${ status }`,
			'--porcelain',
		] );
		const id = trimWpOut( out );
		wpCli( [ 'post', 'meta', 'update', id, FIXTURE_META_KEY, FIXTURE_META_VALUE ] );
		wpCli( [ 'post', 'meta', 'update', id, 'salary_staff', staffId ] );
		return id;
	}

	const tplPub1 = createTpl( 'PR57_Tpl_Pub_1', 'publish', staffIds[ 0 ] );
	const tplPub2 = createTpl( 'PR57_Tpl_Pub_2', 'publish', staffIds[ 1 ] );
	const tplPriv = createTpl( 'PR57_Tpl_Priv', 'private', staffIds[ 2 ] );
	const tplDraft = createTpl( 'PR57_Tpl_Draft', 'draft', staffIds[ 0 ] );

	return {
		staffIds,
		termId,
		tplPub1,
		tplPub2,
		tplPriv,
		tplDraft,
	};
}

// パネルの details を確実に開く。
async function ensurePanelOpen( page ) {
	const details = page.locator( 'details.bvsl-bulk-create' ).first();
	await expect( details ).toBeVisible();
	// 直接 open 属性を付与する方が安定する（click だと iframe や overlay の影響を受けることがある）。
	await details.evaluate( ( el ) => {
		el.open = true;
	} );
}

test.describe( 'PR #57: 一括登録パネル件数取得 + 文言中立化', () => {
	// fixture のセットアップに wp-cli を多数呼ぶため、テストごとのタイムアウトを延長する。
	test.setTimeout( 180000 );

	test.beforeEach( async ( { page } ) => {
		// 既存 fixture を掃除。
		// salary は bill_copy_post の複製なので fixture meta が引き継がれない。
		// 一括登録で生成される salary のタイトルは「<staff_title> / <term_name>」になり
		// PR57_Staff_ から始まる prefix で識別できるため、title prefix でも掃除する。
		purgePostsByTitlePrefix( 'salary', 'PR57_Staff_' );
		purgePostFixtures( 'salary' );
		purgePostFixtures( 'salary-template' );
		purgePostFixtures( 'salary-staff' );
		purgeTermFixtures( 'salary-term' );
		// 新規 fixture を投入。
		setupFixtures();
		await loginAsAdmin( page );
	} );

	test.afterAll( () => {
		purgePostsByTitlePrefix( 'salary', 'PR57_Staff_' );
		purgePostFixtures( 'salary' );
		purgePostFixtures( 'salary-template' );
		purgePostFixtures( 'salary-staff' );
		purgeTermFixtures( 'salary-term' );
	} );

	test( '01: パネル lead 文が「対象テンプレート N 件」表記で、N=publish+private（draft 除外）', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/edit.php?post_type=salary' );
		await ensurePanelOpen( page );

		// lead 文を取得。
		const lead = page.locator( '.bvsl-bulk-create__lead' );
		await expect( lead ).toBeVisible();
		const leadText = ( await lead.innerText() ).trim();

		// 件数 N を抽出。
		const match = leadText.match( /(\d+)\s*件/ );
		expect( match ).not.toBeNull();
		const count = Number( match[ 1 ] );

		// fixture では publish 2 件 + private 1 件 = 3 件、draft 1 件は除外。
		expect( count ).toBe( 3 );

		// 文言が新表記であること（PR #57 で変更）。
		expect( leadText ).toContain( '一括展開の対象となる給与テンプレート' );
		expect( leadText ).toContain( '公開・非公開を含み、下書きは除外' );
		// 旧文言が残っていないこと。
		expect( leadText ).not.toContain( '公開中の給与テンプレートは' );
	} );

	test( '02: localize された templateCount と i18n 文言が新表記', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=salary' );
		// localize 値はページ読み込み時点で window.bvslBulkCreatePanel に入る。
		const data = await page.evaluate( () => window.bvslBulkCreatePanel || null );
		expect( data ).not.toBeNull();
		// wp_localize_script はスカラ値を文字列化する仕様のため、Number キャストして比較する。
		expect( Number( data.templateCount ) ).toBe( 3 );

		expect( data.i18n.summary ).toContain( '対象テンプレート' );
		expect( data.i18n.summary ).not.toContain( '公開中のテンプレート' );
		expect( data.i18n.confirm ).toContain( '対象テンプレート' );
		expect( data.i18n.confirm ).not.toContain( '公開中のテンプレート' );
	} );

	test( '03: 件数プレビュー（送信前 summary）が「対象テンプレート 3 件」を表示', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/edit.php?post_type=salary' );
		await ensurePanelOpen( page );

		// 支給分 select。
		const termSelect = page.locator( '#bvsl-bulk-term' );
		await expect( termSelect ).toBeVisible();
		await termSelect.selectOption( { label: 'PR57_Term_2026_05' } );

		// JS が描画する summary 領域に「対象テンプレート 3 件」が出る。
		const summaryText = page.locator( '#bvsl-bulk-summary' );
		await expect( summaryText ).toContainText( /対象テンプレート\s*3\s*件/, {
			timeout: 5000,
		} );
		// 旧表記が出ていないこと。
		await expect( summaryText ).not.toContainText( '公開中のテンプレート' );
	} );

	test( '04: デグレ確認 - 一括登録実行で publish + private = 3 件分の salary が draft で生成', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/edit.php?post_type=salary' );
		await ensurePanelOpen( page );

		const termSelect = page.locator( '#bvsl-bulk-term' );
		await termSelect.selectOption( { label: 'PR57_Term_2026_05' } );

		// confirm ダイアログを承諾する。
		page.once( 'dialog', ( dialog ) => {
			dialog.accept();
		} );

		// 送信ボタンを押す。
		const submitBtn = page.locator( '#bvsl-bulk-submit' );
		await expect( submitBtn ).toBeEnabled();
		await submitBtn.click();

		// リダイレクト後、結果通知が表示される。
		await page.waitForURL( /post_type=salary/, { timeout: 30000 } );

		// publish 2 件 + private 1 件 = 3 件分の salary が draft で生成されているはず。
		// title prefix で確認する（「PR57_Staff_X / PR57_Term_2026_05」のような形になる想定）。
		const allDraftRaw = wpCli( [
			'post',
			'list',
			'--post_type=salary',
			'--post_status=draft',
			'--format=json',
			'--fields=ID,post_title',
		] );
		const allDrafts = parseWpJson( allDraftRaw, [] );
		const pr57Drafts = allDrafts.filter(
			( p ) => typeof p.post_title === 'string' && /PR57_Staff_/.test( p.post_title )
		);
		// PR57_Staff_1 (publish), PR57_Staff_2 (publish), PR57_Staff_3 (private) の 3 件。
		expect( pr57Drafts.length ).toBe( 3 );
	} );

	test( '05: 0 件時のガイダンス - publish/private が 0 件のとき empty 状態 + 通常 lead が出ない', async ( {
		page,
	} ) => {
		// fixture テンプレを全消去 → draft のみのテンプレを 1 件だけ作って 0 件状態を作る。
		purgePostFixtures( 'salary-template' );
		const draftId = trimWpOut(
			wpCli( [
				'post',
				'create',
				'--post_type=salary-template',
				'--post_title=PR57_OnlyDraft',
				'--post_status=draft',
				'--porcelain',
			] )
		);
		wpCli( [ 'post', 'meta', 'update', draftId, FIXTURE_META_KEY, FIXTURE_META_VALUE ] );

		await page.goto( '/wp-admin/edit.php?post_type=salary' );
		await ensurePanelOpen( page );

		// 0 件のとき empty 案内が出る（form 自体は描画されない）。
		const emptyHint = page.locator(
			'details.bvsl-bulk-create p',
			{ hasText: '給与テンプレートがまだ登録されていません' }
		);
		await expect( emptyHint ).toBeVisible();

		// 通常 lead は描画されない。
		const lead = page.locator( '.bvsl-bulk-create__lead' );
		await expect( lead ).toHaveCount( 0 );

		// localize 値も 0。
		const data = await page.evaluate( () => window.bvslBulkCreatePanel || null );
		expect( data ).not.toBeNull();
		// wp_localize_script はスカラを文字列化するため Number キャスト後に比較。
		expect( Number( data.templateCount ) ).toBe( 0 );
	} );
} );
