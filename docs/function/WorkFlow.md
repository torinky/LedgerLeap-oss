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

* **ワークフロー関連通知タイプ:** `notification_types`
  テーブルに以下のような通知タイプを追加します。これらの通知を受け取るかどうかは、ユーザー（ロール）がフォルダ別通知設定で制御できます。
    * `workflow_summary` (担当者向け集約): 点検者・承認者に対し、未処理タスク件数を定期的に通知します（メール依存低減の主要通知）。通知設定はグローバル設定か代表フォルダで行う想定です。
    * `status_returned_to_draft` (申請者向け個別):
      点検者・承認者によってタスクが「作成中に戻された」ことを申請者にリアルタイム（またはそれに近い形）で通知します。差し戻し理由コメントも通知内容に含みます。
    * `approved` (申請者向け個別): 申請したレコードが最終承認されたことを申請者にリアルタイム（またはそれに近い形）で通知します。
    * `inspection_completed` (申請者向け個別 - オプション): 点検が完了し、承認ステップに進んだことを申請者に通知する中間報告。必要に応じて定義・実装します（デフォルトOFF推奨）。
    * *(個別依頼通知 `inspection_requested`, `approval_requested` は原則として `workflow_summary` で代替)*
* **ユーザーによる制御:** ユーザー（またはロール管理者）は、Filament の RoleResource 内にある「フォルダ別通知設定」(
  `NotificationSettingsRelationManager`) を使って、フォルダごとにこれらのワークフロー関連通知の ON/OFF を設定できます（
  `workflow_summary` の設定方法は要別途検討）。
* **通知送信時の確認:** `NotificationService` は、各種ワークフロー通知を送信する際に、受信対象ユーザーが該当する通知タイプの設定を
  ON にしているかを確認し、ON の場合にのみ送信します。

### 10. ワークフロー通知の詳細シナリオ (新規セクション)

各通知タイプが、それぞれのユーザーロールにとってどのような役割を果たすかのシナリオ例です。

* **担当者 (点検者/承認者) のシナリオ:**
    * **タスクの把握:** 定期的な `workflow_summary` 通知（例:
      1日1回）で、自分宛ての未処理タスクがあることを認識します。「承認待ち〇件あり」の通知をクリック（またはヘッダーアイコンのバッジを見て）、通知一覧画面の「未処理タスク」タブを開きます。
    * **タスク処理:** リストから各タスクの詳細を確認し、「点検完了」「承認」または「作成中に戻す」（編集含む）のアクションを行います。
    * **フォローアップ (要検討):** 自分が点検完了した案件が、その後承認されたか、あるいは滞留していないかを確認するための導線が必要です（現状は通知一覧やタスクリストでは追跡困難）。
* **作成者 (申請者) のシナリオ:**
    * **申請:** レコードを作成・編集し、「作成完了（点検依頼）」または「承認申請」（点検ステップがない場合）を行います。
    * **進捗確認 (任意):** `inspection_completed` 通知（もし有効なら）で、点検が完了し承認ステップに進んだことを知ります。または、詳細画面でステータスを確認します。
    * **差し戻し対応:** `status_returned_to_draft` 通知を受け取り、理由コメントを確認します。通知内のリンクから詳細画面を開き、レコードを編集して再度申請を行います。
    * **完了確認:** `approved` 通知を受け取り、申請が承認されたことを確認します。

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

### ✅ ステップ 6.3: 通知/タスク画面統合とコンポーネント分割、タブ件数表示 (完了)

* **目的:** 通知一覧画面を通常の Blade ビューで再構築し、タブ内に独立した Livewire
  コンポーネント（通知リスト、アクティビティログ、未処理タスクリスト）を配置。タブタイトルに動的に件数を表示し、マイポータルからの導線を確保する。
* **実施済みタスク:**
    1. **ルート定義変更:** `/notifications` ルートをコントローラー (`UserNotificationController@index`) に変更。
       `/activity-log` リダイレクト設定、`/workflow/pending` 削除。[完了]
    2. **コントローラー作成 (`UserNotificationController`):** 親 Blade ビュー (`notifications.index`) を返し、アクティブタブ情報を渡す
       `index` メソッドを実装。[完了]
    3. **親 Blade ビュー作成 (`notifications/index.blade.php`):** DaisyUI タブ (または Alpine.js 連携の MaryUI タブ)
       を使用し、3つのタブ（通知、アクティビティログ、未処理タスク）の枠組みを作成。URLパラメータに基づき初期アクティブタブを設定。各タブ内に対応する
       Livewire コンポーネントを `@livewire` で埋め込み。Alpine.js を使用し、子コンポーネントからのイベント (
       `update-tab-count`) を受けてタブの aria-label (または表示テキスト) の件数を動的に更新。[完了]
    4. **Livewire コンポーネント分割/リファクタリング:**
        * `App\Livewire\Notifications\NotificationList` (新規作成/改修): 通知リスト関連ロジックを実装。`render`
          時に合計件数を取得し `update-tab-count` イベントを発行。[完了]
        * `App\Livewire\UserActivityLog` (既存利用): アクティビティログ関連ロジックを実装 (
          必要なら件数イベント発行も)。[完了]
        * `App\Livewire\Workflow\PendingList` (既存利用): 未処理タスクリスト関連ロジックを実装。`render` 時に合計件数を取得し
          `update-tab-count` イベントを発行。[完了]
    5. **マイポータルビュー (`my-portal.blade.php`) 修正:** 承認待ち件数カードのリンク先を
       `route('notifications.index', ['tab' => 'tasks'])` に修正。[完了]
    6. **(削除)** 従来の `UserNotificationList` コンポーネントを削除またはリファクタリング。[完了]
* **動作確認:**
    * 通知一覧画面のタブ表示、初期タブ設定、タブ切り替えが正しく動作することを確認。[完了]
    * 各タブ内に対応するリスト（通知、ログ、タスク）が表示され、ページネーションやアクションが機能することを確認。[完了]
    * マイポータルから「未処理タスク」タブへ直接リンクできることを確認。[完了]
    * **各タブのラベルに初期件数が表示され、リストの内容が変化（既読化、タスク処理など）した後に件数が動的に更新されることを確認。
      **[完了]
