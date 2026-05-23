# Filament v4 移行準備メモ

**status:** confirmed  
**last_confirmed_at:** 2026-03-29  
**recheck_after:** 2026-04-12  
**recheck_trigger:** Filament 本体 / 関連プラグイン / Tailwind / Laravel のいずれかを更新したとき

## Sprint 0 完了

Sprint 0（事前調査）は完了済み。
詳細は [`Filament v4 Sprint 0 完了報告`](./2026-03-29_filament-v4-sprint0-completion-report.md) を参照。

## Sprint 1 完了

Sprint 1（コア更新準備）は完了済み。
`AdminPanelProvider` で使っている主要 API 自体は v4 公式 docs にも存在することを確認し、`composer.json` と `app/Providers/Filament/AdminPanelProvider.php` に先行変更を入れない方針を確定した。
詳細は [`Filament v4 Sprint 1 完了報告`](./2026-03-29_filament-v4-sprint1-completion-report.md) を参照。

## 目的

LedgerLeap の Filament を v4 に上げる前提で、公式ドキュメントに基づく移行手順、影響範囲、スプリント分割、未確認事項を整理する。
このメモは「実装前の準備」と「GitHub Issue 化の土台」を兼ねる。

## 公式ドキュメントから読み取れる v4 移行の要点

参照元:
- Filament v4 Upgrade Guide: https://filamentphp.com/docs/4.x/upgrade-guide
- Filament v4 Installation: https://filamentphp.com/docs/4.x/introduction/installation
- Filament v4 Panel Configuration: https://filamentphp.com/docs/4.x/panel-configuration
- Filament v4 Plugins: https://filamentphp.com/docs/4.x/plugins

### 1. まず自動アップグレードを実行する

公式は最初に `php artisan filament:upgrade` を実行し、案内に従うことを推奨している。  
ただし、このコマンドは全破壊的変更を吸収するわけではないため、手動で upgrade guide を必ず追う必要がある。

### 2. 必須要件を満たす

Filament v4 の要件:
- PHP 8.2+
- Laravel 11.28+
- Tailwind CSS 4.1+（カスタム theme CSS を使う場合）

### 3. 新しいディレクトリ構成を採用できる

v4 では resources / clusters の新しいデフォルト構成が導入された。  
`php artisan filament:upgrade-directory-structure-to-v4 --dry-run` で差分確認し、必要なら適用する。  
適用後はクラス参照の手修正が残る可能性がある。

### 4. カスタムテーマは Tailwind v4 前提へ移行する

公式の例では、古い `@config` ベースから `@source` ベースへ移行する必要がある。  
LedgerLeap では `resources/sass/filamentCustom.scss` と `tailwind.config.js` が関連するため、Filament v4 へ上げるだけでなく Tailwind 側の再整備も必要。

### 5. v3 の挙動差をグローバル設定で戻せる箇所がある

公式 docs 上で確認した、LedgerLeap に関係しそうな代表例:
- `Table::configureUsing(...)->defaultKeySort(false)` で v3 の primary key sorting を維持できる
- `Field::configureUsing(...)->uniqueValidationIgnoresRecordByDefault(false)` で v3 の `unique()` 挙動を維持できる
- `default_filesystem_disk` を `FILAMENT_FILESYSTEM_DISK` 環境変数で固定できる

### 6. プラグインは個別に v4 対応確認が必要

Filament 公式は、各 plugin には一律の移行方法がないと明言している。  
特に plugin service provider の扱い変更があり、既存 plugin の更新可否を最初に確認する必要がある。

---

## 現状の影響範囲

### A. Filament コア / Panel 基盤

対象:
- `app/Providers/Filament/AdminPanelProvider.php`
- `composer.json` の `filament/filament`
- `composer.json` の `post-autoload-dump` にある `@php artisan filament:upgrade`

現状メモ:
- `filament/filament` は `^3` 指定
- `composer.lock` では `filament/filament v3.3.49`
- `AdminPanelProvider` は `topNavigation()`、`renderHook('panels::global-search.after', ...)`、`NavigationItem` を使用
- `PanelProvider` ベースの構成なので、v4 の panel API 差分は最優先で確認が必要

### B. Resource / Page / Widget 群

対象:
- `app/Filament/Resources/**`
- `app/Filament/Pages/Dashboard.php`
- `app/Filament/Widgets/DashboardLinksWidget.php`
- `app/Filament/Tables/**`
- `app/Filament/Traits/**`

