# ワークフロー（承認フロー）機能

## 概要

ワークフロー機能は、LedgerLeap における台帳レコードの新規登録や更新に対して、**段階的な確認・承認プロセス**
を導入するための機能です。データの正確性やコンプライアンスを確保しつつ、業務プロセスをシステム上で完結させます。厳格な固定ルートではなく、運用に合わせた柔軟な承認者設定と、承認者の負担を軽減する通知体系を目指しています。
**既存の台帳変更履歴** (`LedgerDiff`)、**Activity Log** (`spatie/laravel-activitylog`)、および**通知設定機能** (
`RoleFolderPermission`) と連携し、ユーザーは通知の受け取りを細かく制御できます。

## 機能の目的と背景

* **品質・正確性の担保:** 重要なデータの登録や変更に際し、複数段階（作成完了、点検、承認）のチェックを経ることで、品質を高めます。
* **コンプライアンス対応:** 内部統制や規定プロセスに基づき、特定の操作に承認を必須とすることで、コンプライアンス要件を満たします。
* **証跡の確保:** 「誰がいつどの段階を完了させ、誰がいつ承認、または編集によりステータスを戻したか」というプロセスと理由（任意）を
  **Activity Log に記録**し、追跡や監査を可能にします。各時点でのデータ内容は `LedgerDiff` にスナップショットとして保存されます。
* **柔軟な運用:** 固定的・階層的な承認ルートだけでなく、案件ごとに担当者（点検者・承認者）を選択したり、ロールに対して依頼したりできます。
* **通知の最適化と制御:** 確認・承認担当者への通知は個別の依頼ごとではなく、未処理件数をまとめて通知する形式を基本とし、通知疲れを防ぎます。ユーザーは通知の
  ON/OFF を設定できます。

## 制約と前提条件

* **オンプレミス環境:** インターネット接続のない環境を想定。
* **通知手段:** システム内通知（ヘッダーのバッジ表示、マイポータルへの表示）、ブラウザネイティブ通知（プッシュ通知）を主とし、メールは補助的。
* **既存機能の活用:** **台帳変更履歴 (`LedgerDiff` - データスナップショット用)**、**Activity Log (プロセス履歴用)**
  、ロールベース権限、フォルダ別通知設定 (`RoleFolderPermission`) を活用。
* **対象:** 現状、主に台帳レコード (`Ledger`) の新規登録・更新を対象とします。ファイルアップロード等への適用は今後の検討課題です。
* **ワークフローの有効化:** ワークフローは全ての台帳で必須ではなく、**台帳定義 (`LedgerDefine`) ごとに有効/無効を設定**
  できます。(`ledger_defines.workflow_enabled` カラムで管理想定)。

## ワークフローの状態定義

本システムにおけるワークフローの**現在の状態**は、主に **`Ledger` テーブルの `status` カラム**で管理されます。`LedgerDiff`
テーブルにも履歴としてステータスは記録されますが、最新の状態は `Ledger` が保持します。

* `DRAFT` (作成中/編集中): レコード作成中または修正中の初期状態。編集権限があれば誰でも編集可能。
* `PENDING_INSPECTION` (点検待ち): 作成者が「作成完了」し、点検者の確認待ち。編集権限があれば編集可能だが、保存時にステータスは
  `DRAFT` に戻る。
* `PENDING_APPROVAL` (承認待ち): 点検者が「点検完了」し、最終承認者の確認待ち。編集権限があれば編集可能だが、保存時にステータスは
  `DRAFT` に戻る。
* `APPROVED` (承認済み): 最終承認者が「承認」し、ワークフローが完了した状態。この状態のレコードは原則として編集不可（ロックされる）。

*(必要に応じて `INSPECTED` (点検完了) 状態も定義可能)*

## 機能詳細

### 1. ワークフローの有効化設定

* 台帳定義 (`LedgerDefine`) の作成・編集時に、その定義に基づく台帳でワークフローを有効にするか (`workflow_enabled`)
  を設定します。
* ワークフローが無効な台帳定義の場合、以降のワークフロー関連の操作（ボタン表示、ステータス変更など）は行われません（通常の直接保存）。

### 2. ワークフローの開始と進行 (状態遷移)

