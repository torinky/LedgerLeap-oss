# ワークフロー（承認フロー）機能

## 概要

ワークフロー機能は、LedgerLeap における台帳レコードの新規登録や更新に対して、**段階的な確認・承認プロセス**を導入するための機能です。データの正確性やコンプライアンスを確保しつつ、業務プロセスをシステム上で完結させます。厳格な固定ルートではなく、運用に合わせた柔軟な承認者設定と、承認者の負担を軽減する通知体系を目指しています。**既存の台帳変更履歴** (`LedgerDiff`)、**Activity Log** (`spatie/laravel-activitylog`)、および**通知設定機能** (`RoleFolderPermission`) と連携し、ユーザーは通知の受け取りを細かく制御できます。

## 機能の目的と背景

*   **品質・正確性の担保:** 重要なデータの登録や変更に際し、複数段階（作成完了、点検、承認）のチェックを経ることで、品質を高めます。
*   **コンプライアンス対応:** 内部統制や規定プロセスに基づき、特定の操作に承認を必須とすることで、コンプライアンス要件を満たします。
*   **証跡の確保:** 「誰がいつどの段階を完了させ、誰がいつ承認、または編集によりステータスを戻したか」というプロセスと理由（任意）を **Activity Log に記録**し、追跡や監査を可能にします。各時点でのデータ内容は `LedgerDiff` にスナップショットとして保存されます。
*   **柔軟な運用:** 固定的・階層的な承認ルートだけでなく、案件ごとに担当者（点検者・承認者）を選択したり、ロールに対して依頼したりできます。
*   **通知の最適化と制御:** 確認・承認担当者への通知は個別の依頼ごとではなく、未処理件数をまとめて通知する形式を基本とし、通知疲れを防ぎます。ユーザーは通知の ON/OFF を設定できます。

## 制約と前提条件

*   **オンプレミス環境:** インターネット接続のない環境を想定。
*   **通知手段:** システム内通知（ヘッダーのバッジ表示、マイポータルへの表示）、ブラウザネイティブ通知（プッシュ通知）を主とし、メールは補助的。
*   **既存機能の活用:** **台帳変更履歴 (`LedgerDiff` - データスナップショット用)**、**Activity Log (プロセス履歴用)**、ロールベース権限、フォルダ別通知設定 (`RoleFolderPermission`) を活用。
*   **対象:** 現状、主に台帳レコード (`Ledger`) の新規登録・更新を対象とします。ファイルアップロード等への適用は今後の検討課題です。
*   **ワークフローの有効化:** ワークフローは全ての台帳で必須ではなく、**台帳定義 (`LedgerDefine`) ごとに有効/無効を設定**できます。(`ledger_defines.workflow_enabled` カラムで管理想定)。

## ワークフローの状態定義

本システムにおけるワークフローの**現在の状態**は、主に **`Ledger` テーブルの `status` カラム**で管理されます。`LedgerDiff` テーブルにも履歴としてステータスは記録されますが、最新の状態は `Ledger` が保持します。

*   `DRAFT` (作成中/編集中): レコード作成中または修正中の初期状態。編集権限があれば誰でも編集可能。
*   `PENDING_INSPECTION` (点検待ち): 作成者が「作成完了」し、点検者の確認待ち。編集権限があれば編集可能だが、保存時にステータスは `DRAFT` に戻る。
*   `PENDING_APPROVAL` (承認待ち): 点検者が「点検完了」し、最終承認者の確認待ち。編集権限があれば編集可能だが、保存時にステータスは `DRAFT` に戻る。
*   `APPROVED` (承認済み): 最終承認者が「承認」し、ワークフローが完了した状態。この状態のレコードは原則として編集不可（ロックされる）。

*(必要に応じて `INSPECTED` (点検完了) 状態も定義可能)*

## 機能詳細

### 1. ワークフローの有効化設定

*   台帳定義 (`LedgerDefine`) の作成・編集時に、その定義に基づく台帳でワークフローを有効にするか (`workflow_enabled`) を設定します。
*   ワークフローが無効な台帳定義の場合、以降のワークフロー関連の操作（ボタン表示、ステータス変更など）は行われません（通常の直接保存）。

### 2. ワークフローの開始と進行 (状態遷移)

