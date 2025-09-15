# 自動リンク機能 マルチテナント対応計画

**日付:** 2025年9月15日
**作成者:** Gemini
**ステータス:** 計画中
**関連ドキュメント:**
*   [マルチテナント実装課題の解決戦略](./2025-09-07_issue-resolution-strategy.md)
*   [新マルチテナント実装計画書 (最終版)](./2025-08-30_new-multi-tenant-implementation-plan-final.md)
*   [AutoLink機能概要](/docs/function/AutoLink.md)
*   [AutoLinkService概要](/docs/services/AutoLinkService.md)

## 1. 概要

本計画は、LedgerLeapの自動リンク機能（AutoLink）をマルチテナント環境に対応させるための詳細な実装計画である。`AutoLink` 定義は常に特定のテナントに紐づくものとし、システム全体で共通のグローバルルールは導入しない。

これにより、管理者はテナントごとにカスタマイズされた自動リンクルールを設定できるようになる。

## 2. 目的

*   `AutoLink` 定義にテナントスコープを導入し、テナント固有の自動リンクルールを可能にする。
*   `AutoLink` 定義は常に特定のテナントに紐づくものとし、グローバルな自動リンクルールは導入しない。
*   `AutoLinkService` が現在のテナントコンテキストを考慮し、適切な自動リンクを適用するように改修する。
*   Filament管理画面からテナント固有の `AutoLink` 定義を管理できるようにする。

## 3. ユーザーシナリオと機能要件

### 3.1. ユーザーシナリオ

*   **シナリオ1: テナント固有のルール設定 (管理者)**
    *   佐藤さんは、特定のテナントAにのみ適用される「A社製品コード（例: `A-PROD-XXX`）」の自動リンクルールを設定したい。他のテナントにはこのルールは適用されない。
*   **シナリオ2: 優先度に基づく自動リンク適用 (実務担当者)**
    *   田中さんは、自身のテナントの台帳を閲覧している。表示されるテキストに、テナント固有のルールにマッチする文字列が含まれている場合、優先度設定に基づいて適切なリンクが適用されることを期待する。

### 3.2. 機能要件

1.  **`auto_links` テーブルの拡張:**
    *   `tenant_id` カラムを `NOT NULL` で追加する。
2.  **`AutoLink` モデルの対応:**
    *   `BelongsToTenant` トレイトを適用する。`tenant_id` が `null` のレコードを扱うためのカスタマイズは不要。
3.  **`AutoLinkService` の改修:**
    *   現在のテナントの `AutoLink` 定義のみを取得する。グローバル `AutoLink` 定義の取得ロジックは不要。
    *   取得した定義を `priority` に基づいてソートし、適用する。
    *   キャッシュキーの生成ロジックを、テナントIDのみを考慮するように調整する。
4.  **Filament管理画面の改修:**
    *   `AutoLink` 定義の作成・編集フォームに `tenant_id` を選択するフィールドを追加する。
    *   **リスト表示では全てのテナントの設定を俯瞰で見られるようにする。**
    *   **編集UIでは、他のテナントにフォーカスしていてもテナントを別のテナントに変更できるようにする。**
    *   一覧画面で `tenant_id` を表示し、フィルタリング・ソート可能にする。
    *   中央管理者は全ての `AutoLink` 定義を管理でき、テナント管理者は自身のテナントの `AutoLink` 定義のみを管理できるようにする。

## 4. ユーザー体験に関する検討（2025年9月15日追記）

本機能の実装後、ユーザーより「ロール管理画面でのテナント連携をよりシームレスにできないか」というフィードバックがあった。これは、具体的なイメージがあるわけではなく、既存のフォルダ単位での適用範囲調整機能や、ロール管理におけるテナントごとのフォルダ権限割り当て機能との類似性を高めることで、ユーザーの操作時の「違和感」を減らし、目的が直感的にわかるUIにしたいという意図である。

**検討の背景:**

