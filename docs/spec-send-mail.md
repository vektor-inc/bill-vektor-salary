# 給与明細メール通知機能 実装計画

## 目的
- 投稿タイプ `salary`（給与明細）の投稿内容を、該当スタッフへメール通知できるようにする。
- まずフェーズ1として、通知メール本文の元になる「メッセージ構成」機能を実装する。

## フェーズ1 スコープ（今回の計画対象）
- タクソノミー `salary-term`（支給分）にタームメタ `共通メッセージ` を追加する。
- 給与明細編集画面の「給与明細基本項目」メタボックス内で、「メッセージ」入力欄の上に共通メッセージを表示する。
- 支給分のチェック状態変更時に、共通メッセージ表示をリアルタイム更新する。
- 「メッセージ」下の説明文を新文言へ変更する。
- 「メッセージ構成」項目（ラジオ3択、デフォルト1）を追加する。
- メッセージ組み立て関数を新設し、公開テンプレートの `bill-message` 表示を `salary_message` 直参照から差し替える。

## フェーズ1 確定事項
- 複数の `salary-term` がチェックされている場合、DOM順で走査し、**最初に共通メッセージが空でないターム**を採用する。
- `メッセージ構成` はラジオボタンUIで実装する方針とする。
- 構成 `2` / `3` で片方が空の場合は改行を入れず、非空の値のみを出力する。

## 変更対象ファイル（予定）
- `bill-vektor-salary.php`
  - 管理画面JSへのデータ受け渡し（`wp_localize_script()` など）の追加。
  - 必要に応じて共通メッセージ取得用 Ajax フックを追加。
- `inc/custom-field-setting/custom-field-salary-normal.php`
  - `salary_message` の説明文変更。
  - `salary_message_structure`（メッセージ構成）のフィールド定義追加。
- `assets/js/admin-salary.js`
  - 支給分チェック状態変化を監視し、共通メッセージ表示を更新する DOM 制御を追加。
- `template-parts/doc/frame-salary.php`
  - `$post->salary_message` 直参照を、メッセージ組み立て関数呼び出しへ変更。
- `inc/` 配下の新規ファイル（例: `inc/salary-message.php`）
  - 共通メッセージ取得・最終メッセージ組み立ての関数を定義。

## データ仕様（フェーズ1）
- タームメタキー: `bvsl_salary_term_common_message`（仮）
  - 対象: taxonomy `salary-term`
  - 値型: 文字列（複数行可）
  - 保存時: `wp_kses_post()` 相当でサニタイズ
- 投稿メタキー: `salary_message_structure`
  - 対象: post type `salary`
  - 値: `message_or_common` / `common_then_message` / `message_then_common`
  - デフォルト: `message_or_common`

## 管理画面仕様（フェーズ1）
1. `salary-term` の追加/編集画面に「共通メッセージ」入力欄を追加
- 追加画面: `salary-term_add_form_fields`
- 編集画面: `salary-term_edit_form_fields`
- 保存: `created_salary-term` / `edited_salary-term`

2. 給与明細編集画面での表示
- 表示位置: 「給与明細基本項目」メタボックス内の「メッセージ」入力欄の上。
- 表示内容:
  - チェック済み `salary-term` を DOM 順で確認し、共通メッセージが入力されている最初の1件だけ表示。
  - 該当なしの場合は表示しない。
- 更新契機:
  - 初期表示時
  - `salary-term` チェックボックスの `change` 時（リアルタイム反映）

3. メッセージ説明文変更
- 旧: `※未記入の場合は「今月もお疲れ様でした。」になります。`
- 新: `※ 共通メッセージもメッセージも両方未記入の場合は、「今月もお疲れでした」になります。`

4. メッセージ構成（新規）
- 項目名: `メッセージ構成`
- 入力UI: ラジオボタン（優先）
- 選択肢:
  - `1`: メッセージの内容を反映。メッセージが空の場合は共通メッセージを反映。
  - `2`: 共通メッセージ + メッセージ
  - `3`: メッセージ + 共通メッセージ
- デフォルト: `1`

