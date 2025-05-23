<?php

namespace App\Livewire\Notifications;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;

use Livewire\Component;
use Mary\Traits\Toast;


// Filament 通知を use

class Settings extends Component
{
    use Toast;

    #[Locked] // ユーザーIDは変更不可にする
    public User $user;


    #[Locked] // 対象とするPermission名リストは変更不可
    public array $targetPermissionNames = [
        'receive_workflow_summary_email',
        'receive_workflow_action_email',
        // 今後、ユーザーが設定可能な他のPermissionがあればここに追加
    ];

    public array $notificationSettings = []; // ビューで使う設定データ配列


    // 保存ボタンを有効にするかどうかの Computed プロパティ
    #[Computed]
    public function canSaveChanges(): bool
    {
        // notificationSettings 配列が存在し、かつ disabled が false の項目が1つでもあれば true
        // collect を使って判定
        return !empty($this->notificationSettings) && collect($this->notificationSettings)->contains(fn($setting) => !$setting['disabled']);
    }

    public function mount()
    {
        $this->user = Auth::user();
        $this->loadSettings(); // 初期設定読み込み
    }

    protected function loadSettings(): void
    {
        $this->notificationSettings = [];
        $permissions = Permission::whereIn('name', $this->targetPermissionNames)->get();

        foreach ($permissions as $permission) {
            $permissionName = $permission->name;
            $hasDirectPermission = $this->user->hasDirectPermission($permissionName);
            $hasPermissionViaRole = $this->user->hasPermissionTo($permissionName) && !$hasDirectPermission;

            // description は lang ファイルから取得 (キー名を descriptions に変更)
            $descriptionKey = 'permission.descriptions.' . $permissionName; // descriptions を使用
            // Lang::has() でキーの存在を確認してから __() を使う
            $description = Lang::has($descriptionKey) ? __($descriptionKey) : ($permission->description ?? '');

            $this->notificationSettings[$permissionName] = [
                'name' => $permissionName,
                'label' => __('permission.name.' . $permissionName),
                'description' => $description, // 修正した description を格納
                'enabled' => $this->user->can($permissionName),
                'is_direct' => $hasDirectPermission,
                'via_role' => $hasPermissionViaRole,
                'disabled' => $hasPermissionViaRole,
            ];
        }
    }

    public function render()
    {
        return view('livewire.notifications.settings')
            ->layout('layouts.app', ['title' => __('ledger.notification.settings.title')]);
    }

    public function save(): void
    {
        // 保存ボタンが非活性なら処理しない
        if (!$this->canSaveChanges()) {
            return;
        }

        $this->validate([
            'notificationSettings.*.enabled' => 'required|boolean',
        ]);

        try {
            foreach ($this->notificationSettings as $permissionName => $setting) {
                // ロール経由で無効化されている場合はスキップ
                if ($setting['disabled']) {
                    continue;
                }

                $hasDirectPermissionCurrently = $this->user->hasDirectPermission($permissionName);
                $shouldHavePermission = $setting['enabled']; // トグルの状態

                // 状態が変更された場合のみ処理
                if ($shouldHavePermission && !$hasDirectPermissionCurrently) {
                    $this->user->givePermissionTo($permissionName);
                } elseif (!$shouldHavePermission && $hasDirectPermissionCurrently) {
                    $this->user->revokePermissionTo($permissionName);
                }
            }

            // 保存成功のトースト通知
            $this->success(__('ledger.stored.success')); // MaryUI の toastSuccess を使用

            // 設定を再読み込みしてビューに反映
            $this->loadSettings();

        } catch (\Exception $e) {
            // エラーのトースト通知
            $this->error(__('ledger.stored.failed'), $e->getMessage()); // MaryUI の toastError を使用

            // 必要であればログに記録
            Log::error("Failed to save notification settings for user {$this->user->id}: " . $e->getMessage());
        }
    }
}