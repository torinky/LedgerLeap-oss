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
- [ ] タイトル表記の設定化方針を確定する
  - Evidence: `config/app.php` の `APP_NAME` は既に `<title>` 系で使われているため、まず現状の設定経路を基準化する
- [ ] フッターの権利表記表示を config 化する
  - Evidence: `resources/views/layouts/app.blade.php` の footer slot と `resources/views/layouts/appWithDrawer.blade.php` の表示構造を確認済み
- [ ] その他の表示文言の設定配置を決める
  - Evidence: `resources/views/layouts/daisyuiNavigation.blade.php` とロゴ/メタ表示を含む共通ビューを整理対象にする
- [ ] 公開 docs の configuration.md に案内を追加する
  - Evidence: `docs/work/2026-05-23_oss-publication-plan.md` §3.3 / §5.4 の設定駆動方針と整合させる

## 完了条件
- [ ] システムタイトルの表示方針が config ベースで説明できる
- [ ] フッターの権利表記・導線の設定配置が決まっている
- [ ] その他の表示文言の追加先が決まっている
- [ ] 公開 docs で設定項目を案内できる

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