* **作成/編集 (`DRAFT`):** ユーザーは台帳レコードを作成・編集します。`Ledger` レコードの `status` は `DRAFT` です。
* **作成完了/点検依頼:** `DRAFT` 状態から**「作成完了（点検依頼）」**ボタンをクリックします。
    * 次の担当者（点検者）を選択または確認します。
    * **`LedgerDiff` に現在のデータ内容のスナップショットを記録**します。
    * **`Activity Log` に `inspection_requested` イベントを記録**します (
      実行者、対象LedgerDiff、次担当者ID、コメント等をプロパティに含める)。
    * **`Ledger` テーブルの `status` を `PENDING_INSPECTION` に更新**し、最新の `LedgerDiff` ID (`latest_diff_id`)
      、内容プレビュー (`preview_content`) を更新します。
    * 点検者の未処理件数カウンターがインクリメントされます。
* **点検完了/承認申請:** `PENDING_INSPECTION` 状態から点検者が**「点検完了（承認申請）」**ボタンをクリックします。
    * 次の担当者（承認者）を選択または確認します。
    * **`Activity Log` に `inspection_completed` イベントを記録**します (
      実行者、対象LedgerDiff、次担当者ID、コメント等をプロパティに含める)。
    * **`Ledger` テーブルの `status` を `PENDING_APPROVAL` に更新**します。
    * 点検者のカウンターはデクリメントされ、承認者のカウンターがインクリメントされます。
* **承認:** `PENDING_APPROVAL` 状態から承認者が**「承認」ボタン**をクリックします。
    * **`Activity Log` に `approved` イベントを記録**します (実行者、対象LedgerDiff、コメント等をプロパティに含める)。
    * **最新の `LedgerDiff` の内容を `Ledger` テーブルに反映**させます (`content`, `content_attached`, `version` 更新)。
    * **`Ledger` テーブルの `status` を `APPROVED` に更新**します。
    * 承認者のカウンターはデクリメントされます。
    * (オプション) 申請者等に承認完了通知が送信されます（通知設定ONの場合）。

### 3. 承認フロー中のレコード編集 (重要)

* **編集権限:** ワークフローの状態が `DRAFT`, `PENDING_INSPECTION`, `PENDING_APPROVAL`
  のいずれであっても、そのレコードに対する編集権限を持つユーザーは、レコード内容を編集することが可能です。
* **編集時の確認:** レコードを編集し、「保存」ボタンをクリックしようとすると、確認ダイアログが表示されます。
* **理由入力 (任意推奨):** 確認ダイアログで「はい」を選択すると、理由入力フィールドが表示されることが望ましいです。
* **保存時の処理:** 理由入力後（または入力せず）に確定すると、以下の処理が行われます。
    1. **`LedgerDiff` に編集後の内容のスナップショットを新規作成**します。
    2. **`Activity Log` に `edited_while_pending` (または `draft_saved`) イベントを記録**します (
       実行者、対象LedgerDiff、理由コメント等をプロパティに含める)。
    3. **`Ledger` テーブルの `status` を `DRAFT` に更新**し、`latest_diff_id` と `preview_content` も更新します。
    4. **通知:** 関係者にステータスが `DRAFT` に戻ったことを通知します（通知設定ONの場合）。
    5. カウンターの調整：必要に応じて点検者/承認者のカウンターをデクリメントします。

### 4. 担当者（点検者/承認者）のアクション

担当者は、自分宛ての未処理タスク (`PENDING_INSPECTION` または `PENDING_APPROVAL`) に対して、主に以下の操作を行います。

* **内容確認:** 最新の `LedgerDiff` レコードの内容を確認します。
* **次のステップへ進める:** 「点検完了（承認申請）」または「承認」ボタンをクリックします。
* **修正が必要な場合:** レコードを**直接編集**し、保存することでステータスを `DRAFT` に戻します（理由入力推奨）。または、別途コミュニケーションをとります。

### 5. 承認済みレコードの扱い

* `APPROVED` 状態 (`Ledger.status`) のレコードは、原則として**編集がロック**されます。

### 6. ワークフローステータスと履歴 (証跡)

* 現在のレコードの状態は `Ledger.status` で確認できます。
* 各時点でのデータ内容は `LedgerDiff` テーブルの履歴で確認できます。
* **ワークフローのプロセス履歴（誰がいつ、何のアクションを、どんな理由で実行したか）は、`LedgerDiff` に紐づく `Activity Log`
  を参照**することで時系列で追跡可能です。
* 台帳の詳細画面や変更履歴画面で、Activity Log を活用してこれらの履歴を表示します。

### 7. 柔軟な承認ルート

