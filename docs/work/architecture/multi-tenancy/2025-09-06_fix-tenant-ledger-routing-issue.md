# テナント環境における台帳一覧表示のルーティング問題の調査と解決

**日付:** 2025年9月6日
**ステータス:** 解決済み

## 1. 問題の概要

マルチテナント環境の導入後、特定の条件下で台帳一覧ページが表示できないという問題が確認された。

- **エラーが発生するURL:** `/{tenant}/ledger` (例: `/debug-tenant/ledger`)
- **正常に表示されるURL:** `/{tenant}/ledger/folder/{id}` (例: `/cccc/ledger/folder/115`)

エラー発生時、ログには `Livewire\Exceptions\MethodNotFoundException` が記録され、Livewireコンポーネントの `toJSON` メソッドが見つからないことが示唆された。しかし、正常なケースとエラーのケースで、レンダリングされるデータの内容には大きな違いが見られず、原因の特定が困難な状況であった。

## 2. 調査の経緯

当初、Livewireコンポーネントの `public` プロパティに Eloquent オブジェクトが格納されていることがシリアライズエラーの原因であるという仮説を立てた。しかし、この説明では「なぜテナントやURLによって挙動が違うのか」という疑問を解消できなかった。

ユーザーからの指示に基づき、視点を変えて **ルート設定** を比較した結果、以下の決定的な違いが判明した。

- **エラーが発生するルート (`/ledger`):**
  Livewireコンポーネント `App\Livewire\Ledger\RecordsTable` を **直接** 呼び出していた。

- **正常に表示されるルート (`/ledger/folder/{id}`):**
  `App\Http\Controllers\Ledger\IndexController` という **コントローラを経由して** ビューを呼び出し、そのビューの中でLivewireコンポーネントをレンダリングしていた。

この発見から、問題の根本原因はコンポーネントの内部ロジックそのものよりも、「ルートからコンポーネントが呼び出されるまでの仕組みの違い」にあると結論付けられた。

## 3. 解決策

調査結果に基づき、ユーザーから「エラーが発生するルートも、正常なケースに合わせてコントローラ経由のルーティングにする」という明確な指示があった。

この方針に従い、`routes/tenant.php` の該当するルート定義を以下の通り修正した。

- **変更前:**
  ```php
  Route::get('/ledger', \App\Livewire\Ledger\RecordsTable::class)->name('ledger.index');
  ```

- **変更後:**
  ```php
  Route::get('/ledger', LedgerIndexController::class)->name('ledger.index');
  ```

## 4. 結果

上記修正により、`/ledger` ルートも `IndexController` を経由して処理されるようになった。これにより、両方のURLで処理フローが統一され、問題は完全に解決した。すべてのテナントにおいて、フォルダ指定の有無にかかわらず、台帳一覧ページが正常に表示されることが確認された。

## 5. 今後の課題

調査の過程で、`routes/tenant.php` には他にもLivewireコンポーネントを直接呼び出しているルートが複数存在することが確認されている。

- `/ledger/define/{defineId}`
- `/ledger/create/{ledgerDefineId}`
- `/ledger/edit/{ledgerId}`
- `/folders/create/{parentId?}`
- `/folders/{folder}/edit`
- `/notifications/settings`
- `/my-portal`

これらのルートは、それぞれがフルページコンポーネントとして意図通りに機能しており、現時点では問題は発生していない。

しかし、将来的にコンポーネントの初期化ロジックが複雑化した場合、今回と同様の問題が発生する可能性がある。その際は、今回と同様にコントローラ経由のルーティングに変更するアプローチが有効な解決策となるだろう。