*   **既存機能との類似性:** LedgerLeapには既に、AutoLink機能におけるフォルダ単位の適用範囲調整や、ロールに各テナントのフォルダ権限を割り当てる機能が存在する。これらの機能は、テナントとフォルダの関連性をユーザーに意識させるUIを提供している。
*   **ユーザーの期待:** 上部ナビゲーションでテナントを選択している状況で、ロールにフォルダ権限や通知設定を割り当てる際に、「このロールに、今見ているテナントのフォルダの権限を割り当てる」という流れが、より自然に感じられるUIが求められている可能性がある。

**検討された改善の方向性:**

1.  **フォルダ選択時のデフォルトテナントの自動設定:**
    *   FilamentのRelationManagerモーダルを開いた際に、上部ナビゲーションで選択されているテナント（またはセッションに保存されているテナントID）のフォルダがデフォルトで選択された状態にする。これにより、ユーザーがFilamentに遷移してきたテナントのコンテキストを自動的に引き継ぎ、操作性を向上させる。
2.  **ロール編集画面でのテナント別権限の可視化:**
    *   RelationManagerのテーブル表示で、現在のテナントのフォルダ権限/通知設定が強調されたり、デフォルトでフィルタリングされたりすることで、ユーザーは自分が今どのテナントのロール設定を見ているのかを明確に認識できる。
3.  **ロール一覧画面でのテナントフィルタリング:**
    *   ロール一覧から特定のテナントに関連するロールを探す際に役立つフィルタリング機能を追加する。

これらのアイデアは、ユーザーの操作時の「違和感」を減らし、既存の概念と結びつけやすくすることを目的としている。具体的な実装については、今後の開発フェーズで優先度を考慮し、ユーザー体験の向上に繋がるよう検討を進める。

## 5. 詳細な実装計画

### ステップ1: `auto_links` テーブルの `tenant_id` 対応

*   **目的:** `auto_links` テーブルに `tenant_id` カラムを追加し、テナントスコープの基盤を構築する。
*   **タスク:**
    1.  **マイグレーションファイルの修正:**
        `database/migrations/2025_07_28_105234_create_auto_links_table.php` を修正し、`tenant_id` カラムを `NOT NULL` で追加するロジックを記述する。
        ```php
        // database/migrations/2025_07_28_105234_create_auto_links_table.php
        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('auto_links', function (Blueprint $table) {
                    $table->id();
                    $table->string('tenant_id'); // NOT NULL に変更
                    $table->string('label');
                    $table->string('pattern');
                    $table->string('url_template');
                    $table->text('description')->nullable();
                    $table->integer('priority')->default(0);
                    $table->boolean('is_enabled')->default(true);
                    $table->boolean('open_in_new_tab')->default(true);
                    $table->string('link_type')->nullable();
                    $table->unsignedBigInteger('creator_id')->nullable();
                    $table->unsignedBigInteger('modifier_id')->nullable();
                    $table->timestamps();

                    $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
                    $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                    $table->foreign('modifier_id')->references('id')->on('users')->onDelete('set null');
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('auto_links');
            }
        };
        ```
    2.  **マイグレーションの実行:**
        `vendor/bin/sail artisan migrate:fresh --seed` を実行し、データベーススキーマを更新する。
*   **検証方法:**
    *   データベースクライアントで `auto_links` テーブルのスキーマを確認し、`tenant_id` カラムが `NOT NULL` で追加されていること、および外部キー制約が設定されていることを確認する。
*   **完了の定義:** `auto_links` テーブルに `tenant_id` カラムが `NOT NULL` で追加され、マイグレーションが成功すること。

### ステップ2: `AutoLink` モデルの `BelongsToTenant` 対応

*   **目的:** `AutoLink` モデルがテナントスコープに対応する。
*   **タスク:**
    1.  **`BelongsToTenant` トレイトの適用:**
        `app/Models/AutoLink.php` に `use Stancl\Tenancy\Database\Concerns\BelongsToTenant;` を追加し、`use BelongsToTenant;` をトレイトリストに追加する。
    2.  **グローバルスコープの調整の削除:**
        `booted()` メソッド内の `static::addGlobalScope('withoutTenantId', ...)` と `scopeGlobal()` メソッドは削除する。`tenant_id` が `NOT NULL` のため、これらの調整は不要。
    3.  **`AutoLinkObserver` の修正:**
        `AutoLinkObserver` が `AutoLink` モデルの変更を検知し、キャッシュをクリアするロジックが、テナントスコープの変更後も正しく動作することを確認する。
