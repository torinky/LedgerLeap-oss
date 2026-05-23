# Filament v4 Sprint 1 完了報告

**status:** confirmed  
**last_confirmed_at:** 2026-03-29  
**related_issue:** https://github.com/torinky/LedgerLeap/issues/123  
**related_memo:** `docs/work/architecture/filament/2026-03-29_filament-v4-migration-preparation.md`

## 判定

Sprint 1（コア更新準備）は完了。`composer.json` と `app/Providers/Filament/AdminPanelProvider.php` にコード変更を入れない方針を維持し、その判断は Sprint 2 でプラグイン分岐が確定したことで完了扱いにできた。

## 結論

Sprint 1 では、現時点では `composer.json` と `app/Providers/Filament/AdminPanelProvider.php` に**コード変更を入れない**方針は維持する。
そのうえで、Sprint 2 で依存プラグインの継続可否が確定したため、Sprint 1 全体を完了扱いにできる。

### 依存プラグインの確認結果

- `althinect/filament-spatie-roles-permissions`
  - `composer.lock`: `v2.3.3`
  - `require`: `filament/filament:^3.0`
  - README に Filament v4 対応の明記なし
  - Sprint 2 判定: **後回し**
- `codewithdennis/filament-select-tree`
  - `composer.lock`: `v3.1.58`
  - `require`: `filament/forms:^3.0`
  - README で `composer require codewithdennis/filament-select-tree:4.x` と案内あり
  - Sprint 2 判定: **継続**
- `15web/filament-tree`
  - `composer.lock`: `v1.0.3`
  - `require`: `filament/support:^3.2`
  - README の badge も Filament 3.2 を示し、v4 の明記なし
  - Sprint 2 判定: **代替**

### 再調査で確認できたこと

Filament v4 の公式 docs を再確認し、`AdminPanelProvider` で使っている主要 API が v4 でも明示的に案内されていることを確認した。

- `topNavigation()` は v4 の navigation overview に存在する
- `navigationItems()` は v4 の navigation overview に存在する
- `navigationGroups()` も v4 の navigation overview に存在する
- `renderHook()` は v4 の panel configuration と advanced render hooks に存在する
- `FilamentView::registerRenderHook()` は v4 の公式推奨パターンとして案内されている

したがって、`AdminPanelProvider` の現状実装は「v4 で即座に壊れると確定した API」を使っているわけではない。
依存プラグインの一部も Sprint 2 で「後回し / 継続 / 代替」に分類済みであり、Sprint 1 の完了認定を妨げる未確定事項はなくなった。

### 理由

1. Filament 本体はまだ v3 系で稼働しており、v4 への切り替え前提が未成立
2. 依存プラグインの v4 対応が未確定
   - `althinect/filament-spatie-roles-permissions`
   - `codewithdennis/filament-select-tree`
   - `15web/filament-tree`
3. `AdminPanelProvider` の主要 API は v4 docs にも載っており、現段階での先行修正は必須ではない
4. Tailwind v4 移行も絡むため、panel 基盤だけを先に部分更新すると確認コストが増える

## Sprint 1 で確定した方針

- `composer.json` の Filament 制約は **まだ変更しない**
- `AdminPanelProvider` は **まだ変更しない**
- v4 移行時に、
  - composer 依存更新
  - panel / navigation / render hook 差分吸収
  - 必要な global configuration
  をまとめて Sprint 2 以降の実装対象にする
- 依存プラグインの `v4` 対応方針は Sprint 2 で確定済み

## 追加の判断

Sprint 1 の再調査により、`AdminPanelProvider` については「v4 公式 docs で確認済みのため先行変更不要」と整理できた。
Sprint 2 で依存プラグインの方針も確定したため、Sprint 1 は単独で完了報告できる状態になった。

## 完了した確認項目

- `composer.json` の Filament 制約を確認した
  - 現在: `filament/filament:^3`
- `app/Providers/Filament/AdminPanelProvider.php` の差分候補を確認した
  - `PanelProvider` ベース
  - `topNavigation()` 使用
  - `NavigationItem` 使用
  - `renderHook('panels::global-search.after', ...)` 使用
- v4 の公式要件と差分吸収候補を整理した
  - `php artisan filament:upgrade`
  - `php artisan filament:upgrade-directory-structure-to-v4 --dry-run`
  - `Table::configureUsing(...)`
  - `Field::configureUsing(...)`

## 証拠

### ローカル証拠
- `composer.json`: `filament/filament:^3`
- `composer.lock`: `filament/filament v3.3.49`
- `app/Providers/Filament/AdminPanelProvider.php`: panel / navigation / render hook の v3 実装
- `docs/work/architecture/filament/2026-03-29_filament-v4-migration-preparation.md`: 影響範囲と未確定論点
- `docs/work/architecture/filament/2026-03-29_filament-v4-sprint0-completion-report.md`: Sprint 0 完了報告

### 公式ドキュメント
- https://filamentphp.com/docs/4.x/upgrade-guide
- https://filamentphp.com/docs/4.x/panel-configuration
- https://filamentphp.com/docs/4.x/plugins

## 次のアクション

1. Sprint 3 で custom Blade / theme を v4 前提へ寄せる
2. v4 移行開始時に `composer.json` と `AdminPanelProvider` をまとめて更新する