* **成果物:** 親 Blade + 子 Livewire 構成による、統合された通知・タスク管理画面。タブへの動的な件数表示機能。
* **ドキュメント更新:** このセクションを更新。「機能詳細(承認待ちタスクの確認、通知UI)
  」「関連ファイル」更新。画面構成変更、コンポーネント分割、タブ件数表示について追記。

---

### ✅ ステップ 6.4: ワークフロー通知タイプの定義と設定UI (完了)

* **目的:** ワークフローに関連する通知の種類をデータベースに定義し、ロール管理画面（Filament）からフォルダごとにユーザー（ロール）が通知を受け取るかどうかを
  **テーブル上で直接**設定できるようにする。
* **実施済みタスク:**
    1. **通知タイプ (`notification_types` テーブル) へのレコード追加:** Seeder (`NotificationTypeSeeder`)
       を修正し、ワークフロー関連の通知タイプ (`inspection_requested`, `approval_requested`, `status_returned_to_draft`,
       `approved`, `workflow_summary`, `inspection_completed`?) を定義・登録。[完了]
    2. **`NotificationSettingsRelationManager.php` の改修:**
        * Relation Manager のリレーションを `Role` -> `RoleFolderPermission` (`roleFolderPermissions`) に変更。
        * テーブル (`table()` メソッド) のカラム定義を修正:
            * `folder.title`, `notificationType.name` を `RoleFolderPermission` のリレーション経由で表示するように変更。翻訳にも対応。
            * 通知 ON/OFF 表示を **`ToggleColumn`** に変更。`getStateUsing` で現在の状態を Boolean に変換し、
              `updateStateUsing` でトグル操作時に `RoleFolderPermission.permission` を `NOTIFY_ON`/`NOTIFY_OFF`
              に更新し、成功/失敗通知を表示するロジックを実装。[完了]
        * ヘッダーアクション (`headerActions`) の「Attach」ボタン (`Action::make('create')`) を修正し、フォーム内で新しい通知タイプを選択できるように
          `CheckboxList` の `options()` を更新。[完了]
        * 行アクション (`actions`) の「Edit」ボタンは、`ToggleColumn`
          の導入により不要となったため削除（または非表示化）。「Delete」アクションは維持。[完了]
    3. **翻訳ファイル (`ledger.php`) 更新:** 新しい通知タイプ名 (`ledger.notification_types.*`) と説明 (
       `ledger.notification_types_description.*` - 任意) を追加。[完了]
* **動作確認:**
    * `notification_types` テーブルに新しいレコードが追加されていることを確認。[完了]
    * ロール管理画面 >
      通知設定タブで、テーブルが表示され、フォルダ名、通知タイプ、通知ON/OFFトグルスイッチが表示されることを確認。[完了]
    * 「Attach」ボタンのフォームで新しい通知タイプが選択できることを確認。[完了]
    * **テーブル上のトグルスイッチをクリックすると、即座に通知設定 (ON/OFF) が更新され、成功通知が表示されることを確認。
      **[完了]
    * テーブルの表示（フォルダ名、通知タイプ名）が正しいことを確認。[完了]
* **成果物:** ワークフロー関連通知タイプが DB に定義され、管理者が Filament UI を通じてロール・フォルダごとに通知の
  ON/OFF を直接設定できる機能。
* **ドキュメント更新:** このセクションを更新。「機能詳細(通知設定との連携)」「関連ファイル(NotificationType,
  NotificationSettingsRelationManager)」更新。**UI がトグルスイッチによる直接編集に変更された点**と、定義された通知タイプとその目的を記述。

---

### ✅ ステップ 6.5: 個別通知の実装 (完了)

* **目的:** ワークフローの各アクションが発生したタイミングで、主に関連ユーザー（申請者、担当者）へ個別通知を送信する。ユーザーは通知設定に基づいて受信を制御できる。
  **通知トリガーは `WorkflowService` 内の各アクションメソッドとする (Observer は使用しない)。**
* **実施済みタスク:**
    1. **`LedgerDiffObserver` の不採用決定:** ワークフローアクションは `WorkflowService` 内で完結するため、Observer
       を使用せず、Service 内で直接通知処理を呼び出す方針に変更。[完了]
    2. **`NotificationService` 拡張:**
        * ワークフロー通知送信用の汎用メソッド
          `sendWorkflowNotification(User $recipient, NotificationType $type, LedgerDiff $diff, ?string $comment = null, ?Folder $folder = null)`
          を実装。[完了]
        * メソッド内で受信者の通知設定 (`RoleFolderPermission`) を確認し、ON の場合のみ通知（システム内
          `DatabaseNotification`) を送信するロジックを実装。[完了]
        * 通知内容（ペイロード）を生成するロジックを実装。[完了]
        * 通知クリック時のリンク先 (`route()`) を設定。[完了]
    3. **`WorkflowService` 修正:** 各アクションメソッド (`requestInspection`, `requestApproval`, `approve`,
       `returnToDraft`, `saveEditedRecord`) の**トランザクション成功後**に、適切な通知タイプと受信者を特定し、
       `NotificationService::sendWorkflowNotification` を呼び出す処理を追加。[完了]
        * 差し戻し時 (`status_returned_to_draft`): 申請者へ通知。
        * 承認完了時 (`approved`): 申請者へ通知。
        * (任意) 点検完了時 (`inspection_completed`): 申請者へ通知。
        * (オプション) 点検/承認依頼時 (`inspection_requested`, `approval_requested`):
          担当者へ通知（集約通知を主とするため、デフォルトOFFまたは設定次第）。
    4. **通知リスト画面 (`NotificationList`) 調整:** 新しいペイロード構造 (`data['payload']`)
       に合わせて通知内容を表示するように修正。[完了]
* **動作確認:**
    * ワークフローの各アクションに応じて、通知設定が ON になっている適切なユーザーにシステム内通知が届くことを確認。[完了]
    * 通知の内容（メッセージ、リンク先、コメント等）が正しく表示されることを確認。[完了]
    * 通知設定が OFF のユーザーには通知が届かないことを確認。[完了]
