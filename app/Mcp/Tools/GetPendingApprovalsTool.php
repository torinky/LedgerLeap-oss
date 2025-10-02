<?php

namespace App\Mcp\Tools;

use App\Enums\WorkflowStatus;
use App\Mcp\Helpers\ResponseHelper;
use App\Mcp\Helpers\TranslationHelper;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Ledger;
use App\Models\User;
use App\Services\WorkflowService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * 承認待ちタスク取得MCPツール
 * 
 * ユーザーに割り当てられた承認待ち・点検待ちタスクを取得し、
 * 既存の翻訳キーを活用した自然な日本語で表示します。
 */
class GetPendingApprovalsTool extends Tool
{
    use AuthenticatedMcpTool;

    protected string $description = <<<'MARKDOWN'
        Get pending approval and inspection tasks assigned to the user with Japanese translations
MARKDOWN;

    public function handle(Request $request, WorkflowService $workflowService): Response
    {
        try {
            $user = $this->authenticateUser();
            
            $format = $request->get('format', 'raw');
            $limit = (int) $request->get('limit', 50);
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'asc');

            // ユーザーに割り当てられた承認・点検待ちタスクを取得
            $pendingTasks = $this->getPendingTasksForUser($user, $limit, $sortBy, $sortDirection);
            
            $inspectionTasks = $pendingTasks['inspections'];
            $approvalTasks = $pendingTasks['approvals'];

            // ResponseHelperを使用してレスポンス構築
            $response = ResponseHelper::buildWorkflowTasksResponse(
                $inspectionTasks,
                $approvalTasks,
                $format
            );

            // 緊急度による並び替え情報を追加
            if ($sortBy === 'urgency') {
                $response['sort_info'] = [
                    'sorted_by' => trans('ledger.priority.label'),
                    'direction' => $sortDirection === 'desc' ? trans('ledger.sort.high_to_low') : trans('ledger.sort.low_to_high')
                ];
            }

            return Response::json($response);
            
        } catch (\Exception $e) {
            return Response::error(
                $e->getMessage()
            );
        }
    }

    /**
     * ユーザーの承認待ち・点検待ちタスクを取得
     */
    private function getPendingTasksForUser(
        User $user, 
        int $limit, 
        string $sortBy, 
        string $sortDirection
    ): array {
        // 点検待ちタスクの取得
        $inspectionQuery = Ledger::where('status', WorkflowStatus::PENDING_INSPECTION)
            ->whereHas('latestDiff', function ($query) use ($user) {
                $query->where('inspector_id', $user->id);
            })
            ->with(['define', 'creator', 'latestDiff', 'define.folder']);

        // 承認待ちタスクの取得  
        $approvalQuery = Ledger::where('status', WorkflowStatus::PENDING_APPROVAL)
            ->whereHas('latestDiff', function ($query) use ($user) {
                $query->where('approver_id', $user->id);
            })
            ->with(['define', 'creator', 'latestDiff', 'define.folder']);

        // ソート条件の適用
        $this->applySorting($inspectionQuery, $sortBy, $sortDirection);
        $this->applySorting($approvalQuery, $sortBy, $sortDirection);

        $inspectionTasks = $inspectionQuery->limit($limit)->get()
            ->map([$this, 'formatTaskForResponse'])->toArray();
            
        $approvalTasks = $approvalQuery->limit($limit)->get()
            ->map([$this, 'formatTaskForResponse'])->toArray();

        return [
            'inspections' => $inspectionTasks,
            'approvals' => $approvalTasks,
        ];
    }

    /**
     * ソート条件の適用
     */
    private function applySorting($query, string $sortBy, string $sortDirection): void
    {
        $direction = strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
        
        switch ($sortBy) {
            case 'created_at':
            case 'updated_at':
                $query->orderBy($sortBy, $direction);
                break;
            case 'title':
                $query->orderBy('content->title', $direction);
                break;
            case 'urgency':
                // 作成日時の逆順で緊急度を判定 (古いものほど緊急)
                $query->orderBy('created_at', $direction === 'desc' ? 'asc' : 'desc');
                break;
            case 'deadline':
                // 期限がある場合の並び替え（カスタムロジック）
                $query->orderByRaw("JSON_EXTRACT(content, '$.deadline') $direction");
                break;
            default:
                $query->orderBy('created_at', 'asc');
        }
    }

    /**
     * タスクをレスポンス用にフォーマット
     */
    public function formatTaskForResponse(Ledger $ledger): array
    {
        $ageDays = $ledger->created_at->diffInDays(now());
        $title = $ledger->content['title'] ?? $ledger->define->title ?? trans('ledger.title_unknown');
        
        return [
            'id' => $ledger->id,
            'title' => $title,
            'status' => $ledger->status->value,
            'status_label' => TranslationHelper::translateWorkflowStatus($ledger->status->value),
            'assignee_name' => $this->getAssigneeName($ledger),
            'creator_name' => $ledger->creator?->name ?? trans('ledger.unknown'),
            'folder_name' => $ledger->define?->folder?->name ?? '',
            'created_at' => $ledger->created_at->toISOString(),
            'updated_at' => $ledger->updated_at->toISOString(),
            'age_days' => $ageDays,
            'age_text' => TranslationHelper::formatAgeDays($ageDays),
            'deadline' => $ledger->content['deadline'] ?? null,
            'priority' => $this->calculatePriority($ledger),
            'workflow_type' => $ledger->status === WorkflowStatus::PENDING_INSPECTION ? 'inspection' : 'approval',
        ];
    }

    /**
     * 担当者名の取得
     */
    private function getAssigneeName(Ledger $ledger): string
    {
        $latestDiff = $ledger->latestDiff;
        if (!$latestDiff) {
            return trans('ledger.unassigned');
        }

        if ($ledger->status === WorkflowStatus::PENDING_INSPECTION) {
            return $latestDiff->inspector?->name ?? trans('ledger.unassigned');
        } elseif ($ledger->status === WorkflowStatus::PENDING_APPROVAL) {
            return $latestDiff->approver?->name ?? trans('ledger.unassigned');
        }

        return trans('ledger.unassigned');
    }

    /**
     * 優先度の計算
     */
    private function calculatePriority(Ledger $ledger): string
    {
        $ageDays = $ledger->created_at->diffInDays(now());
        
        // 期限がある場合
        if (isset($ledger->content['deadline'])) {
            $deadline = \Carbon\Carbon::parse($ledger->content['deadline']);
            $daysUntilDeadline = now()->diffInDays($deadline, false);
            
            if ($daysUntilDeadline < 0) {
                return trans('ledger.priority.overdue');
            } elseif ($daysUntilDeadline <= 1) {
                return trans('ledger.priority.urgent');
            } elseif ($daysUntilDeadline <= 3) {
                return trans('ledger.priority.high');
            }
        }
        
        // 滞留時間による判定
        if ($ageDays >= 7) {
            return trans('ledger.priority.high');
        } elseif ($ageDays >= 3) {
            return trans('ledger.priority.medium');
        }
        
        return trans('ledger.priority.low');
    }
}