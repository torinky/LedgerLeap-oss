# `app/Livewire/Ledger/Show.php` リファクタリング計画 - Step 2: WorkflowPanel コンポーネントの分離

## 1. 目的

`Show.php` から、ワークフローの状態表示、アクションボタン、関連モーダルといった、ワークフローに特化したUIとそのロジックを、ワークフローに特化したUIとそのロジックを、複数のLivewire子コンポーネントに分離します。これにより、親コンポーネントの責務をさらに軽減し、ワークフロー関連のUIを独立して管理・開発できるようにします。

## 2. 段階的アプローチとテンプレート構築

大規模な変更によるリスクを低減し、今後の開発効率を向上させるため、段階的なアプローチを採用します。

このアプローチは、単にリスクを低減するだけでなく、**今後のリファクタリング作業における標準的な進め方（テンプレート）を構築する**ことを重要な目的としています。

## 3. 詳細な作業計画

### Step 2.1: ワークフロー関連UIの複数Livewireコンポーネントへの分割と段階的移行

当初、承認機能のみを分離する計画でしたが、UIの整合性維持に課題があったため、より包括的かつ安全な段階的アプローチに見直しました。

1.  **ワークフロー関連Livewireコンポーネントの作成とロジックの分散:**
    *   以下のLivewireコンポーネントを生成します。
        *   `vendor/bin/sail artisan make:livewire Ledger/WorkflowStatusCard`
        *   `vendor/bin/sail artisan make:livewire Ledger/WorkflowActionButtons`
        *   `vendor/bin/sail artisan make:livewire Ledger/WorkflowHistoryList`
    *   `Show.php` からワークフロー関連のプロパティとメソッド（承認、差し戻し、申請取り下げなど全てのワークフローアクション）を、それぞれの責務に応じてこれらの新しいコンポーネントに分散してコピーします。
    *   各コンポーネントは、親から渡される `LedgerRecord` を受け取るための `public Ledger $ledgerRecord;` プロパティを定義します。
    *   必要なサービス（`WorkflowService` など）をコンストラクタまたは `mount` メソッドで依存注入します。

2.  **ビューの分離と `Show` への埋め込み（元のUIは一時非表示）:**
    *   `show.blade.php` からワークフロー関連のUI（上部ステータスカード、下部フッターのワークフローアクションボタン、ワークフロー履歴リスト）を、それぞれの責務に応じた新しいコンポーネントのBladeファイル（例: `workflow-status-card.blade.php`, `workflow-action-buttons.blade.php`, `workflow-history-list.blade.php`）にコピーします。
    *   `show.blade.php` の元のワークフローUIがあった場所に、それぞれのコンポーネントを埋め込みます。
        *   例: `<livewire:ledger.workflow-status-card :ledgerRecord="$ledgerRecord" wire:key="status-card-{{ $ledgerRecord->id }}" />`
        *   例: `<livewire:ledger.workflow-action-buttons :ledgerRecord="$ledgerRecord" wire:key="action-buttons-{{ $ledgerRecord->id }}" />`
        *   例: `<livewire:ledger.workflow-history-list :ledgerRecord="$ledgerRecord" wire:key="history-list-{{ $ledgerRecord->id }}" />`
    *   **元のUIは、`wire:ignore` で囲むことで一時的に非表示にします。**

3.  **コンポーネント間の連携（イベント駆動）:**
    *   各子コンポーネントは、自身の責務範囲内で完結する処理を行い、親コンポーネントや他の子コンポーネントに影響を与える必要がある場合は、Livewire のイベント (`$this->dispatch()`) を使用して通知します。
    *   ワークフローアクションを実行する子コンポーネント（例: `WorkflowActionButtons`）は、アクションメソッドが成功した際に、`$this->dispatch('workflowUpdated');` を実行してイベントを発行します。
    *   親である `Show.php` は、`#[On('workflowUpdated')]` アトリビュートを使ってこのイベントをリッスンし、`$this->ledgerRecord->refresh()` を実行して最新の台帳情報を再読み込みします。