* **成果物:** ワークフローの進行状況に応じて関係者に個別通知が送信される機能。
* **ドキュメント更新:** このセクションを更新。「機能詳細(通知設定との連携、通知送信)」「関連ファイル(Service)」更新。**Observer
  を使わず Service から通知をトリガーする方式**に変更した点を明記。

---

### ✅ ステップ 6.6: 集約通知の実装 (完了)

* **目的:** 定期的に担当者へ未処理タスク件数を通知する。
* **実施済みタスク:**
    1. **集約通知コマンド作成 (`SendWorkflowSummaryNotification`):** `User` モデルから未処理カウンター (
       `pending_inspection_count` + `pending_approval_count`) > 0 のユーザーを取得するロジックを実装。[完了]
    2. **`NotificationService` 拡張:** `sendWorkflowSummaryNotification(User $user)` メソッドを追加。メソッド内でユーザーが
       `notify` Permission を持っているか (`can('notify')`) を確認し、持っている場合に新しい専用通知クラス
       `WorkflowSummaryNotification` を使って通知（まずはシステム内 `DatabaseNotification`
       ）を送信するロジックを実装。[完了]
    3. **コマンド実装:** 作成したコマンド (`SendWorkflowSummaryNotification`) の `handle` メソッドで、対象ユーザーを取得しループして
       `NotificationService::sendWorkflowSummaryNotification` を呼び出すように実装。[完了]
    4. **通知クラス作成 (`WorkflowSummaryNotification`):** 未処理件数をコンストラクタで受け取り、`toDatabase`
       で通知データを生成する専用クラスを作成。[完了]
    5. **Kernel 登録:** `app/Console/Kernel.php` の `schedule()` メソッドに `SendWorkflowSummaryNotification`
       コマンドを登録（例: `daily()`）。[完了]
    6. **スケジューラー実行方式の変更:** Laravel Sail 環境での安定性と設定の容易さを考慮し、cron を利用する代わりに、*
       *`schedule:work` コマンド** を使用する方式に変更。`docker-compose.yml` に `scheduler` サービス（または `queue`
       サービスを拡張）を追加/修正し、`php artisan schedule:work` を実行するように設定。[完了]
* **動作確認:**
    * 手動で `php artisan workflow:send-summary` を実行し、期待されるユーザー（未処理タスクがあり、`notify`
      権限を持つ）に集約通知が届く（リストに表示される）ことを確認。[完了]
    * `schedule:work` を実行しているコンテナが正常に動作し、スケジュールされた時刻（例: `daily()` なら翌日、テスト用に
      `everyMinute()` に変更して1分後）にコマンドが自動実行され、通知が送信されることを確認。[完了]
* **成果物:** 定期的に担当者へ未処理タスク件数を通知する集約通知機能と、その実行基盤。
* **ドキュメント更新:** このセクションを更新。「機能詳細(集約通知)」「関連ファイル(Command, Kernel, NotificationService,
  WorkflowSummaryNotification)」更新。**スケジューラー実行方式が `schedule:work` に変更された点**、**受信条件が `notify`
  Permission に変更された点**を明記。

---

### ✅ ステップ 6.7: メール通知用 Permission 定義とロールへの初期割当 (完了)

* **目的:** ワークフロー関連のメール通知を受け取るかどうかを制御するための **Permission**
  を定義し、初期ロールに割り当てる。管理者がロール/ユーザーに権限設定できるようにする。
* **実施済みタスク:**
    1. **Permission 定義:** `RolesAndPermissionsSeeder` で、メール通知の受信権限を示す新しい Permission を**中粒度**
       で作成 (`receive_workflow_summary_email`, `receive_workflow_action_email`)。`description` も設定。[完了]
    2. **初期ロールへの割り当て:** `RolesAndPermissionsSeeder`
       内で、デフォルトロール (`Super Admin`, `Organization Admin`, `Project Manager`, `Editor`) に、作成したメール通知
       Permission を `syncPermissions` を使って割り当て。[完了]
    3. **Filament UI 更新 (`RoleResource.php`, `UserResource.php`):**
        * ロール/ユーザー編集フォーム (`form()`) 内の Permission 選択コンポーネント (`Select`) をカスタマイズし、権限を*
          *グループ化**して表示するように変更。新しいメール通知関連 Permission も表示・選択・保存可能。[完了]
        * ユーザー編集フォームに、ユーザーへ**直接** Permission を割り当てるための Select コンポーネントを追加。[完了]
    4. **翻訳ファイル更新 (`permission.php`):** 新しい Permission
       名に対応する翻訳キー (`permission.name.receive_...`) と、グループ化用の翻訳キー (`permission.group.*`)
       を追加。[完了]
    5. **Seeder 実行:** `php artisan db:seed --class=RolesAndPermissionsSeeder` (または `migrate:fresh --seed`)
       を実行し、Permission とロールへの割り当てをデータベースに反映。[完了]
    6. **権限名変更:** フォルダ権限設定に関する Permission 名を `*_rolefolderpermissions` から
       `*_folder_permissions` に変更（翻訳キー、グループ化ロジック含む）。[完了]
* **動作確認:**
    * `permissions` テーブルに新しいメール通知関連の権限が登録されていることを確認。[完了]
    * Filament のロール編集画面およびユーザー編集画面で、権限が**グループ化されて**
      表示され、新しい権限がチェックを入れて保存・解除できることを確認。[完了]
    * 初期設定したロールを持つユーザーが、期待通りにメール通知権限を持っていること（例: Tinker で
      `$user->can('receive_workflow_summary_email')` を実行して確認）。[完了]
* **成果物:** メール通知の受信を制御するための Permission 基盤と、管理者による設定 UI。
* **ドキュメント更新:** 「機能詳細(通知設定との連携)」「関連ファイル(Seeder, RoleResource, UserResource)」更新。メール通知制御に
  Permission を利用する方式、定義された Permission の種類と目的、UIでのグループ化について追記。権限名の変更 (
  `folder_permissions`) を反映。[完了]

---

### ✅ ステップ 6.8: 個人用メール通知設定画面の実装 (完了)

