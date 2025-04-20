<?php

namespace App\Repositories;

use App\Models\Ledger;
use App\Models\User;
use App\Models\LedgerDiff;
use App\Enums\WorkflowStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

// Auth ファサードを追加

class WorkflowTaskRepository
{
    public function getPendingTasksForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        // ユーザーが所属するロールIDを取得 (承認ルートで使用する場合)
        // $userRoleIds = $user->roles()->pluck('id')->toArray(); // 直接のロールのみ？
        $userRoleIds = $user->getAllUniqueRoles()->pluck('id')->toArray(); // 継承含む

        return LedgerDiff::query()
            ->with(['creator:id,name', 'ledger.define:id,title']) // 必要な情報のみ Eager Load
            ->where(function ($query) use ($user, $userRoleIds) {
                // 自分が点検者で、ステータスが点検待ち
                $query->where('status', WorkflowStatus::PENDING_INSPECTION)
                    ->where('inspector_id', $user->id);
                // ToDo: または、自分が所属するロールが点検者ロールに指定されている場合
                // ->orWhere(function($q) use ($userRoleIds) {
                //     $q->where('status', WorkflowStatus::PENDING_INSPECTION)
                //       ->whereNull('inspector_id') // 個人指定がない場合？
                //       ->whereIn('inspector_role_id', $userRoleIds); // inspector_role_id カラムが必要
                // });
            })
            ->orWhere(function ($query) use ($user, $userRoleIds) {
                // 自分が承認者で、ステータスが承認待ち
                $query->where('status', WorkflowStatus::PENDING_APPROVAL)
                    ->where('approver_id', $user->id);
                // ToDo: または、自分が所属するロールが承認者ロールに指定されている場合
                // ->orWhere(function($q) use ($userRoleIds) {
                //     $q->where('status', WorkflowStatus::PENDING_APPROVAL)
                //       ->whereNull('approver_id')
                //       ->whereIn('approver_role_id', $userRoleIds); // approver_role_id カラムが必要
                // });
            })
            ->latest('requested_at') // 申請が新しい順
            ->paginate($perPage);
    }

    /*    public function getPendingTasksForUser(User $user, int $perPage = 15): LengthAwarePaginator
        {
            $userRoleIds = $user->getAllUniqueRoles()->pluck('id')->toArray();

            // 修正: Ledger テーブルをクエリ対象にする
            return Ledger::query()
                ->with(['creator:id,name', 'define:id,title']) // 必要なリレーション
                ->where(function ($query) use ($user, $userRoleIds) {
                    // 自分が点検者で、ステータスが点検待ち
                    $query->where('status', WorkflowStatus::PENDING_INSPECTION)
                        ->latestDiff()->where('inspector_id', $user->id);
                    // ToDo: ロール指定の点検者
                    // ->orWhere(function($q) use ($userRoleIds) {
                    //     $q->where('status', WorkflowStatus::PENDING_INSPECTION)
                    //       ->whereIn('inspector_role_id', $userRoleIds); // Ledger に inspector_role_id が必要
                    // });
                })
                ->orWhere(function ($query) use ($user, $userRoleIds) {
                    // 自分が承認者で、ステータスが承認待ち
                    $query->where('status', WorkflowStatus::PENDING_APPROVAL)
                        ->latestDiff()->where('approver_id', $user->id);
                    // ToDo: ロール指定の承認者
                    // ->orWhere(function($q) use ($userRoleIds) {
                    //     $q->where('status', WorkflowStatus::PENDING_APPROVAL)
                    //       ->whereIn('approver_role_id', $userRoleIds); // Ledger に approver_role_id が必要
                    // });
                })
                // 修正: requested_at は LedgerDiff にないので、Ledger.updated_at などでソート？
                //       または Activity Log と JOIN する？ -> Activity Log 参照がより正確か
                //       一旦 updated_at でソートしておく
                ->latest('updated_at')
                ->paginate($perPage);
        }*/
    /*    public function getPendingTasksForUser(User $user, int $perPage = 15): LengthAwarePaginator
        {
            $userRoleIds = $user->getAllUniqueRoles()->pluck('id')->toArray();

            return Ledger::query()
                ->with(['creator:id,name', 'define:id,title'])
                // latestDiff リレーションが存在する場合のみ whereHas を適用
                ->when(method_exists(Ledger::class, 'latestDiff'), function ($query) use ($user, $userRoleIds) {
                    $query->where(function ($subQuery) use ($user, $userRoleIds) {
                        // 自分が点検者で、ステータスが点検待ち (LedgerDiff 側に inspector_id がある前提)
                        $subQuery->where('status', WorkflowStatus::PENDING_INSPECTION)
                            ->whereHas('latestDiff', function ($diffQuery) use ($user) {
                                // LedgerDiff テーブルのカラム名を指定 (例: inspector_id)
                                // カラムが存在するか確認することが推奨される
                                if (Schema::hasColumn('ledger_diffs', 'inspector_id')) {
                                    $diffQuery->where('inspector_id', $user->id);
                                } else {
                                    // カラムが存在しない場合、この条件は常に false にする
                                    $diffQuery->whereRaw('1 = 0');
                                }
                            });
                        // ToDo: ロール指定の点検者 (LedgerDiff 側に inspector_role_id がある前提)
                        // ...
                    })
                        ->orWhere(function ($subQuery) use ($user, $userRoleIds) {
                            // 自分が承認者で、ステータスが承認待ち (LedgerDiff 側に approver_id がある前提)
                            $subQuery->where('status', WorkflowStatus::PENDING_APPROVAL)
                                ->whereHas('latestDiff', function ($diffQuery) use ($user) {
                                    // LedgerDiff テーブルのカラム名を指定 (例: approver_id)
                                    // カラムが存在するか確認することが推奨される
                                    if (Schema::hasColumn('ledger_diffs', 'approver_id')) {
                                        $diffQuery->where('approver_id', $user->id);
                                    } else {
                                        // カラムが存在しない場合、この条件は常に false にする
                                        $diffQuery->whereRaw('1 = 0');
                                    }
                                });
                            // ToDo: ロール指定の承認者 (LedgerDiff 側に approver_role_id がある前提)
                            // ...
                        });
                })
                ->orderBy('updated_at', 'desc')
                ->paginate($perPage);
        }*/

}