*   **検証方法:**
    *   `php artisan tinker` を使用し、テナントコンテキストを初期化した場合としない場合で `AutoLink::all()` を実行し、現在のテナントのレコードのみが取得できることを確認する。
*   **完了の定義:** `AutoLink` モデルがテナントスコープに対応し、現在のテナントのレコードのみが正しく取得できること。

### ステップ3: `AutoLinkService` の改修

*   **目的:** 現在のテナントの `AutoLink` 定義のみを取得し、優先度に基づいて適用するロジックを実装する。
*   **タスク:**
    1.  **`getAutoLinksForContext()` メソッドの修正:**
        *   現在のテナントコンテキストで `AutoLink` 定義を取得する。
        *   グローバル `AutoLink` 定義を取得するロジック（`tenancy()->central(function () { ... });` の部分）は削除する。
        *   取得した定義を `priority` カラムでソートする。
        *   キャッシュキーの生成ロジックを、テナントIDのみを考慮するように調整する。
*   **検証方法:**
    *   ユニットテストを作成し、現在のテナントのルールのみが正しく取得され、優先度に基づいて適用されることを確認する。
*   **完了の定義:** `AutoLinkService` がテナント固有の自動リンク適用ロジックを実装し、ユニットテストが成功すること。

### ステップ4: Filament `AutoLinkResource` の改修

*   **目的:** 管理者が `AutoLink` 定義をテナント固有として作成・管理できるようにする。
*   **タスク:**
    1.  **フォームへの `tenant_id` フィールド追加:**
        *   `app/Filament/Resources/AutoLinkResource.php` の `form()` メソッドに `Select::make('tenant_id')` を追加する。
        *   オプションとして、全てのテナントを表示する。
        *   `default(tenant()?->id)` とし、現在のテナントコンテキストを初期値とする。
        *   中央管理者の場合は、全てのテナントを選択できるようにする。テナント管理者の場合は、自身のテナントのみを選択できるようにする。
    2.  **一覧画面への `tenant_id` カラム表示:**
        *   `table()` メソッドに `TextColumn::make('tenant.name')` を追加し、`tenant_id` に基づくテナント名を表示する。
        *   `tenant_id` でフィルタリング・ソート可能にする。
    3.  **クエリの調整:**
        *   `getEloquentQuery()` メソッドを修正し、中央管理者の場合は全ての `AutoLink` 定義を表示し、テナント管理者の場合は自身のテナントの `AutoLink` 定義のみを表示するようにする。
*   **検証方法:**
    *   Filament管理画面で `AutoLink` 定義の作成・編集・一覧表示を行い、期待通りの挙動となることを確認する。
*   **完了の定義:** Filament管理画面がテナント固有の `AutoLink` 定義の管理に対応すること。

## 5. テスト計画

本機能の品質を保証するため、以下のテストを実施する。

*   **ユニットテスト:**
    *   `AutoLinkService` の `getAutoLinksForContext()` メソッドが、現在のテナントのルールのみを正しく取得し、優先度に基づいてソートすること。
*   **フィーチャーテスト:**
    *   テナントコンテキストで、テナント固有ルールが正しく適用されること。
    *   Filament管理画面で、テナント固有ルールが正しく作成・編集・表示されること。

## 6. 完了の定義

*   `auto_links` テーブルに `tenant_id` カラムが `NOT NULL` で追加され、`AutoLink` モデルがテナントスコープに対応すること。
*   `AutoLinkService` がテナント固有ルールを適切に適用すること。
*   Filament管理画面からテナント固有の `AutoLink` 定義を管理できること。
*   上記機能を検証する全てのテストが成功すること。