* **推奨担当者:** `LedgerDefine` に設定可能。
* **申請時指定:** 申請者は推奨担当者を変更、または直接ユーザー/ロールを選択可能。選択された担当者情報は **`Activity Log`
  の `properties`** に記録するか、あるいは **`LedgerDiff` の最新レコードに `inspector_id`/`approver_id` を保持**します（*
  *要検討: 次の担当者情報の保持場所**）。
* **ロール指定承認:** ロール指定時の詳細な挙動は要検討。

### 8. 承認待ちタスクの確認と集約通知

* **承認待ちリスト:** `Ledger` テーブルの `status` が `PENDING_INSPECTION` または `PENDING_APPROVAL` であり、かつ**最新の
  Activity Log または `LedgerDiff` に記録された担当者**が自分であるレコードをリスト表示します。（**要検討: リスト取得ロジックの複雑化
  **）
* **集約通知:** 定期バッチで、未処理タスクを持つ担当者に対し通知（通知設定ONの場合）。

### 9. 通知設定との連携

* **ワークフロー関連通知タイプ:** `notification_types` テーブルに `inspection_requested`, `approval_requested`,
  `inspection_completed`, `approved`, `status_returned_to_draft`, `workflow_summary` などを追加。
* **ユーザーによる制御:** フォルダ別通知設定で ON/OFF を設定可能。
* **通知送信時の確認:** `NotificationService` は、**Activity Log のイベントをトリガー**にするか、`WorkflowService`
  から明示的に呼び出され、通知設定を確認して送信。

### 10. ワークフローと台帳定義の変更

* `Ledger` の `status` が `PENDING_INSPECTION` または `PENDING_APPROVAL` のレコードが存在する場合、関連する
  `LedgerDefine` の列構成変更は禁止されます。

### 11. ワークフロー中のレコードのリスト表示

* `Ledger` テーブルの全レコードを表示。
* `Ledger.status` に基づいてバッジ表示・スタイル変更。
* 未承認レコードの内容は `Ledger.preview_content` (最新Diffのコピー) またはリレーション経由で表示。

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

---

## 実装計画（案）

以下は、本ワークフロー機能を段階的に実装するためのステップ計画案です。各ステップで動作する機能範囲を定義し、このドキュメントも更新していくことを想定しています。
**履歴管理には Activity Log を活用し、最新のデータは `Ledgers` テーブルに直接保存します。**

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

* **目的:** 点検者/承認者がタスクを確認し、「点検完了（承認申請）」または「承認」を行い、最終的にデータが `Ledger`
  テーブルに反映されるようにする（ワークフロー有効時）。リスト表示でステータスを確認できるようにする。
* **実施済みタスク:**
    1. **UI作成 (`PendingList`, `ActionButtons`):** 承認待ちリスト (`/workflow/pending`) を表示する Livewire
       コンポーネント (`PendingList`)
       を作成。リストには申請者、申請日時、台帳名、ステータス等を表示。各行にアクションボタン（「点検完了(承認申請)
       」、「承認」、「作成中に戻す」）を、ユーザーの役割とタスクのステータスに応じて表示。承認申請時には承認者選択モーダルを表示。[完了]
    2. **ロジック実装 (`ActionButtons`, `WorkflowService`):**
        * 「点検完了（承認申請）」(`WorkflowService::requestApproval`): 承認者を選択・検証後、`LedgerDiff` の `status` を
          `PENDING_APPROVAL` に更新、`approver_id`, `inspected_at`, `modifier_id`
          を記録。カウンター調整とイベント発行の呼び出しポイントをコメントで明記。[完了]
        * 「承認」(`WorkflowService::approve`): `LedgerDiff` の `status` を `APPROVED` に更新、`approved_at`, `modifier_id`
          を記録。**`Ledger` レコードを `LedgerDiff` の内容で作成/更新し、`Ledger.status` も `APPROVED` に更新、`version`
          をインクリメントする処理を実装。** カウンター調整とイベント発行の呼び出しポイントをコメントで明記。[完了]
        * 「作成中に戻す」(`WorkflowService::returnToDraft`): 理由入力後、`LedgerDiff` と `Ledger` の `status` を `DRAFT`
          に更新、`returned_at`, `comments`, `modifier_id`
          を記録。カウンター調整とイベント発行の呼び出しポイントをコメントで明記。[完了]
        * 上記アクションに対応する Livewire メソッド (`PendingList::requestApproval`, `approveTask`,
          `returnTaskToDraft`) を実装し、Service を呼び出し、Toast 表示とリスト更新 (`$refresh`) を行う。[完了]
    3. **台帳リスト表示改善:** 台帳リスト表示画面 (`RecordsTable` コンポーネントと関連 Blade) を修正。
        * テーブルヘッダーに「ステータス」列を追加。[完了]
        * 各レコード行に現在の `Ledger.status` を示すバッジ (`WorkflowStatus::label()`, `colorClass()` 使用)
          を表示。[完了]
        * `APPROVED` 以外のステータスの行を視覚的に区別（例: `opacity-70`）。[完了]
        * `APPROVED` 状態のレコードの編集ボタンを無効化 (`Ledger::isLocked()` 使用)。[完了]