* **目的:** ユーザーが自身の通知設定画面 (`/notifications/settings`) で、メール通知の受信設定（直接割り当てられた
  Permission）を ON/OFF できるようにする。ロールによって設定が強制されている場合は、その旨を表示し変更不可とする。
* **実施済みタスク:**
    1. **Livewire コンポーネント改修 (`App\Livewire\Notifications\Settings.php`):**
        * 既存のモックデータを削除し、`mount` でログインユーザー (`Auth::user()`) と対象 Permission (
          `targetPermissionNames`) を取得。
        * `loadSettings` メソッドで、各 Permission についてユーザーの権限状態（`can()`, `hasDirectPermission()`,
          `hasPermissionTo()`）を判定。
        * ビュー表示用の `$notificationSettings` 配列を準備 (`name`, `label`, `description` [翻訳キー使用], `enabled`,
          `is_direct`, `via_role`, `disabled`)。
        * `#[Title]` 属性でページタイトルを設定（翻訳キー参照）。
        * `Mary\Traits\Toast` を使用し、`save()` メソッドで保存成功・失敗時にトースト通知 (`toastSuccess`, `toastError`)
          を表示するように変更。
        * `save()` メソッドで、ロール強制設定 (`$setting['disabled']`) をスキップし、トグルの状態に基づいてユーザーの直接
          Permission を `givePermissionTo` または `revokePermissionTo` で更新するロジックを実装。
        * `canSaveChanges` Computed プロパティを実装し、変更可能な設定項目がない場合に `false` を返すようにした。
    2. **Blade ビュー改修 (`resources/views/livewire/notifications/settings.blade.php`):**
        * `<x-mary-header>` を使用して画面タイトルを表示。
        * 画面説明文、ラベル、ツールチップ等のテキストを翻訳キー (`ledger.php`, `permission.php`) 参照に変更。
        * `$notificationSettings` 配列をループし、各 Permission に対する `<x-mary-toggle>` を表示。
        * `wire:model` でコンポーネントのプロパティにバインド。
        * ロールによる設定強制がある場合 (`$setting['disabled']` が true)、トグルを `:disabled` で無効化し、親要素に
          `opacity-60`, `bg-base-200`, `cursor-not-allowed` クラスを適用。さらに `<div class="tooltip">` でツールチップを表示。
        * 保存ボタンの表示を `@if($this->canSaveChanges())` で制御し、変更可能な項目がない場合は非活性状態のボタンを表示（または非表示）。
    3. **翻訳ファイル更新 (`ledger.php`, `permission.php`):** 画面タイトル、説明文、ツールチップ、ボタンラベル等の翻訳キーを追加・修正。
       `permission.descriptions` キーを追加。
* **動作確認:**
    * 通知設定画面 (`/notifications/settings`) に正しいページタイトルと画面ヘッダーが表示されること。[完了]
    * メール通知 ON/OFF のトグルが表示・制御できること。[完了]
    * ロールで設定されている項目はトグルが無効化され、ツールチップが表示されること。[完了]
    * ユーザーがトグルを操作して「保存」ボタンを押すと、成功/失敗の**トースト通知**が表示され、ユーザーの直接 Permission
      が更新されること (DB `model_has_permissions` テーブル等で確認)。[完了]
    * **変更可能な設定項目がない場合、保存ボタンが非活性になること。**[完了]
    * 保存後、画面のトグル状態が正しく更新されること。[完了]
* **成果物:** ユーザーが自身のメール通知設定（直接権限）を管理でき、ロール設定による制限も理解できる
  UI。設定結果はトーストでフィードバックされる。
* **ドキュメント更新:** 「機能詳細(通知設定との連携)」「関連ファイル(Settingsコンポーネント, Userモデル, Permissionモデル,
  翻訳ファイル)」更新。個人用通知設定画面の実装詳細、Toast通知、ページタイトル、ボタン非活性化について追記。[完了]

---

### ✅ ステップ 6.9: 個別通知と集約通知の実装 (メール送信) (完了)

* **目的:** ワークフローのアクションに応じて、設定に基づき個別通知と集約通知を**メールで送信**する。
* **実施済みタスク:**
    1. **Mailable クラス作成:**
        * `app/Mail/WorkflowActionMail.php`: 個別アクション通知用。コンストラクタで `NotificationType`, `LedgerDiff`,
          `User`, `comment` を受け取り、タイプに応じて件名、本文、アクションURLを翻訳キーを使用して設定。
        * `app/Mail/WorkflowSummaryMail.php`: 集約通知用。コンストラクタで未処理件数を受け取り、件名、本文、アクションURLを翻訳キーを使用して設定。
    2. **Markdown メールテンプレート作成:**
        * `resources/views/emails/workflow/action.blade.php`: `WorkflowActionMail` 用。渡された変数を表示し、Laravel
          の標準メールコンポーネント (`<x-mail::message>`, `<x-mail::button>` など) を使用。**行頭インデントを削除し、Markdown
          レンダリングを修正。**
        * `resources/views/emails/workflow/summary.blade.php`: `WorkflowSummaryMail` 用。渡された未処理件数を表示し、タスク一覧へのリンクボタンを設置。
          **行頭インデントを削除し、Markdown レンダリングを修正。**
    3. **Notification クラス修正:**
        * `app/Notifications/GenericNotification.php`:
            * `via()` メソッドを修正。`$notifiable` (User) が `receive_workflow_action_email` 権限を持ち、かつ通知タイプがワークフロー関連の場合のみ
              `'mail'` チャネルを追加するように変更。
            * `toMail()` メソッドを追加（または修正）。`WorkflowActionMail` を生成し、必要なデータを渡して返すように実装。*
        * `app/Notifications/WorkflowSummaryNotification.php`:
            * `via()` メソッドを修正。`$notifiable` (User) が `receive_workflow_summary_email` 権限を持つ場合のみ
              `'mail'` チャネルを追加するように変更。
            * `toMail()` メソッドを追加（または修正）。`WorkflowSummaryMail` を生成し、件数を渡して返すように実装。
    4. **`NotificationService` 確認:**
        * `sendWorkflowNotification`: メール送信可否の Permission チェックは Notification クラスの `via()`
          に任せるため、Service 側でのチェックは不要であることを確認。システム内通知の判定 (`shouldReceiveNotification`)
          は維持。
        * `sendWorkflowSummaryNotification`: 同様に Permission チェックは Notification クラスの `via()` に任せることを確認。
    5. **翻訳ファイル更新 (`ledger.php`):** メール件名、本文、ボタンテキストなどの翻訳キーを追加。
    6. **環境設定確認 (`.env`):** Mailpit への接続設定（`MAIL_HOST`, `MAIL_PORT` 等）を確認。