*   **作成/編集 (`DRAFT`):** ユーザーは台帳レコードを作成・編集します。`Ledger` レコードの `status` は `DRAFT` です。
*   **作成完了/点検依頼:** `DRAFT` 状態から**「作成完了（点検依頼）」**ボタンをクリックします。
    *   次の担当者（点検者）を選択または確認します。
    *   **`LedgerDiff` に現在のデータ内容のスナップショットを記録**します。
    *   **`Activity Log` に `inspection_requested` イベントを記録**します (実行者、対象LedgerDiff、次担当者ID、コメント等をプロパティに含める)。
    *   **`Ledger` テーブルの `status` を `PENDING_INSPECTION` に更新**し、最新の `LedgerDiff` ID (`latest_diff_id`)、内容プレビュー (`preview_content`) を更新します。
    *   点検者の未処理件数カウンターがインクリメントされます。
*   **点検完了/承認申請:** `PENDING_INSPECTION` 状態から点検者が**「点検完了（承認申請）」**ボタンをクリックします。
    *   次の担当者（承認者）を選択または確認します。
    *   **`Activity Log` に `inspection_completed` イベントを記録**します (実行者、対象LedgerDiff、次担当者ID、コメント等をプロパティに含める)。
    *   **`Ledger` テーブルの `status` を `PENDING_APPROVAL` に更新**します。
    *   点検者のカウンターはデクリメントされ、承認者のカウンターがインクリメントされます。
*   **承認:** `PENDING_APPROVAL` 状態から承認者が**「承認」ボタン**をクリックします。
    *   **`Activity Log` に `approved` イベントを記録**します (実行者、対象LedgerDiff、コメント等をプロパティに含める)。
    *   **最新の `LedgerDiff` の内容を `Ledger` テーブルに反映**させます (`content`, `content_attached`, `version` 更新)。
    *   **`Ledger` テーブルの `status` を `APPROVED` に更新**します。
    *   承認者のカウンターはデクリメントされます。
    *   (オプション) 申請者等に承認完了通知が送信されます（通知設定ONの場合）。

### 3. 承認フロー中のレコード編集 (重要)

*   **編集権限:** ワークフローの状態が `DRAFT`, `PENDING_INSPECTION`, `PENDING_APPROVAL` のいずれであっても、そのレコードに対する編集権限を持つユーザーは、レコード内容を編集することが可能です。
*   **編集時の確認:** レコードを編集し、「保存」ボタンをクリックしようとすると、確認ダイアログが表示されます。
*   **理由入力 (任意推奨):** 確認ダイアログで「はい」を選択すると、理由入力フィールドが表示されることが望ましいです。
*   **保存時の処理:** 理由入力後（または入力せず）に確定すると、以下の処理が行われます。
    1.  **`LedgerDiff` に編集後の内容のスナップショットを新規作成**します。
    2.  **`Activity Log` に `edited_while_pending` (または `draft_saved`) イベントを記録**します (実行者、対象LedgerDiff、理由コメント等をプロパティに含める)。
    3.  **`Ledger` テーブルの `status` を `DRAFT` に更新**し、`latest_diff_id` と `preview_content` も更新します。
    4.  **通知:** 関係者にステータスが `DRAFT` に戻ったことを通知します（通知設定ONの場合）。
    5.  カウンターの調整：必要に応じて点検者/承認者のカウンターをデクリメントします。

### 4. 担当者（点検者/承認者）のアクション

担当者は、自分宛ての未処理タスク (`PENDING_INSPECTION` または `PENDING_APPROVAL`) に対して、主に以下の操作を行います。

*   **内容確認:** 最新の `LedgerDiff` レコードの内容を確認します。
*   **次のステップへ進める:** 「点検完了（承認申請）」または「承認」ボタンをクリックします。
*   **修正が必要な場合:** レコードを**直接編集**し、保存することでステータスを `DRAFT` に戻します（理由入力推奨）。または、別途コミュニケーションをとります。

### 5. 承認済みレコードの扱い

*   `APPROVED` 状態 (`Ledger.status`) のレコードは、原則として**編集がロック**されます。

### 6. ワークフローステータスと履歴 (証跡)

*   現在のレコードの状態は `Ledger.status` で確認できます。
*   各時点でのデータ内容は `LedgerDiff` テーブルの履歴で確認できます。
*   **ワークフローのプロセス履歴（誰がいつ、何のアクションを、どんな理由で実行したか）は、`LedgerDiff` に紐づく `Activity Log` を参照**することで時系列で追跡可能です。
*   台帳の詳細画面や変更履歴画面で、Activity Log を活用してこれらの履歴を表示します。

