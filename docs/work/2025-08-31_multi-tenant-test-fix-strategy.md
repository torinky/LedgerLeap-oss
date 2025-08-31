# マルチテナント環境におけるテスト失敗の分析と修正戦略

**日付:** 2025年8月31日
**作成者:** Gemini
**ステータス:** 策定中

## 1. はじめに

本ドキュメントは、LedgerLeapプロジェクトのマルチテナント機能実装において発生しているテスト失敗の根本原因を分析し、その解決に向けた具体的な修正戦略を提示することを目的とします。特に、`stancl/tenancy` パッケージの挙動と、アプリケーションのルーティング定義の整合性に焦点を当てます。

## 2. 根本原因の分析

現在のテスト結果では、多くのフィーチャーテストが `UrlGenerationException: Missing required parameter for [Route: ...] [Missing parameter: tenant]` というエラーで失敗しています。これは、URL生成時にテナントIDが正しく含まれていないことを示しています。

この問題の根本原因は、以下の2点に集約されます。

### 2.1. `stancl/tenancy` の `PathTenantResolver` の挙動とルーティングの不整合

LedgerLeapプロジェクトでは、`stancl/tenancy` の `PathTenantResolver` を使用して、URLパス（例: `http://localhost/{tenant_id}/my-portal`）からテナントを識別する方式を採用しています。この方式では、テナントにスコープされるルートは、`{tenant}` プレースホルダーを含む形式で定義されることが期待されます。

`app/Providers/RouteServiceProvider.php` を確認すると、テナントにスコープされるルートは `routes/tenant.php` に定義され、`Stancl\Tenancy\Middleware\InitializeTenancyByPath::class` ミドルウェアが適用されるように設定されています。これは正しいアプローチです。

しかし、現状では `routes/web.php` にも、テナントにスコープされるべきルート（例: `/my-portal`, `/ledger` およびその配下のCRUDルートなど）が定義されています。`routes/web.php` は `InitializeTenancyByPath` ミドルウェアのスコープ外で読み込まれるため、これらのルートはテナントコンテキストを認識できません。

### 2.2. `AuthenticatedSessionController` での `route()` ヘルパーの挙動

`app/Http/Controllers/Auth/AuthenticatedSessionController.php` では、ログイン後のリダイレクトURLを生成するために `route()` ヘルパーを使用しています。

```php
if ($tenant) {
    return redirect()->intended(route($landingPageRouteName, ['tenant' => $tenant->id]));
}
return redirect()->intended(route($landingPageRouteName));
```

`route()` ヘルパーは、ルート定義に基づいてURLを生成します。
*   もしルートが `/{tenant}/my-portal` のようにパス形式で定義されていれば、`route('my-portal', ['tenant' => $tenant->id])` は `http://localhost/{tenant_id}/my-portal` のようなパス形式のURLを生成します。
*   しかし、ルートが `/my-portal` のように定義されており、`{tenant}` プレースホルダーが含まれていない場合、`route()` ヘルパーは `tenant` パラメータをクエリ文字列として追加し、`http://localhost/my-portal?tenant={tenant_id}` のようなURLを生成してしまいます。

このクエリ文字列形式のURLは、`PathTenantResolver` が期待する形式ではないため、テナントコンテキストが正しく初期化されず、その後の処理で `Missing parameter: tenant` エラーが発生する原因となります。

## 3. ペルソナとユースケースから見たテストの重要性

LedgerLeapプロジェクトは、Webベースの台帳管理システムであり、複数の組織やプロジェクトでの利用を想定したマルチテナント機能を特徴としています。このシステムにおいて、テストは単にコードの品質を保証するだけでなく、ビジネス要件を満たす上で極めて重要な役割を担います。

### 3.1. ターゲットユーザーと主要機能

*   **ターゲットユーザー:** 中小企業や大企業の部門・チーム単位での利用。実務担当者、管理者、現場リーダー/作業班長。
*   **主要機能:** 全文検索（添付ファイル含む）、柔軟な権限管理、ワークフロー、階層型フォルダ管理、自動リンク、リアルタイム通知など。
*   **マルチテナントの目的:** ID枯渇、パフォーマンス低下、リソース集中、コンプライアンス要件への対応。

### 3.2. マルチテナント環境下でのテストの重要性

上記のペルソナとユースケースを踏まえると、マルチテナント環境下でのテストは以下の点を特に重視する必要があります。

1.  **テナント間のデータ分離の保証:**
    *   **重要性:** 各テナントのデータが他のテナントから参照・操作できないことは、セキュリティとコンプライアンスの観点から最も基本的な要件です。誤ってデータが混在すると、情報漏洩や業務の混乱を招きます。
    *   **テストの焦点:** あるテナントで作成されたデータが、別のテナントのコンテキストでアクセスできないことを確認するテストケースを網羅的に記述します。
