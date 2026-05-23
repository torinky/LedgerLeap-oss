# Livewire 4 + Filament 5 Migration Replan

**関連 Issue**: [#126](https://github.com/torinky/LedgerLeap/issues/126) / **新規主 Issue 候補**: Livewire 4 + Filament 5 移行再計画

## 1. 背景

`Issue #126` の調査で、Filament 4 系は upstream 上 `livewire/livewire ^3.5` を要求し、`Livewire 4` と同時成立しないことが確認された。
一方で、`filament/support v5.0.0` は `livewire/livewire ^4.0` を要求しており、**Livewire 4 を採用するなら Filament 5 系が整合的** である。

そのため、本件は `Filament 4` 前提の再計画ではなく、**Livewire 4 + Filament 5** を基準に移行計画を組み直す。

## 2. 目的

- Livewire 4 と Filament 5 を整合した基盤として導入する
- `SelectTree` / tree fork / 各 Filament リソースを新基盤へ合わせる
- `Folder` / `Organization` を含む tree 系 UI の既存体験を維持する
- 既存の `Issue #126` は tree fork / 個別プラグイン調査の凍結資料として残す

## 3. 依存確認メモ

### 3.1 コア依存

- `filament/filament v5.0.0` は `filament/actions`, `filament/forms`, `filament/infolists`, `filament/notifications`, `filament/schemas`, `filament/support`, `filament/tables`, `filament/widgets` の同一版を要求する
- `filament/forms v5.0.0` は `filament/support self.version` を要求する
- `filament/support v5.0.0` は `livewire/livewire ^4.0` を要求する

### 3.2 周辺パッケージ

- `codewithdennis/filament-select-tree v4.0.18`
  - `filament/forms ^4.0 || ^5.0` を要求
  - Livewire 4 / Filament 5 ルートに進める候補がある
- `althinect/filament-spatie-roles-permissions v3.3.1`
  - `filament/filament ^4.0 || ^5.0` を要求
  - 権限系の再導入候補として v5 互換がある
- `15web/filament-tree` ローカル fork
  - 現時点では `filament/support ^4.0` 前提で作成しているため、Filament 5 路線なら fork 側の再調整が必要

## 4. 公式アップグレードガイド反映ポイント

### 4.1 Filament v5 upgrade guide

- Filament v5 は **Livewire v4.0+** を前提にする
- Filament v5 では **`composer require filament/upgrade:"^5.0" --dev` → `vendor/bin/filament-v5`** の自動アップグレード手順が推奨される
- upgrade script 実行後に、アプリ固有の `composer require` / `composer update` 指示を手動で反映する必要がある
- upgrade guide は、互換性のない plugin は一時的に外す・代替に差し替える・アップグレードを待つ・PR で協力する、の4択を示している
- plugin の upgrade では `PluginServiceProvider` の廃止に注意し、`PackageServiceProvider` と static `$name` の採用が必要になる

### 4.2 Livewire v4 upgrade guide

- Livewire v4 は **`Route::livewire()`** を推奨し、full-page component のルート定義更新が必要
- config ファイルの key 名や既定値が更新されているため、既存設定の差分確認が必要
- `wire:model.blur` / `wire:model.change` などの modifier 挙動が変わるため、フォームと tree 系 UI の双方向同期を再確認する必要がある
- 旧 Volt 利用が残っていれば、`livewire/volt` の削除と `Route::livewire()` への置換が必要
- フォームや tree のテストでは、modifier の再同期タイミングを前提に見直す必要がある

## 4. Sprint Plan

### Sprint 0: 旧方針の凍結と再計画の確定

- [ ] `Issue #126` を tree fork / SelectTree 調査の凍結資料として扱う
- [ ] Livewire 4 + Filament 5 を主系とする新しい Issue を起票する
- [ ] 依存更新の順序と rollback 方針を確定する
- **完了条件**: Filament 5 / Livewire 4 路線で進める前提と対象範囲が確定している

### Sprint 1: Composer 基盤の Filament 5 / Livewire 4 化

- [ ] `composer.json` の Filament / Livewire 制約を v5 / v4 に合わせる
- [ ] `composer.lock` を再解決し、コアパッケージの更新可否を確認する
- [ ] `composer require filament/upgrade:"^5.0" --dev` → `vendor/bin/filament-v5` の自動アップグレード手順を試す
- [ ] `SelectTree` と tree fork の依存が lock 解決を阻害しないか確認する
- **完了条件**: コア依存が Filament 5 / Livewire 4 で解決できる、または阻害箇所が特定されている

### Sprint 2: 周辺プラグインの互換化

- [ ] `codewithdennis/filament-select-tree` を v4 系へ更新するか、代替・fork・削除を決める
- [ ] `15web/filament-tree` fork 側を Filament 5 に合わせる
- [ ] fork 側 service provider を `PackageServiceProvider` + static `$name` 前提へ見直す
- [ ] 必要なら `althinect/filament-spatie-roles-permissions` の再導入可否を再確認する
- **完了条件**: tree / 権限 / 主要プラグインが Filament 5 に追従できる

### Sprint 3: Filament リソースと画面導線の追従

- [ ] `FolderResource` / `OrganizationResource` / tree pages を Filament 5 API に合わせる
- [ ] `SelectTree` を使うフォームや relation manager を追従する
- [ ] `AdminPanelProvider` など panel 基盤の差分を解消する
- [ ] Livewire 側の `Route::livewire()` / config / `wire:model` 周りの差分を確認する
- **完了条件**: 主要画面が新基盤で起動・操作できる

### Sprint 4: 回帰確認・不要資産整理

- [ ] 旧 override / 不要 package / 不要 view / 不要 asset を整理する
- [ ] tenant / ACL / tree / form の主要導線を回帰確認する
- [ ] 必要なテストを追加し、再発防止の観点を固定する
- **完了条件**: Livewire 4 + Filament 5 基盤で安定運用できる

## 5. 判断基準

- **Filament 4 に留める**: Livewire 3 を受け入れる場合のみ
- **Livewire 4 を優先する**: Filament 5 への上げ直しが必要
- **tree fork を継続**: Filament 5 前提へ fork 側を再調整する

## 6. 参照

- `Issue #126`
- `docs/work/architecture/filament/2026-04-01_issue-126_dependency-inventory-and-migration-targets.md`
- `docs/work/architecture/filament/2026-04-01_issue-126_filament-tree-fork-sprint1-plan.md`

