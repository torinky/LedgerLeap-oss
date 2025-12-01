<?php

namespace App\Mcp\Tools;

use App\Helpers\ActivityLogFormatter;
use App\Mcp\Helpers\TranslationHelper;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\CustomActivity;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * アクティビティログ取得MCPツール
 *
 * システム全体またはフィルタリングされたアクティビティログを取得し、
 * 既存の翻訳キーとActivityLogFormatterを活用した自然な日本語で表示します。
 */
class GetActivityLogTool extends Tool
{
    use AuthenticatedMcpTool;

    protected string $description = <<<'MARKDOWN'
        Get activity logs with filtering options and Japanese translations
MARKDOWN;

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer('The maximum number of logs to return. Default: 50.')->default(50),
            'format' => $schema->string('Response format: "raw" (default) or "summary".')->enum(['raw', 'summary'])->default('raw'),
            'ledger_id' => $schema->integer('Filter logs by ledger ID.'),
            'folder_id' => $schema->integer('Filter logs by folder ID (includes logs for ledgers in that folder).'),
            'user_id' => $schema->integer('Filter logs by the user who performed the action.'),
            'event_type' => $schema->string('Filter by event type (e.g., "created", "updated", "status_updated").'),
            'start_date' => $schema->string('Filter logs from this date (YYYY-MM-DD).'),
            'end_date' => $schema->string('Filter logs up to this date (YYYY-MM-DD).'),
        ];
    }

    public function handle(Request $request): Response
    {
        try {
            $user = $this->authenticateUser();

            // フィルタパラメータ
            $limit = (int) $request->get('limit', 50);
            $format = $request->get('format', 'raw');
            $ledgerId = $request->get('ledger_id') ? (int) $request->get('ledger_id') : null;
            $folderId = $request->get('folder_id') ? (int) $request->get('folder_id') : null;
            $userId = $request->get('user_id') ? (int) $request->get('user_id') : null;
            $eventType = $request->get('event_type');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            // アクティビティログを取得
            $activities = $this->getActivities(
                $user,
                $limit,
                $ledgerId,
                $folderId,
                $userId,
                $eventType,
                $startDate,
                $endDate
            );

            // レスポンス構築
            $response = $this->buildActivityLogResponse($activities, $format);

            return Response::json($response);

        } catch (\Exception $e) {
            return Response::error(
                trans('ledger.error.occurred_with_message', ['message' => $e->getMessage()])
            );
        }
    }

    /**
     * アクティビティログを取得
     */
    private function getActivities(
        User $user,
        int $limit,
        ?int $ledgerId,
        ?int $folderId,
        ?int $userId,
        ?string $eventType,
        ?string $startDate,
        ?string $endDate
    ): array {
        $query = CustomActivity::query()
            ->with(['causer:id,name', 'subject'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        // フィルタリング
        if ($ledgerId) {
            $query->where('subject_type', Ledger::class)
                ->where('subject_id', $ledgerId);
        }

        if ($folderId) {
            // フォルダに関連する台帳のアクティビティを取得
            $folderLedgerIds = Ledger::whereHas('define', function ($q) use ($folderId) {
                $q->where('folder_id', $folderId);
            })->pluck('id');

            $query->where(function ($q) use ($folderId, $folderLedgerIds) {
                $q->where(function ($subQ) use ($folderId) {
                    $subQ->where('subject_type', Folder::class)
                        ->where('subject_id', $folderId);
                })
                    ->orWhere(function ($subQ) use ($folderLedgerIds) {
                        $subQ->where('subject_type', Ledger::class)
                            ->whereIn('subject_id', $folderLedgerIds);
                    });
            });
        }

        if ($userId) {
            $query->where('causer_id', $userId);
        }

        if ($eventType) {
            $query->where('event', $eventType);
        }

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate.' 23:59:59');
        }

        $activities = $query->limit($limit)->get();

        return $activities->map(function ($activity) {
            return $this->formatActivityForResponse($activity);
        })->toArray();
    }

    /**
     * アクティビティをレスポンス用にフォーマット
     */
    private function formatActivityForResponse(CustomActivity $activity): array
    {
        $changes = ActivityLogFormatter::formatChanges($activity);
        $changesHtml = $changes instanceof \Illuminate\Support\HtmlString ? $changes->toHtml() : (string) $changes;
        $changesText = strip_tags($changesHtml);

        // getSubjectDetailLinkはroute()を呼び出すためエラーが発生する可能性がある
        try {
            $subjectLink = ActivityLogFormatter::getSubjectDetailLink($activity);
        } catch (\Exception $e) {
            $subjectLink = null;
        }

        return [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'description' => $activity->description,
            'event' => $activity->event,
            'event_label' => ActivityLogFormatter::getOperationDescription($activity),
            'subject_type' => $activity->subject_type,
            'subject_id' => $activity->subject_id,
            'subject_display' => ActivityLogFormatter::getSubjectDisplay($activity),
            'causer_id' => $activity->causer_id,
            'causer_name' => ActivityLogFormatter::getCauserDisplayName($activity),
            'properties' => $activity->properties,
            'changes' => $changesText,
            'changes_html' => $changesHtml,
            'comment' => ActivityLogFormatter::formatComment($activity),
            'created_at' => $activity->created_at->toISOString(),
            'created_at_formatted' => $activity->created_at->isoFormat('YYYY/MM/DD HH:mm:ss'),
            'subject_link' => $subjectLink,
        ];
    }

    /**
     * アクティビティログレスポンスの構築
     */
    private function buildActivityLogResponse(array $activities, string $format): array
    {
        $activityCount = count($activities);

        $summary = trans('ledger.statistics.activity_count', ['count' => $activityCount]);

        $displayFields = TranslationHelper::activityDisplayFields();

        if ($format === 'summary') {
            return TranslationHelper::buildMcpResponse(
                $summary,
                $displayFields,
                [
                    'activities' => $activities,
                    'total_count' => $activityCount,
                ]
            );
        }

        return [
            '__summary__' => $summary,
            '__display_fields__' => $displayFields,
            'activities' => $activities,
            'total_count' => $activityCount,
        ];
    }
}
