<?php

namespace App\Mcp\Tools;

use App\Mcp\Helpers\TranslationHelper;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Services\Ledger\LedgerDiffProcessor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * ワークフロー履歴取得MCPツール
 *
 * 指定された台帳のワークフロー履歴を取得し、
 * 既存の翻訳キーを活用した自然な日本語で表示します。
 */
class GetWorkflowHistoryTool extends Tool
{
    use AuthenticatedMcpTool;

    protected string $description = <<<'MARKDOWN'
        Get workflow history for a specific ledger.

        Optionally compare two versions by passing `base_diff_id` and `target_diff_id`,
        or set `compare_latest_vs_previous=true` to compare the latest history item with
        the immediately previous version.
MARKDOWN;

    public function schema(JsonSchema $schema): array
    {
        return [
            'ledger_id' => $schema->integer('The ID of the ledger to get history for.')->required(),
            'limit' => $schema->integer(
                'The maximum number of history items to return. Default: 50.'
            )->default(50),
            'format' => $schema->string(
                'Response format: "raw" (default) or "summary".'
            )->enum(['raw', 'summary'])->default('raw'),
            'base_diff_id' => $schema->integer('Optional newer diff ID to use as the comparison base.'),
            'target_diff_id' => $schema->integer('Optional older diff ID to compare against the base diff.'),
            'compare_latest_vs_previous' => $schema->boolean(
                'When true, compares the latest history item with the immediately previous version.'
            )->default(false),
        ];
    }

    public function handle(Request $request): Response
    {
        try {
            $user = $this->authenticateUser();

            $ledgerId = (int) $request->get('ledger_id');
            $format = $request->get('format', 'raw');
            $limit = (int) $request->get('limit', 50);
            $baseDiffId = $request->get('base_diff_id');
            $targetDiffId = $request->get('target_diff_id');
            $compareLatestVsPrevious = filter_var(
                $request->get('compare_latest_vs_previous', false),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            ) ?? false;

            $baseDiffId = $baseDiffId !== null ? (int) $baseDiffId : null;
            $targetDiffId = $targetDiffId !== null ? (int) $targetDiffId : null;

            if (! $ledgerId) {
                return Response::error(trans('ledger.error.ledger_id_required'));
            }

            // 台帳の存在確認と権限チェック
            $ledger = Ledger::with(['define.folder'])->find($ledgerId);
            if (! $ledger) {
                return Response::error(trans('ledger.error.ledger_not_found'));
            }

            // フォルダへの読み取り権限チェック
            $permissionCheck = $this->checkFolderPermissionOrError(
                $user,
                $ledger->define->folder,
                'READ'
            );
            if ($permissionCheck instanceof Response) {
                return $permissionCheck;
            }

            // ワークフロー履歴を取得
            $history = $this->getWorkflowHistory($ledger, $limit);

            $comparison = $this->resolveComparison(
                $ledger,
                $baseDiffId,
                $targetDiffId,
                $compareLatestVsPrevious,
            );
            if ($comparison instanceof Response) {
                return $comparison;
            }

            // レスポンス構築
            $response = $this->buildHistoryResponse($history, $format, $ledger, $comparison);

            return Response::json($response);
        } catch (\Exception $e) {
            return Response::error(
                trans('ledger.error.occurred_with_message', ['message' => $e->getMessage()])
            );
        }
    }