* **動作確認:**
    * 点検/承認アクションでステータスが遷移し、`LedgerDiff` と `Ledger` の関連カラムが更新されることを確認。[完了]
    * 承認時に `Ledger` データが正しく反映されることを確認。[完了]
    *
  台帳リストで各レコードのステータスが表示され、見た目で区別できること、承認済みレコードの編集がロックされることを確認。[完了]
    * 「作成中に戻す」アクションが機能し、ステータスと履歴が記録されることを確認。[完了]
* **成果物:** ワークフロー有効時の点検・承認・データ反映、および台帳リストでのステータス表示機能。基本的なワークフローサイクルが動作する状態。
* **ドキュメント更新:** このセクションを更新。「機能詳細(担当者のアクション、リスト表示)」「関連ファイル」に実装内容を反映。
  `PendingList` 等の新コンポーネントに関するドキュメント作成（または既存ドキュメントへの追記）。

  
---

### ✅ ステップ 3: `content` 重複削減と編集時挙動の実装 (完了)

* **目的:** **ステータス変更のみのアクションで `LedgerDiff` を作成/更新する際に、`content` 等を NULL (
  または空文字列/空JSON) として記録**するように変更する。承認フロー中の編集でステータスが `DRAFT`
  に戻る挙動を実装する。承認済みレコードの編集をロックする。**`Ledger` に `latest_diff_id` カラムを追加し、関連付ける。**
* **実施済みタスク:**
    1. **マイグレーション変更・実行:** `ledger_diffs` テーブルの `content` 等を nullable に変更。`ledgers` テーブルに
       `latest_diff_id` カラムを追加。[完了]
    2. **モデル変更 (`Ledger`):** `latestDiff()` リレーション定義、`$fillable` に `latest_diff_id` 追加。[完了]
    3. **`WorkflowService` 修正:** `requestApproval`, `approve`, `returnToDraft` で作成/更新する `LedgerDiff` の
       `content` 等を **NULL** (または空) で記録するように修正。[完了]
    4. **`WorkflowService` 修正:** 全ての `LedgerDiff` 作成/更新処理の最後で、関連する `Ledger` の `latest_diff_id`
       を更新する処理を追加。[完了]
    5. **(新規) `saveEditedRecord` メソッド (WorkflowService):** 編集によるDRAFT戻し処理を実装。新規 `LedgerDiff` (
       content有り) を作成し、`Ledger.status` を `DRAFT` に、`Ledger.content` 等も更新。[完了]
    6. **UI変更 (`ModifyColumn`):** 編集時の警告ダイアログ、理由入力フィールド（任意）追加。[完了]
    7. **ロジック実装 (`ModifyColumn`):** 保存ボタン (`saveChanges`) クリック時に `WorkflowService::saveEditedRecord`
       を呼び出すように変更。[完了]
    8. **`APPROVED` レコードのロック実装:** 実装済み。[完了]
* **動作確認:**
    * ステータス変更のみのアクションでは `LedgerDiff` に `content` なしのレコードが作成されることを確認。[完了]
    * 下書き保存、編集DRAFT戻し時には `LedgerDiff` に `content` が記録されることを確認。[完了]
    * フロー中編集で警告が表示され、保存すると `DRAFT` に戻り、新しい `LedgerDiff` (content有) が作成され、`Ledger`
      内容も更新されることを確認。[完了]
    * 各アクション後に `ledgers.latest_diff_id` が正しく更新されることを確認。[完了]
* **成果物:** `content` の重複が削減されたワークフロー履歴、安全な編集フロー、`Ledger`と最新`Diff`の連携。
* **ドキュメント更新:** このセクションを更新。

---

### ✅ ステップ 4: 承認待ちリスト修正、詳細画面実装、バージョン管理導入 (完了)