* **動作確認:**
    * 各ワークフローアクション発生時、および集約通知コマンド実行時に、対応する Permission
      を持つユーザーにメールが送信されること (Mailpit で確認)。[完了]
    * Permission がないユーザーにはメールが送信されないこと。[完了]
    * メールの内容（件名、本文、リンク）が各シナリオで正しいこと。[完了]
    * メールテンプレートが正しくレンダリングされていること（インデント問題解消）。[完了]
    * システム内通知が引き続き正しく動作すること。[完了]
    * キュージョブがエラーなく処理されること。[完了]
* **成果物:** ユーザーの権限設定に基づき、ワークフロー関連通知がメールでも送信される機能。
* **ドキュメント更新:** 「機能詳細(通知送信)」「関連ファイル(Mailable, Notificationクラス, Service, Command, 翻訳ファイル)
  」更新。メール送信ロジック、Permission チェック、Mailable/テンプレート実装、キュー処理について追記。[完了]

---

### ✅ ステップ 7: フォルダ権限への「点検」「承認」追加と包含関係対応 (完了)

* **目的:** 点検・承認権限を定義し、複数権限を管理できるテーブル構造とUIを実装し、包含関係を考慮したアクセス制御を行う。
* **方針:** `RoleFolderPermission` テーブルは**既存の構造（`permission` Enum に全タイプ、複合ユニーク制約維持）を維持**
  し、アクセス権限 (`READ`～`ADMIN`) と通知設定 (`NOTIFY_ON`/`OFF`) を同じテーブルで管理する。アクセス権限は **Role-Folder
  ごとに複数の権限レコードを持つ**ことを許容する。権限チェック時に包含関係 (`ADMIN` > `APPROVE` > `INSPECT` > `WRITE` >
  `READ`) を考慮する。アクセス権限の設定は `FolderPermissionRelationManager` で、通知設定は
  `NotificationSettingsRelationManager` で行う。
* **実施済みタスク:**
    1. **Enum 修正 (`FolderPermissionType.php`):**
        * `INSPECT`, `APPROVE` ケースを追加。
        * `accessPermissions()` 等のヘルパーメソッド修正。
        * 包含関係定義 (`HIERARCHY`) と `includes()` メソッドを追加。
    2. **DBマイグレーション修正 (`...create_role_folder_permissions_table.php`):**
        * `permission` カラムの `enum()` 定義に `'inspect'`, `'approve'` を追加。
        * **既存の複合ユニーク制約 (`role_id`, `folder_id`, `notification_type_id`, `permission`) を維持。** (
          *これにより、アクセス権限レコードでは `notification_type_id` は NULL として保存される*)
    3. **Filament UI 修正 (`FolderPermissionRelationManager.php` - `NotificationSettingsRelationManager`
       をベースに作成):**
        * `$relationship` を `roleFolderPermissions` に設定。
        * `query()` でアクセス権限レコード (`permission` が `READ`～`ADMIN`) のみをフィルタリング。
        * テーブルカラム: フォルダ名でグループ化し、各行に設定されているアクセス権限 (`permission`) のラベルとバッジを表示。
        * ヘッダーアクション (`Action::make('create')`):
            * フォームで単一フォルダ (`SelectTree`) と複数アクセス権限 (`CheckboxList`) を選択。
            * `action` で既存のアクセス権限レコードを削除し、選択された（包含関係適用済み）権限に対応する
              `RoleFolderPermission` レコードを複数作成 (保存時 `notification_type_id` は `NULL`)。
            * *当初案の `CreateAction` は `getKey()` エラーのためカスタムアクションに変更。*
        * アクション (`EditAction::make`, `DeleteAction::make`, `Action::make('detach_folder_permissions')`):
            * 編集: モーダルで `CheckboxList` を使用し、現在のアクセス権限をロード・編集。保存時に既存レコード削除＆新規作成。
            * 削除: 特定の権限レコード (`RoleFolderPermission`) を削除。
            * 全解除: 特定フォルダに対する全アクセス権限レコードを削除。
        * 包含関係ハンドリング (UI連動): `CheckboxList` の `afterStateUpdated`
          で包含関係に基づいてチェックを自動更新するヘルパーメソッド (`applyPermissionHierarchy`) を使用。
        * *当初案の `AttachAction` は `Folder` モデルとのリレーションシップを前提としていたため、`RoleFolderPermission`
          を直接操作するカスタムアクション (`Action::make('create')`) に変更。*
        * *`FolderRelationManager` のベースとして `NotificationSettingsRelationManager`
          を使用し、アクセス権限管理に特化するように改修。*
    4. **`UserService::hasFolderPermission` 修正:**
        * 指定された権限タイプまたはそれより上位の権限を持つ `RoleFolderPermission`
          レコードが、対象ユーザーのロールと対象フォルダ/祖先フォルダの組み合わせで**アクセス権限として**（`permission` が
          `READ`～`ADMIN`）存在するか確認するようにロジックを修正。
    5. **`WorkflowService` 権限チェック確認:** `UserService::hasFolderPermission`
       の呼び出し箇所を確認。権限チェックが正しく機能することを確認。[完了]
    6. **`NotificationSettingsRelationManager` 確認:** この Relation Manager が通知設定 (`permission` が `NOTIFY_ON`/
       `OFF`, `notification_type_id` を使用) のみを扱うようにフィルタリングやロジックが維持されていることを確認。[完了] (
       *今回は変更なし*)
    7. **翻訳ファイル更新:** 新権限名、UIラベル、成功/失敗メッセージ等の翻訳キーを追加。[完了]

* **成果物:** 点検・承認権限が定義され、Role-Folder
  ごとに複数のアクセス権限を設定・管理できるUI。包含関係を考慮した権限チェック機構。通知設定とアクセス権限設定のUI分離（テーブル構造は共用）。
