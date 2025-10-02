<?php

namespace App\Mcp\Helpers;

use Carbon\Carbon;

/**
 * MCPレスポンス構築ヘルパー
 * 
 * 標準的なMCPレスポンス形式を生成し、
 * 既存の翻訳キーを活用した自然な日本語表示を提供します。
 */
class ResponseHelper
{
    /**
     * ワークフロータスク一覧レスポンス構築
     */
    public static function buildWorkflowTasksResponse(
        array $inspectionTasks,
        array $approvalTasks,
        string $format = 'raw'
    ): array {
        $inspectionCount = count($inspectionTasks);
        $approvalCount = count($approvalTasks);
        $totalTasks = $inspectionCount + $approvalCount;

        $summary = TranslationHelper::workflowSummary($inspectionCount, $approvalCount);
        $displayFields = TranslationHelper::workflowDisplayFields();

        if ($format === 'summary') {
            return TranslationHelper::buildMcpResponse(
                $summary,
                $displayFields,
                [
                    'total_tasks' => $totalTasks,
                    'inspection_count' => $inspectionCount,
                    'approval_count' => $approvalCount,
                    'tasks' => array_merge(
                        self::formatWorkflowTasks($inspectionTasks, 'inspection'),
                        self::formatWorkflowTasks($approvalTasks, 'approval')
                    )
                ]
            );
        }

        return TranslationHelper::buildMcpResponse(
            $summary,
            $displayFields,
            [
                'pending_inspections' => $inspectionTasks,
                'pending_approvals' => $approvalTasks,
                'total_tasks' => $totalTasks,
            ]
        );
    }

    /**
     * アクティビティログレスポンス構築
     */
    public static function buildActivityLogResponse(
        array $activities,
        int $totalCount,
        string $format = 'raw'
    ): array {
        $summary = trans('ledger.statistics.activity_count', ['count' => $totalCount]);
        $displayFields = TranslationHelper::activityDisplayFields();

        if ($format === 'summary') {
            $activities = array_map([self::class, 'formatActivityForSummary'], $activities);
        }

        return TranslationHelper::buildMcpResponse(
            $summary,
            $displayFields,
            [
                'activities' => $activities,
                'total' => $totalCount,
            ]
        );
    }

    /**
     * 統計レスポンス構築
     */
    public static function buildStatisticsResponse(
        array $stats,
        string $period = 'month'
    ): array {
        $totalCount = $stats['total_count'] ?? 0;
        $summary = TranslationHelper::statisticsSummary($totalCount, $period);

        return TranslationHelper::buildMcpResponse(
            $summary,
            [
                'period' => trans('ledger.period.period'),
                'total' => trans('ledger.total'),
                'status' => trans('ledger.workflow.status.label') . '別',
                'creator' => trans('ledger.creator.name') . '別',
            ],
            [
                'statistics' => $stats,
                'period' => $period,
            ]
        );
    }

    /**
     * ワークフロータスクのフォーマット
     */
    private static function formatWorkflowTasks(array $tasks, string $type): array
    {
        return array_map(function ($task) use ($type) {
            $ageDays = isset($task['created_at']) ? 
                Carbon::parse($task['created_at'])->diffInDays(now()) : 0;
            
            return [
                'title' => $task['title'] ?? trans('ledger.title_unknown'),
                'status' => TranslationHelper::translateWorkflowStatus($task['status'] ?? ''),
                'assignee' => $task['assignee_name'] ?? trans('ledger.unassigned'),
                'deadline' => $task['deadline'] ?? null,
                'age' => TranslationHelper::formatAgeDays($ageDays),
                'age_days' => $ageDays,
                'type' => $type,
                'id' => $task['id'] ?? null,
            ];
        }, $tasks);
    }

    /**
     * アクティビティのサマリー用フォーマット
     */
    private static function formatActivityForSummary(array $activity): array
    {
        return [
            'time' => $activity['created_at'] ?? '',
            'causer' => $activity['causer']['name'] ?? trans('ledger.activity.unknown_user'),
            'operation' => trans("ledger.activity.event.{$activity['event']}", [], $activity['event']),
            'subject' => $activity['subject_type'] ?? '',
            'description' => $activity['description'] ?? '',
        ];
    }

    /**
     * 承認実行結果レスポンス
     */
    public static function buildApprovalExecutionResponse(
        bool $success,
        string $action,
        ?string $newStatus = null,
        ?string $nextAssignee = null
    ): array {
        if (!$success) {
            return TranslationHelper::buildErrorResponse(trans('ledger.error.approval_processing_failed'));
        }

        $actionText = match($action) {
            'approve' => trans('ledger.workflow.approved_message'),
            'reject' => trans('ledger.workflow.returned_to_draft_message'),
            'return_to_draft' => trans('ledger.workflow.returned_to_draft_message'),
            default => trans('ledger.processing_completed_with_action', ['action' => $action])
        };

        $response = TranslationHelper::buildSuccessResponse($actionText);
        
        if ($newStatus) {
            $response['new_status'] = TranslationHelper::translateWorkflowStatus($newStatus);
        }
        
        if ($nextAssignee) {
            $response['next_assignee'] = $nextAssignee;
        }

        return $response;
    }
}