### 7. 柔軟な承認ルート

*   **推奨担当者:** `LedgerDefine` に設定可能。
*   **申請時指定:** 申請者は推奨担当者を変更、または直接ユーザー/ロールを選択可能。選択された担当者情報は **`Activity Log` の `properties`** に記録するか、あるいは **`LedgerDiff` の最新レコードに `inspector_id`/`approver_id` を保持**します（**要検討: 次の担当者情報の保持場所**）。
*   **ロール指定承認:** ロール指定時の詳細な挙動は要検討。

### 8. 承認待ちタスクの確認と集約通知

*   **承認待ちリスト:** `Ledger` テーブルの `status` が `PENDING_INSPECTION` または `PENDING_APPROVAL` であり、かつ**最新の Activity Log または `LedgerDiff` に記録された担当者**が自分であるレコードをリスト表示します。（**要検討: リスト取得ロジックの複雑化**）
*   **集約通知:** 定期バッチで、未処理タスクを持つ担当者に対し通知（通知設定ONの場合）。

### 9. 通知設定との連携

*   **ワークフロー関連通知タイプ:** `notification_types` テーブルに `inspection_requested`, `approval_requested`, `inspection_completed`, `approved`, `status_returned_to_draft`, `workflow_summary` などを追加。
*   **ユーザーによる制御:** フォルダ別通知設定で ON/OFF を設定可能。
*   **通知送信時の確認:** `NotificationService` は、**Activity Log のイベントをトリガー**にするか、`WorkflowService` から明示的に呼び出され、通知設定を確認して送信。

### 10. ワークフローと台帳定義の変更

*   `Ledger` の `status` が `PENDING_INSPECTION` または `PENDING_APPROVAL` のレコードが存在する場合、関連する `LedgerDefine` の列構成変更は禁止されます。

### 11. ワークフロー中のレコードのリスト表示

*   `Ledger` テーブルの全レコードを表示。
*   `Ledger.status` に基づいてバッジ表示・スタイル変更。
*   未承認レコードの内容は `Ledger.preview_content` (最新Diffのコピー) またはリレーション経由で表示。

## 関連ファイル (想定)

* **モデル:**
    * `App\Models\LedgerDiff`: カラム追加 (`status`, `inspector_id`, `approver_id`, `requested_at`, `inspected_at`,
      `approved_at`, `comments`) が必要。
    * `App\Models\Ledger`: `status` (Enum), `version` カラム追加済み。`isLocked()` メソッドあり。
    * `App\Models\LedgerDefine`: `workflow_enabled` (boolean), `version`, 推奨担当者カラム追加済み。
    * `App\Models\NotificationType`: ワークフロー用タイプ追加が必要。
* **Enum:**
    * `App\Enums\WorkflowStatus`: (`DRAFT`, `PENDING_INSPECTION`, `PENDING_APPROVAL`, `APPROVED`) 作成済み。
* **サービス:**
    * `App\Services\WorkflowService`: 状態遷移ロジック、編集時巻き戻し処理、カウンター管理、通知トリガー。
    * `App\Services\NotificationService`: ワークフロー通知送信ロジック追加。
* **リポジトリ:**
    * `App\Repositories\WorkflowTaskRepository` (新規作成推奨): 点検/承認待ちリスト取得。
* **コントローラー/Livewire:**
    * `App\Livewire\Ledger\CreateColumn`, `App\Livewire\Ledger\ModifyColumn`: ワークフロー有効チェック、状態遷移ボタン表示制御、担当者選択
      UI、編集時警告/理由入力、保存/申請処理。
    * `App\Livewire\Workflow\PendingList` (新規作成): 承認待ちリスト表示。
    * `App\Livewire\Workflow\ActionButtons` (新規作成 or PendingList内): 「点検完了」「承認」ボタン処理。
* **通知クラス:** `InspectionRequested`, `ApprovalRequested` など新規作成が必要。
* **コマンド:** `App\Console\Commands\SendWorkflowSummaryNotification` 新規作成が必要。
* **Filament (関連):**
    * `App\Filament\Resources\LedgerDefineResource`: `workflow_enabled` 設定 UI、列構成変更時のワークフローチェック追加。
    * `App\Filament\Resources\RoleResource\RelationManagers\NotificationSettingsRelationManager`: 通知タイプ選択肢追加。