* **ドキュメント更新:** 上記の実施タスクと成果物を反映。[完了]

---

### ✅ ステップ 8: 実績ベースの担当者選択支援機能の実装 (完了)

* **目的:** 申請者（または点検者）が、点検・承認依頼時に、実績・権限・直近履歴に基づいた候補リストから迷わず効率的に担当者を選択できるUIを提供する。
* **方針:**
    * 担当者選択UIを再利用可能なモーダルコンポーネント (`WorkflowAssigneeModal` と `WorkflowAssigneeSelect`) として実装。
    * モーダル内で、実績多数ユーザー、権限保有ユーザー、直近担当者を統合し、推奨度順にソートした単一の検索可能リストを表示。各候補には推奨理由を示すアイコン/タグを表示。
    * 親コンポーネント (台帳作成/編集画面、詳細画面、承認待ちリスト) からこのモーダルを呼び出し、選択結果を受け取ってワークフロー処理を実行する。
    * **台帳定義への静的な推奨担当者設定は行わず、動的な実績データと権限情報に基づいて候補を提示する。**
* **実施済みタスク:**
    1. **担当者候補取得ロジック実装 (`WorkflowService`, `WorkflowAssigneeSelect`):**
        *
       `WorkflowService::getFrequentAssignees(int $ledgerDefineId, string $roleType, int $limit, string $searchQuery = '')`:
       特定の台帳定義と役割タイプ（点検者/承認者）において、過去に頻繁に担当したユーザーを登場回数順に取得するメソッドを実装。ユーザー名での検索機能も含む。
        * `WorkflowAssigneeSelect::getRecentAssignee(?int $ledgerId, string $roleType)`:
          特定の台帳レコードの直近の点検者/承認者を取得するメソッドを実装。
        * `WorkflowAssigneeSelect::fetchInspectorOptions()`, `fetchApproverOptions()`:
            * `roleType` に応じて、上記 `getFrequentAssignees`、`UserService::getUsersWithFolderPermission`、
              `getRecentAssignee` を呼び出し、実績・権限・直近の各候補リストを取得。
            * 取得したリストを統合し、重複を除外。各候補に推奨理由（実績多数、権限あり、直近担当）を付与し、表示優先度を設定。
            * 優先度と名前でソートした最終的な候補リストを生成するロジックを実装。
    2. **担当者選択Livewireコンポーネント作成 (`WorkflowAssigneeSelect.php`, `workflow-assignee-select.blade.php`):**
        * プロパティ (`ledgerDefineId`, `folderId`, `roleType`, `ledgerId`, `@Modelable selectedUserId`, `searchQuery`,
          `options`) を定義。
        * MaryUI Select (`<x-mary-select searchable>`) を使用し、サーバーサイド検索 (`search-function`) で担当者候補を動的に表示。
        * `searchAssignees(string $value = '')` メソッド: ユーザー入力に応じて `fetch*Options`
          を呼び出し、選択中のユーザーを必ず含めた上でオプションリストを更新。
        * `updatedSelectedUserId()`: 選択変更時にオプションを再読み込みし、表示を維持。
        * オプション表示には理由アイコン/タグを含める。
    3.
        *
  *担当者選択モーダルLivewireコンポーネント作成 (`WorkflowAssigneeModal.php`, `workflow-assignee-modal.blade.php`):
  **
    * モーダル表示状態 (`showModal`) を管理。
    * 親からパラメータ (`ledgerDefineId`, `folderId`, `roleType`, `ledgerId`, `initialUserId`) を受け取る
      `openModal()` メソッド（イベントリスナー `#[On('open-assignee-modal')]`）を実装。
    * 内部で `@livewire('workflow.workflow-assignee-select', ...)` を呼び出し、`initialUserId`
      を渡して初期選択を設定し、選択された担当者ID (`selectedUserId`) を自身のプロパティとバインド。
    * 「担当者を選択」ボタンで親コンポーネントに `assignee-selected` イベント（選択された `userId`, `roleType`
      を含む）を発行し、モーダルを閉じる。
        4. **親コンポーネント修正 (`CreateColumn.php`, `ModifyColumn.php`, `Show.php`, `PendingList.php`):**
            * 点検依頼/承認申請ボタンのクリックアクションを、モーダルを開くメソッド (
              `openAssigneeModal('inspector'/'approver')`) 呼び出しに変更。
            * `openAssigneeModal()`: モーダルに渡すパラメータを設定。実績ベースで初期選択ユーザーIDを決定し、
              `open-assignee-modal` イベントを発行。
            * `assignee-selected` イベントをリッスンするメソッド (
              `handleAssigneeSelected(int $userId, string $roleType)`)
              を実装。受け取った `userId` を使って `WorkflowService` の `requestInspection` / `requestApproval` を呼び出す。
            * 各親コンポーネントから `getFrequentAssigneesInternal` を `WorkflowService::getFrequentAssignees` の呼び出しに修正。
* **動作確認:**
    * 台帳作成/編集画面で点検依頼ボタンクリック → 下書き保存 → 実績ベースで初期選択された点検者候補モーダル表示 → 選択 →
      点検依頼実行。[完了]
    * 台帳詳細画面/承認待ちリストで点検完了（承認申請）ボタンクリック → 実績ベースで初期選択された承認者候補モーダル表示 →
      選択 → 承認申請実行。[完了]
    * モーダル内の検索機能、候補リストの表示順、理由アイコンが期待通り動作すること。[完了]
    * 他のワークフローアクション（承認、戻し）が引き続き正しく動作すること。[完了]
* **成果物:** 再利用可能な担当者選択モーダルコンポーネント。実績・権限・直近履歴に基づいた、よりインテリジェントな担当者候補提示機能。UIから直接担当者を選択する部分をモーダルに集約。
* **ドキュメント更新:** 上記の実施タスクと成果物を反映。[完了]

---

### ✅ ステップ 9: 関連タスクリストの表示とシンプルなタスク引き継ぎ (完了)