4.  **テストの作成と実行:**
    *   **新規作成:** 各子コンポーネント（例: `WorkflowStatusCard`, `WorkflowActionButtons`, `WorkflowHistoryList`）の機能（アクションボタン表示、モーダル制御、イベントハンドリング、サービス呼び出し、トースト表示など）を検証するユニットテストとフィーチャーテストを実装します。
        *   例: `tests/Feature/Livewire/Ledger/WorkflowActionButtonsTest.php`
    *   **既存テストの実行:** `ShowTest.php` を実行し、既存のフィーチャーテストがパスすることを確認します。

5.  **元のUIとロジックの削除:**
    *   各子コンポーネントが完全に機能していることを確認した後、`show.blade.php` から一時的に非表示にしていた元のUIを完全に削除します。
    *   `Show.php` から、各子コンポーネントに委譲したワークフロー関連のプロパティとメソッドを完全に削除します。

## 4. 期待される成果物

*   ワークフロー関連機能が複数のLivewire子コンポーネントに適切に分散され、`Show.php` からワークフローに関するコードが削減され、見通しが改善された状態。
*   イベントを通じて親コンポーネントと疎に連携する、クリーンなアーキテクチャの確立。
*   他の機能にも適用可能な、テストに裏付けられたリファクタリングの**成功モデル（テンプレート）**。

## 5. 作業結果と課題解決 (2025-08-12 更新)

### 5.1. 現在の進捗状況

*   **`WorkflowActionButtons` コンポーネントのテスト完了:**
    *   `tests/Feature/Livewire/Ledger/WorkflowActionButtonsTest.php` に存在するすべてのテストが成功することを確認しました。
    *   テストの過程で、Livewireコンポーネントのテストにおける `Toast` トレイトの扱い（`$this->dispatch` の利用）や、`render` メソッドで暗黙的に呼び出されるメソッドのモック戦略に関する知見が得られました。
    *   関連して、`tests/Feature/Livewire/Ledger/ShowTest.php` で発生していた副次的なエラーも解消済みです。

*   **`WorkflowStatusCard`, `WorkflowHistoryList` のコンポーネントファイルの作成:**
    *   計画通り、コンポーネントファイルは作成済みです。

### 5.2. 新たな課題: コンポーネント間のロジック重複

当初の計画では、`WorkflowActionButtons` にワークフロー関連のアクションをすべて集約する想定でした。しかし、UI/UX上の要請から、`WorkflowStatusCard` コンポーネント内にも、状況に応じて状態を変化させるためのアクションボタン（例: 承認、差し戻し）を配置する必要があることが判明しました。

これにより、`WorkflowActionButtons` と `WorkflowStatusCard` の間で、ワークフロー実行に関するプロパティとメソッドが重複してしまうという新たな課題が浮上しました。これはコードの再利用性を損ない、メンテナンス性を低下させるため、リファクタリングの目的に反します。

### 5.3. 解決策: `WorkflowActions` トレイトによるロジックの共通化

このロジック重複問題を解決するため、以下の通り、PHPの **トレイト (Trait)** を用いて共通ロジックをカプセル化するアプローチを採用します。

1.  **`WorkflowActions` トレイトの作成:**
    *   `app/Traits/WorkflowActions.php` を新規に作成し、`WorkflowActionButtons.php` からワークフローのアクションに関連するプロパティとメソッドをすべてこのトレイトに移動しました。

2.  **各コンポーネントでのトレイトの使用:**
    *   `app/Livewire/Ledger/WorkflowActionButtons.php` と `app/Livewire/Ledger/WorkflowStatusCard.php` の両方で、`WorkflowActions` トレイトを `use` するように修正しました。
    *   これにより、両コンポーネントは重複したコードを持つことなく、同じワークフロー関連機能を利用できるようになりました。

3.  **`WorkflowHistoryList` のテスト完了:**
    *   `tests/Feature/Livewire/Ledger/WorkflowHistoryListTest.php` を作成し、すべてのテストが成功することを確認しました。

4.  **`Show.php` のリファクタリング完了:**
    *   `Show.php` から、`WorkflowActions` トレイトに移動したプロパティとメソッドを削除しました。
    *   `Show.php` の `boot` メソッドから `WorkflowService` の依存性注入を削除しました。
    *   `tests/Feature/Livewire/Ledger/ShowTest.php` から、`Show.php` の責務ではなくなったテストケースを削除し、残りのテストがすべて成功することを確認しました。

