# Laravel 13 アップグレード Issue 草案

**status:** draft  
**last_updated_at:** 2026-04-04  
**related_memo:** `docs/work/architecture/2026-04-04_laravel-13-upgrade-plan.md`

このファイルは、GitHub issue にそのまま貼れる粒度を意識した草案です。  
実装前の共通認識、Sprint 分割、依存関係、ロールバック観点をまとめています。

---

## 作成順の推奨

1. `Issue 1` でブロッカーと依存の対応方針を確定する
2. `Issue 2` で Composer / framework の更新を通す
3. `Issue 3` で CSRF / bootstrap / middleware の互換を吸収する
4. `Issue 4` で回帰テストとリリース可否を判断する

---

## Issue 1: Laravel 13 / Sprint 0 - 依存関係互換性マトリクスとブロッカー確定

### 目的
Laravel 13 へ上げる前に、Composer 解決を止めている依存を確定し、更新・代替・fork・保留の方針を決める。

### スコープ
- `composer.json` / `composer.lock` の Laravel 系依存棚卸し
- `darkaonline/l5-swagger` の扱い決定
- `15web/filament-tree` の扱い決定
- `laravel/boost` / `laravel/tinker` / `phpunit/phpunit` / `pestphp/pest` の更新前提整理
- 13 対応済みとみなせる依存の切り分け

### 想定ラベル
- `type:upgrade`
- `priority:high`
- `area:platform`

### チェックリスト
- [ ] `composer.json` と `composer.lock` の Laravel 関連依存を棚卸しする
- [ ] `darkaonline/l5-swagger` の更新・代替・fork のいずれで進めるか決める
- [ ] `15web/filament-tree` の更新・代替・fork のいずれで進めるか決める
- [ ] `laravel/boost` / `laravel/tinker` / `phpunit/phpunit` / `pestphp/pest` の更新前提を確定する
- [ ] 13 対応済みとみなせる依存を一覧化する

### 受け入れ条件
- ブロッカー一覧が明文化されている
- 各ブロッカーに対して「更新 / 代替 / fork / 保留」のどれかが決まっている
- 次の Sprint に渡す順序が確定している

### 依存
- なし

### 参考
- `docs/work/architecture/2026-04-04_laravel-13-upgrade-plan.md`
- `composer.json`
- `composer.lock`

---

## Issue 2: Laravel 13 / Sprint 1 - Composer 更新と framework bump

### 目的
Laravel 13 の依存解決が通るように、Composer 依存を整合させる。

### スコープ
- `laravel/framework` を `^13.0` に更新
- `laravel/boost` を `^2.0` に更新
- `laravel/tinker` を `^3.0` に更新
- `phpunit/phpunit` を `^12.0` に更新
- `pestphp/pest` を `^4.0` に更新
- 依存更新後の autoload / scripts / package discovery を確認

### 想定ラベル
- `type:upgrade`
- `priority:high`
- `area:composer`

### チェックリスト
- [ ] `composer.json` の Laravel 系制約を 13 系へ更新する
- [ ] 開発補助系パッケージの世代差分を解消する
- [ ] `composer update` が依存制約で止まらないことを確認する
- [ ] `autoload` / `scripts` / package discovery の破綻がないことを確認する
- [ ] 最低限の起動確認を実施する

### 受け入れ条件
- `composer update` が 13 系の制約で止まらない
- 開発補助系の更新も含めて lock が整合している
- 少なくとも起動確認ができる状態になっている

### 依存
- Issue 1 完了

### 参考
- https://laravel.com/docs/13.x/upgrade

---

## Issue 3: Laravel 13 / Sprint 2 - CSRF / bootstrap / middleware 互換対応

### 目的
Laravel 13 の起動経路に影響する変更を吸収し、Web / Filament / tenant ルートの導線を維持する。

### スコープ
- `VerifyCsrfToken` 直接参照の見直し
- `PreventRequestForgery` への移行可否判断
- `app/Http/Kernel.php` の web middleware 群確認
- `app/Providers/Filament/AdminPanelProvider.php` の middleware 設定確認
- `bootstrap/app.php` の起動経路確認
- `config/cache.php` などの framework デフォルト依存確認

### 想定ラベル
- `type:upgrade`
- `priority:high`
- `area:http`

### チェックリスト
- [ ] `app/Http/Middleware/VerifyCsrfToken.php` の扱いを Laravel 13 前提で整理する
- [ ] `app/Http/Kernel.php` の middleware 参照を確認する
- [ ] `app/Providers/Filament/AdminPanelProvider.php` の middleware 設定を確認する
- [ ] `bootstrap/app.php` の起動経路差分を確認する
- [ ] `config/cache.php` などの framework デフォルト依存を確認する

### 受け入れ条件
- CSRF / middleware 参照が Laravel 13 前提で整理されている
- Filament と tenant ルートの基本導線が壊れていない
- 起動周辺の差分が再現可能な形で記録されている

### 依存
- Issue 2 完了

### 参考
- `app/Http/Kernel.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Http/Middleware/VerifyCsrfToken.php`
- https://laravel.com/docs/13.x/upgrade#request-forgery-protection

---

## Issue 4: Laravel 13 / Sprint 3 - 回帰テスト・検証・リリース準備

### 目的
Laravel 13 への更新後に、主要機能の回帰がないことを確認し、リリース可否を判断する。

### スコープ
- tenant 初期化が必要な Feature test の重点実行
- Filament / Livewire / permission / search / MCP の代表導線確認
- Mroonga / database-migrations / search 系テスト確認
- 必要なら frontend build の確認
- ロールバック手順と残課題の記録

### 想定ラベル
- `type:verification`
- `priority:high`
- `area:testing`

### チェックリスト
- [ ] tenant 初期化が必要な Feature test を重点実行する
- [ ] Filament / Livewire / permission / search / MCP の代表導線を確認する
- [ ] Mroonga / database-migrations / search 系テストを確認する
- [ ] 必要に応じて frontend build を確認する
- [ ] ロールバック手順と残課題を記録する

### 受け入れ条件
- 主要テストが PASS
- 既知の差分・未解決課題が記録されている
- リリース可否を判断できる材料が揃っている

### 依存
- Issue 2, 3 完了

### 参考
- `docs/work/architecture/2026-04-04_laravel-13-upgrade-plan.md`

---

## Sprint 割り当ての目安

- Sprint 0: Issue 1
- Sprint 1: Issue 2
- Sprint 2: Issue 3
- Sprint 3: Issue 4

---

## GitHub へ起こすときの補足

- Issue 本文には、上記の「目的 / スコープ / チェックリスト / 受け入れ条件」をそのまま使える
- もし `l5-swagger` と `filament-tree` の検討が重くなれば、Issue 1 をさらに 2 件に分割してもよい
- 実装時は、Issue 1 → 2 → 3 → 4 の順で進めると依存関係が追いやすい

---

## 起票済み GitHub Issues

- Issue 1: `#129` - Laravel 13 / Sprint 0 - 依存関係互換性マトリクスとブロッカー確定
- Issue 2: `#130` - Laravel 13 / Sprint 1 - Composer 更新と framework bump
- Issue 3: `#131` - Laravel 13 / Sprint 2 - CSRF / bootstrap / middleware 互換対応
- Issue 4: `#132` - Laravel 13 / Sprint 3 - 回帰テスト・検証・リリース準備