* **目的:** ログインユーザーに関連するワークフロータスク（自分宛、自分が申請、引き継ぎ可能）を1画面に集約して表示し、状況に応じたアクション（主に詳細画面への遷移と、タスク引き継ぎ）を可能にする。
* **方針:** 通知画面に「自分宛のタスク」リストと「その他の関連タスク」リストを上下に表示。タスク引き継ぎアクションは「その他の関連タスク」リストから実行し、コメント入力モーダルを経由。新しい通知タイプ
  `task_claimed` を使用。
* **実施済みタスク:**
    1. **通知タイプ `task_claimed` の定義:**
        * `notification_types` テーブルに `task_claimed` を追加 (Seeder)。
        * 翻訳キー (`ledger.notification_types.task_claimed`, メール件名・本文用キー) を追加。
        * Mailable (`TaskClaimedMail.php`) とメールテンプレート (`emails/workflow/task-claimed.blade.php`)
          を作成。受信者タイプに応じてメッセージを調整。
        * `GenericNotification.php` を拡張し、`task_claimed` 通知タイプと関連パラメータ (`originalAssignee`) を扱えるように
          `via()` と `toMail()` を修正。
    2. **Livewireコンポーネント改修/作成:**
        * **`PendingList.php` (自分宛のタスク - 既存):**
            * 表示項目（台帳名, ステータス, 申請者, 申請日時, 滞留時間など）を調整。
            * アクションボタンを「詳細表示」に集約（編集権限に応じて「編集」も）。
            * `refreshPendingList` イベントリスナーを追加し、他のリストの変更に応じて自身を更新。
        * **`OtherRelatedTasksList.php` (その他の関連タスク - 新規):**
            * **データ取得ロジック (`loadTasks()`):**
                * `fetchMySubmissionsPendingOthers()`: 「自分が申請したが、現在他の人が担当しているタスク」を取得・整形。
                * `fetchClaimableTasks()`: `UserService::getClaimableTasks()` を呼び出し「引き継ぎ可能なタスク」を取得・整形。
                * 上記を結合・重複排除・ソートし、整形済みデータ (`task_type` 含む) をビューに渡す。
            * **テーブル表示項目:** 台帳名, ステータス, 現在の担当者名, 申請者名, 申請日時, 滞留時間, タスクタイプ (
              「申請中」「引き継ぎ可能」など)。
            * **アクションボタン:** 「詳細表示」「編集」(自分が申請したタスク用、権限に応じて)、「引き継ぐ」(
              引き継ぎ可能なタスク用、ツールチップ付き)。
    3. **ビュー作成/修正:**
        * **`other-related-tasks-list.blade.php` (新規):** 上記テーブルとアクションボタンを実装。
        * **`pending-list.blade.php` (既存修正):** 表示項目とアクションボタンを修正。
        * **`notifications/index.blade.php` (既存修正):** 1画面内に `@livewire('workflow.pending-list')` と
          `@livewire('workflow.other-related-tasks-list')` を上下に配置。
        * **`livewire/workflow/claim-task-comment-modal.blade.php` (新規):** 引き継ぎコメント入力用モーダルビュー。
    4. **タスク引き継ぎアクション実装 (`OtherRelatedTasksList.php`):**
        * `openClaimTaskCommentModal(int $ledgerId)`: 対象タスク情報を保持し、コメント入力モーダルを表示。
        * `claimTaskWithComment()`: `WorkflowService::claimTask()` を呼び出し、成功時にリスト更新 (`loadTasks()`,
          `refreshPendingList` イベント発行)、トースト通知表示。
    5. **`WorkflowService::claimTask(Ledger $ledger, User $claimer, ?string $comments)` 実装:**
        * バリデーション（ステータス、申請者/担当者重複、権限）。
        * トランザクション内で LedgerDiff 作成（担当者変更、コメント記録）、Ledger 更新（`latest_diff_id`, `modifier_id`
          ）、カウンター調整。
        * `NotificationService::sendWorkflowNotification` を使用し、通知タイプ `task_claimed` で申請者、元担当者、新担当者（引き継ぎ者）に通知送信。
    6. **`UserService::getClaimableTasks(User $user)` 実装:**
        * ユーザーが `INSPECT` または `APPROVE` 権限を持つフォルダを特定 (子孫含む)。
        * 該当フォルダに属し、進行中で、自分が担当者でも申請者でもない `Ledger` を取得。
* **動作確認:**
    * 通知画面に2つのリストが上下に表示されることを確認。[完了]
    * 「その他の関連タスク」リストに「自分が申請したタスク」「引き継ぎ可能なタスク」が正しく表示されることを確認。[完了]
    * 各タスクのアクションボタンが適切に表示されることを確認。[完了]
    *
  引き継ぎボタン→コメントモーダル表示→引き継ぎ実行→担当者変更・カウンター更新・Diff作成・Ledger更新・通知送信（システム内・メール）・リスト表示更新・トースト通知の一連の流れを確認。[完了]
    * 各種バリデーションはユニットテストで網羅的に確認。[要テスト]
* **成果物:**
    * 「自分宛」と「その他関連（自分が申請/引き継ぎ可能）」の2つのリストに集約されたタスク管理UI。
    * シンプルなタスク引き継ぎ機能と、関係者への適切な通知。
* **ドキュメント更新:** 上記の実施タスクと成果物を反映。[完了]

---

### ステップ 10: 台帳詳細/編集画面からのワークフローアクションと変更点提示

* **目的:** 点検者または承認者が、**台帳詳細画面を起点**として、必要に応じて内容を編集（編集画面で行う）し、コメントを付与して、ワークフローを進行できるようにする。その際、
  **再上程の場合は、カラムごとに変更点を分かりやすく提示する**ことを目指す。
* **方針:**
    * タスクリストからは原則として台帳詳細画面 (`Show.php`) に遷移させる。
    * 詳細画面がワークフローアクションの主要な起点の一つとなる。ここでは**カラムごとの差分表示**を重視する。
    * 内容編集は詳細画面から編集画面 (`ModifyColumn.php`) に遷移して行う。
    * ワークフローアクション（点検完了、承認、差し戻し）実行時には、コメント入力モーダルを表示する。
    * 編集画面からも、内容保存と同時にコメント付きでワークフローアクションを実行できるようにする。
    * `LedgerDiff` には、ステータス変更時のコメントと、内容変更の両方を適切に記録する。