注意点:
- `RoleResource` は base package の resource を継承している
- `FolderResource` は tree/select-tree 系パッケージに強く依存する
- `DashboardLinksWidget` は独自 Blade と Vite CSS を引いている
- `HasFolderSelection` は `SelectTree` と `Filament\Tables\Filters\Filter` を組み合わせている

### C. 外部 Filament 系パッケージ

現状 lock 実体:
- `filament/filament v3.3.49`
- `althinect/filament-spatie-roles-permissions v2.3.3`（`filament/filament:^3.0` 要求）
- `codewithdennis/filament-select-tree v3.1.58`（`filament/forms:^3.0` 要求）
- `15web/filament-tree v1.0.3`（`filament/support:^3.2` 要求）

再調査メモ:
- `althinect/filament-spatie-roles-permissions` は README / require ともに v4 明記なしで、現行 lock のままでは v4 未対応として扱う
- `codewithdennis/filament-select-tree` は README で `4.x` 系の利用を案内しており、v4 対応ラインは存在するが、現行 lock は v3 系のまま
- `15web/filament-tree` は README の badge / require ともに Filament 3 系で、現行 lock のままでは v4 未対応として扱う

結論:
- 本体 v4 化だけでは足りず、3rd party plugin の v4 対応有無が実作業の分岐点になる
- 少なくとも `althinect/filament-spatie-roles-permissions` と `15web/filament-tree` は、現時点の lock では v4 完了条件を満たさない

### D. カスタム Blade / UI

対象:
- `resources/views/filament/widgets/dashboard-links-widget.blade.php`
- `resources/views/filament/navigation/tenant-switcher.blade.php`
- `resources/views/filament/tables/columns/*.blade.php`
- `resources/views/vendor/filament-tree/*.blade.php`

注意点:
- `x-filament::*` / `x-filament-actions::*` / `x-filament-widgets::*` の namespace 互換
- `wire:navigate` を使うページ遷移
- `@vite('resources/sass/filamentCustom.scss')` による custom theme 読み込み

### E. テナント / 権限 / ナビゲーション

