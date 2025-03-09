<?php

namespace App\Policies;

use App\Models\User;

class ActivityLogPolicy
{
    /**
     * ユーザーがアクティビティログを表示できるかどうかの判定
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_activity_logs');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('view_activity_logs');
    }
}
