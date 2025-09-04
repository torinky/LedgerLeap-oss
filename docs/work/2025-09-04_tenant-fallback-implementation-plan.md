# テナント不定URLからのフォールバック実装計画

**日付:** 2025年9月4日
**作成者:** Gemini
**ステータス:** <span style="color: blue;">計画完了・実装待ち</span>

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
    現状では、これらのアクセスは `TenancyNotInitializedException` 例外を発生させ、ユーザーを混乱させるエラーページにつながってしまう。

## 3. 仕様

ユーザー体験を損なわない、親切なフォールバック処理を実装する。

*   **トリガー:** `Stancl\Tenancy\Exceptions\TenancyNotInitializedException` が発生した全てのウェブアクセス。
*   **アクション:** ユーザーを「マイポータル」ページ (`/my-portal`) へリダイレクトさせる。
*   **ユーザーへの通知:** リダイレクト後、画面に以下の警告トースト通知を一度だけ表示する。
    *   **種別:** 警告 (Warning)
    *   **メッセージ:** 「アクセスしようとしたページのテナントが指定されていなかったため、マイポータルに移動しました。」

## 4. 実装方針

Laravelの例外処理メカニズムを利用して、グローバルなフォールバックを実現する。

1.  **例外ハンドラの修正:**
    *   **対象ファイル:** `app/Exceptions/Handler.php`
    *   **修正メソッド:** `register()`
    *   **実装内容:** `renderable()` メソッドを使い、`TenancyNotInitializedException` を捕捉するクロージャを登録する。この中でリダイレクト処理を実装する。この方法は、Laravelの現代的な例外処理のベストプラクティスに沿っている。

    ```php
    // app/Exceptions/Handler.php の register() メソッド内
    $this->renderable(function (\Stancl\Tenancy\Exceptions\TenancyNotInitializedException $e, $request) {
        // HTMLレスポンスを期待するリクエストの場合のみリダイレクト
        if ($request->is('web') || $request->expectsJson() === false) {
            return redirect()->route('my-portal')
                ->with('warning', __('messages.tenant_not_identified_redirect'));
        }
    });
    ```

2.  **言語ファイルの更新:**
    *   **対象ファイル:** `resources/lang/ja.json` （または `lang/ja/messages.php`）
    *   **追加キー:** `tenant_not_identified_redirect`
    *   **値:** `"アクセスしようとしたページのテナントが指定されていなかったため、マイポータルに移動しました。"`

3.  **トースト通知の表示:**
    *   アプリケーションのメインレイアウト（例: `resources/views/components/layouts/app.blade.php`）に、セッションに `warning` キーが存在する場合にMaryUIのトーストを表示する仕組みが既に存在することを前提とする。もし存在しない場合は、追加実装が必要。

## 5. 検証方法

*   **手動テスト:**
    1.  任意のユーザーでログインする。
    2.  ブラウザのアドレスバーに、テナントIDを含まないURL（例: `http://localhost/ledgers/1`）を直接入力してアクセスする。
    3.  `/my-portal` にリダイレクトされることを確認する。
    4.  画面右上に警告メッセージのトーストが正しく表示されることを確認する。

*   **自動テスト (Feature Test):**
    *   **テストファイル:** `tests/Feature/TenantFallbackTest.php` を新規作成する。
    *   **テスト内容:**
        1.  ユーザーとテナントを作成し、`actingAs()` で認証状態にする。
        2.  `get('/some/tenant-less/path')` のように、テナントIDを含まない任意のパスへリクエストを送信する。
        3.  レスポンスが `my-portal` ルートへのリダイレクトであることを `assertRedirectToRoute('my-portal')` で検証する。
        4.  セッションに `warning` のキーで正しいメッセージが格納されていることを `assertSessionHas('warning', __(...))` で検証する。

## 6. 関連ドキュメント

*   **親ドキュメント:** [新マルチテナント実装計画書 (最終版)](./2025-08-30_new-multi-tenant-implementation-plan-final.md)
