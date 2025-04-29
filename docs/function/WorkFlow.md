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

### ✅ ステップ 4.2: 詳細画面 UI 改善と履歴表示のタブ化 (完了)

* **目的:** 台帳詳細画面 (`Show`) の情報配置を見直し、UI の一貫性を向上させる。ワークフロー履歴をタブ内に表示し、*
  *ステータスとアクションボタンを画面上部に配置**する。
* **実施済みタスク:**
    1. **UI構造変更 (`show.blade.php` Livewire用):**
        * メインコンテンツエリアに `<x-mary-tabs>` を導入。[完了]
        * **「基本情報」タブ (デフォルト):** `<x-ledger.detail.table>` による最新内容表示を配置。[完了]
        * **「ワークフロー履歴」タブ:** ステップ4.1で実装したワークフロー履歴リスト表示ロジックをタブ内に移動。[完了]
        * **ステータス表示とアクションボタン:** 画面上部（タブの上）に `<x-mary-card>`
          を配置し、現在のステータスと担当者、およびワークフローのアクションボタン（「点検完了(承認申請)
          」「承認」「作成中に戻す」）を表示。各ボタンの表示/非表示/有効状態を `$ledgerRecord->status` と権限チェックメソッド (
          `can...()`) で制御。[完了]
        * **フッターパネル:** 既存の編集ボタン、変更履歴 (`ShowDiff`) ボタン、閉じるボタンを配置。[完了]
    2. **ロジック (`Show.php` Livewire):**
        * 権限チェックメソッド (`canRequestApproval`, `canApprove`, `canReturnToDraft`) 実装済み。[完了]
        * アクションボタンに対応するメソッド (`open...Modal`, `approveTask`, `returnTaskToDraft`) 実装済み。[完了]
* **動作確認:**
    * 詳細画面を開くと、デフォルトで最新内容が表示されること。[完了]
    * 「ワークフロー履歴」タブをクリックすると、履歴リストが表示されること。[完了]
    * 画面上部に、現在のステータスと権限に応じたアクションボタン（承認、戻す等）が正しく表示/非表示/有効化されること。[完了]
    * フッターパネルの編集ボタン、履歴ボタンも正しく表示・制御されていること。[完了]
    * 全てのアクションボタンが正しく機能すること。[完了]
* **成果物:** タブ UI による情報整理と、適切な場所に配置されたアクションボタンを備えた、改善された台帳詳細画面。
* **ドキュメント更新:** このセクションを更新。「機能詳細(詳細表示、履歴)」セクションを修正。UI構成の変更（タブ、*
  *ステータス/アクション上部配置**、フッターパネル）について追記。「関連ファイル」更新。

---

### ✅ ステップ 5: ワークフロー有効化制御、定義変更制限、`NONE` ステータス導入 (完了)

* **目的:** 台帳定義の設定に基づきワークフローの有効/無効を制御する。ワークフロー進行中の定義変更を制限する。ワークフロー非適用を示す
  `NONE` ステータスを導入し、状態管理と処理分岐を明確化する。
* **実施済みタスク:**
    1. **Enum 定義変更:** `app/Enums/WorkflowStatus.php` に `NONE` ケースを追加し、関連メソッドを定義・修正。[完了]
    2. **DB スキーマ変更:** `create_ledgers_table.php` の `status` カラムのデフォルト値を `WorkflowStatus::NONE->value`
       に変更。`create_ledger_defines_table.php` に `workflow_enabled` カラムを追加。マイグレーションを実行。[完了]
    3. **台帳定義編集画面 (`LedgerDefine\Edit`) 実装:**
        * UI (`edit.blade.php`): `workflow_enabled` 設定用のトグルスイッチ (`<x-mary-toggle>`) を追加。[完了]
        * ロジック (`Edit.php`): `mount` で値を読み込み、`store` で保存。**有効→無効変更時に進行中 Ledger の `status`
          を `NONE` に更新する処理を実装。**[完了]
    4. **台帳作成/編集画面 (`CreateColumn`/`ModifyColumn`) 分岐処理実装:**
        * ビュー (`create-column.blade.php`, `modify-column.blade.php`): `@if($ledgerRecord->definee->workflow_enabled)`
          ディレクティブでアクションボタンエリアの表示を切り替え。[完了]
    5. **台帳リスト/詳細画面 状態表示処理実装:**
        * ビュー (`records-table.blade.php`, `table-row.blade.php`, `livewire/ledger/show.blade.php`):
          `@if($ledgerRecord->status !== \App\Enums\WorkflowStatus::NONE)` 等の条件分岐を追加し、`NONE`
          状態の場合はステータス関連表示を非表示または変更。[完了]
    6. **直接保存ロジック実装 (`CreateColumn::saveDirectly`):** ワークフロー無効時の「保存」ボタンから呼び出されるメソッドを実装し、
       `Ledger.status` を `NONE` で保存。[完了]
    7. **台帳定義変更制限ロジック (`LedgerDefine\ModifyColumn`):** 列定義を変更するアクションの実行前に
       `canModifyDefinition()` メソッドを呼び出し、進行中の Ledger があれば変更をブロックする処理を実装。[完了]
    8. **`WorkflowService` メソッドのステータスチェック強化:** 各ワークフローアクションメソッド冒頭で、対象
       `Ledger.status` が適切かチェックするロジックを追加。[完了]
