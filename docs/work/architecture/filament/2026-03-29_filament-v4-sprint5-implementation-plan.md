# Filament v4 Sprint 5 実装計画

**status:** planned  
**last_updated_at:** 2026-03-29  
**related_issue:** https://github.com/torinky/LedgerLeap/issues/123  
**related_memo:** `docs/work/architecture/filament/2026-03-29_filament-v4-migration-preparation.md`  
**basis:** Sprint 0〜4 完了

## 目的

Sprint 5 は、Filament v4 の実装開始フェーズとして、実際の composer 更新・panel 基盤更新・依存プラグイン切替・必要最小限の UI 追従をまとめて進める段階。

## 実装スコープ

### 1. Core update

対象:
- `composer.json`
- `composer.lock`
- `app/Providers/Filament/AdminPanelProvider.php`
- 必要なら `config/filament.php` 相当の global configuration

やること:
- `filament/filament` を v4 系へ更新する
- `php artisan filament:upgrade` の案内に沿って差分を反映する
- `AdminPanelProvider` の navigation / render hook / plugin registration を v4 前提で再確認する

### 2. Plugin migration

対象:
- `codewithdennis/filament-select-tree`
- `15web/filament-tree`
- `althinect/filament-spatie-roles-permissions`

やること:
- `codewithdennis/filament-select-tree` を 4.x 系へ切り替える
- `15web/filament-tree` は `solutionforest/filament-tree` 4.x か、別実装へ置換する
- `althinect/filament-spatie-roles-permissions` は当面維持し、必要なら後続 sprint で代替設計を切り出す

### 3. UI / theme finalization

対象:
- `resources/views/filament/*`
- `resources/views/vendor/filament-tree/*`
- `resources/sass/filamentCustom.scss`
- `tailwind.config.js`

やること:
- dynamic Tailwind クラスを増やさず、既存の UI を維持する
- 必要な class が欠けた場合のみ Tailwind content / safelist を追加する
- `sail npm run build` は、追加ユーティリティが増えた場合に限り実施する

### 4. Regression verification

対象:
- `tests/Feature/Filament/*`
- `tests/Feature/Livewire/TenantSwitcherTest.php`
- `tests/Feature/Search/*`
- `tests/Unit/Services/TenantAccessServiceTest.php`
- `tests/Unit/Services/PermissionServiceTest.php`

やること:
- tenant / ACL / tree / search / dashboard の回帰を再確認する
- 破損が出た箇所だけを局所修正する
- Issue と `docs/work` の状態を同期して、次の完了判定へ進める

## 主要リスクと対応策

| リスク | 影響 | 対応策 |
| --- | --- | --- |
| plugin 互換性不足 | forms / tree / roles 周辺が壊れる | 1 つずつ切り替え、各段階で既存テストを実行する |
| ACL キャッシュの残留 | 権限表示や編集可否が不安定になる | 変更後に `flushAllUserPermissionsCache()` と `TenantAccessService::clearAllCache()` を必ず確認する |
| tenant routing mismatch | nav / widget の遷移先が崩れる | tenant URL の型を既存ルーティングに揃え、主要導線を再テストする |
| Tailwind class 欠落 | UI の見た目が崩れる | 動的 class を避け、必要時のみ `sail npm run build` を実施する |
| panel API 差分 | AdminPanelProvider / hook で不具合 | v4 docs の推奨 API に合わせ、差分は最小に保つ |

## 完了条件

- `filament/filament` の v4 更新が composer へ反映済み
- `AdminPanelProvider` の v4 差分が適用済み
- `codewithdennis/filament-select-tree` の 4.x 化が反映済み
- `15web/filament-tree` の置換方針が実装へ反映済み
- tenant / ACL / tree / search / dashboard の主要導線テストが PASS
- `docs/work` と Issue が Sprint 5 前提で更新済み

## 参照

- `docs/work/architecture/filament/2026-03-29_filament-v4-migration-preparation.md`
- `docs/work/architecture/filament/2026-03-29_filament-v4-sprint4-completion-report.md`
- `docs/work/architecture/filament/README.md`

