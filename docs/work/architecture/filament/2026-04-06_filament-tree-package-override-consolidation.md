# Filament Tree Package Override Consolidation

**関連 Issue**: [#126](https://github.com/torinky/LedgerLeap/issues/126)

## 1. 背景

`resources/views/vendor/filament-tree/*` に置いていた tree の override は、`15web/filament-tree` の既定挙動から app 側で分岐した一時対応でした。

ただし、override が app 側に残り続けると、package 更新時に差分が分散し、見た目・初期化ロジック・アクション制御のどこが正なのか分かりづらくなります。
そのため、tree の変更履歴を package 側に集約し、app 側 override を削除して保守境界を明確化しました。

## 2. 変更内容

### 2.1 package 側へ寄せた内容

- `packages/15web/filament-tree/resources/views/header.blade.php`
  - app 側 override と同じく create action を非表示に変更
- `packages/15web/filament-tree/resources/views/row.blade.php`
  - depth ベースの左余白と `data-depth` を package 側へ移動
- `packages/15web/filament-tree/resources/dist/filament-tree.min.js`
  - tree 初期化を package 側で完結するように整理
  - `DOMContentLoaded` 後と Livewire commit 後に Sortable を初期化する形へ更新

### 2.2 app 側から削除した内容

- `resources/views/vendor/filament-tree/header.blade.php`
- `resources/views/vendor/filament-tree/row.blade.php`
- `resources/views/vendor/filament-tree/tree.blade.php`

## 3. 影響範囲

- `app/Filament/Resources/FolderResource/Pages/ListFoldersTree.php`
- `app/Filament/Resources/OrganizationResource/Pages/ListOrganizationsTree.php`
- `tests/Feature/Filament/OrganizationResourceTest.php`
- `tests/Feature/Filament/FolderResourceTest.php`
- `packages/15web/filament-tree/*`

tree の表示・操作は package 側を正とし、app 側は resource 固有の定義だけを持つ状態へ戻しました。

## 4. 検証

- `./vendor/bin/sail test tests/Feature/Filament/OrganizationResourceTest.php tests/Feature/Filament/FolderResourceTest.php`
- 結果: `19 passed (50 assertions)`

## 5. 今後の保守方針

- tree の既定挙動は `packages/15web/filament-tree` 側で管理する
- app 側の `resources/views/vendor/filament-tree/*` での上書きは増やさない
- 追加修正が必要な場合は、まず package 側へ反映してから app 側の差分を最小化する
- tree 関連の変更は、この記録と対応 commit を参照して追跡する

## 6. 参照

- 実装: `packages/15web/filament-tree/resources/views/header.blade.php`
- 実装: `packages/15web/filament-tree/resources/views/row.blade.php`
- 実装: `packages/15web/filament-tree/resources/dist/filament-tree.min.js`
- 削除: `resources/views/vendor/filament-tree/header.blade.php`
- 削除: `resources/views/vendor/filament-tree/row.blade.php`
- 削除: `resources/views/vendor/filament-tree/tree.blade.php`