## メッセージ組み立て仕様（フェーズ1）
- 新規関数（仮）: `bvsl_build_salary_message( $post_id )`
- 入力:
  - 投稿メッセージ: `salary_message`
  - 支給分共通メッセージ: `salary-term` から1件選定して取得
  - 構成種別: `salary_message_structure`
- 出力ルール:
  - 構成 `1`: 投稿メッセージ優先、空なら共通メッセージ
  - 構成 `2`: 共通メッセージ + 改行 + 投稿メッセージ（空要素は連結しない）
  - 構成 `3`: 投稿メッセージ + 改行 + 共通メッセージ（空要素は連結しない）
  - 上記で最終的に空なら `今月もお疲れでした`
- 表示側:
  - 既存 `template-parts/doc/frame-salary.php` の `bill-message` で本関数を使用

## JS 実装方針（フェーズ1）
- 既存 `assets/js/admin-salary.js` に追記して対応。
- 共通メッセージ取得は以下のどちらかで実装:
  - A. 初期ロード時に「term_id => 共通メッセージ」を `wp_localize_script()` で渡し、フロント側だけで解決（軽量・高速）
  - B. チェック変更ごとに Ajax で都度取得（要件の「取得しに行く」に忠実）
- 本件は「リアルタイムで取得しに行く」という要件のため、基本案は B。ただし管理画面負荷と保守性を見て A へ切替可能な設計にする。

## 実装順序（フェーズ1）
1. `salary-term` に共通メッセージの入力/保存を追加
2. メッセージ組み立て関数を新設（単体で呼べる形）
3. 給与明細メタボックスの文言・メッセージ構成フィールドを追加
4. 管理画面JSで共通メッセージの挿入とリアルタイム更新を実装
5. 公開テンプレートの `bill-message` を新関数へ差し替え
6. 動作確認（支給分変更時、構成3パターン、空値フォールバック）

## 確認観点（フェーズ1）
- 支給分1件選択時に共通メッセージが正しく表示される
- 支給分複数選択時に「共通メッセージが入っている最初の1件」だけ表示される
- 支給分チェック変更直後に表示が更新される
- 構成1/2/3で出力順序が仕様どおり
- 両方未記入時に `今月もお疲れでした` になる
- 既存の給与明細表示（他項目）に影響がない

## 不明点・要確認
- `VK_Custom_Field_Builder` の `radio` 型サポート有無
  - 本計画ではサポートあり前提で進める。実装時に差異があれば `select` へ代替する。

## 技術可否回答
- ご相談内容は、フェーズ1の範囲では **技術的に実現可能**。
- 特に「支給分チェック変更時にJavaScriptでDOM挿入してリアルタイム反映」は問題なく対応可能。

---

## フェーズ2 スコープ（PDF発行・保存）

### 目的
- 給与明細投稿をPDF化し、WordPressメディアライブラリに保存する。
- 将来のフェーズ3（メール送信）でPDFを添付ファイルとして利用できるよう、ファイルパスを投稿メタに保持する。

### 採用技術
- PHPライブラリ **mPDF** を Composer で追加（`mpdf/mpdf`）
- 日本語フォント：mPDF 同梱の DejaVu / フォールバック、または別途 IPAフォント等を設定
- 既存の `frame-salary.php` のHTMLを元にPDFを生成する

### フェーズ2 確定事項
- PDF生成はボタン手動押下によるトリガーとする（自動生成はフェーズ3以降）
- 生成されたPDFはWordPressメディアライブラリに保存する
- 発行のたびに**履歴として蓄積**する（再発行しても前のPDFは残る）
- 履歴は個別に削除可能（削除時はメディアライブラリのファイルも合わせて削除）
- PDFファイル名は `salary-{post_id}-{YmdHis}.pdf` とする（タイムスタンプで重複回避）
- 既存の `salary_send_pdf` フィールドはラベルを「発行済PDF（非推奨）」に変更して残す

## 変更対象ファイル（フェーズ2予定）

### 新規ファイル
- `inc/salary-pdf.php`
  - PDF発行の中心ロジック
  - `bvsl_generate_salary_pdf( $post_id )` 関数を定義
  - mPDF のインスタンス化・HTML組み立て・PDF生成・メディアライブラリへの保存
