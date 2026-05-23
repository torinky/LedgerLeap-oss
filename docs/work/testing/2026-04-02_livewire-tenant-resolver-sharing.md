# Livewire tenant resolver 共通化の記録

**記録日:** 2026-04-02
**状態:** confirmed
**対象:** `app/Livewire/Traits/InitializesTenantContext.php` / `app/Livewire/AttachedFile/FileInspector.php` / `resources/views/livewire/attached-file/file-inspector/*.blade.php`

## 1. 何が分かったか

Livewire の更新リクエストや `#[Lazy]` 関連の描画で `tenant()` が `null` になり、Blade の `route()` 生成が失敗することがあった。

今回の対応では、tenant 解決を `InitializesTenantContext` の共通 resolver `resolveTenantId()` に寄せ、`tenantId` → `tenant('id')` → `model->tenant_id` の順で復元できるようにした。

## 2. 再利用できるルール

- tenant が必要な URL 生成は、Blade で `tenant()?->id` を直書きせず、`resolveTenantId($model->tenant_id)` を使う。
- `render()` / computed property / Blade partial のいずれでも、同じ tenant fallback 順を共有する。
- Livewire の更新リクエストでは route tenant が失われる前提で、モデルの `tenant_id` を最後の保険にする。

## 3. 変更の中心

- `app/Livewire/Traits/InitializesTenantContext.php`
  - `resolveTenantId(string|int|null $fallbackTenantId = null)` を追加
- `app/Livewire/AttachedFile/FileInspector.php`
  - `getFileRouteUrl()` / permissions URL の tenant 取得を共通 resolver に統一
- Blade 3箇所
  - `quick-actions.blade.php`
  - `tabs/content.blade.php`
  - `tabs/details.blade.php`
  - いずれも resolver 呼び出しに統一

## 4. 回帰テスト

`tests/Feature/Livewire/AttachedFile/FileInspectorTest.php` に、`tenancy()->end()` 後でも `tenant_id` ベースで URL が作れることを確認するケースを追加した。

## 5. 検証結果

- `./vendor/bin/sail test tests/Feature/Livewire/AttachedFile/FileInspectorTest.php`
- 結果: `39 passed`

## 6. フレッシュネス

- `status`: confirmed
- `last_confirmed_at`: 2026-04-02
- `recheck_after`: 2026-07-02
- `recheck_trigger`: Livewire の boot / hydrate 順序、tenant 付き route 生成、または `tenant()` null 失敗が再発したとき