* **目的:** **承認待ちリスト (`PendingList`) に最新のタスクのみが表示されるように修正**する。*
  *台帳詳細画面 (`ledger.show`) でワークフロー情報とアクションボタンを表示**できるようにする。**`LedgerDiff` にバージョン番号を記録
  **する。
* **実施済みタスク:**
    1. **マイグレーション変更・実行:** `ledger_diffs` テーブルに `version` カラムを追加。[完了]
    2. **モデル変更:** `LedgerDiff` に `version` を `$fillable` に追加。[完了]
    3. **`WorkflowService` 修正:** 全ての `LedgerDiff` 作成時に `Ledger.version` を `LedgerDiff.version`
       にセットして保存するように修正。[完了]
    4. **承認待ちリストビュー (`pending-list.blade.php`) 修正:** ループ変数を `$ledger` に、表示データとアクションボタンの引数を
       `$ledger` 基準に修正。[完了]
    5. **承認待ちリストコンポーネント (`PendingList.php`) 修正:** アクションメソッドの引数を `$ledgerId` に変更し、内部で
       `Ledger::find()` するように修正。[完了]
    6. **台帳詳細画面 (`Show.php` Livewire & `show.blade.php` View) 作成/修正:** `Ledger` (`with latestDiff`)
       を取得し、ステータス、担当者、`Ledger.content`
       を表示。担当者の場合にアクションボタンを表示・実行できるように実装。変更履歴へのリンク追加。[完了]
    7. **台帳リスト表示ビュー (`table-row.blade.php`) 確認:** 内容表示が `$ledgerRecord->content`
       を参照していることを再確認。[完了]
* **動作確認:**
    * `LedgerDiff` に正しい `version` が記録されること。[完了]
    * 承認待ちリストに最新の正しいタスクのみが表示されること。[完了]
    * 台帳詳細画面で正しい情報とアクションボタンが表示され、動作すること。[完了]
    * 通常の台帳リスト表示が引き続き正しく行われること。[完了]
* **成果物:** 正確な承認待ちリスト表示、ワークフロー情報とアクションを備えた台帳詳細画面、バージョン管理された変更履歴の基盤。
* **ドキュメント更新:** このセクションを更新。「機能詳細」「関連ファイル」「実装計画」を修正。

---

#### ✅ ステップ 4.1: ワークフロープロセス履歴表示機能の実装 (完了)

* **目的:** 台帳詳細画面 (`ledger.show`) に、**`LedgerDiff` テーブルを活用し、ワークフローのプロセス履歴**
  （誰が、いつ、何のアクションを、どの担当者に対して、どんなコメント付きで実行したか）をユーザーに分かりやすく表示する。
* **実施済みタスク:**
    1. **履歴表示 UI 作成:** 台帳詳細画面 (`show.blade.php` Livewire用)
       に、ワークフロー履歴を表示するための専用セクション（例: カードとテーブル）を追加。[完了]
    2. **データ取得ロジック (`Show.php` Livewire):** `$ledgerRecord->ledgerDiff()->with([...])->orderBy(...)` で関連
       Diff を取得し、`$workflowHistory` プロパティにセットしてビューに渡す処理を実装。[完了]
    3. **ビューでの表示ロジック (`show.blade.php` Livewire用):** `$workflowHistory` をループし、各 Diff
       の日時、操作者、ステータス、担当者、コメントを表示。`content` がある Diff から `ShowDiff` 画面へのリンクを設置。[完了]
* **動作確認:**
    * 台帳詳細画面で、ワークフロー履歴が時系列で表示され、関連情報が正しく表示されることを確認。[完了]
    * データ内容へのリンクが機能することを確認。[完了]
* **成果物:** 台帳詳細画面でのワークフロープロセス履歴の可視化。
* **ドキュメント更新:** このセクションを更新。「機能詳細(ワークフローステータスと履歴)」更新。

---

### ステップ 4.2: 詳細画面 UI 改善 (タブ導入とアクション集約) (Next)

