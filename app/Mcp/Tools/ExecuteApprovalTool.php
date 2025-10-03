<?php

namespace App\Mcp\Tools;

use App\Enums\WorkflowStatus;
use App\Mcp\Helpers\ResponseHelper;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Ledger;
use App\Models\User;
use App\Services\WorkflowService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * 承認処理実行MCPツール
 *
 * ワークフロータスクの承認・差戻し処理を実行します。
 * 既存のWorkflowServiceを活用して、承認フロー全体を管理します。
 */
class ExecuteApprovalTool extends Tool
{
    use AuthenticatedMcpTool;

    protected string $description = <<<'MARKDOWN'
        Execute workflow approval actions (approve, return to draft) with Japanese translations
MARKDOWN;

    public function handle(Request $request, WorkflowService $workflowService): Response
    {
        try {
            $user = $this->authenticateUser();

            $ledgerId = (int) $request->get('ledger_id');
            $action = $request->get('action'); // 'approve', 'return_to_draft'
            $comments = $request->get('comments');
            $nextApproverId = $request->get('next_approver_id') ? (int) $request->get('next_approver_id') : null;

            if (! $ledgerId) {
                return Response::error(trans('ledger.error.ledger_id_required'));
            }

            if (! in_array($action, ['approve', 'return_to_draft'])) {
                return Response::error(trans('ledger.error.invalid_action'));
            }

            $ledger = Ledger::with(['define.folder', 'latestDiff', 'creator'])->findOrFail($ledgerId);

            // 権限チェック
            $authResult = $this->checkActionPermission($user, $ledger, $action, $workflowService);
            if ($authResult instanceof Response) {
                return $authResult;
            }

            // アクション実行
            $result = $this->executeAction(
                $action,
                $ledgerId,
                $user->id,
                $comments,
                $nextApproverId,
                $workflowService
            );

            if (! $result['success']) {
                return Response::error($result['message']);
            }

            // 成功レスポンス構築
            $response = ResponseHelper::buildApprovalExecutionResponse(
                true,
                $action,
                $result['new_status'] ?? null,
                $result['next_assignee'] ?? null
            );

            // 詳細情報を追加
            $response['ledger'] = [
                'id' => $ledger->id,
                'title' => $this->extractTitleFromLedger($ledger),
                'status' => $result['new_status'] ?? $ledger->status->value,
            ];

            return Response::json($response);

        } catch (\App\Exceptions\Workflow\InvalidWorkflowActionException $e) {
            return Response::error($e->getMessage());
        } catch (\App\Exceptions\Workflow\UnauthorizedWorkflowActionException $e) {
            return Response::error($e->getMessage());
        } catch (\App\Exceptions\Workflow\InsufficientPermissionsException $e) {
            return Response::error($e->getMessage());
        } catch (\App\Exceptions\Workflow\WorkflowConditionException $e) {
            return Response::error($e->getMessage());
        } catch (\Exception $e) {
            return Response::error(
                trans('ledger.error.approval_processing_failed').': '.$e->getMessage()
            );
        }
    }

    /**
     * アクション権限チェック
     */
    private function checkActionPermission(
        User $user,
        Ledger $ledger,
        string $action,
        WorkflowService $workflowService
    ): ?Response {
        switch ($action) {
            case 'approve':
                if (! $workflowService->canApprove($user, $ledger)) {
                    return Response::error(trans('ledger.workflow.error.cannot_approve'));
                }
                break;

            case 'return_to_draft':
                if (! $workflowService->canReturnToDraft($user, $ledger)) {
                    return Response::error(trans('ledger.workflow.error.cannot_return_to_draft'));
                }
                break;
        }

        return null; // 権限OK
    }

    /**
     * アクション実行
     */
    private function executeAction(
        string $action,
        int $ledgerId,
        int $userId,
        ?string $comments,
        ?int $nextApproverId,
        WorkflowService $workflowService
    ): array {
        try {
            $ledger = null;

            switch ($action) {
                case 'approve':
                    $ledger = $workflowService->approve(
                        $ledgerId,
                        $userId,
                        $comments,
                        $nextApproverId
                    );
                    break;

                case 'return_to_draft':
                    $ledger = $workflowService->returnToDraft(
                        $ledgerId,
                        $userId,
                        $comments
                    );
                    break;
            }

            if (! $ledger) {
                return [
                    'success' => false,
                    'message' => trans('ledger.error.operation_failed'),
                ];
            }

            // 次の担当者名を取得
            $nextAssigneeName = null;
            if ($ledger->status === WorkflowStatus::PENDING_APPROVAL && $ledger->latestDiff?->approver_id) {
                $nextApprover = User::find($ledger->latestDiff->approver_id);
                $nextAssigneeName = $nextApprover?->name;
            }

            return [
                'success' => true,
                'new_status' => $ledger->status->value,
                'next_assignee' => $nextAssigneeName,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ledgerからタイトルを抽出
     */
    private function extractTitleFromLedger(Ledger $ledger): string
    {
        $fallbackTitle = $ledger->define?->title ?? trans('ledger.title_unknown');

        if (! $ledger->define || ! $ledger->define->column_define || empty($ledger->content)) {
            return $fallbackTitle;
        }

        // 最初のカラムの値を取得
        $firstColumn = collect($ledger->define->column_define)->first();
        if ($firstColumn && isset($ledger->content[$firstColumn->id])) {
            $titleValue = $ledger->content[$firstColumn->id];
            if (! empty($titleValue) && is_string($titleValue)) {
                return $titleValue;
            }
        }

        return $fallbackTitle;
    }
}
