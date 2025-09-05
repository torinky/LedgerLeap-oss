# テナント横断権限管理機能 詳細設計書

**日付:** 2025年9月6日
**作成者:** Gemini
**ステータス:** 設計完了

## 1. 概要

本ドキュメントは、[新マルチテナント実装計画書](./2025-08-30_new-multi-tenant-implementation-plan-final.md)のステップ11で定義された「テナント横断権限管理機能」の実装に関する詳細な設計を定義するものである。

### 1.1. 目的

管理者が中央管理画面（Filament）から、全テナント共通の役割（Role）に対し、テナントごとに属するフォルダ（Folder）へのアクセス権限（読み取り、書き込み等）を柔軟に割り当てられるようにする。

### 1.2. アーキテクチャ方針

本機能は、既存のデータベース構造とモデルリレーションを最大限に活用して実現する。

*   **役割 (Role) / 権限 (Permission):** テナントに依存しない**中央テーブル**で一元管理する。
*   **フォルダ (Folder):** `BelongsToTenant` トレイトにより、各テナントに所属する。
*   **役割とフォルダの紐付け:** `role_folder_permissions` 中間テーブルが、「中央のRole」と「テナント固有のFolder」を紐付ける役割を担う。このテーブルは `role_id` と `folder_id` を持ち、`folder_id` を通じて間接的にテナントが特定されるため、このテーブル自体に `tenant_id` カラムは不要である。

このアーキテクチャにより、ユーザーの要件である「共通の役割を、テナント固有のデータに紐付ける」権限管理モデルを実現する。

## 2. 実装方針

`RoleResource` に関連付けられた `FolderPermissionRelationManager` を、既存のUIを可能な限り維持しつつ、以下の点を中心に部分的に改修する。

1.  **フォルダ選択の拡張:** 権限を新規に割り当てる際、全テナントのフォルダから対象を選択できるようにする。
2.  **表示の明確化:** 権限が割り当てられたフォルダが、どのテナントに属するのかをテーブル上で明確に表示する。

## 3. `FolderPermissionRelationManager` の改修タスク

### 3.1. テーブル表示の拡張 (`table()` メソッド)

**目的:** どのテナントのフォルダに対する権限設定なのかを一覧上で明確にする。

*   **タスク1: テナント名カラムの追加**
    *   `columns` 配列に、`folder` リレーションを介してテナント名を表示するカラムを追加する。
        ```php
        // TextColumn::make('folder.title') の前に以下を追加
        TextColumn::make('folder.tenant.name')
            ->label(__('ledger.tenant'))
            ->sortable()
            ->searchable(),
        ```
*   **タスク2: Eager Loadingの追加**
    *   `query()` メソッドのクエリビルダに `with()` を追加し、`folder` と `folder.tenant` リレーションをEager Loadすることで、N+1問題を防止する。
        ```php
        // query() メソッド内の return 文を修正
        $query->with(['folder.tenant']);
        return $query;
        ```

### 3.2. フォルダ選択機能の拡張 (`create` アクション)

**目的:** 権限を新規に割り当てる際、モーダル内で全テナントのフォルダをツリー形式で表示し、選択できるようにする。

*   **タスク: `SelectTree` コンポーネントのクエリ変更**
    *   `headerActions` の `create` アクション内にある `SelectTree::make('folder_id')` の定義を修正する。
    *   `relationship()` メソッドの `modifyQueryUsing` オプションを利用し、クロージャ内で `tenancy()->disable()` を実行する。これにより、`SelectTree` がフォルダを読み込む際のテナントスコープが一時的に無効化され、全テナントのフォルダが取得・表示されるようになる。
        ```php
        // create アクションの form() 内
        SelectTree::make('folder_id')
            ->label(__('ledger.folder.title'))
            ->relationship(
                name: 'folder', // リレーション名
                titleAttribute: 'title', // 表示する属性
                parentAttribute: 'parent_id', // 親IDの属性
                // ★ クエリをカスタマイズしてテナントスコープを解除
                modifyQueryUsing: fn(EloquentBuilder $query) => tenancy()->disable() && $query->orderBy('_lft')
            )
            ->required()
            // ... 他のオプション
        ```
    *   **（推奨）オプション表示の改善:** `SelectTree` に `getOptionLabel` のようなカスタマイズ機能があれば、テナント名も併記（例: `経理部 (テナントA)`）することで、管理者がフォルダを識別しやすくなる。これは `SelectTree` プラグインの機能に依存する。

## 4. 影響範囲

*   本改修は `FolderPermissionRelationManager` に限定される。
*   データベーススキーマの変更や、`Role` `Permission` `Folder` モデル自体のロジック変更は不要である。
*   `model_has_roles` テーブルへの `tenant_id` 追加も不要である。