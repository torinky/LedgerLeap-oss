<?php

namespace App\Policies;

use App\Models\CustomActivity;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomActivityPolicy
{
    use HandlesAuthorization;

    protected $userService;

    public function __construct(UserService $userService)
    {
        //        dd('ActivityLogPolicy::__construct called');
        $this->userService = $userService;
    }

    /**
     * ユーザーがアクティビティログを表示できるかどうかの判定
     */
    public function viewAny(User $user): bool
    {
        return $this->userService->hasPermission($user, 'view_activity_logs');
    }

    public function view(User $user, ?CustomActivity $activity = null): bool
    {
        return $this->userService->hasPermission($user, 'view_activity_logs');
    }
}