* **動作確認:**
    * `LedgerDefine` 編集画面でワークフロー有効/無効トグルが表示され、設定が保存されることを確認。[完了]
    * ワークフロー有効/無効設定に応じて、台帳作成/編集画面のアクションボタンが切り替わることを確認。[完了]
    * ワークフロー有効/無効設定に応じて、台帳リスト/詳細画面のステータス関連表示が切り替わることを確認。[完了]
    * ワークフロー無効時は直接保存され、`Ledger.status` が `NONE` になることを確認。[完了]
    * ワークフロー設定を有効→無効に変更した場合、進行中だったレコードのステータスが `NONE` に戻ることを確認。[完了]
    *
  ワークフロー進行中に台帳定義の列構成を変更しようとするとエラーメッセージが表示され、変更がブロックされることを確認。[完了]
* **成果物:** ワークフローの有効/無効を台帳定義ごとに制御でき、状態管理と UI 表示が連動する機能。定義変更時の安全性向上。
* **ドキュメント更新:** このセクションを更新。「機能詳細(ワークフロー有効化設定、直接保存、定義変更制限、リスト/詳細表示)
  」「関連ファイル」を更新。`NONE` ステータスの導入と設定変更時の挙動、UI分岐について追記。

---

### ✅ ステップ 5.1: WF無効時の変更履歴記録と履歴表示調整 (完了)

* **目的:** **ワークフローが無効な台帳でも、データ変更時に `LedgerDiff` を変更履歴として記録**する。詳細画面および*
  *変更履歴画面 (`ShowDiff`)** の履歴表示をワークフローの状態に応じて調整する。
* **実施済みタスク:**
    1. **直接保存ロジック修正 (`CreateColumn::saveDirectly`):** `Ledger` 更新と同時に、**新規 `LedgerDiff` レコードを作成
       **し、`content` 等を記録、`status` は `WorkflowStatus::NONE` と設定。[完了]
    2. **履歴表示ビュー (`show.blade.php` Livewire用 - 詳細画面の履歴タブ):** ループ内で
       `@if ($diff->status !== \App\Enums\WorkflowStatus::NONE)` 条件分岐を追加し、`status` が `NONE`
       の場合はワークフロー関連情報を表示せず、「変更」などのシンプル表示に調整。[完了]
    3. **変更履歴画面ビュー (`show-diff.blade.php` Livewire用) 修正:**
        * `@if ($currentDiffRecord->status !== \App\Enums\WorkflowStatus::NONE)`
          でワークフロー情報エリアの表示を制御。[完了]
        * (任意) `status` が `NONE` の場合に代替メッセージを表示。[完了]
        * データ内容表示エリアの表示条件を `content` が空でない場合に修正。[完了]
* **動作確認:**
    * ワークフロー無効の台帳を編集・保存した場合、`LedgerDiff` が `status=NONE` で作成され、`content`
      が記録されることを確認。[完了]
    *
  詳細画面の履歴タブで、ワークフロー無効時の変更履歴がシンプルに表示され、ワークフロー有効時の履歴は詳細情報付きで表示されることを確認。[完了]
    * 変更履歴画面 (`ShowDiff`)
      で、ワークフロー無効時の履歴を表示した場合、ワークフロー情報エリアが表示されず、データ内容のみが表示されることを確認。[完了]
    * 変更履歴画面 (`ShowDiff`) で、ワークフロー有効時の履歴（ステータス変更のみで `content`
      が空の場合も含む）を表示した場合、データ内容エリアに「内容の変更はありません」等が表示され、ワークフロー情報が表示されることを確認。[完了]
* **成果物:** ワークフロー無効時も変更履歴が記録され、履歴表示画面がワークフロー状態に応じて適切に情報を表示する機能。
* **ドキュメント更新:** このセクションを更新。「機能詳細(ワークフロー有効化設定、直接保存、証跡)」「関連ファイル」更新。WF無効時の
  `LedgerDiff` の扱いと、詳細画面および変更履歴画面での履歴表示方法について追記。

---

### ✅ ステップ 6.1: 未処理カウンターの実装 (完了)