* **目的:** 台帳詳細画面 (`Show`) の情報配置を見直し、UI の一貫性を向上させる。ワークフロー履歴をタブ内に表示し、アクションボタンをフッターに集約する。
* **タスク:**
    1. **UI構造変更 (`show.blade.php` Livewire用):**
        * 画面上部のステータス表示とアクションボタンのカードを**削除**。
        * メインコンテンツエリアに `<x-mary-tabs>` を導入。
        * **「基本情報」タブ (デフォルト):** `<x-ledger.detail.table>` による最新内容表示を配置。
        * **「ワークフロー履歴」タブ:** ステップ4.1で実装したワークフロー履歴リスト表示ロジックをここに移動する。
        * **フッターパネル (`fixed bottom-3` のカード):**
            * 既存の編集ボタン、変更履歴 (`ShowDiff`) ボタンを配置。
            * ワークフローのアクションボタン（「点検完了(承認申請)」「承認」「作成中に戻す」）をここに追加。
            * 各ボタンの表示/非表示/有効状態を `$ledgerRecord->status` と Livewire
              コンポーネントの権限チェックメソッド (`can...()`) で制御する。
    2. **ロジック (`Show.php` Livewire):**
        * 権限チェックメソッド (`canRequestApproval`, `canApprove`, `canReturnToDraft`) が実装されていることを確認。
        * アクションボタンに対応するメソッド (`open...Modal`, `approveTask`, `returnTaskToDraft`) は変更なし。
* **動作確認:**
    * 詳細画面を開くと、デフォルトで最新内容が表示されること。
    * 「ワークフロー履歴」タブをクリックすると、履歴リストが表示されること。
    * フッターパネルに、現在のステータスと権限に応じたアクションボタン（編集、履歴、承認、戻す等）が正しく表示/非表示/有効化されること。
    * フッターのアクションボタンが正しく機能すること。
* **ドキュメント更新:** 「機能詳細(詳細表示、履歴)」セクションを修正。UI構成の変更（タブ、フッター集約）について追記。「関連ファイル」更新。

---

### ステップ 5: ワークフロー有効化制御と定義変更制限 (旧ステップ2.1)

* **目的:** 台帳定義の設定に基づきワークフローの有効/無効を制御し、ワークフロー進行中の定義変更を制限する。
* **タスク:** (変更なし)
    1. ワークフロー有効/無効分岐処理 (`CreateColumn`/`ModifyColumn`)。
    2. 直接保存ロジック（WF無効時）。
    3. 台帳定義変更制限 (`LedgerDefineResource`)。
* **動作確認:** (変更なし)
* **ドキュメント更新:** 「機能詳細(ワークフロー有効化設定、台帳定義変更制限)」「関連ファイル」更新。

---

### ステップ 6: 通知機能の実装 (旧ステップ4)

* **目的:** ワークフローに関連する通知（集約・個別）を実装する。
* **タスク:** (大きな変更なし)
    1. `notification_types` レコード追加。
    2. Filament UI 更新。
    3. 未処理カウンター実装。`WorkflowService` で増減処理。
    4. `NotificationService` 拡張。
    5. 集約通知コマンド作成・登録。
    6. システム内通知 UI 実装。
    7. (オプション) ブラウザ通知実装。
* **動作確認:** (変更なし)
* **ドキュメント更新:** 通知関連セクション更新。

---

### ステップ 7: 柔軟な承認ルート機能の実装 (旧ステップ5)

* **目的:** 承認者/点検者をより柔軟に設定できるようにする。担当者情報を `LedgerDiff` に記録。
* **タスク:**
    1. (オプション) 推奨担当者設定機能。
    2. 担当者選択 UI 改善（ロール指定含む）。
    3. ロジック実装（選択された担当者情報を `LedgerDiff` の `inspector_id`/`approver_id` (またはロールID用カラム) に記録）。
    4. **承認待ちリスト取得ロジック (`WorkflowTaskRepository`) 修正:** **`LedgerDiff` テーブルの最新レコード**
       （ステータスと担当者IDを持つ）を参照してリストを取得するように変更。
* **動作確認:** 推奨担当者表示、担当者変更、ロール指定時の動作を確認。
* **ドキュメント更新:** 「柔軟な承認ルート」セクション更新。担当者情報の記録場所とリスト取得方法について追記。

---

## 今後の展望 / 要検討事項

* **要検討事項:**
    * 承認済み (`APPROVED`) レコードの変更・編集解除プロセス。
    * ロール指定承認の詳細な挙動。
    * 担当者選択 UI のコンポーネント選定。
    * 通知カウンターの実装方法。
    * ブラウザ通知の実装方針。
    * 点検ステップが不要な場合のワークフロー。
    * **履歴表示機能:** `LedgerDiff` を遡って NULL/空でない content を探すロジックのパフォーマンスと実装方法。
    * **Activity Log の活用:** 補助的な履歴記録として活用するかどうか再検討。
* ファイルアップロード等へのワークフロー適用。
* 承認ルートの高度化。
* 期限と督促機能。
* 代理機能。