- `assets/js/admin-salary-pdf.js`（または既存 `admin-salary.js` に追記）
  - 「PDF発行」ボタンのクリックハンドラ
  - Ajax リクエスト送信・完了後のUI更新

### 変更ファイル
- `composer.json`
  - `"require"` に `"mpdf/mpdf": "^8.0"` を追加
- `bill-vektor-salary.php`
  - `inc/salary-pdf.php` の require_once を追加
  - Ajax ハンドラ登録：`wp_ajax_bvsl_generate_salary_pdf`
  - 「PDF発行」ボタンを出力するメタボックスを追加（`add_meta_box`）
  - 管理画面JSへの追加データ受け渡し（PDF用 nonce など）

## データ仕様（フェーズ2）

| キー | 対象 | 値型 | 内容 |
|------|------|------|------|
| `bvsl_pdf_history` | post type `salary` | 配列（シリアライズ） | 発行済みPDF履歴。複数件保持 |
| `salary_send_pdf` | post type `salary` | string（URL） | 既存フィールド。非推奨扱いで継続保持 |

### `bvsl_pdf_history` の配列構造

```php
[
  [
    'attachment_id' => 123,
    'filename'      => 'salary-456-20260227160000.pdf',
    'issued_at'     => '2026-02-27 16:00:00',  // 発行日時（Y-m-d H:i:s）
    'pdf_url'       => 'http://localhost:8888/wp-content/uploads/salary-pdf/salary-456-20260227160000.pdf', // PDFのURL
  ],
  // 新しいものが先頭になるよう array_unshift() で追加する
]
```

## 管理画面仕様（フェーズ2）

### 「PDF発行」メタボックス（サイドバー）
- 表示位置：`side`（サイドバー）の `high` 優先度（公開ボタンの直上）
- 表示内容：
  - `salary` 投稿タイプ専用
  - **「PDF発行」ボタン**（常時表示）
  - 新規投稿（未保存）の場合はボタンを無効化し「先に保存してください」と表示
- ボタンクリック後の挙動：
  - Ajax でサーバーへリクエスト送信
  - 処理中はボタンを無効化（スピナー表示）
  - 完了後に下記「発行済みPDF管理テーブル」の先頭に新しい行を追加

### 給与明細基本項目メタボックス内：発行済みPDF管理テーブル
- 表示位置：`VK_Custom_Field_Builder` が出力する既存フォームテーブルの**直下**に追加
- 表示タイミング：`bvsl_pdf_history` に1件以上ある場合のみ表示
- テーブル構成（新しいものが上）：

| 列 | 内容 |
|----|------|
| 発行日時 | `issued_at` を `Y/m/d H:i` 形式で表示 |
| ファイル名 | `filename` をテキストで表示 |
| 削除ボタン | 「削除」ボタン（Ajax）。クリックで該当行削除＋メディアライブラリのファイルも削除 |
| プレビューボタン | 「プレビュー」ボタン（`<a target="_blank">`）。クリックでPDFを別タブで開く |

- 削除確認：ブラウザの `confirm()` ダイアログ（「このPDFを削除しますか？」）
- 削除後：Ajax レスポンス受信後に該当 `<tr>` を DOM から削除

### 既存フィールド `salary_send_pdf` の扱い
- `custom-field-salary-normal.php` 内のラベルを `発行済PDF` → `発行済PDF（非推奨）` に変更
- 説明文を `手動登録されたPDFURL。新しいPDF発行機能（上記テーブル）をご利用ください。` に変更
- フィールド自体は削除しない（既存ユーザーのデータを保持）

## PDF生成仕様（フェーズ2）

### HTMLソース
- `frame-salary.php` の出力を `ob_start()` / `ob_get_clean()` でキャプチャして mPDF に渡す
- グローバル変数 `$post` は対象の WP_Post オブジェクトをセットして実行
- CSSは `wp_head()` に依存しないDedicatedなインラインCSSを定義する（印刷用スタイルを流用）