* **目的:** 各担当者（点検者/承認者）の未処理タスク件数をデータベースで管理する仕組みの基盤を実装する。
* **実施済みタスク:**
    1. **DB スキーマ変更:** `users` テーブルに `pending_inspection_count`, `pending_approval_count` カラム (
       unsignedInteger, default 0, index付き) を追加するマイグレーションを作成・実行。[完了]
    2. **モデル更新 (`User.php`):** `$casts` 配列に新しいカウンターカラムを `integer` として追加。[完了]
    3. **`WorkflowService` 修正:** 各ワークフローアクションメソッド (`requestInspection`, `requestApproval`, `approve`,
       `returnToDraft`, `saveEditedRecord`) 内で、関連する担当者のカウンターカラム (`pending_inspection_count` または
       `pending_approval_count`) を `increment()` / `decrement()`
       を使用して増減させるロジックを実装。トランザクション内で実行。[完了]
* **動作確認:**
  各ワークフローアクション実行時に、関連するユーザーのカウンターカラムの値がDB上で正しく増減することを確認。[完了]
* **成果物:** 担当者ごとの未処理タスク件数を追跡するためのデータベース基盤と更新ロジック。
* **ドキュメント更新:** このセクションを更新。「機能詳細(通知)」および「関連ファイル(Userモデル, WorkflowService)
  」にカウンター実装について追記。

---

### ✅ ステップ 6.2: システム内通知 UI への連携 (完了)

* **目的:** ヘッダーアイコンとマイポータルのカードに、ステップ6.1で実装した未処理ワークフロータスクのカウンター値を表示する。
* **実施済みタスク:**
    1. **ヘッダー通知アイコン (`Notifications\Icon` Livewire):**
        * コンポーネント (`Icon.php`) で、ログインユーザーの `$user->pending_inspection_count` と
          `$user->pending_approval_count` を合計して取得するロジックを追加 (`refreshCounts` メソッド)。[完了]
        * ビュー (`icon.blade.php`) で、取得した未処理タスク合計件数を、既存の未読通知件数とは別のバッジ (例: 警告色)
          で表示するように修正。各バッジにツールチップを追加。[完了]
        * `wire:poll` を調整し、定期的に両方の件数を更新するように設定（任意）。[完了]
    2. **マイポータル (`MyPortal.php`):** `mount` メソッドで `$pendingTaskCount`
       プロパティに、ログインユーザーのカウンターカラム (`pending_inspection_count + pending_approval_count`)
       の合計値を直接セットするように修正。[完了]
* **動作確認:**
    * ヘッダーのベルアイコン右上に、未処理タスク件数と未読通知件数が別々のバッジで表示されることを確認。[完了]
    * 各バッジにマウスオーバーすると、内容を示すツールチップが表示されることを確認。[完了]
    * マイポータルの「承認待ちタスク」カードの件数が、ヘッダーのタスク件数と一致することを確認。[完了]
    * ワークフローアクション実行後、ページリロード（または `wire:poll`
      による更新後）に、ヘッダーとマイポータルの件数が正しく増減することを確認。[完了]
* **成果物:** ユーザーがヘッダーとマイポータルで自身の未処理タスク件数を容易に把握できる UI。
* **ドキュメント更新:** このセクションを更新。「機能詳細(通知UI)」「関連ファイル(Iconコンポーネント, MyPortal)」更新。

### ステップ 6.3: 通知/タスク画面構成変更とコンポーネント分割 (Next)

* **目的:** **通知一覧画面 (`/notifications`) を通常の Blade ビュー (`notifications/index.blade.php`) で再構築**
  する。その中にタブ UI を設け、**「通知リスト」「アクティビティログ」「未処理タスクリスト」をそれぞれ独立した Livewire
  コンポーネントとして呼び出す**構成に変更する。マイポータルからのリンクも調整する。
