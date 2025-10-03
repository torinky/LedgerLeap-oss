<?php

namespace App\Mcp\Tools;

use App\Mcp\Helpers\TranslationHelper;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Ledger;
use App\Models\User;
use App\Services\WorkflowService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * ワークフロータスク引き継ぎMCPツール
 *
 * ワークフロータスクの担当者を変更（引き継ぎ）します。
 * 既存のWorkflowService::claimTaskを活用します。
 */
class ClaimWorkflowTaskTool extends Tool
{
    use AuthenticatedMcpTool;

    protected string $description = <<<'MARKDOWN'
        Claim a workflow task (take over as assignee) with Japanese translations
MARKDOWN;

    public function handle(Request $request, WorkflowService $workflowService): Response
    {
        try {
            $user = $this->authenticateUser();

            $ledgerId = (int) $request->get('ledger_id');
            $comments = $request->get('comments');

            if (! $ledgerId) {
                return Response::error(trans('ledger.error.ledger_id_required'));
            }

            // 台帳の存在確認
            $ledger = Ledger::with(['define.folder', 'creator', 'latestDiff'])->find($ledgerId);
            if (! $ledger) {
                return Response::error(trans('ledger.error.ledger_not_found'));
            }

            // タスク引き継ぎ実行
            try {
                $updatedLedger = $workflowService->claimTask($ledger, $user, $comments);

                // 成功レスポンス構築
                $response = $this->buildClaimSuccessResponse($updatedLedger, $user);

                return Response::json($response);

            } catch (\Exception $e) {
                return Response::error($e->getMessage());
            }

        } catch (\Exception $e) {
            return Response::error(
                trans('ledger.error.occurred_with_message', ['message' => $e->getMessage()])
            );
        }
    }

    /**
     * 引き継ぎ成功レスポンスの構築
     */
    private function buildClaimSuccessResponse(Ledger $ledger, User $claimer): array
    {
        $latestDiff = $ledger->latestDiff;
        $status = $ledger->status->value;
        $statusLabel = $ledger->status->label();

        // 新しい担当者名
        $newAssigneeName = $claimer->name;

        // タスクタイプの判定
        $taskType = match ($status) {
            'PENDING_INSPECTION' => trans('ledger.workflow.inspection_pending'),
            'PENDING_APPROVAL' => trans('ledger.workflow.approval_pending'),
            default => trans('ledger.workflow.pending_tasks')
        };

        $summary = trans('ledger.workflow.task_claimed_successfully_with_details', [
            'task_type' => $taskType,
            'assignee' => $newAssigneeName,
        ]);

        return TranslationHelper::buildSuccessResponse(
            $summary,
            [
                'ledger' => [
                    'id' => $ledger->id,
                    'title' => $this->extractLedgerTitle($ledger),
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'new_assignee' => $newAssigneeName,
                    'new_assignee_id' => $claimer->id,
                ],
                'claimed_at' => now()->toISOString(),
                'comments' => $comments,
            ]
        );
    }

    /**
     * 台帳タイトルの抽出
     */
    private function extractLedgerTitle(Ledger $ledger): string
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