    /**
     * ワークフロー履歴を取得
     */
    private function getWorkflowHistory(Ledger $ledger, int $limit): array
    {
        $diffs = LedgerDiff::where('ledger_id', $ledger->id)
            ->with(['modifier:id,name', 'inspector:id,name', 'approver:id,name'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return $diffs->map(function ($diff) {
            return $this->formatDiffForResponse($diff);
        })->toArray();
    }

    /**
     * LedgerDiffをレスポンス用にフォーマット
     */
    private function formatDiffForResponse(LedgerDiff $diff): array
    {
        $statusLabel = $diff->status->label();
        $isContentChange = $diff->status->value === 'NONE';

        // 詳細情報の構築
        $detail = $this->buildDetailText($diff, $isContentChange);

        return [
            'id' => $diff->id,
            'ledger_id' => $diff->ledger_id,
            'version' => $diff->version,
            'created_at' => $diff->created_at->toISOString(),
            'created_at_formatted' => $diff->created_at->isoFormat('YYYY/MM/DD HH:mm:ss'),
            'modifier_name' => $diff->modifier?->name ?? trans('ledger.unknown'),
            'status' => $diff->status->value,
            'status_label' => $isContentChange ?
                trans('ledger.workflow.history_action_modified') : $statusLabel,
            'detail' => $detail,
            'comments' => $diff->comments,
            'inspector_name' => $diff->inspector?->name,
            'approver_name' => $diff->approver?->name,
            'has_content' => ! empty($diff->content),
        ];
    }

    /**
     * 詳細テキストの構築
     */
    private function buildDetailText(LedgerDiff $diff, bool $isContentChange): string
    {
        if ($isContentChange) {
            return trans('ledger.workflow.workflow_inactive_at_this_point');
        }

        $details = [];

        // ステータスに応じた担当者情報
        if ($diff->status->value === 'PENDING_INSPECTION' && $diff->inspector) {
            $details[] = trans('ledger.workflow.next_inspector').': '.$diff->inspector->name;
        } elseif ($diff->status->value === 'PENDING_APPROVAL' && $diff->approver) {
            $details[] = trans('ledger.workflow.next_approver').': '.$diff->approver->name;
        } elseif ($diff->status->value === 'APPROVED' && $diff->approver) {
            $details[] = trans('ledger.workflow.approved_by').': '.$diff->approver->name;
        }

        // コメントがあれば追加
        if ($diff->comments) {
            $details[] = trans('ledger.workflow.comments').': '.$diff->comments;
        }

        return implode(' / ', $details);
    }

    /**
     * 履歴レスポンスの構築
     */
    private function buildHistoryResponse(
        array $history,
        string $format,
        Ledger $ledger,
        ?array $comparison = null
    ): array {
        $historyCount = count($history);

        $historySummary = trans('ledger.workflow.history_count_message', [
            'count' => $historyCount,
            'ledger_title' => $this->extractLedgerTitle($ledger),
        ]);

        $summary = $comparison
            ? $historySummary.' '.$comparison['summary']
            : $historySummary;

        $displayFields = [
            'created_at_formatted' => trans('ledger.workflow.history_datetime'),
            'modifier_name' => trans('ledger.workflow.history_user'),
            'status_label' => trans('ledger.workflow.history_action'),
            'detail' => trans('ledger.workflow.history_detail'),
        ];

        $payload = [
            'history' => $history,
            'total_count' => $historyCount,
            'ledger_id' => $ledger->id,
        ];

        if ($comparison) {
            $payload['comparison'] = $comparison;
        }

        if ($format === 'summary') {
            return TranslationHelper::buildMcpResponse(
                $summary,
                $displayFields,
                $payload
            );
        }

        return $payload + [
            '__summary__' => $summary,
            '__display_fields__' => $displayFields,
            'ledger' => [
                'id' => $ledger->id,
                'title' => $this->extractLedgerTitle($ledger),
                'status' => $ledger->status->value,
                'current_version' => $ledger->version,
            ],
        ];
    }

    private function resolveComparison(
        Ledger $ledger,
        ?int $baseDiffId,
        ?int $targetDiffId,
        bool $compareLatestVsPrevious,
    ): Response|array|null {
        $manualComparisonRequested = $baseDiffId !== null || $targetDiffId !== null;

        if (! $manualComparisonRequested && ! $compareLatestVsPrevious) {
            return null;
        }

        if ($targetDiffId !== null && $baseDiffId === null) {
            return Response::error(trans('ledger.mcp.workflow_history_base_diff_required'));
        }

        $mode = $manualComparisonRequested ? 'manual' : 'latest_vs_previous';
        $baseDiff = $manualComparisonRequested
            ? $this->findDiffForLedger($ledger, $baseDiffId)
            : $this->findLatestDiffForLedger($ledger);

        if (! $baseDiff) {
            return Response::error(trans('ledger.mcp.workflow_history_base_diff_not_found'));
        }

        $targetDiff = $targetDiffId !== null
            ? $this->findDiffForLedger($ledger, $targetDiffId)
            : $this->findPreviousDiffForLedger($ledger, $baseDiff);

        if (! $targetDiff) {
            return Response::error(trans('ledger.mcp.workflow_history_target_diff_not_found'));
        }

        if ($baseDiff->id === $targetDiff->id) {
            return Response::error(trans('ledger.mcp.workflow_history_same_diff_not_allowed'));
        }

        if ($baseDiff->id < $targetDiff->id) {
            [$baseDiff, $targetDiff] = [$targetDiff, $baseDiff];
        }

        $diffResult = app(LedgerDiffProcessor::class)->prepareContentDiff(
            $ledger,
            $targetDiff,
            $baseDiff->id,
        );

        $changedFields = collect($diffResult['contentChanges'])
            ->filter(fn (array $change) => ! in_array($change['status'], ['unchanged', 'empty'], true))
            ->map(fn (array $change) => $this->formatChangedField($change))
            ->values()
            ->all();

        return [
            'mode' => $mode,
            'summary' => trans('ledger.mcp.workflow_history_comparison_summary', [
                'base_version' => $baseDiff->version,
                'target_version' => $targetDiff->version,
                'count' => count($changedFields),
                'modifier' => $baseDiff->modifier?->name ?? trans('ledger.unknown'),
                'datetime' => $baseDiff->created_at->isoFormat('YYYY/MM/DD HH:mm:ss'),
            ]),
            'base_diff' => $this->formatComparisonDiff($baseDiff),
            'target_diff' => $this->formatComparisonDiff($targetDiff),
            'changed_fields_count' => count($changedFields),
            'changed_fields' => $changedFields,
            'has_changes' => (bool) ($diffResult['hasChangedColumns'] ?? false),
            'changed_at' => $baseDiff->created_at->toISOString(),
            'changed_at_formatted' => $baseDiff->created_at->isoFormat('YYYY/MM/DD HH:mm:ss'),
            'changed_by' => $baseDiff->modifier?->name ?? trans('ledger.unknown'),
            'next_actions' => [
                trans('ledger.mcp.workflow_history_next_action_trace_related'),
                trans('ledger.mcp.workflow_history_next_action_review_activity'),
            ],
        ];
    }

    private function findDiffForLedger(Ledger $ledger, ?int $diffId): ?LedgerDiff
    {
        if (! $diffId) {
            return null;
        }

        return LedgerDiff::query()
            ->with(['modifier:id,name', 'inspector:id,name', 'approver:id,name'])
            ->where('ledger_id', $ledger->id)
            ->whereKey($diffId)
            ->first();
    }

    private function findLatestDiffForLedger(Ledger $ledger): ?LedgerDiff
    {
        return LedgerDiff::query()
            ->with(['modifier:id,name', 'inspector:id,name', 'approver:id,name'])
            ->where('ledger_id', $ledger->id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    private function findPreviousDiffForLedger(Ledger $ledger, LedgerDiff $baseDiff): ?LedgerDiff
    {
        return LedgerDiff::query()
            ->with(['modifier:id,name', 'inspector:id,name', 'approver:id,name'])
            ->where('ledger_id', $ledger->id)
            ->where('id', '<', $baseDiff->id)
            ->orderBy('id', 'desc')
            ->first();
    }

    private function formatComparisonDiff(LedgerDiff $diff): array
    {
        return [
            'id' => $diff->id,
            'version' => $diff->version,
            'created_at' => $diff->created_at->toISOString(),
            'created_at_formatted' => $diff->created_at->isoFormat('YYYY/MM/DD HH:mm:ss'),
            'modifier_name' => $diff->modifier?->name ?? trans('ledger.unknown'),
            'status' => $diff->status->value,
            'status_label' => $diff->status->label(),
            'comments' => $diff->comments,
        ];
    }

    private function formatChangedField(array $change): array
    {
        $column = $change['column_define'] ?? [];

        return [
            'column_id' => is_array($column) ? ($column['id'] ?? null) : null,
            'column_name' => is_array($column)
                ? ($column['name'] ?? trans('ledger.field.title'))
                : trans('ledger.field.title'),
            'group' => is_array($column) ? ($column['group'] ?? null) : null,
            'change_type' => $change['status'],
            'change_type_label' => trans('ledger.mcp.workflow_history_change_type_'.$change['status']),
            'before_value' => $change['old_value'],
            'after_value' => $change['current_value'],
            'before_text' => $this->stringifyComparisonValue($change['old_value']),
            'after_text' => $this->stringifyComparisonValue($change['current_value']),
        ];
    }

    private function stringifyComparisonValue(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return trans('ledger.mcp.workflow_history_empty_value');
        }

        if (is_bool($value)) {
            return $value ? trans('ledger.yes') : trans('ledger.no');
        }

        if (is_scalar($value)) {
            $valueText = trim((string) $value);

            return $valueText !== ''
                ? $valueText
                : trans('ledger.mcp.workflow_history_empty_value');
        }

        if (is_array($value)) {
            $parts = [];

            foreach ($value as $key => $item) {
                $itemText = $this->stringifyComparisonValue($item);

                if ($itemText === trans('ledger.mcp.workflow_history_empty_value')) {
                    continue;
                }

                $parts[] = is_string($key)
                    ? $key.': '.$itemText
                    : $itemText;
            }

            return $parts !== []
                ? implode(' / ', $parts)
                : trans('ledger.mcp.workflow_history_empty_value');
        }

        return (string) $value;
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
        $firstColumnId = is_object($firstColumn) ? ($firstColumn->id ?? null) : ($firstColumn['id'] ?? null);

        if ($firstColumnId !== null && isset($ledger->content[$firstColumnId])) {
            $titleValue = $ledger->content[$firstColumnId];
            if (! empty($titleValue) && is_string($titleValue)) {
                return $titleValue;
            }
        }

        return $fallbackTitle;
    }
}
