<?php

namespace App\Livewire\Workflow;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Collection; // Collection を use
use Livewire\Component;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\Locked;
// use Livewire\Attributes\Computed; // Computed は使わない
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkflowAssigneeSelect extends Component
{
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

    public string $searchQuery = ''; // 検索クエリ用プロパティ
    // 検索用メソッド名 (MaryUI デフォルトは 'search')
    // public string $searchFunctionName = 'searchAssignees';

    // MaryUI <x-choices> に渡すオプションリスト (Collection 型)
    public Collection $options;

    protected UserService $userService;

    public function boot(UserService $userService): void
    {
        $this->userService = $userService;
        // 初期オプションを Collection で初期化
        $this->options = collect([]);
    }

    public function mount(int $ledgerDefineId, int $folderId, string $roleType, ?int $ledgerId = null, ?int $initialUserId = null): void
    {
        $this->ledgerDefineId = $ledgerDefineId;
        $this->folderId = $folderId;
        $this->roleType = $roleType;
        $this->ledgerId = $ledgerId;
        // --- selectedUserId の初期値をセット ---
        // wire:model で渡される値よりも mount で渡された初期値を優先する場合はここで設定
        // ただし、@Modelable があると wire:model が優先される可能性が高い
//         $this->selectedUserId = $initialUserId;
        // ------------------------------------
        $this->searchAssignees(); // 初期オプションをロード
        // mount 時点で selectedUserId (wire:model で渡された値 or initialUserId) がセットされているので、
        // loadOptions 内で選択中ユーザーをリストに含める処理が機能するはず。
    }

    /**
     * MaryUI searchable から呼び出される検索メソッド
     * (search-function 属性で名前変更可能)
     *
     * @param string $value 検索クエリ
     */
    public function searchAssignees(string $value = ''): void
    {
        // ★ 引数で渡された検索語をプロパティに保存
        $this->searchQuery = $value;
        Log::debug("Searching assignees with query: '{$this->searchQuery}', roleType: {$this->roleType}, selectedUserId: {$this->selectedUserId}");

        // 統合・ソート済みの候補リストを取得 (前回実装したロジック)
        if ($this->roleType === 'inspector') {
            $combinedOptions = $this->fetchInspectorOptions(
                $this->ledgerDefineId, $this->folderId, $this->ledgerId, $this->searchQuery // 検索語を渡す
            );
        } elseif ($this->roleType === 'approver') {
            // TODO: 承認者用の取得ロジック
            $combinedOptions = $this->fetchApproverOptions(
                $this->ledgerDefineId, $this->folderId, $this->ledgerId, $this->searchQuery
            );
        } else {
            $combinedOptions = [];
        }

        // 選択中のユーザー情報を取得し、候補リストに必ず含める
        $selectedOption = null;
        if ($this->selectedUserId) {
            // 既存の候補リストから探す
            $selectedKey = array_search($this->selectedUserId, array_column($combinedOptions, 'id'));
            if ($selectedKey !== false) {
                $selectedOption = $combinedOptions[$selectedKey];
                // 一旦削除して後で先頭に追加する or そのままにしておく
                unset($combinedOptions[$selectedKey]); // 一旦削除
            } else {
                // 候補リストにない場合はDBから取得
                $user = User::find($this->selectedUserId);
                if ($user) {
                    // 理由をどうするか？ (例: 'selected' or 'authorized'?)
                    $selectedOption = ['id' => $user->id, 'name' => $user->name, 'reasons' => ['selected'], 'sort_priority' => 0]; // 最優先に
                }
            }
        }

        // 候補リストを MaryUI <x-choices> 用の形式 (id, name を持つ配列 or Collection) に変換
        $finalOptions = collect($combinedOptions)->map(function ($opt) {
            $reasonLabels = array_map(fn($r) => $this->getReasonLabel($r), $opt['reasons'] ?? []);
            $displayName = $opt['name'] . (!empty($reasonLabels) ? ' ' . implode(' ', $reasonLabels) : '');
            return ['id' => (int)$opt['id'], 'name' => $displayName];
        });

        // 選択中のユーザーがいれば、リストの先頭に追加（またはマージ）
        if ($selectedOption) {
            $reasonLabels = array_map(fn($r) => $this->getReasonLabel($r), $selectedOption['reasons'] ?? []);
            $displayName = $selectedOption['name'] . (!empty($reasonLabels) ? ' ' . implode(' ', $reasonLabels) : '');
            // 既にリストに含まれていない場合のみ追加 (unsetした場合) or 常に先頭に追加する場合
            // $finalOptions->prepend(['id' => $selectedOption['id'], 'name' => $displayName]);
            $finalOptions = $finalOptions->prepend(['id' => $selectedOption['id'], 'name' => $displayName])->unique('id'); // uniqueで重複削除
        }

        // $this->options を更新
        $this->options = $finalOptions;
        Log::debug("Assignee options loaded/updated. Count: " . $this->options->count());
    }


    /**
     * 点検者候補リストを取得・統合・ソートする (修正: 検索クエリを追加)
     */
    protected function fetchInspectorOptions(int $ledgerDefineId, int $folderId, ?int $currentLedgerId, string $searchQuery = ''): array
    {
        $requiredPermission = FolderPermissionType::INSPECT;
        $folder = Folder::find($folderId);
        if (!$folder) return [];

        // --- データ取得 ---
        // 実績ベース (検索語を渡す)
        $frequentInspectors = $this->getFrequentAssignees($ledgerDefineId, 'inspector', 5, $searchQuery);
        // 権限ベース (修正: 検索語を引数で渡す)
        $authorizedUsers = $this->userService->getUsersWithFolderPermission($folder, $requiredPermission, $searchQuery);
        // 直近担当者 (検索に関わらず取得しておく)
        $recentInspector = $this->getRecentAssignee($currentLedgerId, 'inspector');

        // --- 統合リスト作成 (検索結果を考慮) ---
        $options = [];
        $addedUserIds = [];

        // 1. 直近担当者 (検索語にヒットするか、選択中であれば表示)
        if ($recentInspector && !isset($addedUserIds[$recentInspector->id]) && (empty($searchQuery) || stripos($recentInspector->name, $searchQuery) !== false || $this->selectedUserId === $recentInspector->id)) {
            $options[$recentInspector->id] = ['id' => $recentInspector->id, 'name' => $recentInspector->name, 'reasons' => ['recent'], 'sort_priority' => 1];
            $addedUserIds[$recentInspector->id] = true;
        }

        // 2. 実績多数ユーザー (検索語を考慮)
        foreach ($frequentInspectors as $user) {
            // 実績取得時に既に検索語で絞られているはず
            $userId = $user['id'];
            if (!isset($addedUserIds[$userId])) {
                $options[$userId] = ['id' => $userId, 'name' => $user['name'], 'reasons' => ['frequent'], 'sort_priority' => 2];
                $addedUserIds[$userId] = true;
            } elseif (isset($options[$userId])) {
                $options[$userId]['reasons'][] = 'frequent';
                $options[$userId]['sort_priority'] = min($options[$userId]['sort_priority'], 2);
            }
        }

        // 3. その他の権限保有ユーザー (検索語でフィルタ済み)
        foreach ($authorizedUsers as $user) {
            $userId = $user->id;
            if (!isset($addedUserIds[$userId])) {
                $options[$userId] = ['id' => $userId, 'name' => $user->name, 'reasons' => ['authorized'], 'sort_priority' => 3];
                $addedUserIds[$userId] = true;
            } elseif (isset($options[$userId]) && !in_array('authorized', $options[$userId]['reasons'])) {
                $options[$userId]['reasons'][] = 'authorized';
            }
        }

        // --- ソート ---
        $sortedOptions = array_values($options);
        usort($options, function ($a, $b) {
            return $a['sort_priority'] <=> $b['sort_priority'] ?: strcmp($a['name'], $b['name']);
        });

        return $sortedOptions;
    }

    /**
     * 実績ベースの推奨担当者を取得
     */
    protected function getFrequentAssignees(int $ledgerDefineId, string $roleType, int $limit, string $searchQuery = ''): array
    {
        $column = ($roleType === 'inspector') ? 'inspector_id' : 'approver_id';

        $query = LedgerDiff::select("{$column} as user_id", DB::raw('count(*) as count'))
            ->join('users', 'users.id', '=', "ledger_diffs.{$column}") // users テーブルを JOIN
            ->where('ledger_define_id', $ledgerDefineId)
            ->whereNotNull("ledger_diffs.{$column}") // テーブル名を明確化
            ->when($searchQuery, function ($q) use ($searchQuery) { // 検索クエリでフィルタ
                $q->where('users.name', 'like', "%{$searchQuery}%");
            })
            ->groupBy('user_id', 'users.name') // group by に name も追加
            ->orderByDesc('count')
            ->limit($limit)
            ->select("ledger_diffs.{$column} as user_id", 'users.name as user_name', DB::raw('count(*) as count')); // select を最後に

        return $query->get()
            ->map(fn($diff) => ['id' => $diff->user_id, 'name' => $diff->user_name ?? __('ledger.unknown_user'), 'count' => $diff->count])
            ->filter(fn($item) => $item['id'] !== null)
            ->toArray();
    }

    // --- getRecentAssignee, getReasonLabel は変更なし ---

    /**
     * 直近の担当者を取得
     */
    protected function getRecentAssignee(?int $ledgerId, string $roleType): ?User
    {
        if (!$ledgerId) {
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
     * 理由コードに対応するラベル（アイコン）を返す
     */
    protected function getReasonLabel(string $reason): string
    {
        return match ($reason) {
            'recent' => '🕒', // 直近
            'frequent' => '⭐', // 実績
            'authorized' => '✅', // 権限
            default => '',
        };
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

    /**
     * 承認者候補リストを取得・統合・ソートする
     *
     * @param int $ledgerDefineId
     * @param int $folderId
     * @param ?int $currentLedgerId
     * @param string $searchQuery
     * @return array
     */
    protected function fetchApproverOptions(int $ledgerDefineId, int $folderId, ?int $currentLedgerId, string $searchQuery = ''): array
    {
        $requiredPermission = FolderPermissionType::APPROVE; // <<<--- 承認権限
        $folder = Folder::find($folderId);
        if (!$folder) return [];

        // --- データ取得 ---
        // 実績ベース (承認者)
        $frequentApprovers = $this->getFrequentAssignees($ledgerDefineId, 'approver', 5, $searchQuery);
        // 権限ベース (承認権限)
        $authorizedUsers = $this->userService->getUsersWithFolderPermission($folder, $requiredPermission, $searchQuery);
        // 直近担当者 (承認者)
        $recentApprover = $this->getRecentAssignee($currentLedgerId, 'approver');

        // --- 統合リスト作成 (ロジックは点検者と同じ) ---
        $options = [];
        $addedUserIds = [];

        // 1. 直近承認者
        if ($recentApprover && !isset($addedUserIds[$recentApprover->id]) && ($this->selectedUserId === $recentApprover->id || empty($searchQuery) || stripos($recentApprover->name, $searchQuery) !== false) ) {
            $options[$recentApprover->id] = ['id' => $recentApprover->id, 'name' => $recentApprover->name, 'reasons' => ['recent'], 'sort_priority' => 1];
            $addedUserIds[$recentApprover->id] = true;
        }

        // 2. 実績多数承認者
        foreach ($frequentApprovers as $user) {
            $userId = $user['id'];
            if (!isset($addedUserIds[$userId])) {
                $options[$userId] = ['id' => $userId, 'name' => $user['name'], 'reasons' => ['frequent'], 'sort_priority' => 2];
                $addedUserIds[$userId] = true;
            } elseif (isset($options[$userId])) {
                $options[$userId]['reasons'][] = 'frequent';
                $options[$userId]['sort_priority'] = min($options[$userId]['sort_priority'], 2);
            }
        }

        // 3. その他の承認権限保有ユーザー
        foreach ($authorizedUsers as $user) {
            $userId = $user->id;
            if (!isset($addedUserIds[$userId])) {
                $options[$userId] = ['id' => $userId, 'name' => $user->name, 'reasons' => ['authorized'], 'sort_priority' => 3];
                $addedUserIds[$userId] = true;
            } elseif (isset($options[$userId]) && !in_array('authorized', $options[$userId]['reasons'])) {
                $options[$userId]['reasons'][] = 'authorized';
            }
        }

        // --- ソート ---
        $sortedOptions = array_values($options);
        usort($sortedOptions, function ($a, $b) {
            if ($a['sort_priority'] !== $b['sort_priority']) {
                return $a['sort_priority'] <=> $b['sort_priority'];
            }
            return $a['name'] <=> $b['name'];
        });

        return $sortedOptions;
    }

}
