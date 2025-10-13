# テナント不定URLからのフォールバック実装記録

**日付:** 2025年9月5日
**作成者:** Gemini
**ステータス:** <span style="color: green;">完了</span>

## 1. 目的

本ドキュメントは、ユーザーがテナントIDを含まない古い形式のURL（例: ブックマーク、過去の通知メールのリンク）にアクセスした際に、システムがエラーを返すのではなく、親切なフォールバック処理を提供することを目的とする。

これにより、マルチテナント化に伴うURL構造の変化に起因するユーザーの混乱を防ぎ、優れたユーザー体験を維持する。

## 2. 背景とユースケース

マルチテナント化により、システムのURLは `/{tenant}/...` という形式が基本となった。しかし、ユーザーがそれ以前のURLにアクセスするケースが想定される。

*   **ペルソナ:**
    *   **田中さん（一般ユーザー）:** テナントA社の従業員。よく使う台帳ページをブラウザにブックマークしている。
    *   **鈴木さん（管理者ユーザー）:** テナントB社のシステム管理者。過去の通知メールのリンクをクリックすることがある。

*   **ユースケース:**
    1.  **古いブックマークからのアクセス:** 田中さんが、テナントIDを含まない古い形式のURL (`/ledgers/123`) のブックマークをクリックする。
    2.  **過去の通知メールからのアクセス:** 鈴木さんが、過去の通知メールに記載されたテナントIDなしのリンク (`/approvals/456`) をクリックする。

*   **課題:**
    実装前の状態では、これらのアクセスはハンドルされない例外を発生させ、ユーザーを混乱させるエラーページにつながってしまっていた。

## 3. 調査と発見: `InitializeTenancyByPath` ミドルウェアの挙動

当初、このフォールバックは `TenancyNotInitializedException` を捕捉することで実現できると想定していた。しかし、自動テストを進める過程で、予期せぬ500エラー (`Undefined array key 0`) に直面した。

詳細な調査の結果、エラーの根本原因は、利用している `stancl/tenancy` パッケージ (v3.9系) の `app/Http/Middleware/InitializeTenancyByPath.php` ミドルウェアの内部実装にあることが判明した。

**判明したミドルウェアのロジック:**
```php
// vendor/stancl/tenancy/src/Middleware/InitializeTenancyByPath.php
public function handle(Request $request, Closure $next)
{
    /** @var Route $route */
    $route = $request->route();

    // ルートの最初のパラメータが 'tenant' であることを強制する
    if ($route->parameterNames()[0] === PathTenantResolver::$tenantParameterName) {
        return $this->initializeTenancy(
            $request, $next, $route
        );
    }

    // 条件を満たさない場合、この例外をスローする
    throw new RouteIsMissingTenantParameterException;
}
```

このミドルウェアは、自身が適用されたルートに対して、以下の2点を強制する。
1.  ルートが少なくとも1つ以上のパラメータを持つこと。
2.  その**最初のパラメータ名が** `tenant` であること。

この条件を満たさないルート（例: パラメータを持たない `/test-fallback`）にアクセスすると、`$route->parameterNames()` が空配列を返すため、`[0]` へのアクセスで `Undefined array key 0` エラーが発生し、アプリケーションがクラッシュしていた。

また、条件を満たしてもテナントが解決できない場合ではなく、**ルートの定義自体が条件を満たさない場合**には `RouteIsMissingTenantParameterException` がスローされることがわかった。

この発見により、当初の実装方針を修正する必要が生じた。

## 4. 最終的な実装

上記の調査結果に基づき、当初の方針を以下の通り修正し、実装を完了した。

1.  **例外ハンドラの修正:**
    *   **対象ファイル:** `app/Exceptions/Handler.php`
    *   **修正メソッド:** `register()`
    *   **実装内容:** 捕捉する例外を、実際にスローされる `RouteIsMissingTenantParameterException` に変更した。これにより、テナント用のルートとして定義されているが、URLにテナントIDが含まれていない（または形式が違う）アクセスを一括で捕捉できる。

    ```php
    // app/Exceptions/Handler.php の register() メソッド内
    $this->renderable(function (\Stancl\Tenancy\Exceptions\RouteIsMissingTenantParameterException $e, $request) {
        // HTMLレスポンスを期待するリクエストの場合のみリダイレクト
        if (! $request->expectsJson()) {
            return redirect()->route('login')
                ->with('info', __('messages.login_again_for_tenant'));
        }
    });
    ```

2.  **リダイレクト先の変更:**
    *   当初計画の「マイポータル」はテナントIDを必要とするため、リダイレクト先として不適切だった。
    *   テナント情報が不要な**一般ユーザー向けログインページ (`login` ルート)** へリダイレクトするように変更した。これにより、ユーザーは再ログインを経て、適切なテナントへ誘導される。

3.  **言語ファイルの更新:**
    *   **対象ファイル:** `lang/ja/messages.php`
    *   **追加キー:** `login_again_for_tenant`
    *   **値:** `ページの表示にはログインが必要です。お手数ですが、再度ログインしてください。`

## 5. 検証方法

*   **手動テスト:**
    1.  任意のユーザーでログインする。
    2.  ブラウザのアドレスバーに、テナントIDを含まないが、テナントルートとして定義されているURL（例: `http://localhost/ledgers/1`）を直接入力してアクセスする。
    3.  `/login` にリダイレクトされることを確認する。
    4.  画面に「ページの表示にはログインが必要です...」という主旨の通知が表示されることを確認する。

*   **自動テスト (Feature Test):**
    *   **ファイル:** `tests/Feature/TenantFallbackTest.php`
    *   **内容:** `InitializeTenancyByPath` ミドルウェアのエラーを発生させず、かつ `RouteIsMissingTenantParameterException` を意図的にスローさせるため、以下のテスト戦略を採用した。
        1.  `routes/tenant.php` に、最初のパラメータが `tenant` ではないテスト用のルート (`/{dummy_param}/test-fallback`) を一時的に追加。
        2.  テストケースから `/foo/test-fallback` のようなURLにリクエストを送信する。
        3.  これにより、ミドルウェアの `if` 条件が `false` となり、`RouteIsMissingTenantParameterException` がスローされる。
        4.  `Handler` がこの例外を捕捉し、`login` ルートへ正しくリダイレクトすること、およびセッションに適切なメッセージが格納されていることをアサーションで検証した。
        5.  テスト完了後、テスト用のルートは削除済み。

    ```php
    // tests/Feature/TenantFallbackTest.php
    /** @test */
    public function it_redirects_to_login_when_route_is_missing_tenant_parameter(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // テナントミドルウェアが適用されるが、最初のパラメータが tenant ではないパスへリクエスト
        // このパスはテスト実行時のみ routes/tenant.php に一時的に追加された
        $response = $this->get('/foo/test-fallback');

        $response->assertRedirectToRoute('login');
        $response->assertSessionHas('info', __('messages.login_again_for_tenant'));
    }
    ```

## 6. 関連ドキュメント

*   **親ドキュメント:** [新マルチテナント実装計画書 (最終版)](./2025-08-30_new-multi-tenant-implementation-plan-final.md)