### 5.4. 今後の進め方

*   **UIの動作確認:**
    *   ブラウザで実際のアプリケーションを起動し、`Ledger` の詳細画面におけるワークフロー関連のUI（ステータス表示、アクションボタン、履歴表示）が、リファクタリング後も期待通りに動作することを確認します。
    *   特に、承認、差し戻し、承認申請などのアクションが正しく実行され、トーストメッセージが表示されること、およびワークフローの状態が更新されることを確認します。

### 5.5. 過去の課題と解決策からの学び

(このセクションは、以前の知見を保持するために内容はそのまま残します)

#### 課題1: `mary-toast` イベントがテストで捕捉されない

*   **原因**: Livewireのテストフレームワークは、MaryUIの`Toast`トレイトが内部的に使用する`$this->js()`ヘルパーを介したJavaScriptイベントのディスパッチを直接捕捉できませんでした。
*   **解決策**: `app/Livewire/Ledger/WorkflowActionButtons.php` および `app/Livewire/Ledger/Show.php` 内の`$this->success()`や`$this->error()`の呼び出しを、Livewireのテストフレームワークが捕捉可能な`$this->dispatch('mary-toast', ...)`を明示的に呼び出すように修正しました。これにより、テストの信頼性が向上しました。

#### 課題2: `WorkflowService::approve`がテストで呼び出されない (`InvalidCountException`) および`Ledger`モデルのモックに関する`TypeError`

この課題は複数の要因が絡み合っており、段階的にデバッグと修正を行いました。

*   **初期の`TypeError`の原因**:
    *   `tests/Feature/Livewire/Ledger/WorkflowActionButtonsTest.php`において、`Ledger`モデルを`Mockery::spy`で過度にモックしたことが原因でした。Livewireのモデルハイドレーションの内部メカニズムが、モックされたオブジェクトのプロパティにアクセスする際に`TypeError`を引き起こしました。
    *   **解決策**: `Ledger`モデルのモックを止め、代わりに実際のEloquentモデルインスタンスとデータベースセットアップを使用するアプローチに戻しました。

*   **`WorkflowService::approve`が呼び出されない原因 (`InvalidCountException`)**:
    *   `app/Livewire/Ledger/WorkflowActionButtons.php`の`handleActionWithComment`メソッド内で、`$allInspectionsDone`と`$allApprovalsWillBeDone`の両方が`true`になる条件が満たされなかったため、`workflowService->approve`が実行されませんでした。
    *   **デバッグ過程**:
        *   `app/Models/Ledger.php`および`app/Livewire/Ledger/WorkflowActionButtons.php`に`Log::info()`ステートメントを追加し、`$allInspectionsDone`、`$allApprovalsWillBeDone`、および`$progress`配列の値を詳細に調査しました。
        *   ログの出力から、`Ledger`モデルの`getRequiredRolesProgressDetails()`メソッドが、`inspection.total_count: 1`（期待値は0）および`approval.total_count: 0`（期待値は1）という予期せぬ値を返していることが判明しました。特に、`inspection.total_roles`に`approver`ロールが誤って含まれていました。
    *   **根本原因**:
        *   `spatie/laravel-permission`の`BelongsToMany`リレーションシップ（`requiredInspectorRoles`および`requiredApproverRoles`）において、`attach()`メソッドを使用する際にピボットテーブルの`type`カラムを明示的に指定していなかったことが原因でした。リレーションシップ定義の`wherePivot('type', ...)`句は取得時のフィルタリングには機能しますが、`attach()`時にはデフォルト値を設定しません。このため、`$folder->requiredApproverRoles()->attach($approverRole->id);`の呼び出しでは`type`カラムが正しく設定されず、ロールが意図しない`type`で関連付けられたり、`requiredInspectorRoles`リレーションシップによって誤って取得されたりしていました。
    *   **最終解決策**:
        *   `tests/Feature/Livewire/Ledger/WorkflowActionButtonsTest.php`において、ロールをアタッチする際にピボットテーブルの`type`カラムを明示的に指定するように修正しました。具体的には、`$folder->requiredApproverRoles()->attach($approverRole->id, ['type' => 'approver']);`と記述しました。
        *   これにより、`getRequiredRolesProgressDetails()`が期待通りの値（`inspection.is_all_completed: true`、`approval.is_all_completed: true`）を返すようになり、`workflowService->approve`が正しく呼び出されるようになりました。

