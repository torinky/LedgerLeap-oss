# Role Folder Permission EditAction Filament 5 Fix

**関連 Issue**: [#127](https://github.com/torinky/LedgerLeap/issues/127)

## 1. 背景

`app/Filament/Resources/RoleResource/RelationManagers/FolderPermissionRelationManager.php` の edit action で、Filament 5 の `Schema` 注入と旧来の `Form` 期待が混在していました。
その結果、モーダル表示時に TypeError が発生していました。

## 2. 対応

- `mountUsing()` ベースのモーダル初期化を `fillForm()` に置換
- `->form()` を `->schema()` に置換
- 権限保存ロジックは変更せず、Filament 5 の action API へ合わせた

## 3. 検証

- `php -l /Users/kazutaka/PhpstormProjects/LedgerLeap/app/Filament/Resources/RoleResource/RelationManagers/FolderPermissionRelationManager.php`
  - 結果: `No syntax errors detected`
- 既存の `RoleResource` 配下 relation manager を横断確認し、同種の `mountUsing(function (Schema ...))` / `->form(fn (Schema ...))` パターンがこの 1 ファイルに限定されていることを確認
- Issue コメント: `#127` の `issuecomment-4174202556` に修正内容と検証結果を記録

## 4. 参照

- 実装: `app/Filament/Resources/RoleResource/RelationManagers/FolderPermissionRelationManager.php`
- Issue コメント: `https://github.com/torinky/LedgerLeap/issues/127#issuecomment-4174202556`

