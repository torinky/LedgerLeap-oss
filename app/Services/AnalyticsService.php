<?php

namespace App\Services;

use App\Models\CustomActivity;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function __construct(
        private WritableFolderRepository $folderRepository
    ) {}

    /**
     * 期間別台帳統計を取得
     *
     * @param  User  $user  統計を取得するユーザー
     * @param  Carbon  $from  開始日時
     * @param  Carbon  $to  終了日時
     * @return array 統計データ
     */
    public function getLedgerStatsByPeriod(User $user, Carbon $from, Carbon $to): array
    {
        // ユーザーが閲覧可能なフォルダIDを取得
        $accessibleFolderIds = $this->folderRepository->getReadableFolderIds($user);

        // アクセス可能なフォルダがない場合は空の統計を返す
        if (empty($accessibleFolderIds)) {
            return [
                'period' => [
                    'from' => $from->toIso8601String(),
                    'to' => $to->toIso8601String(),
                ],
                'total_created' => 0,
                'by_define' => [],
                'by_status' => [],
                'by_creator' => [],
            ];
        }

        // 基本クエリ: アクセス可能なフォルダの台帳のみ
        $baseQuery = Ledger::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereHas('define', function ($query) use ($accessibleFolderIds) {
                $query->whereIn('folder_id', $accessibleFolderIds);
            });

        // 総数
        $totalCreated = (clone $baseQuery)->count();

        // 台帳定義別の統計
        $byDefine = (clone $baseQuery)
            ->select('ledger_define_id', DB::raw('count(*) as count'))
            ->groupBy('ledger_define_id')
            ->with('define:id,title')
            ->get()
            ->map(function ($item) {
                return [
                    'ledger_define_id' => $item->ledger_define_id,
                    'ledger_define_name' => $item->define->title ?? trans('common.unknown'),
                    'count' => $item->count,
                ];
            })
            ->toArray();

        // ステータス別の統計
        $byStatus = (clone $baseQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                $statusValue = is_object($item->status) ? $item->status->value : $item->status;

                return [
                    'status' => $statusValue,
                    'status_display' => trans('ledger.workflow.status.'.$statusValue, [], 'ja'),
                    'count' => $item->count,
                ];
            })
            ->toArray();

        // 作成者別の統計（上位5名）
        $byCreator = (clone $baseQuery)
            ->select('creator_id', DB::raw('count(*) as count'))
            ->groupBy('creator_id')
            ->orderByDesc('count')
            ->limit(5)
            ->with('creator:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'user_id' => $item->creator_id,
                    'user_name' => $item->creator->name ?? trans('common.unknown'),
                    'count' => $item->count,
                ];
            })
            ->toArray();

        return [
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'total_created' => $totalCreated,
            'by_define' => $byDefine,
            'by_status' => $byStatus,
            'by_creator' => $byCreator,
        ];
    }

    /**
     * ユーザー別活動統計を取得
     *
     * @param  User  $user  統計を取得するユーザー
     * @param  Carbon  $from  開始日時
     * @param  Carbon  $to  終了日時
     * @return array 統計データ
     */
    public function getUserActivityStats(User $user, Carbon $from, Carbon $to): array
    {
        // ユーザーが閲覧可能なフォルダIDを取得
        $accessibleFolderIds = $this->folderRepository->getReadableFolderIds($user);

        // アクセス可能なフォルダがない場合は空の統計を返す
        if (empty($accessibleFolderIds)) {
            return [
                'period' => [
                    'from' => $from->toIso8601String(),
                    'to' => $to->toIso8601String(),
                ],
                'total_activities' => 0,
                'by_event' => [],
                'by_user' => [],
                'by_hour' => [],
            ];
        }

        // アクティビティログの基本クエリ
        $baseQuery = CustomActivity::query()
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($query) use ($accessibleFolderIds) {
                // Ledgerに関連するアクティビティで、アクセス可能なフォルダのもののみ
                $query->where(function ($q) use ($accessibleFolderIds) {
                    $q->where('subject_type', Ledger::class)
                        ->whereIn('subject_id', function ($subQuery) use ($accessibleFolderIds) {
                            $subQuery->select('id')
                                ->from('ledgers')
                                ->whereIn('ledger_define_id', function ($defineQuery) use ($accessibleFolderIds) {
                                    $defineQuery->select('id')
                                        ->from('ledger_defines')
                                        ->whereIn('folder_id', $accessibleFolderIds)
                                        ->whereNull('deleted_at');
                                });
                        });
                })
                    ->orWhereNull('subject_id');
            });

        // 総数
        $totalActivities = (clone $baseQuery)->count();

        // イベント種類別の統計
        $byEvent = (clone $baseQuery)
            ->select('description', DB::raw('count(*) as count'))
            ->groupBy('description')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'event' => $item->description,
                    'event_display' => trans('activity.'.$item->description, [], 'ja'),
                    'count' => $item->count,
                ];
            })
            ->toArray();

        // ユーザー別の統計（上位10名）
        $byUser = (clone $baseQuery)
            ->select('causer_id', DB::raw('count(*) as count'))
            ->whereNotNull('causer_id')
            ->groupBy('causer_id')
            ->orderByDesc('count')
            ->limit(10)
            ->with('causer:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'user_id' => $item->causer_id,
                    'user_name' => $item->causer->name ?? trans('common.unknown'),
                    'count' => $item->count,
                ];
            })
            ->toArray();

        // 時間帯別の統計
        $byHour = (clone $baseQuery)
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('count(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => $item->hour,
                    'count' => $item->count,
                ];
            })
            ->toArray();

        return [
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'total_activities' => $totalActivities,
            'by_event' => $byEvent,
            'by_user' => $byUser,
            'by_hour' => $byHour,
        ];
    }

    /**
     * フォルダ別統計を取得
     *
     * @param  User  $user  統計を取得するユーザー
     * @return array 統計データ
     */
    public function getFolderStats(User $user): array
    {
        // ユーザーが閲覧可能なフォルダを取得
        $accessibleFolders = Folder::whereIn('id', $this->folderRepository->getReadableFolderIds($user))->get();

        $stats = $accessibleFolders->map(function ($folder) {
            // フォルダ内の台帳定義数
            $defineCount = $folder->ledgerDefines()->count();

            // フォルダ内の台帳数（台帳定義経由）
            $ledgerCount = Ledger::whereHas('define', function ($query) use ($folder) {
                $query->where('folder_id', $folder->id);
            })->count();

            // 最近の活動（過去7日間）
            $recentActivity = Ledger::whereHas('define', function ($query) use ($folder) {
                $query->where('folder_id', $folder->id);
            })
                ->where('created_at', '>=', now()->subDays(7))
                ->count();

            return [
                'folder_id' => $folder->id,
                'folder_name' => $folder->name,
                'folder_path' => $folder->path ?? $folder->name,
                'ledger_define_count' => $defineCount,
                'ledger_count' => $ledgerCount,
                'recent_activity' => $recentActivity,
            ];
        })->toArray();

        return [
            'folders' => $stats,
            'total_folders' => count($stats),
            'total_ledger_defines' => array_sum(array_column($stats, 'ledger_define_count')),
            'total_ledgers' => array_sum(array_column($stats, 'ledger_count')),
        ];
    }
}
