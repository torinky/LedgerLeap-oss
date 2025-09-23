<?php

namespace App\Livewire\Workflow;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Services\UserService;
use App\Services\WorkflowService;
use Illuminate\Support\Collection as SupportCollection;
// SupportCollection を use
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;
use App\Livewire\Traits\InitializesTenantContext;

class WorkflowAssigneeSelect extends Component
{
    use InitializesTenantContext;

    #[Locked]
    public int $ledgerDefineId;

    #[Locked]
    public int $folderId;

    #[Locked]
    public string $roleType; // 'inspector' or 'approver'

    #[Locked]
    public ?int $ledgerId = null;

    #[Modelable]
    public ?int $selectedUserId = null;

    #[Locked]
    public array $requiredInspectorRoleIds = []; // 親から渡される

    #[Locked]
    public array $requiredApproverRoleIds = [];  // 親から渡される

    public string $searchQuery = ''; // 検索クエリ用プロパティ

    // 検索用メソッド名 (MaryUI デフォルトは 'search')
    // public string $searchFunctionName = 'searchAssignees';
    protected WorkflowService $workflowService; // WorkflowService をインジェクト

    // MaryUI <x-choices> に渡すオプションリスト (Collection 型)
    public SupportCollection $options;

    protected UserService $userService;

    public function boot(UserService $userService, WorkflowService $workflowService): void // WorkflowService を追加
    {
        $this->userService = $userService;
        $this->workflowService = $workflowService; // インジェクト

        // 初期オプションを Collection で初期化
        $this->options = collect([]);
    }

    public function mount(
        int $ledgerDefineId, int $folderId, string $roleType, ?int $ledgerId = null,
        ?int $initialUserId = null, array $requiredInspectorRoleIds = [], array $requiredApproverRoleIds = []
    ): void {
        $this->ledgerDefineId = $ledgerDefineId;
        $this->folderId = $folderId;
        $this->roleType = $roleType;
        $this->ledgerId = $ledgerId;
        $this->selectedUserId = $initialUserId;
        $this->requiredInspectorRoleIds = $requiredInspectorRoleIds;
        $this->requiredApproverRoleIds = $requiredApproverRoleIds;
        $this->searchAssignees('');
    }

    /**
     * MaryUI searchable から呼び出される検索メソッド
     * (search-function 属性で名前変更可能)
     *
     * @param  string  $value  検索クエリ
     */
    public function searchAssignees(string $value = ''): void
    {
        $this->searchQuery = $value;
        Log::debug("Searching assignees with query: '{$this->searchQuery}', roleType: {$this->roleType}, selectedUserId: {$this->selectedUserId}");

        $this->options = $this->fetchOptions($this->roleType,
            ($this->roleType === 'inspector') ? FolderPermissionType::INSPECT : FolderPermissionType::APPROVE
        );

        // 選択中のユーザーがオプションに含まれているか確認し、なければ追加
        if ($this->selectedUserId && ! $this->options->contains('id', $this->selectedUserId)) {
            $selectedUser = User::with(['organizations', 'roles'])->find($this->selectedUserId);
            if ($selectedUser) {
                // 選択中ユーザーにカスタム属性を付与してリストの先頭に追加
                $selectedUser->custom_reasons = ['selected']; // 仮の理由
                $selectedUser->custom_sort_priority = -1; // 最優先
                $this->options->prepend($selectedUser);
            }
        }
        // 再度ソート
        $this->options = $this->options->sortBy([
            ['custom_sort_priority', 'asc'],
            ['name', 'asc'],
        ])->unique('id')->values();

        Log::debug('Assignee options updated. Count: '.$this->options->count());
    }

    /**
     * 点検者候補リストを取得・統合・ソートする
     */
    protected function fetchInspectorOptions(): SupportCollection
    {
        return $this->fetchOptions('inspector', FolderPermissionType::INSPECT);
    }

    /**
     * 承認者候補リストを取得・統合・ソートする
     */
    protected function fetchApproverOptions(): SupportCollection
    {
        return $this->fetchOptions('approver', FolderPermissionType::APPROVE);
    }