対象:
- `app/Livewire/TenantSwitcherFilament.php`
- `app/Filament/Widgets/DashboardLinksWidget.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `config/filament-spatie-roles-permissions.php`

注意点:
- `filament_from_tenant_id` セッション依存
- `tenant` クエリパラメータ依存
- tenant-aware な URL 生成と通知導線がある
- ACL 系は permission cache / tenant access cache の再評価が必要になりやすい

---

## 影響確認で先に潰すべき論点

1. `filament-spatie-roles-permissions` は v4 対応済みか、未対応なら代替/延期が必要か
2. `filament-select-tree` は v4 の `Forms` / `Schema` でそのまま動くか
3. `filament-tree` のテンプレート上書きは v4 の Blade component 名前空間で破綻しないか
4. `AdminPanelProvider` の `renderHook()` と navigation API が v4 でも同じ構造で使えるか
5. カスタム theme を Tailwind v4 に合わせる必要があるか
6. 既存の `Table::configureUsing` / `Field::configureUsing` 相当を入れるべきか
7. `php artisan filament:upgrade-directory-structure-to-v4 --dry-run` を使うか、現行構成維持で進めるか

---

## スプリント案

### Sprint 0: 事前調査

ゴール:
- v4 移行の前提条件と plugin 対応状況を確定する
- どこまでを一気に更新できるか、どこから段階移行にするかを決める

成果物:
- 影響範囲マップ
- 互換性不明点一覧
- 実行順序の確定

### Sprint 1: コア更新準備

ゴール:
- composer 制約と panel 基盤の差分吸収

現状:
- 個別の調査と方針整理は完了
- 依存プラグインの分岐も Sprint 2 で確定済みのため、Sprint 1 は完了扱い

対象:
- `composer.json`
- `AdminPanelProvider`
- 必要なら global configuration

### Sprint 2: プラグイン分岐

ゴール:
- `filament-spatie-roles-permissions` / `filament-select-tree` / `filament-tree` の継続可否判断

成果物:
- 続行 / 代替 / 後回し の判定
- 置き換え時の設計案

完了時点の判断:
- `althinect/filament-spatie-roles-permissions`: **後回し**
  - 現行 lock は v4 未対応
  - v4 移行時点では別案を後続で検討する
- `codewithdennis/filament-select-tree`: **継続**
  - 4.x 系が存在するため、移行時は 4.x 系へ切り替える
- `15web/filament-tree`: **代替**
  - `solutionforest/filament-tree` 4.x か、自前実装へ置換する

### Sprint 3: UI / Blade / テーマ修正

ゴール:
- custom Blade と theme を v4 仕様へ追従

対象:
- `resources/views/filament/*`
- `resources/views/vendor/filament-tree/*`
- `resources/sass/filamentCustom.scss`

完了時点の判断:
- `resources/views/filament/widgets/dashboard-links-widget.blade.php` の dynamic Tailwind class を除去した
- `resources/views/filament/navigation/tenant-switcher.blade.php` は変更不要と確認した
- `resources/views/vendor/filament-tree/row.blade.php` は変更不要と確認した
- `resources/sass/filamentCustom.scss` は既存 hover 拡張を維持した
- Tailwind 設定の追加調整は不要と判断した

詳細は [`Filament v4 Sprint 3 完了報告`](./2026-03-29_filament-v4-sprint3-completion-report.md) を参照。

### Sprint 4: 回帰確認と整理

ゴール:
- tenant / ACL / tree / search / dashboard の主要導線を確認
- `docs/work` と GitHub Issue の状態を閉じる

完了時点の判断:
- tenant / ACL / tree / search / dashboard の主要導線を既存テストで回帰確認した
- 追加の Tailwind 変更は不要だったため、`sail npm run build` は今回未実施
- 詳細は [`Filament v4 Sprint 4 完了報告`](./2026-03-29_filament-v4-sprint4-completion-report.md) を参照

---

## 調査結果からの推奨実行順

1. 公式 upgrade guide を再確認
2. `composer.json` の v4 依存に上げる前に plugin 対応を確認
3. `AdminPanelProvider` と custom theme を先に棚卸し
4. tree/select-tree の代替可否を判断
5. その後に実装スプリントへ入る

## Sprint 完了状況

- Sprint 0: 完了
- Sprint 1: 完了
- Sprint 2: 完了
- Sprint 3: 完了
- Sprint 4: 完了

## Sprint 5 実装計画

Sprint 5 は実装開始フェーズ。
詳細は [`Filament v4 Sprint 5 実装計画`](./2026-03-29_filament-v4-sprint5-implementation-plan.md) を参照。

### 目的

- `filament/filament` を v4 へ上げる
- `AdminPanelProvider` を v4 前提へ反映する
- `codewithdennis/filament-select-tree` の 4.x 化と `filament-tree` 代替を実装する
- tenant / ACL / tree / search / dashboard の主要導線を再度 PASS にする

### 主要リスク

- plugin 互換性不足
- ACL キャッシュの残留
- tenant routing mismatch
- Tailwind class 欠落
- panel API 差分

### 対応方針

- plugin は 1 つずつ切り替え、各段階で既存テストを回す
- ACL 変更後は `flushAllUserPermissionsCache()` と `TenantAccessService::clearAllCache()` を確認する
- tenant URL は既存ルーティングに合わせ、主要導線を再検証する
- dynamic Tailwind class を避け、必要時のみ `sail npm run build` を使う
- panel API は v4 docs の推奨パターンに寄せ、差分を最小にする

### Sprint 5 の最小着手順

1. `composer.json` の Filament / plugin 前提を v4 実装向けに更新する
2. `app/Providers/Filament/AdminPanelProvider.php` を v4 API 前提で再確定する
3. `codewithdennis/filament-select-tree` の 4.x 化を適用する
4. `15web/filament-tree` の代替先を実装へ反映する
5. tenant / ACL / tree / search / dashboard の回帰テストを再実行する

---

## GitHub Issue に落とし込むときの見出し案

- Filament v4 移行の事前調査と影響範囲整理
- Filament v4 コア更新と `AdminPanelProvider` 再確認
- Filament v4 plugin 互換性検証（roles-permissions / select-tree / tree）
- Filament v4 custom theme / Blade 互換修正
- Filament v4 テナント・権限・ナビゲーション回帰確認

---

## 参考情報

### ローカル証拠
- `composer.json`: `filament/filament` が `^3`
- `composer.lock`: `filament/filament v3.3.49`
- `composer.lock`: `althinect/filament-spatie-roles-permissions v2.3.3`
- `composer.lock`: `codewithdennis/filament-select-tree v3.1.58`
- `composer.lock`: `15web/filament-tree v1.0.3`
- `app/Providers/Filament/AdminPanelProvider.php`: panel 基盤と custom navigation
- `resources/sass/filamentCustom.scss`: custom theme CSS
- `tailwind.config.js`: Tailwind v3 系の content 設定

### 公式ドキュメント
- https://filamentphp.com/docs/4.x/upgrade-guide
- https://filamentphp.com/docs/4.x/introduction/installation
- https://filamentphp.com/docs/4.x/panel-configuration
- https://filamentphp.com/docs/4.x/plugins