2.  **共通機能のテナント対応:**
    *   **重要性:** 認証、ユーザー管理、権限管理など、テナントに依存しない（中央DBに配置される）機能が、テナント環境下でも期待通りに動作することを確認する必要があります。これらの機能は、テナントのコンテキストが切り替わっても一貫した挙動を示すべきです。
    *   **テストの焦点:** 中央のユーザーが複数のテナントにアクセスできること、ロールや権限がテナント間で正しく適用されることなどを検証します。
3.  **テナントコンテキストの正確な解決:**
    *   **重要性:** URLパスやセッションなどから、現在のテナントが正しく識別され、アプリケーション全体に適用されていることは、マルチテナントアプリケーションの基盤です。これが機能しないと、ルーティングエラーやデータアクセスエラーが頻発し、システムが利用不能になります。
    *   **テストの焦点:** `stancl/tenancy` のミドルウェアが正しく機能し、コントローラーやLivewireコンポーネント、モデルのクエリが常に正しいテナントスコープで実行されていることを確認します。

## 4. 修正戦略

上記の分析に基づき、テスト失敗を解消し、マルチテナント環境下でのアプリケーションの堅牢性を高めるための修正戦略を以下に示します。

### 4.1. ルート定義の整理

最も根本的な問題であるルーティングの不整合を解消します。

*   **`routes/web.php` の役割:**
    *   テナントに依存しない、中央のアプリケーションルートのみを定義します。
    *   例: `/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`, `/logout`, `/` (テナント選択/リダイレクト用)。
    *   これらのルートは `InitializeTenancyByPath` ミドルウェアのスコープ外に置かれます。
*   **`routes/tenant.php` の役割:**
    *   テナントにスコープされるすべてのアプリケーションルートを定義します。
    *   例: `/{tenant}/my-portal`, `/{tenant}/ledger`, `/{tenant}/ledger/{ledgerId}`, `/{tenant}/ledgerDefine` など、台帳管理システムの主要機能に関するルート。
    *   これらのルートは `RouteServiceProvider` で `InitializeTenancyByPath` ミドルウェアが適用されるグループ内に配置されます。
    *   **具体的な移動計画:**
        *   `routes/web.php` から `Route::middleware('auth')->group(...)` 内のすべてのルートを `routes/tenant.php` に移動します。
        *   `routes/web.php` に残るのは、認証関連のルートと、`/` から `/ledger` へのリダイレクトのみとなります。

### 4.2. テストコードの修正

ルート定義の整理後、既存のフィーチャーテストを修正し、新しいルーティング構造とテナントコンテキストの要件に適合させます。

*   **テナントコンテキストの初期化:**
    *   テナントにスコープされるルートをテストするすべてのフィーチャーテスト（例: `Tests\Feature\Livewire\LedgerColumnValidationTest`, `Tests\Feature\ProfileTest` など）の `setUp()` メソッド、または個々のテストケース内で、テスト対象のテナントを明示的に初期化します。
    *   `$tenant = Tenant::create(); tenancy()->initialize($tenant);`
*   **URLアサーションの修正:**
    *   テスト内でリダイレクトURLや生成されるURLをアサートする際、`/{tenant_id}/path` の形式になっていることを確認します。
    *   例: `$response->assertRedirect('/' . $tenant->getTenantKey() . '/my-portal');`
*   **認証テスト (`AuthenticationTest`) の修正:**
    *   `users can authenticate using the login screen` テストが、リダイレクトURLの形式不一致で失敗していました。ルート定義を整理することで、`AuthenticatedSessionController` が `route()` ヘルパーでパス形式のURLを生成するようになるため、このテストはパスするはずです。
*   **その他の失敗しているFeatureテストへの適用:**
    *   `Missing parameter: tenant` エラーで失敗している他のすべてのFeatureテストについても、同様にテナントコンテキストの初期化とURLアサーションの修正を適用します。

## 5. 検証計画

修正戦略の適用後、以下の手順で検証を行います。

1.  **データベースのクリーンアップと再マイグレーション:**
    *   `vendor/bin/sail artisan migrate:fresh --seed` を実行し、クリーンなデータベース環境を構築します。
2.  **テストの実行:**
    *   `vendor/bin/sail test` を実行し、すべてのテストがパスすることを確認します。
3.  **手動での動作確認:**
    *   ブラウザでアプリケーションにアクセスし、テナントのURL（例: `http://localhost/{tenant_id}/my-portal`）でログイン、台帳の作成・編集、フォルダ操作など、主要な機能が期待通りに動作することを確認します。
    *   異なるテナントでログインし、データが分離されていることを確認します。

## 6. 結論

本ドキュメントで提示された修正戦略を適用することで、マルチテナント環境におけるテストの信頼性が大幅に向上し、アプリケーションの堅牢性が確保されます。これにより、将来の機能追加や変更がより安全かつ効率的に進められるようになります。