### mPDF 設定
- 用紙サイズ：A4
- 向き：縦（Portrait）
- マージン：上15mm / 右10mm / 下15mm / 左10mm（調整可）
- 文字コード：UTF-8
- 日本語フォント：正常表示できるフォントを設定（IPA明朝等を検討）

### メディアライブラリへの保存
1. `wp_upload_dir()` でアップロードディレクトリを取得
2. `{uploads}/salary-pdf/salary-{post_id}-{YmdHis}.pdf` に保存（タイムスタンプで一意化）
3. `wp_insert_attachment()` でメディアライブラリに登録
4. `wp_generate_attachment_metadata()` でメタ情報を生成・保存
5. `bvsl_pdf_history` に新しいレコードを先頭に `array_unshift()` で追加し `update_post_meta()` で保存

### Ajax エンドポイント：PDF発行
- アクション名：`bvsl_generate_salary_pdf`
- リクエスト：`POST`
  - `nonce`：nonce値
  - `post_id`：給与明細の投稿ID
- レスポンス（成功）：`{ pdf_url: "...", attachment_id: 123, filename: "...", issued_at: "..." }`
- レスポンス（失敗）：`{ message: "エラー内容" }`
- セキュリティ：
  - `check_ajax_referer( 'bvsl_generate_salary_pdf_nonce', 'nonce' )`
  - `current_user_can( 'edit_post', $post_id )` で権限チェック

### Ajax エンドポイント：PDF削除
- アクション名：`bvsl_delete_salary_pdf`
- リクエスト：`POST`
  - `nonce`：nonce値
  - `post_id`：給与明細の投稿ID
  - `attachment_id`：削除対象の attachment ID
- 処理：
  1. `wp_delete_attachment( $attachment_id, true )` でメディアライブラリからファイルごと削除
  2. `bvsl_pdf_history` から該当 `attachment_id` のレコードを除去し `update_post_meta()` で保存
- レスポンス（成功）：`{ deleted: true }`
- レスポンス（失敗）：`{ message: "エラー内容" }`
- セキュリティ：
  - `check_ajax_referer( 'bvsl_generate_salary_pdf_nonce', 'nonce' )`
  - `current_user_can( 'edit_post', $post_id )` で権限チェック

## 実装順序（フェーズ2）

1. `composer.json` に `mpdf/mpdf` を追加し `composer install`
2. `inc/salary-pdf.php` を新規作成：PDF生成・メディア保存・削除ロジック
3. `bill-vektor-salary.php` に Ajax フック2本追加・`salary-pdf.php` の読み込み
4. 「PDF発行」メタボックスをサイドバーに追加（PHP）
5. `custom-field-salary-normal.php` の `salary_send_pdf` ラベル・説明文を変更
6. `Salary_Normal_Custom_Fields::fields_form()` の直後に発行済みPDF管理テーブルを出力する処理を追加（PHP）
7. 管理画面JS（発行・削除のAjaxリクエスト・UI更新）を実装
8. 動作確認（PDF生成・履歴追加・個別削除・メディアライブラリ連動）

## 確認観点（フェーズ2）

- 「PDF発行」ボタン押下でPDFが生成されメディアライブラリに保存される
- 発行するたびに管理テーブルの先頭に行が追加される（新しい順）
- 生成されたPDFを開いて給与明細の内容が正しく表示されている
- 日本語が文字化けしていない
- 削除ボタン押下でメディアライブラリのファイルと管理テーブルの行が両方消える
- `bvsl_pdf_history` に正しく履歴が保存・削除されている
- 権限のないユーザーからのリクエストが拒否される
- 新規投稿（未保存）では発行ボタンが無効化されている
- `salary_send_pdf` フィールドが「発行済PDF（非推奨）」ラベルで表示されている

## 未検討・要確認（フェーズ2）

- mPDF で `frame-salary.php` のCSSレイアウトがどこまで再現できるか（実装時に要調整）
- 日本語フォントの選定（`IPA明朝` か mPDF 同梱フォントか）
- `wp-env` 環境での mPDF 動作確認
- PDFファイルのアクセス権限（公開 URL で直接アクセス可能にするか、非公開にするか）
