<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\LedgerDiff;
use App\Enums\WorkflowStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

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
}
