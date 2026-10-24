# E2E テストの実行

## worktree での準備

1. `npm ci` と `composer install` を実行する。
2. `.wp-env.override.json` に空いている `port` / `testsPort` を設定する。`8888` / `8889` は使わない。
3. `npx wp-env start` で wp-env を起動する。
4. tests 環境を使う場合は、`WP_BASE_URL` に `testsPort` の URL、`WP_ENV_TESTS_PORT` に同じポート番号を指定して `npx playwright test` を実行する。
5. 終了後に `npx wp-env stop` を実行する。`reset` / `clean` / `destroy` は実行しない。

例として tests 環境のポートが `9301` の場合は、次のように実行する。

    WP_BASE_URL=http://localhost:9301 WP_ENV_TESTS_PORT=9301 npx playwright test

## BillVektor テーマ

Playwright の global setup が、ブラウザの接続先と同じ wp-env 環境に BillVektor テーマをインストールして有効化する。通常は `tests/themes/bill-vektor` を手動配置する必要はない。

テーマの自動インストールにはネットワーク接続が必要。自動インストールに失敗する環境では、BillVektor テーマを対象の wp-env 環境へ事前にインストールして有効化する。