    /**
     * 担当者候補リストを取得する共通ロジック
     */
    protected function fetchOptions(string $roleType, FolderPermissionType $requiredPermission): SupportCollection
    {
        $folder = Folder::find($this->folderId); // 必須ロールはFolderモデルのリレーションで取得済みのはず
        if (! $folder) {
            return collect();
        }

        $usersWithOptions = collect(); // Userモデルと追加情報を格納するコレクション
        $addedUserIds = [];

        $targetRequiredRoles = ($roleType === 'inspector') ? $folder->requiredInspectorRoles : $folder->requiredApproverRoles;

        // 1. フォルダ必須ロールの担当者
        foreach ($targetRequiredRoles as $role) {
            foreach ($role->users()->with(['organizations', 'roles'])->where('name', 'like', "%{$this->searchQuery}%")->get() as $user) {
                if (! isset($addedUserIds[$user->id])) {
                    $user->custom_reasons = ['required_role'];
                    $user->custom_sort_priority = 1;
                    $usersWithOptions->push($user);
                    $addedUserIds[$user->id] = true;
                } // 理由追加は map 後に行う
            }
        }

        // 2. 実績ベース推奨ユーザー
        $frequentUserIdsAndNames = $this->workflowService->getFrequentAssignees($this->ledgerDefineId, $roleType, 10, $this->searchQuery); // 少し多めに取得
        foreach ($frequentUserIdsAndNames as $userData) {
            $userId = $userData['id'];
            if (isset($addedUserIds[$userId])) { // 既にリストにあれば理由追加
                $usersWithOptions = $usersWithOptions->map(function (User $u) use ($userId) {
                    if ($u->id === $userId) {
                        $reasons = $u->custom_reasons ?? [];
                        $reasons[] = 'frequent';
                        $u->custom_reasons = $reasons;
                    }

                    return $u;
                });
            } else {
                $user = User::with(['organizations', 'roles'])->find($userId);
                if ($user) {
                    $user->custom_reasons = ['frequent'];
                    $user->custom_sort_priority = 2;
                    $usersWithOptions->push($user);
                    $addedUserIds[$user->id] = true;
                }
            }
        }

        // 3. 直近の担当者履歴
        if ($this->ledgerId) {
            $recentAssignee = $this->getRecentAssignee($this->ledgerId, $roleType);
            if ($recentAssignee && (empty($this->searchQuery) || stripos($recentAssignee->name, $this->searchQuery) !== false)) {
                if (isset($addedUserIds[$recentAssignee->id])) {
                    $usersWithOptions = $usersWithOptions->map(function (User $u) use ($recentAssignee) {
                        if ($u->id === $recentAssignee->id) {
                            $reasons = $u->custom_reasons ?? [];
                            $reasons[] = 'recent';
                            $u->custom_reasons = $reasons;
                            $u->custom_sort_priority = min($u->custom_sort_priority ?? 99, 0);
                        }

                        return $u;
                    });
                } else {
                    $recentAssignee->loadMissing(['organizations', 'roles']);
                    $recentAssignee->custom_reasons = ['recent'];
                    $recentAssignee->custom_sort_priority = 0;
                    $usersWithOptions->push($recentAssignee);
                    $addedUserIds[$recentAssignee->id] = true;
                }
            }
        }

        // 4. その他の権限保有ユーザー
        $authorizedUsers = $this->userService->getUsersWithFolderPermission($folder, $requiredPermission, $this->searchQuery);
        foreach ($authorizedUsers as $user) {
            if (isset($addedUserIds[$user->id])) {
                $usersWithOptions = $usersWithOptions->map(function (User $u) use ($user) {
                    if ($u->id === $user->id && ! in_array('authorized', $u->custom_reasons ?? [])) {
                        $reasons = $u->custom_reasons ?? [];
                        $reasons[] = 'authorized';
                        $u->custom_reasons = $reasons;
                    }

                    return $u;
                });
            } else {
                $user->loadMissing(['organizations', 'roles']);
                $user->custom_reasons = ['authorized'];
                $user->custom_sort_priority = 3;
                $usersWithOptions->push($user);
                $addedUserIds[$user->id] = true;
            }
        }

        // 5. 「過去の承認ルート」からの候補者
        $pastRouteUsers = $this->getPastRouteAssignees($this->ledgerDefineId, $roleType, 3);
        foreach ($pastRouteUsers as $user) {
            if (empty($this->searchQuery) || stripos($user->name, $this->searchQuery) !== false) {
                if (isset($addedUserIds[$user->id])) {
                    $usersWithOptions = $usersWithOptions->map(function (User $u) use ($user) {
                        if ($u->id === $user->id && ! in_array('past_route', $u->custom_reasons ?? [])) {
                            $reasons = $u->custom_reasons ?? [];
                            $reasons[] = 'past_route';
                            $u->custom_reasons = $reasons;
                            $u->custom_sort_priority = min($u->custom_sort_priority ?? 99, 2);
                        }

                        return $u;
                    });
                } else {
                    $user->loadMissing(['organizations', 'roles']);
                    $user->custom_reasons = ['past_route'];
                    $user->custom_sort_priority = 2;
                    $usersWithOptions->push($user);
                    $addedUserIds[$user->id] = true;
                }
            }
        }

        // 最終的なソートと、表示に必要なカスタム属性の最終整形
        return $usersWithOptions->unique('id')->sortBy([
            ['custom_sort_priority', 'asc'],
            ['name', 'asc'],
        ])->values()->map(function (User $user) {
            $user->custom_organization_name = $user->primaryOrganization()?->name ?? ($user->organizations()->first()?->name ?? '');
            $user->custom_roles_string = $user->getAllUniqueRoles()->pluck('name')->implode(', ');

            // ★ 理由アイコン情報を追加
            $presentations = [];
            if (! empty($user->custom_reasons)) {
                foreach ($user->custom_reasons as $reasonCode) {
                    $presentations[] = $this->getReasonPresentation($reasonCode);
                }
            }
            $user->custom_reason_presentations = $presentations;

            return $user;
        });
    }

