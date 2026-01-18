<?php

namespace App\Mcp\Tools;

use App\Enums\WorkflowStatus;
use App\Mcp\Helpers\ResponseHelper;
use App\Mcp\Helpers\TranslationHelper;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Ledger;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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

    public function schema(JsonSchema $schema): array
    {
        return [
            'format' => $schema->string('The format of the response. "raw" (default) returns JSON data. "summary" returns a human-readable summary.')
                ->enum(['raw', 'summary'])->default('raw'),
            'limit' => $schema->integer('The maximum number of tasks to return. Default: 50.')
                ->default(50),
            'sort_by' => $schema->string('Field to sort by: "created_at" (default), "updated_at", "title", "urgency", "deadline".')
                ->enum(['created_at', 'updated_at', 'title', 'urgency', 'deadline'])->default('created_at'),
            'sort_direction' => $schema->string('Sort direction: "asc" or "desc". Default: "asc".')
                ->enum(['asc', 'desc'])->default('asc'),
        ];
    }

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
                    'direction' => $sortDirection === 'desc' ? trans('ledger.sort.high_to_low') : trans('ledger.sort.low_to_high'),
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
        // 既存のWorkflowTaskRepositoryのロジックを参考に実装
        $inspectionQuery = Ledger::where('status', WorkflowStatus::PENDING_INSPECTION)
            ->whereHas('latestDiff', function ($query) use ($user) {
                $query->where('inspector_id', $user->id);
            })
            ->with(['define', 'creator', 'latestDiff', 'define.folder']);

        $approvalQuery = Ledger::where('status', WorkflowStatus::PENDING_APPROVAL)
            ->whereHas('latestDiff', function ($query) use ($user) {
                $query->where('approver_id', $user->id);
            })
            ->with(['define', 'creator', 'latestDiff', 'define.folder']);

        // ソート条件の適用
        $this->applySorting($inspectionQuery, $sortBy, $sortDirection);
        $this->applySorting($approvalQuery, $sortBy, $sortDirection);

        $inspectionTasks = $inspectionQuery->limit($limit)->get()
            ->map(function ($ledger) {
                return $this->formatTaskForResponse($ledger, 'inspection');
            })->toArray();

        $approvalTasks = $approvalQuery->limit($limit)->get()
            ->map(function ($ledger) {
                return $this->formatTaskForResponse($ledger, 'approval');
            })->toArray();

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
    public function formatTaskForResponse(Ledger $ledger, string $type): array
    {
        $ageDays = $ledger->created_at->diffInDays(now());

        // タイトルの取得: contentから適切なカラムの値を抽出
        $title = $this->extractTitleFromContent($ledger);

        return [
            'id' => $ledger->id,
            'title' => $title,
            'type' => $type,
            'status' => $ledger->status->value,
            'status_label' => TranslationHelper::translateWorkflowStatus($ledger->status->value),
            'assignee_name' => $this->getAssigneeName($ledger),
            'creator_name' => $ledger->creator?->name ?? trans('ledger.unknown'),
            'folder_name' => $ledger->define?->folder?->name ?? '',
            'created_at' => $ledger->created_at->toISOString(),
            'updated_at' => $ledger->updated_at->toISOString(),
            'age_days' => $ageDays,
            'age_text' => TranslationHelper::formatAgeDays($ageDays),
            'deadline' => $this->getDeadlineFromContent($ledger),
            'priority' => $this->calculatePriority($ledger),
        ];
    }

    /**
     * Ledgerのcontentからタイトルを抽出
     */
    private function extractTitleFromContent(Ledger $ledger): string
    {
        // LedgerDefineのタイトルをフォールバック値として設定
        $fallbackTitle = $ledger->define?->title ?? trans('ledger.title_unknown');

        // column_defineが存在しない場合はフォールバック値を返す
        if (! $ledger->define || ! $ledger->define->column_define) {
            return $fallbackTitle;
        }

        // contentが空の場合もフォールバック値を返す
        if (empty($ledger->content) || ! is_array($ledger->content)) {
            return $fallbackTitle;
        }

        // 最初のカラムの値を取得（通常はタイトルや名前）
        $firstColumn = collect($ledger->define->column_define)->first();
        if ($firstColumn && isset($ledger->content[$firstColumn->id])) {
            $titleValue = $ledger->content[$firstColumn->id];
            // 空でない値があれば使用
            if (! empty($titleValue) && is_string($titleValue)) {
                return $titleValue;
            }
        }

        // 他のカラムからタイトル的なものを探す（name, title, 件名などの名前のカラム）
        foreach ($ledger->define->column_define as $columnDefine) {
            if (isset($ledger->content[$columnDefine->id])) {
                $columnName = strtolower($columnDefine->name ?? '');
                $titleLikeNames = ['title', 'name', '件名', '名前', 'タイトル', '表題'];

                foreach ($titleLikeNames as $titleLikeName) {
                    if (str_contains($columnName, strtolower($titleLikeName))) {
                        $titleValue = $ledger->content[$columnDefine->id];
                        if (! empty($titleValue) && is_string($titleValue)) {
                            return $titleValue;
                        }
                    }
                }
            }
        }

        return $fallbackTitle;
    }

    /**
     * Ledgerのcontentから期限を抽出
     */
    private function getDeadlineFromContent(Ledger $ledger): ?string
    {
        if (! $ledger->define || ! $ledger->define->column_define || empty($ledger->content)) {
            return null;
        }

        // 期限らしいカラムを探す
        foreach ($ledger->define->column_define as $columnDefine) {
            if (isset($ledger->content[$columnDefine->id])) {
                $columnName = strtolower($columnDefine->name ?? '');
                $deadlineNames = ['deadline', '期限', '締切', '期日', 'due'];

                foreach ($deadlineNames as $deadlineName) {
                    if (str_contains($columnName, strtolower($deadlineName))) {
                        return $ledger->content[$columnDefine->id];
                    }
                }
            }
        }

        return null;
    }

    /**
     * 担当者名の取得
     */
    private function getAssigneeName(Ledger $ledger): string
    {
        $latestDiff = $ledger->latestDiff;
        if (! $latestDiff) {
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

        // 期限がある場合（新しいgetDeadlineFromContentメソッドを使用）
        $deadline = $this->getDeadlineFromContent($ledger);
        if ($deadline) {
            try {
                $deadlineDate = \Carbon\Carbon::parse($deadline);
                $daysUntilDeadline = now()->diffInDays($deadlineDate, false);

                if ($daysUntilDeadline < 0) {
                    return trans('ledger.priority.overdue');
                } elseif ($daysUntilDeadline <= 1) {
                    return trans('ledger.priority.urgent');
                } elseif ($daysUntilDeadline <= 3) {
                    return trans('ledger.priority.high');
                }
            } catch (\Exception $e) {
                // 日付パースに失敗した場合は滞留時間による判定にフォールバック
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
