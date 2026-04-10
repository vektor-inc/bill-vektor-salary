const { test, expect } = require( '@playwright/test' );

// WordPress管理画面にログインする。
test.beforeEach( async ( { page } ) => {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( /wp-admin/ );
} );

test.describe( 'PR #42: 令和8年度雇用保険料率対応', () => {

	test( '1. グローバル設定ページ - 事業の種類が表示・保存できる', async ( { page } ) => {
		// 給与明細 > 給与設定ページを開く。
		await page.goto( '/wp-admin/edit.php?post_type=salary&page=bvsl-settings' );
		await page.waitForLoadState( 'networkidle' );

		// 「事業の種類」セレクトボックスが表示されていることを確認する。
		const businessTypeSelect = page.locator( '#bvsl_business_type' );
		await expect( businessTypeSelect ).toBeVisible();

		// スクリーンショット: 給与設定ページ初期表示。
		await page.screenshot( { path: 'tests/e2e/screenshots/01-settings-page.png', fullPage: true } );

		// 「建設の事業」を選択して保存する。
		await businessTypeSelect.selectOption( 'construction' );
		await page.locator( '#submit' ).click();
		await page.waitForLoadState( 'networkidle' );

		// 保存後、値が保持されていることを確認する。
		await expect( businessTypeSelect ).toHaveValue( 'construction' );

		// スクリーンショット: 設定保存後。
		await page.screenshot( { path: 'tests/e2e/screenshots/02-settings-saved.png', fullPage: true } );

		// テスト後に一般に戻す。
		await businessTypeSelect.selectOption( 'general' );
		await page.locator( '#submit' ).click();
	} );

	test( '2. スタッフ投稿 - 事業の種類フィールドが表示される', async ( { page } ) => {
		// スタッフ新規追加ページを開く。
		await page.goto( '/wp-admin/post-new.php?post_type=staff' );
		await page.waitForLoadState( 'networkidle' );

		// 「事業の種類」セレクトボックスが存在することを確認する。
		const staffBusinessType = page.locator( '#salary_business_type' );
		await expect( staffBusinessType ).toBeVisible();

		// スクリーンショット: スタッフ新規追加画面。
		await page.screenshot( { path: 'tests/e2e/screenshots/03-staff-new.png', fullPage: true } );

		// Staff No.、基本給、事業の種類を入力して公開する。
		await page.locator( '#salary_staff_number' ).fill( 'E2E-001' );
		await page.locator( '#salary_base' ).fill( '300000' );
		await page.locator( '#salary_transportation_total' ).fill( '10000' );
		await staffBusinessType.selectOption( 'agriculture' );

		// タイトルを入力する。
		await page.locator( '#title' ).fill( 'E2Eテスト用スタッフ' );

		// 公開ボタンをクリックする。
		await page.locator( '#publish' ).click();
		await page.waitForLoadState( 'networkidle' );

		// 投稿が正常に公開されたことを確認する。
		await expect( page.locator( '#message' ) ).toBeVisible();
		await expect( page.locator( '#message' ) ).toContainText( '投稿を公開しました' );

		// スクリーンショット: スタッフ保存後。
		await page.screenshot( { path: 'tests/e2e/screenshots/04-staff-saved.png', fullPage: true } );
	} );

	test( '3. 給与明細 - スタッフ選択時のデフォルト値自動反映', async ( { page } ) => {
		// まずテスト用スタッフを作成する。
		await page.goto( '/wp-admin/post-new.php?post_type=staff' );
		await page.waitForLoadState( 'networkidle' );
		await page.locator( '#title' ).fill( 'E2E自動反映テスト用スタッフ' );
		await page.locator( '#salary_staff_number' ).fill( 'E2E-002' );
		await page.locator( '#salary_fuyou' ).fill( '2' );
		await page.locator( '#salary_base' ).fill( '300000' );
		await page.locator( '#salary_transportation_total' ).fill( '15000' );
		await page.locator( '#salary_business_type' ).selectOption( 'agriculture' );
		await page.locator( '#publish' ).click();
		await page.waitForLoadState( 'networkidle' );

		// 給与明細の新規追加ページを開く。
		await page.goto( '/wp-admin/post-new.php?post_type=salary' );
		await page.waitForLoadState( 'networkidle' );

		// スクリーンショット: 給与明細初期表示。
		await page.screenshot( { path: 'tests/e2e/screenshots/05-salary-new-initial.png', fullPage: true } );

		// スタッフを選択する。
		const staffSelect = page.locator( '#salary_staff' );
		// 作成したスタッフを選択する（最後に追加されたもの）。
		const options = await staffSelect.locator( 'option' ).all();
		let staffOptionValue = '';
		for ( const option of options ) {
			const text = await option.textContent();
			if ( text.includes( 'E2E自動反映テスト用スタッフ' ) ) {
				staffOptionValue = await option.getAttribute( 'value' );
				break;
			}
		}

		// スタッフが見つからない場合はテストを失敗させる。
		expect( staffOptionValue, 'E2E自動反映テスト用スタッフが見つかりませんでした' ).toBeTruthy();

		await staffSelect.selectOption( staffOptionValue );

		// Staff No.が自動反映されるまで待つ。
		const staffNumber = page.locator( '#salary_staff_number' );
		await expect( staffNumber ).toHaveValue( 'E2E-002' );

		// 基本給が自動反映されたことを確認する。
		const baseSalary = page.locator( '#salary_base' );
		await expect( baseSalary ).toHaveValue( '300000' );

		// 交通費が自動反映されたことを確認する。
		const transportation = page.locator( '#salary_transportation_total' );
		await expect( transportation ).toHaveValue( '15000' );

		// 事業の種類が自動反映されたことを確認する。
		const businessType = page.locator( '#salary_business_type' );
		await expect( businessType ).toHaveValue( 'agriculture' );

		// スクリーンショット: スタッフ選択後の自動反映結果。
		await page.screenshot( { path: 'tests/e2e/screenshots/06-salary-staff-selected.png', fullPage: true } );
	} );

	test( '4. 給与明細 - 既存値は上書きされない', async ( { page } ) => {
		// テスト用スタッフを確認する（テスト3で作成済み）。
		await page.goto( '/wp-admin/post-new.php?post_type=salary' );
		await page.waitForLoadState( 'networkidle' );

		// 先に基本給を手動入力する。
		const baseSalary = page.locator( '#salary_base' );
		await baseSalary.fill( '350000' );

		// スタッフを選択する。
		const staffSelect = page.locator( '#salary_staff' );
		const options = await staffSelect.locator( 'option' ).all();
		let staffOptionValue = '';
		for ( const option of options ) {
			const text = await option.textContent();
			if ( text.includes( 'E2E自動反映テスト用スタッフ' ) ) {
				staffOptionValue = await option.getAttribute( 'value' );
				break;
			}
		}

		// スタッフが見つからない場合はテストを失敗させる。
		expect( staffOptionValue, 'E2E自動反映テスト用スタッフが見つかりませんでした' ).toBeTruthy();

		await staffSelect.selectOption( staffOptionValue );

		// Staff No.が自動反映されるまで待つ（他のフィールドも反映済みと判断できる）。
		const staffNumber = page.locator( '#salary_staff_number' );
		await expect( staffNumber ).toHaveValue( 'E2E-002' );

		// 基本給は手動入力値（350000）が保持されていることを確認する。
		await expect( baseSalary ).toHaveValue( '350000' );

		// スクリーンショット: 既存値が上書きされないことの確認。
		await page.screenshot( { path: 'tests/e2e/screenshots/07-salary-no-overwrite.png', fullPage: true } );
	} );

	test( '5. 雇用保険料率 - 事業種類別の計算確認', async ( { page } ) => {
		// 給与明細の新規追加ページを開く。
		await page.goto( '/wp-admin/post-new.php?post_type=salary' );
		await page.waitForLoadState( 'networkidle' );

		// 基本給を入力する。
		await page.locator( '#salary_base' ).fill( '300000' );

		// 「令和8年4月1日〜」を選択する。
		await page.locator( '#salary_target_term' ).selectOption( '20260401_after' );

		// 「一般の事業」を選択する。
		await page.locator( '#salary_business_type' ).selectOption( 'general' );

		// 雇用保険料が1500（300000 × 5/1000）に更新されるまで待つ。
		const koyouHoken = page.locator( '#bvsl_koyouhoken_display' );
		await expect( koyouHoken ).toHaveText( '1,500' );

		// スクリーンショット: 一般の事業・令和8年の料率。
		await page.screenshot( { path: 'tests/e2e/screenshots/08-rate-general-2026.png', fullPage: true } );

		// 「建設の事業」に変更する。
		await page.locator( '#salary_business_type' ).selectOption( 'construction' );

		// 雇用保険料が1800（300000 × 6/1000）に更新されるまで待つ。
		await expect( koyouHoken ).toHaveText( '1,800' );

		// スクリーンショット: 建設の事業・令和8年の料率。
		await page.screenshot( { path: 'tests/e2e/screenshots/09-rate-construction-2026.png', fullPage: true } );
	} );

	test( '6. 給与対象時期の選択肢 - 令和8年4月1日〜が追加されている', async ( { page } ) => {
		// 給与明細の新規追加ページを開く。
		await page.goto( '/wp-admin/post-new.php?post_type=salary' );
		await page.waitForLoadState( 'networkidle' );

		// 給与対象時期のオプションを確認する。
		const termSelect = page.locator( '#salary_target_term' );
		const options = await termSelect.locator( 'option' ).allTextContents();

		// 「令和8年4月1日〜」が含まれていることを確認する。
		expect( options ).toContain( '令和8年4月1日〜' );

		// 新しい時期が上に来ることを確認する（最初のオプションが令和8年）。
		expect( options[ 0 ] ).toBe( '令和8年4月1日〜' );

		// スクリーンショット: 給与対象時期の選択肢一覧。
		await termSelect.click();
		await page.screenshot( { path: 'tests/e2e/screenshots/10-term-options.png', fullPage: true } );
	} );

	test( '7. デグレ確認 - 既存の料率が変わっていない', async ( { page } ) => {
		// 給与明細の新規追加ページを開く。
		await page.goto( '/wp-admin/post-new.php?post_type=salary' );
		await page.waitForLoadState( 'networkidle' );

		// 基本給を入力する。
		await page.locator( '#salary_base' ).fill( '300000' );

		// 「一般の事業」を明示選択する。
		await page.locator( '#salary_business_type' ).selectOption( 'general' );

		// 令和7年4月1日〜 を選択 → 1650（300000 × 5.5/1000）。
		await page.locator( '#salary_target_term' ).selectOption( '20250401_after' );
		const koyouHoken = page.locator( '#bvsl_koyouhoken_display' );
		await expect( koyouHoken ).toHaveText( '1,650' );

		// スクリーンショット: 令和7年の料率（デグレ確認）。
		await page.screenshot( { path: 'tests/e2e/screenshots/11-rate-regression-r7.png', fullPage: true } );

		// 令和5年4月1日〜 を選択 → 1800（300000 × 6/1000）。
		await page.locator( '#salary_target_term' ).selectOption( '20230401_after' );
		await expect( koyouHoken ).toHaveText( '1,800' );

		// スクリーンショット: 令和5年の料率（デグレ確認）。
		await page.screenshot( { path: 'tests/e2e/screenshots/12-rate-regression-r5.png', fullPage: true } );
	} );
} );
