<?php

namespace App\Mcp\Tools;

use App\Mcp\Helpers\TranslationHelper;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Ledger;
use App\Models\LedgerDiff;
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
        Get workflow history for a specific ledger with Japanese translations
MARKDOWN;

    public function handle(Request $request): Response
    {
        try {
            $user = $this->authenticateUser();

            $ledgerId = (int) $request->get('ledger_id');
            $format = $request->get('format', 'raw');
            $limit = (int) $request->get('limit', 50);

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

            // レスポンス構築
            $response = $this->buildHistoryResponse($history, $format, $ledger);

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
    private function buildHistoryResponse(array $history, string $format, Ledger $ledger): array
    {
        $historyCount = count($history);

        $summary = trans('ledger.workflow.history_count_message', [
            'count' => $historyCount,
            'ledger_title' => $this->extractLedgerTitle($ledger),
        ]);

        $displayFields = [
            'created_at_formatted' => trans('ledger.workflow.history_datetime'),
            'modifier_name' => trans('ledger.workflow.history_user'),
            'status_label' => trans('ledger.workflow.history_action'),
            'detail' => trans('ledger.workflow.history_detail'),
        ];

        if ($format === 'summary') {
            return TranslationHelper::buildMcpResponse(
                $summary,
                $displayFields,
                [
                    'history' => $history,
                    'total_count' => $historyCount,
                    'ledger_id' => $ledger->id,
                ]
            );
        }

        return [
            '__summary__' => $summary,
            '__display_fields__' => $displayFields,
            'history' => $history,
            'total_count' => $historyCount,
            'ledger' => [
                'id' => $ledger->id,
                'title' => $this->extractLedgerTitle($ledger),
                'status' => $ledger->status->value,
                'current_version' => $ledger->version,
            ],
        ];
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
