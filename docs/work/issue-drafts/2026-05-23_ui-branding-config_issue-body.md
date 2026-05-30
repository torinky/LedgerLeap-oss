# UI ブランディング設定の config 化

## 概要
システムタイトルの表記、フッターの権利表記、その他の表示文言を設定ファイルから切り替えられるようにします。公開リポジトリ初期化時に、ブランド表記をコード直書きから整理された設定へ寄せるのが目的です。

## 起票情報
- 作成日時: 2026-05-23
- 位置づけ: Epic #216 の補完サブイシュー
- 参照タイミング: 公開版の初期表示と権利表記の整備が必要になった段階で作成

## この issue で作るもの
- システムタイトル表記の設定化方針
- フッターの権利表記・ライセンス導線の設定化方針
- 将来追加する案内文や補助テキストを設定駆動に寄せる方針
- 公開 docs の `configuration.md` で案内する設定項目の整理

## 背景 / 目的
- 既に `config/app.php` の `APP_NAME` は `<title>` などで利用されている
- ただし、フッターの権利表記や補助文言はレイアウト側に散らばりやすい
- 公開後の変更を Blade 直書きではなく config からまとめて行えるようにしたい

## 現状
- `config/app.php` の `APP_NAME` は利用済み
- `resources/views/layouts/app.blade.php` と `resources/views/layouts/appWithDrawer.blade.php` でタイトル表示を組み立てている
- フッターの権利表記はまだ統一方針が固まっていない

## スコープ / 非スコープ
### 対象
- `config/app.php`
- `config/ledgerleap.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/appWithDrawer.blade.php`
- `resources/views/layouts/daisyuiNavigation.blade.php`

### 対象外
- 機能追加を伴うレイアウト刷新
- 新しい権利表記文言の法務判断
- 公開 docs 以外の AI 資産整理

## スプリント分解
- [x] タイトル表記の設定化方針を確定する
  - Evidence: `config/ledgerleap.php` (L10-23) に `branding.app_name` / `short_name` を追加。`daisyuiNavigation.blade.php` (L36-44) および `app.blade.php` / `appWithDrawer.blade.php` の `<title>` タグが `config('ledgerleap.branding.app_name')` を参照。
- [x] フッターの権利表記表示を config 化する
  - Evidence: `partials/app-footer.blade.php` (L2-8) が `config('ledgerleap.branding.copyright_owner')` / `copyright_year_start` / `support_url` / `support_email` / `forum_url` を参照。`lang/ja/ledger/ui.php` (L419-425) に `footer.*` 翻訳キー追加。`AdminPanelProvider.php` (L141) が `view('partials.app-footer')` をレンダーフックに登録。
- [x] その他の表示文言の設定配置を決める
  - Evidence: `daisyuiNavigation.blade.php` がロゴ画像・タイトル表示に config を使用。`.env.example` に全ブランディング変数（APP_SHORT_NAME / APP_LOGO / APP_COPYRIGHT_OWNER 等）のコメント付きテンプレートを追加。`AdminPanelProvider.php` (L41-43) が `brandName()` / `brandLogo()` / `favicon()` に config を適用。
- [x] 公開 docs の configuration.md に案内を追加する
  - Evidence: `docs/configuration.md` を作成。ブランディング設定（全11変数）を中心に、データベース、ファイル処理、スコアリング、パフォーマンスモニタリング、自動リンク設定を含む `.env` 設定項目一覧を整備。

## 完了条件
- [x] システムタイトルの表示方針が config ベースで説明できる
  - `config/ledgerleap.php` → `daisyuiNavigation` / `app.blade.php` / `appWithDrawer.blade.php` / `AdminPanelProvider`
- [x] フッターの権利表記・導線の設定配置が決まっている
  - `config/ledgerleap.php` → `partials/app-footer.blade.php` (著作権 + support/email/forum リンク)
- [x] その他の表示文言の追加先が決まっている
  - ナビゲーションロゴ・タイトル → `daisyuiNavigation.blade.php`
  - Filament 管理画面 → `AdminPanelProvider.php`
  - 翻訳キー → `lang/ja/ledger/ui.php`
  - 環境変数 → `.env.example`
- [x] 公開 docs で設定項目を案内できる
  - `docs/configuration.md` に全ブランディング変数のテンプレートを含む設定ガイドを作成済み。`.env.example` にもコメント付きテンプレートを追加。

## GitHub 追跡
- Related: `#216`
- Epic: `#216`
- 起票済み: `#222`

## 参照
- `config/app.php`
- `config/ledgerleap.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/appWithDrawer.blade.php`
- `resources/views/layouts/daisyuiNavigation.blade.php`
- `docs/work/2026-05-23_oss-publication-plan.md`

## 結論・完了報告

### 実装内容
`config/ledgerleap.php` に `branding` セクションを追加し、以下を env でオーバーライド可能にした（commit `af9188e6`）:

- `app_name` / `short_name` / `logo` / `favicon`
- `copyright_owner` / `copyright_year_start`
- `support_url` / `support_email` / `forum_url`

全9ファイル、125行追加 / 7行削除。ナビゲーションバーのロゴ・タイトル表示、フッターの著作権表記・連絡先リンク、Filament 管理画面のブランド設定まで統一的に config 参照へ移行。

### 実施中に発見・修正した後続バグ

#### 1. 編集画面のグループトグルが機能しない
**原因**: `app.blade.php` でのスクリプトロード順序の不整合。`@livewireScriptConfig`（Alpine 初期化）→ `@stack('scripts')`（`ledgerEdit.js` = `groupErrorBadge` 定義）の順でロードされていたため、Alpine が `x-data` 式を評価する時点では `groupErrorBadge` が未定義。`x-data` 全体の初期化が失敗し、`isCollapsed` も `toggle()` も定義されず、個別のグループ開閉が不能になっていた。

`storage/logs/browser.log` に `Alpine Expression Error: Can't find variable: groupErrorBadge` が記録されていたことで発見。

**修正**: `@stack('scripts')` を `@livewireScriptConfig` の前に移動（`app.blade.php` 1行の順序変更）。`ledgerEdit.js` → Alpine 初期化 の順で読み込まれることで、`groupErrorBadge` が `x-data` 評価前に登録される。

#### 2. リスト画面でのフッター表示位置（別途対応中）
`appWithDrawer.blade.php` の `main.drawer-content` に指定された `h-screen` が原因で、複数台帳表示時にフッターがコンテンツ末端ではなく画面中盤に留まる問題。DaisyUI `.drawer` の `overflow: hidden` との組み合わせでグリッドが伸長しないことが根本原因。`h-screen` → `min-h-screen` への変更で対応（別途実施）。

### 後続コミット
- `77696a14` fix(branding): footer display and accessibility fixes
- `f916351c` fix(branding): fix footer overlap issues with sidebar and sticky action bar
- `87a62844` fix(branding): dynamically offset sticky-action-bar above footer on scroll
- `5f57f2be` fix(branding): fix footer height and sticky-bar overlap via x-teleport spacer
- `cd117685` revert(branding): sticky-action-bar と編集画面をf916351c状態に戻す

上記 revert 後にスクリプトロード順序の最終修正を適用。

### 完了判定: ✅ 完了

4項目すべて完了。

### 当初計画との差分
- **追加実装**: Filament AdminPanelProvider のブランド設定、`public/images/icon.svg` の追加、`docs/configuration.md` の作成
- **後続バグ修正**: グループトグル（スクリプトロード順序）、フッター表示位置（継続対応）

Closes #222