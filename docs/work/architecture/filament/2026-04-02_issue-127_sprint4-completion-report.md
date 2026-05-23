# Issue #127 Sprint 4 完了報告

**status:** confirmed  
**last_confirmed_at:** 2026-04-02  
**related_issue:** https://github.com/torinky/LedgerLeap/issues/127  
**related_sprint3:** `docs/work/architecture/filament/2026-04-02_role-folder-permission-edit-action-filament5-fix.md`

## 判定

Sprint 4（回帰確認・不要資産整理）は完了。tenant / ACL / tree / form の主要導線について、既存の Filament テスト群で回帰がないことを確認した。

## 実施した整理

- 旧 tree の生成物として残っていた `public/css/filament-tree/filament-tree.css` を削除
- 旧 tree の生成物として残っていた `public/js/filament-tree/filament-tree.js` を削除
- `resources/views/vendor/filament-tree/header.blade.php` / `row.blade.php` は、現行 tree の導線に必要なため残置

## 実施した確認

### Filament / tenant / ACL / tree / form
- `tests/Feature/Filament/FolderResourceTest.php`
- `tests/Feature/Filament/OrganizationResourceTest.php`
- `tests/Feature/Filament/RoleResourceTest.php`
- `tests/Feature/Filament/RoleResourceFolderPermissionRelationManagerTest.php`

## 結果

- 上記テストはすべて PASS
- Sprint 4 で追加削除した旧 tree asset による回帰は確認されなかった
- Tailwind の追加変更は発生しなかったため、`sail npm run build` は今回の Sprint 4 では未実施

## 証拠

### ローカル証拠
- `tests/Feature/Filament/FolderResourceTest.php`
- `tests/Feature/Filament/OrganizationResourceTest.php`
- `tests/Feature/Filament/RoleResourceTest.php`
- `tests/Feature/Filament/RoleResourceFolderPermissionRelationManagerTest.php`
- `docs/work/architecture/filament/2026-04-02_role-folder-permission-edit-action-filament5-fix.md`

### 実行結果
- `./vendor/bin/sail test tests/Feature/Filament/FolderResourceTest.php tests/Feature/Filament/OrganizationResourceTest.php tests/Feature/Filament/RoleResourceTest.php tests/Feature/Filament/RoleResourceFolderPermissionRelationManagerTest.php`
- 結果: `29 passed (77 assertions)`

## 次のアクション

1. Sprint 5 に進み、残る不要 view / asset / 回帰確認を必要に応じて整理する
2. UI で新たな差分が出た場合のみ `sail npm run build` を再評価する