### 5.6. コンポーネント切り出し作業のテンプレート化にあたっての留意事項

(このセクションは、以前の知見を保持するために内容はそのまま残します)

1.  **LivewireテストとJavaScriptイベントの扱い**: `$this->js()`ヘルパーを介してディスパッチされるJavaScriptイベント（例: MaryUIのToast）は、Livewireのテストフレームワークでは直接捕捉できません。テストでこれらのイベントの発生を検証する必要がある場合は、コンポーネント側で`$this->dispatch('custom-event-name', ...)`のように明示的にLivewireイベントとしてディスパッチする実装を検討してください。これにより、テストの信頼性が向上します。

2.  **Eloquentモデルのテスト戦略**: LivewireコンポーネントにEloquentモデルをプロパティとして渡す場合、モデルの内部的なハイドレーションやリレーションシップの解決が複雑なため、モデル自体を厳密にモックする（特に`__construct`や`getAttribute`など）と、予期せぬ`TypeError`や`BadMethodCallException`が発生しやすくなります。最も堅牢なテスト戦略は以下の通りです。
    *   可能な限り**実際のEloquentモデルインスタンス**をテストに使用し、ファクトリやシーダーを用いてデータベースの状態をテストシナリオに合わせて正確に構築します。
    *   モデルの特定のメソッド（例: `getRequiredRolesProgressDetails()`）の戻り値を制御する必要がある場合でも、そのメソッドが依存するデータベースの状態を適切に設定することで、モックなしで期待通りの結果が得られるように努めます。これにより、テストが実際のアプリケーションの動作に近くなり、信頼性が高まります。
    *   外部サービス（例: `WorkflowService`）など、ビジネスロジックや外部システムとの連携を担うクラスは、引き続きモックの対象とします。

3.  **`BelongsToMany`リレーションシップの`attach()`におけるピボットデータの明示**: `wherePivot`句で特定のカラム（例: `type`）をフィルタリング条件として使用している`BelongsToMany`リレーションシップに対して`attach()`メソッドを使用する際は、そのピボットカラムの値を**必ず第二引数で明示的に指定**してください。これを怠ると、リレーションシップが正しく確立されず、データが意図しない形で保存されたり、関連するメソッド（例: `getRequiredRolesProgressDetails()`）が誤った結果を返したりする原因となります。
    *   例: `$folder->requiredApproverRoles()->attach($approverRole->id, ['type' => 'approver']);`

4.  **テストにおけるデータベース状態の厳密な管理**: `RefreshDatabase`トレイトの使用に加え、リレーションシップの変更（`attach`、`detach`など）を行った後は、必ず`$model->refresh()`を呼び出してモデルインスタンスをリロードし、最新のデータベース状態をテストコンテキストに反映させるようにしてください。これにより、テストが古いキャッシュデータや不整合なリレーションシップを参照することを防ぎます。

5.  **効果的なデバッグ手法**: 複雑な問題に直面した際は、以下のデバッグ手法を組み合わせることで、効率的に原因を特定できます。
    *   **`Log::info()`による詳細なログ出力**: 特に条件分岐の直前や、計算された値（例: `$allInspectionsDone`, `$allApprovalsWillBeDone`, `$progress`配列の内容）の確認に用いることで、コードの実行パスとデータの状態を追跡できます。本番環境のログを汚染しないよう、開発完了後に削除することを忘れないでください。
    *   **`dd()`による一時的な実行中断**: 特定の変数の状態を即座に確認したい場合に強力です。ただし、テスト実行を中断するため、問題の特定や特定の変数の状態を一時的に確認する際に限定的に使用し、コミット前に必ず削除してください。

LivewireのフィーチャーテストはUIの視覚的な欠損を直接検出できない限界があるため、今後は`assertDontSeeHtml()`などのアサーションを適切に活用し、UI要素が意図せず残存していないことを確認することで、リスクを軽減します。
