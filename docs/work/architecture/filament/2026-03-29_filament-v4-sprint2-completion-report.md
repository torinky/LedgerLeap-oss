# Filament v4 Sprint 2 完了報告

**status:** confirmed  
**last_confirmed_at:** 2026-03-29  
**related_issue:** https://github.com/torinky/LedgerLeap/issues/123  
**related_memo:** `docs/work/architecture/filament/2026-03-29_filament-v4-migration-preparation.md`  
**related_sprint1:** `docs/work/architecture/filament/2026-03-29_filament-v4-sprint1-completion-report.md`

## 判定

Sprint 2（プラグイン分岐）は完了。`filament-spatie-roles-permissions` / `filament-select-tree` / `filament-tree` の継続可否を次の通り確定した。

## 結論

- `althinect/filament-spatie-roles-permissions`
  - 現行 lock は v4 未対応として扱う
  - `composer` 更新ではなく、後続で代替方針を検討する
  - 判定: **後回し**
- `codewithdennis/filament-select-tree`
  - 4.x 系の v4 対応ラインが存在する
  - 現行 lock は v3 系のため、移行時は 4.x 系へ切り替える
  - 判定: **継続**
- `15web/filament-tree`
  - 現行 package は v4 対応根拠がなく、lock も v1.0.3 のまま
  - `solutionforest/filament-tree` 4.x が代替候補として存在する
  - 判定: **代替**

## 根拠

### ローカル証拠

- `composer.lock`
  - `althinect/filament-spatie-roles-permissions v2.3.3`
  - `codewithdennis/filament-select-tree v3.1.58`
  - `15web/filament-tree v1.0.3`
- `app/Filament/*`
  - `SelectTree::make(...)` の利用箇所が複数ある
  - `15web/filament-tree` のビュー上書きが存在する
- `bootstrap/cache/packages.php`
  - `15web/filament-tree` が登録済み

### GitHub リポジトリ確認

- `Althinect/filament-spatie-roles-permissions`
  - default branch: `3.x`
  - v4 対応 branch / release の明示なし
- `CodeWithDennis/filament-select-tree`
  - default branch: `4.x`
  - v4 対応ラインが公開されている
- `15web/filament-tree`
  - default branch: `main`
  - v4 対応の明示なし
- `solutionforest/filament-tree`
  - default branch: `4.x`
  - tree 系の代替候補として利用可能

## Sprint 2 で確定した実行方針

- `filament-spatie-roles-permissions` は現行のまま維持し、代替方針は後続で整理する
- `filament-select-tree` は v4 系へ切り替える前提で進める
- `filament-tree` は `solutionforest/filament-tree` 4.x か、必要なら自前実装へ置換する
- 以上の前提を固定したうえで、Sprint 3 で Blade / theme を v4 前提へ寄せる

## 完了した確認項目

- 依存プラグインの公開リポジトリ情報を確認した
- 現行 lock と利用箇所を確認した
- 継続 / 代替 / 後回し の区分を決めた

## 次のアクション

1. Sprint 3 で custom Blade / theme を v4 前提へ寄せる
2. `composer.json` の更新時に `filament-select-tree` の 4.x 系へ切り替える
3. `filament-tree` の置換先を実装方針として確定する