* **マイグレーション:** `ledger_diffs`, `ledgers`, `ledger_defines` テーブルの修正。`notification_types` へのレコード追加。

## 実装計画（案）

以下は、本ワークフロー機能を段階的に実装するためのステップ計画案です。各ステップで動作する機能範囲を定義し、このドキュメントも更新していくことを想定しています。**履歴管理には Activity Log を活用し、最新のデータは `Ledgers` テーブルに直接保存します。**

---

### ✅ ステップ 0: 準備 (完了)

* **目的:** 実装に必要な基本的な要素を準備する。
* **実施済みタスク:**
    1. **Enum 作成:** `app/Enums/WorkflowStatus.php` を作成 (`DRAFT`, `PENDING_INSPECTION`, `PENDING_APPROVAL`,
       `APPROVED` を定義)。 [完了]
    2. **マイグレーション編集:** `ledger_diffs` テーブルにワークフロー関連カラム (`status`, `inspector_id`,
       `approver_id`, `requested_at`, `inspected_at`, `approved_at`, `returned_at`, `comments`) を追加するよう
       `create_ledger_diffs_table.php` を編集。 [完了]
    3. **モデル更新 (`LedgerDiff`):** `LedgerDiff` モデルに新しいカラムを `$fillable`, `$casts` に追加。`inspector()`,
       `approver()` リレーションを追加。 [完了]
    4. **テーブル定義 & モデル更新 (`Ledger`):** `Ledger` テーブルに `status` (Enum) と `version` カラムを追加するよう
       `create_ledgers_table.php` を編集。`Ledger` モデルの `$fillable`, `$casts` を更新し、`isLocked()` ヘルパー、
       `latestDiff()` リレーションを追加。 [完了]
    5. **テーブル定義 & モデル更新 (`LedgerDefine`):** `LedgerDefine` テーブルに `version` と推奨担当者関連カラム (
       `recommended_inspector_id`, `recommended_approver_id`, `recommended_inspector_role_id`,
       `recommended_approver_role_id`) を追加するよう `create_ledger_defines_table.php` を編集。`LedgerDefine` モデルの
       `$fillable` を更新し、関連リレーションを追加。 [完了]
    6. **サービス/リポジトリ作成 (雛形):** `WorkflowService`, `WorkflowTaskRepository` のクラスファイルを作成。 [完了]
    7. **DB再構築:** `php artisan migrate:fresh --seed` を実行し、スキーマ変更を適用。 [完了]
* **成果物:** ワークフローのデータを格納する基本的なDB構造とモデルの準備完了。
* **ドキュメント更新:** このセクションを更新。

---

### ✅ ステップ 1: ワークフロー開始（作成完了/点検依頼）機能の実装 (完了)

* **目的:** ユーザーが台帳レコードを作成/編集し、「作成完了（点検依頼）」として最初の承認ステップに進められるようにする (*
  *ワークフローが有効な台帳定義の場合を前提とする**)。
* **実施済みタスク:**
    1. **UI変更 (`ModifyColumn`等):** 「下書き保存」ボタン、「作成完了（点検依頼）」ボタン、点検者選択 UI (推奨担当者表示含む)
       を追加。 [完了]
    2. **ロジック実装 (`ModifyColumn`, `WorkflowService`):**
        * 「下書き保存」: `LedgerDiff` に `status='DRAFT'` で保存 (`Ledger` は DRAFT で作成/更新)。 [完了]
        * 「作成完了（点検依頼）」: 点検者選択を検証し、`WorkflowService` を呼び出し。`LedgerDiff` に
          `status='PENDING_INSPECTION'`, `inspector_id`, `requested_at` を記録 (`Ledger` の status も更新)
          。カウンター更新呼び出しポイントをコメントで明記。 [完了]
* **動作確認:**
    * 下書き保存、点検依頼の動作（`LedgerDiff` と `Ledger` のステータス変更、`requested_at` 記録）を確認。 [完了]
* **成果物:** ワークフローを開始し、最初の「点検待ち」状態に遷移できる基本機能（ワークフロー有効時）。
* **ドキュメント更新:** このセクションを更新。

---

