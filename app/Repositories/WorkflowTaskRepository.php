<?php

namespace App\Repositories;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

// Auth ファサードを追加

class WorkflowTaskRepository
{
    public function getPendingTasksForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $userRoleIds = $user->getAllUniqueRoles()->pluck('id')->toArray();

        return Ledger::query() // <<<--- Ledger を起点にする
            ->with(['creator:id,name', 'define:id,title,workflow_enabled,folder_id', 'latestDiff']) // latestDiff を Eager Load
            ->where(function ($query) use ($user) {
                // ステータスが点検待ちで、最新Diffの点検者が自分
                $query->where('status', WorkflowStatus::PENDING_INSPECTION)
                    ->whereHas('latestDiff', function ($diffQuery) use ($user) {
                        $diffQuery->where('inspector_id', $user->id);
                    });
                // ToDo: ロール指定点検者
            })
            ->orWhere(function ($query) use ($user) {
                // ステータスが承認待ちで、最新Diffの承認者が自分
                $query->where('status', WorkflowStatus::PENDING_APPROVAL)
                    ->whereHas('latestDiff', function ($diffQuery) use ($user) {
                        $diffQuery->where('approver_id', $user->id);
                    });
                // ToDo: ロール指定承認者
            })
            ->latest('updated_at') // Ledger の更新日時でソート (または latestDiff.created_at でソート?)
            ->paginate($perPage, ['*'], pageName: 'task_page');
    }
}
