<?php

namespace App\Mcp\Helpers;

/**
 * MCPレスポンス用翻訳統合ヘルパー
 * 
 * 既存のLedgerLeap翻訳キーを活用して、
 * 自然な日本語MCPレスポンスを生成します。
 */
class TranslationHelper
{
    /**
     * ワークフロータスクサマリーの生成
     */
    public static function workflowSummary(int $inspectionCount, int $approvalCount): string
    {
        if ($inspectionCount === 0 && $approvalCount === 0) {
            return trans('ledger.workflow.no_pending_tasks');
        }

        return trans('ledger.workflow.summary_notification_message', [
            'inspection_count' => $inspectionCount,
            'approval_count' => $approvalCount
        ]);
    }

    /**
     * ワークフロー表示フィールドの定義
     */
    public static function workflowDisplayFields(): array
    {
        return [
            'title' => trans('ledger.define.title'),
            'status' => trans('ledger.workflow.current_status'),
            'assignee' => trans('ledger.workflow.inspector'),
            'deadline' => trans('ledger.deadline'),
            'age' => trans('ledger.workflow.age'),
            'priority' => trans('ledger.priority'),
        ];
    }

    /**
     * アクティビティ表示フィールドの定義
     */
    public static function activityDisplayFields(): array
    {
        return [
            'time' => trans('ledger.activity.column.time'),
            'causer' => trans('ledger.activity.column.causer'),
            'operation' => trans('ledger.activity.column.operation'),
            'subject' => trans('ledger.activity.column.subject'),
            'changes' => trans('ledger.activity.column.changes'),
        ];
    }

    /**
     * 統計サマリーの生成
     */
    public static function statisticsSummary(int $totalCount, string $period = 'month'): string
    {
        $periodText = match($period) {
            'day' => trans('ledger.period.today'),
            'week' => trans('ledger.period.this_week'),
            'month' => trans('ledger.period.this_month'),
            'year' => trans('ledger.period.this_year'),
            default => $period
        };

        return trans('ledger.statistics.ledger_count_with_period', [
            'count' => $totalCount,
            'period' => $periodText
        ]);
    }

    /**
     * ワークフローステータスの日本語変換
     */
    public static function translateWorkflowStatus(string $status): string
    {
        return match($status) {
            'DRAFT' => trans('ledger.workflow.status.draft'),
            'PENDING_INSPECTION' => trans('ledger.workflow.status.pending_inspection'),
            'PENDING_APPROVAL' => trans('ledger.workflow.status.pending_approval'),
            'APPROVED' => trans('ledger.workflow.status.approved'),
            'RETURNED_TO_DRAFT' => trans('ledger.workflow.returned_to_draft_message'),
            default => $status
        };
    }

    /**
     * 滞留時間の日本語表示
     */
    public static function formatAgeDays(int $days): string
    {
        if ($days === 0) {
            return trans('ledger.time.today');
        } elseif ($days === 1) {
            return trans('ledger.time.one_day');
        } else {
            return trans('ledger.time.days', ['count' => $days]);
        }
    }

    /**
     * 共通のMCPレスポンス構造生成
     */
    public static function buildMcpResponse(
        string $summary,
        array $displayFields,
        array $data,
        array $metadata = []
    ): array {
        return array_merge([
            '__summary__' => $summary,
            '__display_fields__' => $displayFields,
        ], $data, $metadata);
    }

    /**
     * エラーレスポンスの生成
     */
    public static function buildErrorResponse(string $message, string $code = 'error'): array
    {
        return [
            'type' => 'error',
            'code' => $code,
            'message' => $message,
            '__summary__' => trans('ledger.error.occurred_with_message', ['message' => $message])
        ];
    }

    /**
     * 成功レスポンスの生成
     */
    public static function buildSuccessResponse(string $message, array $data = []): array
    {
        return array_merge([
            'type' => 'success',
            'message' => $message,
            '__summary__' => $message
        ], $data);
    }
}