### ✅ ステップ 2: 点検・承認機能（基本形）とデータ反映 (完了)

*   **目的:** 点検者/承認者がタスクを確認し、「点検完了（承認申請）」または「承認」を行い、最終的にデータが `Ledger` テーブルに反映されるようにする（ワークフロー有効時）。リスト表示でステータスを確認できるようにする。
*   **実施済みタスク:**
    1.  **UI作成 (`PendingList`, `ActionButtons`):** 承認待ちリスト (`/workflow/pending`) を表示する Livewire コンポーネント (`PendingList`) を作成。リストには申請者、申請日時、台帳名、ステータス等を表示。各行にアクションボタン（「点検完了(承認申請)」、「承認」、「作成中に戻す」）を、ユーザーの役割とタスクのステータスに応じて表示。承認申請時には承認者選択モーダルを表示。[完了]
    2.  **ロジック実装 (`ActionButtons`, `WorkflowService`):**
        *   「点検完了（承認申請）」(`WorkflowService::requestApproval`): 承認者を選択・検証後、`LedgerDiff` の `status` を `PENDING_APPROVAL` に更新、`approver_id`, `inspected_at`, `modifier_id` を記録。カウンター調整とイベント発行の呼び出しポイントをコメントで明記。[完了]
        *   「承認」(`WorkflowService::approve`): `LedgerDiff` の `status` を `APPROVED` に更新、`approved_at`, `modifier_id` を記録。**`Ledger` レコードを `LedgerDiff` の内容で作成/更新し、`Ledger.status` も `APPROVED` に更新、`version` をインクリメントする処理を実装。** カウンター調整とイベント発行の呼び出しポイントをコメントで明記。[完了]
        *   「作成中に戻す」(`WorkflowService::returnToDraft`): 理由入力後、`LedgerDiff` と `Ledger` の `status` を `DRAFT` に更新、`returned_at`, `comments`, `modifier_id` を記録。カウンター調整とイベント発行の呼び出しポイントをコメントで明記。[完了]
        *   上記アクションに対応する Livewire メソッド (`PendingList::requestApproval`, `approveTask`, `returnTaskToDraft`) を実装し、Service を呼び出し、Toast 表示とリスト更新 (`$refresh`) を行う。[完了]
    3.  **台帳リスト表示改善:** 台帳リスト表示画面 (`RecordsTable` コンポーネントと関連 Blade) を修正。
        *   テーブルヘッダーに「ステータス」列を追加。[完了]
        *   各レコード行に現在の `Ledger.status` を示すバッジ (`WorkflowStatus::label()`, `colorClass()` 使用) を表示。[完了]
        *   `APPROVED` 以外のステータスの行を視覚的に区別（例: `opacity-70`）。[完了]
        *   `APPROVED` 状態のレコードの編集ボタンを無効化 (`Ledger::isLocked()` 使用)。[完了]
*   **動作確認:**
    *   点検/承認アクションでステータスが遷移し、`LedgerDiff` と `Ledger` の関連カラムが更新されることを確認。[完了]
    *   承認時に `Ledger` データが正しく反映されることを確認。[完了]
    *   台帳リストで各レコードのステータスが表示され、見た目で区別できること、承認済みレコードの編集がロックされることを確認。[完了]
    *   「作成中に戻す」アクションが機能し、ステータスと履歴が記録されることを確認。[完了]
*   **成果物:** ワークフロー有効時の点検・承認・データ反映、および台帳リストでのステータス表示機能。基本的なワークフローサイクルが動作する状態。
*   **ドキュメント更新:** このセクションを更新。「機能詳細(担当者のアクション、リスト表示)」「関連ファイル」に実装内容を反映。`PendingList` 等の新コンポーネントに関するドキュメント作成（または既存ドキュメントへの追記）。

---

### ステップ 3: Ledgerテーブルへの下書き/フロー中データ保存（方針D実装）(Next)