* **タスク:**
    1. **差分表示機能の検討・実装 (`Show.php`):**
        * **差分対象の特定:** 現在の `Ledger` の `content` と、比較対象となる過去の `LedgerDiff` の `content`
          を特定するロジックを実装（例: このワークフローが開始された時点のDiff、または前回DRAFTでなかった時点のDiff）。
        * **カラムごとの差分比較:** 特定した2つの `content`（JSON配列を想定）をカラムごとに比較し、変更があったカラムを特定する。
        * **差分表示方法:**
            * 変更があったカラムの値を並べて表示し、視覚的に差が分かるようにする（例: 背景色変更、太字など）。
            * **HTMLレベルでの詳細な差分表示の検討:**
              文字列ベースのカラムについては、変更前後のテキストをHTMLとして比較し、行単位や単語単位での変更箇所をハイライトする機能の導入を検討する（自力実装または適切なJavaScriptライブラリの利用）。既存の
              `DiffDisplay` コンポーネントの改良または新規作成。
        * 詳細画面の「基本情報」タブ（または専用の「変更点」タブ）に、上記差分情報を表示するUIを実装。
    2. **コメント入力モーダル (`WorkflowCommentModal.php` - 新規作成):**
        * 汎用的なコメント入力モーダルコンポーネントを作成。
        * 親からアクションタイプ（`approve_with_comment`, `return_to_draft_with_comment` 等）と対象 `ledgerId` を受け取る。
        * 実行ボタンクリック時に、入力されたコメントとアクションタイプを親にイベントで通知する。
    3. **台帳詳細画面 (`Show.php`) のアクションボタン実装:**
        * フッターまたは適切な位置に、現在のステータスとログインユーザーの権限に応じたアクションボタンを配置:
            * 「承認する」（`canApprove` 時）: クリックでコメント入力モーダル (`actionType='approve'`) を開く。
            * 「点検完了（承認申請する）」（`canRequestApproval` 時）: クリックで担当者選択モーダル (`WorkflowAssigneeModal` で
              `roleType='approver'`) を開く。**このモーダルにコメント入力欄を追加するか、別途コメントモーダルを挟むことを検討。
              **
            * 「作成中に戻す」（`canReturnToDraft` 時）: クリックでコメント入力モーダル (`actionType='return_to_draft'`)
              を開く。
            * 「編集する」: 編集画面へ遷移。
        * コメントモーダル/担当者選択モーダルからのイベントを受け取り、コメントと共に `WorkflowService` の対応メソッドを呼び出す。
    4. **台帳編集画面 (`ModifyColumn.php`) のアクションボタン実装:**
        * フッターのアクションボタンエリアを改修:
            * 「下書き保存」ボタン。
            * **自分が現在の担当者（点検者/承認者）の場合:**
                * 「コメントを付けて点検完了（承認申請）」ボタン: クリック → **現在のフォーム内容で下書き保存** →
                  担当者選択モーダル（コメント入力欄付き、または別途コメントモーダル） → `WorkflowService::requestApproval`
                  呼び出し。
                * 「コメントを付けて承認」ボタン: クリック → **現在のフォーム内容で下書き保存** → コメント入力モーダル →
                  `WorkflowService::approve` 呼び出し。
                * 「コメントを付けて作成中に戻す」ボタン: クリック → コメント入力モーダル →
                  `WorkflowService::returnToDraft` 呼び出し（フォーム内容は保存しない）。
            * **自分が申請者でDRAFT状態の場合:**
                * 「点検依頼」ボタン（担当者選択モーダルを開く）。
    5. **`WorkflowService` メソッド修正:**
        * `requestApproval(ledgerId, approverId, inspectorId, ?string $comments = null)`: コメントを `LedgerDiff` に記録。
        * `approve(ledgerId, approverId, ?string $comments = null)`: コメントを `LedgerDiff` に記録。
        * `returnToDraft(ledgerId, modifierId, ?string $comments)`: コメントを `LedgerDiff` に記録 (
          コメントは必須化も検討)。
        * **内容変更の記録:** 編集画面からのアクションの場合、`WorkflowService` 側では、事前に `ModifyColumn` で内容が保存された最新の
          `Ledger` および対応する `LedgerDiff` を参照し、ステータス変更とコメントを記録した**追加の `LedgerDiff`** (
          contentなし) を作成する。
    6. **通知処理の修正:**
        * 各種ワークフローアクションの通知メール・システム内通知の文面に、入力されたコメントを含めるように
          `GenericNotification` や関連Mailableを修正。
        * 内容が変更された上で点検/承認された場合、その旨を通知に含めることを検討 (例: 「内容が更新され、承認されました」)。
* **成果物:**
    * 詳細画面を中心とした、差分確認とワークフローアクション実行のフロー。
    * 点検者/承認者が内容変更を行う場合、その変更履歴とコメントが明確に記録・通知される仕組み。
    * より実運用に即した、確認と承認のプロセス。

---

## 今後の展望 / 要検討事項

* **要検討事項:**
    * **複数点検者への対応:** 現状は点検者/承認者は各1名を想定しているが、複数名での点検/承認が必要なケース（並列、段階）に対応するかどうか。その場合の担当者管理、ステータス遷移、通知の扱い。
    * **点検者による承認状況フォロー:** 点検者が自分が点検完了した案件の最終承認状況を追跡・確認できる機能（専用リスト、通知など）。
    * 承認済み (`APPROVED`) レコードの変更・編集解除プロセス。
    * ロール指定承認の詳細な挙動。
    * 担当者選択 UI のコンポーネント選定。
    * 通知カウンターの実装方法。
    * ブラウザ通知の実装方針。
    * 点検ステップが不要な場合のワークフロー。
    * 履歴表示機能: `LedgerDiff` の履歴表示の改善（差分表示など）。
    * Activity Log の活用: 補助的な履歴記録として活用するかどうか再検討。
* ファイルアップロード等へのワークフロー適用。
* 承認ルートの高度化。
* 期限と督促機能。
* 代理機能。