* **タスク:**
    1. **ルート定義変更:**
        * `/notifications` ルートを、新しいコントローラー (`UserNotificationController@index` など)
          を指すように変更（または既存コントローラーを利用）。
        * `/activity-log` ルートは通知画面に統合されるため、リダイレクトまたは削除を検討。
        * `/workflow/pending` ルートを削除。
    2. **コントローラー作成/修正 (`UserNotificationController`):** `index` メソッドで、親 Blade ビュー (
       `notifications.index`) を返す。URL クエリパラメータ (`tab=activity`, `tab=tasks` など) をビューに渡す。
    3. **親 Blade ビュー作成/修正 (`resources/views/notifications/index.blade.php`):**
        * `<x-mary-tabs>` を使用し、「通知」「アクティビティログ」「未処理タスク」のタブを作成。
        * 受け取ったクエリパラメータに応じて、デフォルトで表示するタブを設定する JavaScript ロジック（または Blade
          ディレクティブ）を追加。
        * 「通知」タブ内に `@livewire('notifications.notification-list')` を配置。
        * 「アクティビティログ」タブ内に `@livewire('user-activity-log')` (既存コンポーネント名想定) を配置。
        * 「未処理タスク」タブ内に `@livewire('workflow.pending-list')` を配置。
    4. **Livewire コンポーネント分割/リファクタリング:**
        * **`App\Livewire\Notifications\NotificationList` (新規作成 or 既存改修):** 従来の `UserNotificationList` から、*
          *通知リスト関連**のプロパティとロジック（未読/既読取得、表示、既読処理）を抽出・実装。
        * **`App\Livewire\UserActivityLog` (既存改修):** 従来の `UserNotificationList` から、**アクティビティログ関連**
          のプロパティとロジックを抽出・実装（既に別コンポーネントならそのまま）。
        * **`App\Livewire\Workflow\PendingList` (既存改修):** **未処理タスクリスト関連**
          のプロパティとロジック（タスク取得、表示、アクションメソッド、モーダル制御）を実装（現状の `PendingList` を流用）。
    5. **マイポータルビュー (`my-portal.blade.php`) 修正:** 承認待ち件数カードのリンク先を
       `route('notifications.index', ['tab' => 'tasks'])` に修正。
    6. **(削除)** 従来の `UserNotificationList` コンポーネントは不要になるため削除（またはリネームして NotificationList
       として再利用）。
* **動作確認:**
    * `/notifications` にアクセスすると、タブ付きの画面が表示され、デフォルトで「通知」タブが開くこと。
    * 各タブをクリックすると、対応する Livewire コンポーネント（通知リスト、アクティビティログ、未処理タスクリスト）が正しく表示されること。
    * マイポータルの件数カードをクリックすると、`/notifications?tab=tasks` に遷移し、「未処理タスク」タブがアクティブな状態で表示されること。
    * 各タブ内のリスト表示やアクション（既読処理、タスク承認など）が正しく機能すること。
* **ドキュメント更新:** 「機能詳細(承認待ちタスクの確認、通知UI)」「関連ファイル」更新。画面構成の変更（親Blade +
  子Livewire）、コンポーネント分割について追記。

---

### ステップ 6.4: ワークフロー通知タイプの定義と設定UI

* **目的:** ワークフローに関連する通知の種類を定義し、ユーザーが通知設定画面で ON/OFF できるようにする。
* **タスク:**
    1. `notification_types` テーブルへのレコード追加 (Seeder/Migration): `inspection_requested` (集約通知用?),
       `approval_requested` (集約通知用?), `inspection_completed` (申請者向け?), `approved` (申請者向け),
       `status_returned_to_draft` (申請者向け), `workflow_summary` (担当者向け集約) 等を定義。`default_notify` も検討。
    2. Filament UI 更新 (`NotificationSettingsRelationManager.php`): フォームの CheckboxList (`notification_types`) の
       `options()` で新しい通知タイプを表示。テーブルの `notific˚ationType.name` カラムで翻訳キー表示。
    3. 翻訳ファイル更新 (通知タイプ名: `ledger.notification_types.*`)。
* **動作確認:** 通知設定画面 (Role リソース内) で、新しいワークフロー関連通知タイプの ON/OFF が設定できること。
* **ドキュメント更新:** 「機能詳細(通知設定)」「関連ファイル(NotificationType, NotificationSettingsRelationManager)」更新。

---

### ステップ 6.5: 個別通知と集約通知の実装 (通知送信)

* **目的:** ワークフローのアクションに応じて、設定に基づき個別通知と集約通知を送信する。
* **タスク:**
    1. `NotificationService` 拡張 (WF用通知メソッド、設定確認ロジック)。
    2. `WorkflowService` からの呼び出し (戻し時、承認完了時)。
    3. 集約通知コマンド作成・実行 (`SendWorkflowSummaryNotification`)。
    4. 通知リスト画面 (`UserNotificationList`) 調整。
* **動作確認:** 個別/集約通知が設定に基づき送信されること。
* **ドキュメント更新:** 「機能詳細(通知送信)」「関連ファイル」更新。

---

### ステップ 6.6: (オプション) ブラウザ通知機能の実装

* **目的:** 集約通知や重要な個別通知をブラウザプッシュ通知で送信する。
* **タスク:** Web Pushライブラリ導入, Service Worker実装, 購読情報保存, `NotificationService`での送信処理追加。
* **動作確認:** ブラウザ通知の許可・受信を確認。
* **ドキュメント更新:** 「機能詳細(通知手段)」「関連ファイル」更新。

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
    * **ファイルアップロード等へのワークフロー適用。**
    * **承認ルートの高度化。**
    * **期限と督促機能。**
    * **代理機能。**
    * **承認済みレコードの編集解除プロセス。**
    * **ロール指定承認の詳細な挙動。**
    * **担当者選択 UI のコンポーネント選定。** 