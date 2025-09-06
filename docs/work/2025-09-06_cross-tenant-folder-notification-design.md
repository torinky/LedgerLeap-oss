# テナント横断フォルダ通知管理機能 詳細設計書

**日付:** 2025年9月6日
**作成者:** Gemini
**ステータス:** 設計完了

## 1. 概要

本ドキュメントは、中央管理画面（Filament）から、`Role`（役割）に対し、テナントごとに属するフォルダへの通知設定を柔軟に割り当てられるようにする機能の実装に関する詳細な設計を定義するものである。これは、既存のテナント横断権限管理機能の類似機能として位置づけられる。

## 2. 実装方針

`RoleResource` に関連付けられた `NotificationSettingsRelationManager` を、既存のUIを可能な限り維持しつつ、以下の点を中心に改修する。

1.  **テーブル表示の拡張:** 権限が割り当てられたフォルダが、どのテナントに属するのかをテーブル上で明確に表示する。
2.  **フォルダ選択の拡張:** 通知設定を新規に割り当てる際、全テナントのフォルダから対象を選択できるようにする。
3.  **フィルタリングの追加:** テナントでフォルダを絞り込めるようにする。
4.  **グループ化の追加:** テナントでテーブルをグループ化できるようにする。

## 3. `NotificationSettingsRelationManager` の改修タスク

### 3.1. テーブル表示の拡張 (`table()` メソッド)

**目的:** どのテナントのフォルダに対する通知設定なのかを一覧上で明確にする。

*   **タスク1: テナント名カラムの追加**
    *   `columns` 配列に、`folder` リレーションを介してテナント名を表示するカラムを追加する。`folder.tenant.name` を使用し、`name` がない場合は `id` をフォールバックとして表示する。
    *   **コードスニペット:**
        ```php
                        TextColumn::make('folder.tenant.name')
                            ->label(__('ledger.tenant'))
                            ->formatStateUsing(fn (?string $state, Model $record): string => $state ?: ($record->folder->tenant->id ?? '-')),
        ```
*   **タスク2: Eager Loadingの追加**
    *   `query()` メソッドのクエリビルダに `with()` を追加し、`folder.tenant` リレーションをEager Loadすることで、N+1問題を防止する。
    *   **コードスニペット:**
        ```php
                $query->with(['folder:id,title', 'notificationType:id,name', 'folder.tenant']);
        ```
*   **タスク3: グループ化の変更**
    *   `defaultGroup` と `Group::make` を `folder.tenant.id` でグループ化するように変更し、グループヘッダーにはテナントIDを表示する。
    *   **コードスニペット:**
        ```php
            ->groups([
                \Filament\Tables\Grouping\Group::make('folder.tenant.id') // Group by tenant ID (physical column)
                    ->label(__('ledger.tenant'))
                    ->collapsible(), // 折りたたみ可能に
            ])
        ```

### 3.2. フォルダ選択機能の拡張 (`headerActions` -> `create` アクション)

**目的:** 通知設定を新規に割り当てる際、モーダル内で全テナントのフォルダをツリー形式で表示し、選択できるようにする。また、テナントで絞り込みできるようにする。

*   **タスク1: テナント選択フィールドの追加**
    *   `form` 配列に `Select::make('tenant_id')` を追加し、テナント一覧を表示する。
    *   `live()` を有効にし、テナント選択時にフォルダ選択をリセットする `afterStateUpdated` を設定する。
    *   **コードスニペット:**
        ```php
                    Select::make('tenant_id')
                        ->label(__('ledger.tenant'))
                        ->options(
                            \Stancl\Tenancy\Facades\Tenancy::central(function () {
                                return \App\Models\Tenant::all()->mapWithKeys(function ($tenant) {
                                    return [$tenant->id => $tenant->name ?: $tenant->id];
                                });
                            })
                        )
                        ->live()
                        ->afterStateUpdated(function (callable $set) {
                            $set('folder_id', null); // Clear folder selection when tenant changes
                        })
                        ->required(),
        ```
*   **タスク2: `SelectTree` コンポーネントのクエリ変更**
    *   `relationship()` メソッドの `titleAttribute` を `display_title` に変更する。（`Folder` モデルに `getDisplayTitleAttribute()` が実装済みであることを前提とする）
    *   `modifyQueryUsing` オプションを利用し、クロージャ内で `\Stancl\Tenancy\Facades\Tenancy::central()` を実行し、テナントスコープを一時的に無効化する。
    *   `modifyQueryUsing` 内で、`tenant_id` 選択フィールドの値に基づいてフォルダをフィルタリングする。
    *   `modifyQueryUsing` 内で、`folder.tenant` リレーションをEager Loadする。
    *   **コードスニペット:**
        ```php
                    SelectTree::make('folder_id')
                        ->label(__('ledger.folder.title'))
                        ->relationship(relationship: 'folder', titleAttribute: 'display_title', parentAttribute: 'parent_id',
                            modifyQueryUsing: fn(EloquentBuilder $query, callable $get) => \Stancl\Tenancy\Facades\Tenancy::central(function () use ($query, $get) {
                                $selectedTenantId = $get('tenant_id');
                                if ($selectedTenantId) {
                                    $query->where('tenant_id', $selectedTenantId);
                                }
                                return $query->with('tenant')->orderBy('_lft');
                            }))
                        ->required()
                        ->searchable()
                        ->enableBranchNode()
                        ->defaultOpenLevel(1),
        ```

### 3.3. フィルタリングの追加 (`table()` メソッド)

**目的:** テナントでテーブルの表示を絞り込めるようにする。

*   **タスク1: テナントフィルターの追加**
    *   `filters` 配列に `Filter::make('tenant_id')` を追加し、テナントでフィルタリングできるようにする。
    *   **コードスニペット:**
        ```php
                Filter::make('tenant_id')
                    ->form([
                        Select::make('value')
                            ->label(__('ledger.tenant'))
                            ->options(
                                \Stancl\Tenancy\Facades\Tenancy::central(function () {
                                    return \App\Models\Tenant::all()->mapWithKeys(function ($tenant) {
                                        return [$tenant->id => $tenant->name ?: $tenant->id];
                                    });
                                })
                            )
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (EloquentBuilder $query, array $data): EloquentBuilder {
                        if (blank($data['value'])) {
                            return $query;
                        }
                        return $query->whereHas('folder', function (EloquentBuilder $query) use ($data) {
                            $query->where('tenant_id', $data['value']);
                        });
                    })
                    ->label(__('ledger.tenant')),
        ```

## 4. 影響範囲

*   本改修は `NotificationSettingsRelationManager` に限定される。
*   データベーススキーマの変更や、`Role` `Permission` `Folder` `NotificationType` モデル自体のロジック変更は不要である。

## 5. 最終的な変更点

本機能の実装において、以下のファイルが変更される予定である。

*   `app/Filament/Resources/RoleResource/RelationManagers/NotificationSettingsRelationManager.php`