    /**
     * 理由コードに対応する Heroicon 名、ツールチップ用翻訳キー、凡例用ラベル翻訳キーの配列を返す
     *
     * @return array ['icon' => string, 'tooltip_key' => string, 'legend_key' => string]
     */
    protected static function getReasonPresentation(string $reason): array
    {
        return match ($reason) {
            'recent' => ['icon' => 'o-clock',           'tooltip_key' => 'ledger.workflow.reason_tooltip.recent',         'legend_key' => 'ledger.workflow.reason_legend.recent'],
            'frequent' => ['icon' => 'o-star',            'tooltip_key' => 'ledger.workflow.reason_tooltip.frequent',       'legend_key' => 'ledger.workflow.reason_legend.frequent'],
            'authorized' => ['icon' => 'o-check-badge',   'tooltip_key' => 'ledger.workflow.reason_tooltip.authorized',     'legend_key' => 'ledger.workflow.reason_legend.authorized'],
            'required_role' => ['icon' => 'o-identification', 'tooltip_key' => 'ledger.workflow.reason_tooltip.required_role',  'legend_key' => 'ledger.workflow.reason_legend.required_role'],
            'past_route' => ['icon' => 'o-arrow-path',      'tooltip_key' => 'ledger.workflow.reason_tooltip.past_route',     'legend_key' => 'ledger.workflow.reason_legend.past_route'],
            'selected' => ['icon' => 's-check-circle',  'tooltip_key' => 'ledger.workflow.reason_tooltip.selected',       'legend_key' => 'ledger.workflow.reason_legend.selected'],
            default => ['icon' => '', 'tooltip_key' => '', 'legend_key' => ''],
        };
    }

    /**
     * 利用可能な全ての理由の表示情報を取得する (凡例表示用)
     */
    public static function getAllReasonPresentations(): array
    {
        $allReasons = ['recent', 'frequent', 'authorized', 'required_role', 'past_route']; // selected は除く
        $presentations = [];
        foreach ($allReasons as $reason) {
            $presentations[$reason] = self::getReasonPresentation($reason);
        }

        return $presentations;
    }

    /**
     * 直近の担当者を取得
     */
    protected function getRecentAssignee(?int $ledgerId, string $roleType): ?User
    {
        if (! $ledgerId) {
            return null;
        }
        // 修正: roleType に応じてカラムを決定
        $column = ($roleType === 'inspector') ? 'inspector_id' : 'approver_id';

        $latestDiff = LedgerDiff::where('ledger_id', $ledgerId)
            ->whereNotNull($column)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $latestDiff?->{$roleType};
    }

    /**
     * 同じ台帳定義の過去の完了案件から担当者を取得する (新規)
     */
    protected function getPastRouteAssignees(int $ledgerDefineId, string $roleType, int $limit): SupportCollection
    {
        $column = ($roleType === 'inspector') ? 'inspector_id' : 'approver_id';

        // 最新の完了済みLedgerを取得
        $completedLedgers = Ledger::where('ledger_define_id', $ledgerDefineId)
            ->where('status', WorkflowStatus::APPROVED)
            ->orderByDesc('updated_at') // または承認日時
            ->limit($limit * 2) // 複数の担当者がいる可能性を考慮して少し多めに取得
            ->get();

        $assigneeIds = collect();
        foreach ($completedLedgers as $ledger) {
            // そのLedgerの最終承認Diffまたは関連するDiffから担当者IDを取得
            $diffs = $ledger->ledgerDiff()->whereNotNull($column)->orderByDesc('id')->get();
            foreach ($diffs as $diff) {
                if ($diff->{$column}) {
                    $assigneeIds->push($diff->{$column});
                }
            }
        }

        // IDの出現頻度でソートし、上位を取得、Userモデルを返す
        return User::whereIn('id', $assigneeIds->countBy()->sortDesc()->keys()->take($limit))->get();
    }

    public function render()
    {
        return view('livewire.workflow.workflow-assignee-select');
    }

    /**
     * wire:model.live で selectedUserId が更新された後に呼ばれるメソッド
     * 選択状態を維持するためにオプションを再読み込みする
     */
    public function updatedSelectedUserId($value): void
    {
        // 選択がクリアされた場合は何もしない (またはオプションをリセット)
        if (is_null($value)) {
            $this->searchAssignees(''); // 初期状態に戻すなど

            return;
        }
        // 選択されたIDを含むオプションリストを再生成
        // 検索クエリが残っている場合、そのクエリで再検索する必要がある
        $this->searchAssignees($this->searchQuery);
        Log::debug("selectedUserId updated to {$value}, options reloaded.");
    }
}
