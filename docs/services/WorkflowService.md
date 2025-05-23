# WorkflowService

## サービスの責務
台帳 ([`Ledger`](../models/Ledger.md)) のワークフロー（下書き、点検依頼、承認依頼、承認、差し戻しなど）に関連するビジネスロジックを専門に扱います。台帳のステータス変更、関連する差分 ([`LedgerDiff` - `Ledger`モデルドキュメント内の関連セクション参照](../models/Ledger.md#リレーションシップ)) の作成、担当者の更新、および関連する通知のトリガーなどを担当します。

より詳細なワークフロー機能の概要については、[ワークフロー機能説明](/docs/function/WorkFlow.md) も参照してください。

## 主要な公開メソッド

*   **`saveDraft(?int $ledgerId, int $ledgerDefineId, array $content, array $contentAttached, int $modifierId): array`**:
    *   目的・機能: 台帳の下書きを保存します。新規作成または既存の下書きを更新します。新しい `LedgerDiff`（内容スナップショットを含む）を作成し、[`Ledger`](../models/Ledger.md) 本体も更新します。
    *   引数:
        *   `$ledgerId`: 既存の台帳ID (新規の場合は `null`)。
        *   `$ledgerDefineId`: [`LedgerDefine`](../models/LedgerDefine.md) のID。
        *   `$content`: 台帳の主要なデータ内容。
        *   `$contentAttached`: 添付ファイルの検索用インデックス情報。
        *   `$modifierId`: 操作を行ったユーザーのID。
    *   戻り値: `array{ledger: Ledger, ledgerDiff: LedgerDiff}` - 更新/作成された `Ledger` と `LedgerDiff` の連想配列。
    *   特記事項: 承認済みのレコードは編集できません。

*   **`requestInspection(int $ledgerId, int $requesterId, int $inspectorId): Ledger`**:
    *   目的・機能: 台帳の点検を依頼します。[`Ledger`](../models/Ledger.md) のステータスを「点検待ち」に更新し、関連する `LedgerDiff`（内容は空、ワークフロー情報のみ）を作成します。点検担当者の未処理タスクカウンターを増加させ、通知を送信します。
    *   引数:
        *   `$ledgerId`: 台帳ID。
        *   `$requesterId`: 点検依頼を行ったユーザーID。
        *   `$inspectorId`: 次の点検担当者のユーザーID。
    *   戻り値: `Ledger` - 更新後の [`Ledger`](../models/Ledger.md) モデル。
    *   特記事項: 下書き状態からのみ点検依頼が可能です。

*   **`requestApproval(int $ledgerId, int $approverId, int $inspectorId, ?string $comments): Ledger`**:
    *   目的・機能: 台帳の承認を申請します（点検完了後に実行）。[`Ledger`](../models/Ledger.md) のステータスを「承認待ち」に更新し、関連する `LedgerDiff` を作成します。点検担当者のカウンターを減らし、承認担当者のカウンターを増やします。申請者と承認担当者に通知を送信します。
    *   引数:
        *   `$ledgerId`: 台帳ID。
        *   `$approverId`: 次の承認担当者のユーザーID。
        *   `$inspectorId`: 点検操作を行ったユーザーID。
        *   `$comments`: 点検コメント。
    *   戻り値: `Ledger` - 更新後の [`Ledger`](../models/Ledger.md) モデル。
    *   特記事項: 点検待ち状態で、かつ操作者が現在の点検担当者である必要があります。

*   **`approve(int $ledgerId, int $approverId): Ledger`**:
    *   目的・機能: 台帳を承認します。[`Ledger`](../models/Ledger.md) のステータスを「承認済み」に更新し、バージョンをインクリメントします。関連する `LedgerDiff` を作成します。承認担当者のカウンターを減らし、申請者に通知を送信します。
    *   引数:
        *   `$ledgerId`: 台帳ID。
        *   `$approverId`: 承認操作を行ったユーザーID。
    *   戻り値: `Ledger` - 更新後の [`Ledger`](../models/Ledger.md) モデル。
    *   特記事項: 承認待ち状態で、かつ操作者が現在の承認担当者である必要があります。

*   **`returnToDraft(int $ledgerId, int $modifierId, ?string $comments): Ledger`**:
    *   目的・機能: 台帳を「下書き」状態に差し戻します（点検者または承認者が操作）。[`Ledger`](../models/Ledger.md) のステータスを更新し、関連する `LedgerDiff` を作成します。元の担当者のカウンターを調整し、申請者に通知を送信します。
    *   引数:
        *   `$ledgerId`: 台帳ID。
        *   `$modifierId`: 差し戻し操作を行ったユーザーID。
        *   `$comments`: 差し戻し理由のコメント。
    *   戻り値: `Ledger` - 更新後の [`Ledger`](../models/Ledger.md) モデル。
    *   特記事項: 下書きまたは承認済み状態からは差し戻しできません。

*   **`saveEditedRecord(Ledger $ledger, array $newContent, $newContentAttached, int $modifierId, ?string $comments): array`**:
    *   目的・機能: 点検中または承認待ちの台帳が編集された場合に、その内容を保存しステータスを「下書き」に戻します。新しい `LedgerDiff`（編集後の内容を含む）を作成し、[`Ledger`](../models/Ledger.md) 本体も更新します。
    *   引数:
        *   `$ledger`: 編集対象の [`Ledger`](../models/Ledger.md) モデル。
        *   `$newContent`: 編集後の新しい台帳データ。
        *   `$newContentAttached`: 編集後の新しい添付ファイル情報。
        *   `$modifierId`: 編集操作を行ったユーザーID。
        *   `$comments`: 編集理由のコメント。
    *   戻り値: `array{ledger: Ledger, ledgerDiff: LedgerDiff}` - 更新された [`Ledger`](../models/Ledger.md) と `LedgerDiff`。
    *   特記事項: 承認済みのレコードは編集できません。元の担当者のカウンターを調整し、申請者に通知を送信します。

*   **`getFrequentAssignees(int $ledgerDefineId, string $roleType, int $limit, string $searchQuery = ''): array`**:
    *   目的・機能: 特定の台帳定義と役割（点検者または承認者）において、過去に最も頻繁に割り当てられたユーザーのリストを取得します。
    *   引数:
        *   `$ledgerDefineId`: [`LedgerDefine`](../models/LedgerDefine.md) のID。
        *   `$roleType`: 'inspector' または 'approver'。
        *   `$limit`: 取得する最大ユーザー数。
        *   `$searchQuery`: ユーザー名での絞り込み検索クエリ（オプション）。
    *   戻り値: `array` - ユーザーID、ユーザー名、割り当て回数を含む連想配列のリスト。

*   **`claimTask(Ledger $ledger, User $claimer, ?string $comments): Ledger`**:
    *   目的・機能: 現在点検待ちまたは承認待ちのタスクを、権限を持つ別のユーザーが引き継ぎます。
    *   引数:
        *   `$ledger`: 引き継ぎ対象の [`Ledger`](../models/Ledger.md) モデル。
        *   `$claimer`: タスクを引き継ぐ [`User`](../models/User.md) モデル。
        *   `$comments`: 引き継ぎに関するコメント。
    *   戻り値: `Ledger` - 更新後の [`Ledger`](../models/Ledger.md) モデル。
    *   特記事項: 申請者自身はタスクを引き継げません。元の担当者と新しい担当者のタスクカウンターを調整し、関係者に通知します。

*   **`incrementPendingTaskCount(int $userId, string $type = 'approval')` (protected)**:
    *   目的・機能: 指定されたユーザーの未処理タスクカウンター（点検待ち or 承認待ち）をインクリメントします。
*   **`decrementPendingTaskCount(int $userId, string $type)` (protected)**:
    *   目的・機能: 指定されたユーザーの未処理タスクカウンターをデクリメントします。

## 依存する他のクラスや設定

*   **モデル**:
    *   [`App\Models\Ledger`](../models/Ledger.md)
    *   `App\Models\LedgerDiff` (実体は [`Ledger`](../models/Ledger.md) の `ledgerDiff()` リレーション経由で扱われます)
    *   [`App\Models\LedgerDefine`](../models/LedgerDefine.md)
    *   [`App\Models\User`](../models/User.md)
    *   `App\Models\NotificationType`
    *   [`App\Models\Folder`](../models/Folder.md) (通知時のフォルダ情報取得のため)
*   **サービスクラス**:
    *   [`App\Services\NotificationService`](./NotificationService.md): 各ワークフローアクションに応じた通知の送信。
    *   [`App\Services\UserService`](./UserService.md): タスク引き継ぎ時の権限チェックなど。
*   **Enum**:
    *   `App\Enums\WorkflowStatus`: ワークフローの各状態を定義。
    *   `App\Enums\FolderPermissionType` (間接的に [`UserService`](./UserService.md) 経由で使用)
*   **Facades**:
    *   `Illuminate\Support\Facades\DB`: トランザクション処理。
    *   `Illuminate\Support\Facades\Log`: 情報ログ、エラーログの記録。
*   **その他**:
    *   `Carbon\Carbon`: 日時処理。

## その他

*   多くのメソッドでデータベーストランザクションを利用して処理の原子性を保証しています。
*   各主要アクションの後に、関連ユーザーへの通知処理が組み込まれています。
*   ユーザーの未処理タスク数 (`pending_inspection_count`, `pending_approval_count`) の増減管理を行っています。
*   アクティビティログの記録はコメントアウトされている箇所があり、完全には実装されていない可能性があります（`Log::info` による代替ログは存在）。（要確認）
*   各メソッドの冒頭部分で権限チェックに関する `ToDo` コメントがあり、厳密な権限チェックは未実装または別箇所で行われている可能性があります。（要確認）