*   **目的:** ワークフローのどの段階でも、**`Ledgers` テーブルに常に最新のデータ (`content`, `content_attached`) を保存する**ように変更し、リストや詳細表示のロジックを簡素化する。
*   **タスク:**
    1.  **`WorkflowService::saveDraft` 修正:** `LedgerDiff` 作成と**同時に**、`Ledger` レコードの `content`, `content_attached`, `modifier_id`, `version` を**常に更新**するように変更。`status` は `DRAFT`。
    2.  **`WorkflowService::requestInspection` 修正:** `LedgerDiff` 作成と**同時に**、`Ledger` レコードの `content`, `content_attached`, `modifier_id`, `version` を**更新**。`status` は `PENDING_INSPECTION`。
    3.  **`WorkflowService::requestApproval` 修正:** `LedgerDiff` 更新と**同時に**、`Ledger` レコードの `content`, `content_attached`, `modifier_id`, `version` を**更新**。`status` は `PENDING_APPROVAL`。
    4.  **`WorkflowService::approve` 修正:** `LedgerDiff` 更新後、`Ledger` レコードの `status` を `APPROVED` に、`version` をインクリメントする**だけで良い**（`content` 等は既に最新のはず）。
    5.  **`WorkflowService::returnToDraft` 修正:** `LedgerDiff` 更新と**同時に**、`Ledger` レコードの `status` を `DRAFT` に更新。
    6.  **(新規タスク) 編集によるステータス巻き戻し処理 (`WorkflowService` / `ModifyColumn`):**
        *   UI: 編集時の警告ダイアログ、理由入力(任意)。
        *   ロジック: 保存時に警告・理由入力後、**新規 `LedgerDiff` を作成**。**`Ledger` の `status` を `DRAFT` に、`content`, `content_attached`, `version` 等も更新**。カウンター調整呼び出しポイント、通知トリガーをコメントで明記。
    7.  **リスト表示/詳細表示のロジック簡素化:** `RecordsTable` や `ledger.show` (想定) で、常に `Ledger` モデルの `content`, `content_attached` を参照するように修正（`latestDiff` を見る必要がなくなる）。ステータスバッジ表示と行スタイル変更は維持。
    8.  **`APPROVED` レコードのロック実装:** `Ledger.status === APPROVED` の場合に編集不可とする処理を `ModifyColumn` などに追加（ステップ2で実施済みか再確認）。
*   **動作確認:**
    *   下書き保存、点検依頼、承認申請、承認、ステータス戻し、編集中保存の各操作で、`Ledger` テーブルの `content`, `status`, `version` 等が常に最新の状態に更新されることを確認。
    *   台帳リストや詳細画面で、どのステータスでも最新の内容が表示されることを確認。
    *   承認済みレコードがロックされていることを確認。
*   **ドキュメント更新:** 「機能詳細」セクション全体を方針Dに合わせて修正。「関連ファイル」の `Ledger` の役割を更新。

---

### ステップ 4: Activity Log によるプロセス履歴記録

*   **目的:** ワークフローの各アクション（申請、完了、承認、戻し、編集）の履歴を Activity Log に記録し、後から追跡できるようにする。
*   **タスク:**
    1.  **`LedgerDiff` モデルへの設定:** `LogsActivity` Trait、`getActivitylogOptions()` を設定し、ログ対象に `status`, `inspector_id`, `approver_id`, `comments` 等を含める。
    2.  **`WorkflowService` でのカスタムイベント記録:** 各アクションメソッド (`requestInspection`, `requestApproval`, `approve`, `returnToDraft`, および編集時保存処理) の最後で、`activity()->performedOn($ledgerDiff)->causedBy($user)->withProperties([...])->log('イベント名')` を使ってカスタムログを記録する。`properties` には理由コメントや次の担当者IDなど、必要な情報を格納する。
    3.  **履歴表示 UI:** 台帳詳細画面や変更履歴画面で、`$ledgerDiff->activities` (または `$ledger->activities`?) を取得し、記録されたイベント、実行者、日時、プロパティ（コメント等）を時系列で表示する UI を作成する。
*   **動作確認:**
    *   各ワークフローアクションを実行した際に、対応する Activity Log が `LedgerDiff` (または `Ledger`) に紐づいて記録されることを確認。
    *   履歴表示 UI でプロセス履歴が正しく表示されることを確認。
*   **ドキュメント更新:** 「証跡の確保」「ワークフローステータスと履歴」「関連ファイル」セクションを Activity Log 活用方針に更新。

---

### ステップ 5: ワークフロー有効化制御と定義変更制限 (旧ステップ2.1)

*   **目的:** 台帳定義の設定に基づきワークフローの有効/無効を制御し、ワークフロー進行中の定義変更を制限する。
*   **タスク:**
    1.  **ワークフロー有効/無効分岐処理:** `CreateColumn`/`ModifyColumn` で `LedgerDefine.workflow_enabled` をチェックし、UI（表示ボタン）と保存時の処理（ワークフロー経由か直接保存か）を分岐させる。
    2.  **直接保存ロジック:** ワークフロー無効時の「保存」ボタンクリック時に、直接 `Ledger` レコードを作成/更新する処理を実装（`LedgerDiff` や Activity Log は記録しない）。`Ledger.status` は常に `APPROVED` とする。
    3.  **台帳定義変更制限:** `LedgerDefineResource` (Filament) の保存・削除処理前フック等で、関連する `Ledger` の `status` が `PENDING_INSPECTION` または `PENDING_APPROVAL` でないかチェックし、存在する場合は変更/削除をブロックするロジックを追加。
*   **動作確認:**
    *   ワークフロー有効/無効設定に応じたUI・保存動作の分岐。
    *   ワークフロー無効時は直接保存され、ステータスが `APPROVED` になること。
    *   ワークフロー進行中に台帳定義の列構成を変更しようとするとエラーになること。
*   **ドキュメント更新:** 「機能詳細(ワークフロー有効化設定、台帳定義変更制限)」「関連ファイル」更新。

---

### ステップ 6: 通知機能の実装 (旧ステップ4)

*   **目的:** ワークフローに関連する通知（集約・個別）を実装する。
*   **タスク:**
    1.  `notification_types` テーブルにレコード追加。
    2.  Filament UI 更新 (`NotificationSettingsRelationManager`)。
    3.  未処理カウンター実装（DB or Cache）。`WorkflowService` (またはイベントリスナー/Observer) で増減処理。
    4.  `NotificationService` 拡張（ワークフロー用通知メソッド、設定確認ロジック。Activity Log イベントをリッスンして通知をトリガーすることも検討）。
    5.  集約通知コマンド作成 (`SendWorkflowSummaryNotification`) と Kernel 登録。
    6.  システム内通知 UI 実装（ヘッダーバッジ、マイポータル表示）。
    7.  (オプション) ブラウザ通知実装。
*   **動作確認:** カウンター増減、各種通知送信、通知設定反映を確認。
*   **ドキュメント更新:** 通知関連セクション更新。トリガー（イベント/サービス呼び出し/リスナー）について追記。

---

### ステップ 7: 柔軟な承認ルート機能の実装 (旧ステップ5)

*   **目的:** 承認者/点検者をより柔軟に設定できるようにする。担当者情報を Activity Log に記録。
*   **タスク:**
    1.  (オプション) 推奨担当者設定機能。
    2.  担当者選択 UI 改善（ロール指定含む）。
    3.  ロジック実装（選択された担当者情報を Activity Log の properties に記録。ロール指定時の処理調整 - 担当者特定方法）。
    4.  承認待ちリスト取得ロジック (`WorkflowTaskRepository`) 修正: `Ledger.status` と 最新の関連 Activity Log (`inspection_requested`, `inspection_completed`) を参照して担当者を特定し、リストを取得するように変更。
*   **動作確認:** 推奨担当者表示、担当者変更、ロール指定時の動作（リスト表示、通知など）を確認。
*   **ドキュメント更新:** 「柔軟な承認ルート」セクション更新。担当者情報の記録場所とリスト取得方法について追記。

---
## 今後の展望 / 要検討事項
*   **要検討事項:**
    *   ~~新規作成時の `Ledger` レコードの扱い~~ → **方針D (`DRAFT` で作成し、常に最新データを反映) で確定。**
    *   承認済み (`APPROVED`) レコードの変更・編集解除プロセス。
    *   ロール指定承認の詳細な挙動。
    *   担当者選択 UI のコンポーネント選定。
    *   通知カウンターの実装方法。
    *   ブラウザ通知の実装方針。
    *   点検ステップが不要な場合のワークフロー。
    *   **Activity Log のパフォーマンス:** 特に履歴表示、担当者特定クエリ。
    *   ~~担当者情報の保持場所~~ → **Activity Log の properties を主とする方針。** (ただし、リスト取得効率化のために `Ledger` に最新担当者を冗長的に持つことも検討可)
*   ファイルアップロード等へのワークフロー適用。
*   承認ルートの高度化。
*   期限と督促機能。
*   代理機能。
