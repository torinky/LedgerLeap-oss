<?php

namespace App\Livewire\Workflow;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Collection; // Collection を use
use Livewire\Component;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB; // DB ファサードを use
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
    public ?int $ledgerId = null; // 直近履歴取得用

    #[Modelable] // 親コンポーネントと selectedUserId を双方向バインディング
    public ?int $selectedUserId = null;

    public string $searchQuery = '';
    public array $selectOptions = []; // Select コンポーネントに渡す最終的な options 配列

    protected UserService $userService;

    // boot メソッドで依存性を注入
    public function boot(UserService $userService): void
    {
        $this->userService = $userService;
    }

    // コンポーネント初期化
    public function mount(int $ledgerDefineId, int $folderId, string $roleType, ?int $ledgerId = null, ?int $initialUserId = null): void
    {
        $this->ledgerDefineId = $ledgerDefineId;
        $this->folderId = $folderId;
        $this->roleType = $roleType;
        $this->ledgerId = $ledgerId;
        $this->selectedUserId = $initialUserId;
        $this->loadOptions(); // 初期オプションをロード
    }

    // 検索クエリが更新されたときにオプションを再計算
    public function updatedSearchQuery(): void
    {
        $this->loadOptions();
    }

    // selectedUserId が更新されたら親に通知 (Modelableがあるので不要な場合もある)
    // public function updatedSelectedUserId($value): void
    // {
    //     $this->dispatch('assigneeSelected', userId: $value);
    // }

    protected function loadOptions(): void
    {
        // 修正: roleType に応じて適切な候補取得メソッドを呼び出す
        if ($this->roleType === 'inspector') {
            $combinedOptions = $this->fetchInspectorOptions(
                $this->ledgerDefineId,
                $this->folderId,
                $this->ledgerId
            );
        } elseif ($this->roleType === 'approver') {
            // TODO: ステップ8.2 で承認者候補取得ロジックを実装
            $combinedOptions = $this->fetchApproverOptions(
                $this->ledgerDefineId,
                $this->folderId,
                $this->ledgerId
            );
        } else {
            $combinedOptions = []; // 不明な roleType
        }

        // --- 検索フィルタリングとオプション整形 (変更なし) ---
        if (!empty($this->searchQuery)) {
            $combinedOptions = array_filter($combinedOptions, function ($option) {
                return stripos($option['name'], $this->searchQuery) !== false;
            });
        }
        $this->selectOptions = array_map(function ($opt) {
            $reasonLabels = array_map(fn($r) => $this->getReasonLabel($r), $opt['reasons']);
            $opt['display_name'] = $opt['name'] . (!empty($reasonLabels) ? ' ' . implode(' ', $reasonLabels) : '');
            return ['id' => $opt['id'], 'name' => $opt['display_name']];
        }, $combinedOptions);
    }

    /**
     * 点検者候補リストを取得・統合・ソートする
     *
     * @param int $ledgerDefineId
     * @param int $folderId
     * @param ?int $currentLedgerId
     * @return array
     */
    protected function fetchInspectorOptions(int $ledgerDefineId, int $folderId, ?int $currentLedgerId): array
    {
        $requiredPermission = FolderPermissionType::INSPECT; // 点検権限
        $folder = Folder::find($folderId);
        if (!$folder) return [];

        // --- データ取得 ---
        $frequentInspectors = $this->getFrequentAssignees($ledgerDefineId, 'inspector', 5); // 実績点検者
        $authorizedUsers = $this->userService->getUsersWithFolderPermission($folder, $requiredPermission); // 点検権限を持つユーザー
        $recentInspector = $this->getRecentAssignee($currentLedgerId, 'inspector'); // 直近点検者

        // --- 統合リスト作成 ---
        $options = [];
        $addedUserIds = [];

        // 1. 直近点検者
        if ($recentInspector && !isset($addedUserIds[$recentInspector->id])) {
            $options[$recentInspector->id] = ['id' => $recentInspector->id, 'name' => $recentInspector->name, 'reasons' => ['recent'], 'sort_priority' => 1];
            $addedUserIds[$recentInspector->id] = true;
        }

        // 2. 実績多数点検者
        foreach ($frequentInspectors as $user) {
            $userId = $user['id'];
            if (!isset($addedUserIds[$userId])) {
                $options[$userId] = ['id' => $userId, 'name' => $user['name'], 'reasons' => ['frequent'], 'sort_priority' => 2];
                $addedUserIds[$userId] = true;
            } elseif (isset($options[$userId])) {
                $options[$userId]['reasons'][] = 'frequent';
                $options[$userId]['sort_priority'] = min($options[$userId]['sort_priority'], 2);
            }
        }

        // 3. その他の点検権限保有ユーザー
        foreach ($authorizedUsers as $user) {
            $userId = $user->id;
            if (!isset($addedUserIds[$userId])) {
                $options[$userId] = ['id' => $userId, 'name' => $user->name, 'reasons' => ['authorized'], 'sort_priority' => 3];
                $addedUserIds[$userId] = true;
            } elseif (isset($options[$userId]) && !in_array('authorized', $options[$userId]['reasons'])) {
                $options[$userId]['reasons'][] = 'authorized';
            }
        }

        // --- ソート (変更なし) ---
        $sortedOptions = array_values($options);
        usort($sortedOptions, function ($a, $b) { /* ... */ });

        return $sortedOptions;
    }

    // --- TODO: ステップ8.2 で実装 ---
    protected function fetchApproverOptions(int $ledgerDefineId, int $folderId, ?int $currentLedgerId): array
    {
        // 承認者用のロジックを実装 (getFrequentAssignees, getUsersWithFolderPermission, getRecentAssignee の roleType を 'approver' にして呼び出す)
        Log::info("Fetching approver options (Not Implemented Yet)"); // 仮実装
        return []; // 仮に空を返す
    }
    // --- ここまで TODO ---

    /**
     * 実績ベースの推奨担当者を取得 (修正: roleType でカラムを切り替え)
     */
    protected function getFrequentAssignees(int $ledgerDefineId, string $roleType, int $limit): array
    {
        // 修正: roleType に応じて集計カラムを決定
        $column = ($roleType === 'inspector') ? 'inspector_id' : 'approver_id';

        return LedgerDiff::select("{$column} as user_id", DB::raw('count(*) as count'))
            ->where('ledger_define_id', $ledgerDefineId)
            ->whereNotNull($column) // カラムが NULL でないものを対象
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit($limit)
            // 修正: User モデルへのリレーション名を動的に変更 (または User モデル側で適切なリレーションを定義)
            // User モデルに inspector(), approver() リレーションがあればシンプル
            // ->with('user:id,name')
            // 別の方法: User モデルを join する
            ->join('users', 'users.id', '=', "ledger_diffs.{$column}") // users テーブルを JOIN
            ->select("ledger_diffs.{$column} as user_id", 'users.name as user_name', DB::raw('count(*) as count')) // users.name も取得
            ->get()
            ->map(fn($diff) => ['id' => $diff->user_id, 'name' => $diff->user_name ?? __('ledger.unknown_user'), 'count' => $diff->count]) // user_name を使用
            ->filter(fn($item) => $item['id'] !== null)
            ->toArray();
    }

    /**
     * 直近の担当者を取得 (修正: roleType でカラムとリレーションを切り替え)
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

        // 修正: roleType に応じてリレーション名を決定して User を取得
        // LedgerDiff モデルに inspector(), approver() リレーションが定義されている前提
        return $latestDiff?->{$roleType};
    }


    /**
     * 担当者候補リストを取得し、理由と優先度を付けて返す
     *
     * @param int $ledgerDefineId
     * @param int $folderId
     * @param string $roleType 'inspector' or 'approver'
     * @param ?int $currentLedgerId (直近履歴用)
     * @return array [['id' => userId, 'name' => userName, 'reasons' => ['recent', 'frequent'], 'sort_priority' => 1], ...]
     */
    protected function fetchAssigneeOptions(int $ledgerDefineId, int $folderId, string $roleType, ?int $currentLedgerId = null): array
    {
        $requiredPermission = ($roleType === 'inspector') ? FolderPermissionType::INSPECT : FolderPermissionType::APPROVE;
        $folder = Folder::find($folderId);
        if (!$folder) return []; // フォルダが見つからない場合は空

        // --- データ取得 ---
        $frequentUsers = $this->getFrequentAssignees($ledgerDefineId, $roleType, 5); // 実績 (配列)
        $authorizedUsers = $this->userService->getUsersWithFolderPermission($folder, $requiredPermission); // 権限 (Collection<User>)
        $recentAssignee = $this->getRecentAssignee($currentLedgerId, $roleType); // 直近 (User or null)

        // --- 統合リスト作成 ---
        $options = [];
        $addedUserIds = [];

        // 1. 直近担当者
        if ($recentAssignee && !isset($addedUserIds[$recentAssignee->id])) {
            $options[$recentAssignee->id] = [ // IDをキーにする
                'id' => $recentAssignee->id,
                'name' => $recentAssignee->name,
                'reasons' => ['recent'],
                'sort_priority' => 1,
            ];
            $addedUserIds[$recentAssignee->id] = true;
        }

        // 2. 実績多数ユーザー
        foreach ($frequentUsers as $user) {
            $userId = $user['id'];
            if (!isset($addedUserIds[$userId])) {
                $options[$userId] = [
                    'id' => $userId,
                    'name' => $user['name'],
                    'reasons' => ['frequent'],
                    'sort_priority' => 2,
                ];
                $addedUserIds[$userId] = true;
            } elseif (isset($options[$userId])) { // 既にリストにあれば理由追加＆優先度更新
                $options[$userId]['reasons'][] = 'frequent';
                $options[$userId]['sort_priority'] = min($options[$userId]['sort_priority'], 2);
            }
        }

        // 3. その他の権限保有ユーザー
        foreach ($authorizedUsers as $user) {
            $userId = $user->id;
            if (!isset($addedUserIds[$userId])) {
                $options[$userId] = [
                    'id' => $userId,
                    'name' => $user->name,
                    'reasons' => ['authorized'],
                    'sort_priority' => 3,
                ];
                $addedUserIds[$userId] = true;
            } elseif (isset($options[$userId]) && !in_array('authorized', $options[$userId]['reasons'])) {
                // 既にリストにあれば理由追加 (優先度は変えない)
                $options[$userId]['reasons'][] = 'authorized';
            }
        }

        // --- ソート ---
        $sortedOptions = array_values($options); // キーをリセットして配列に
        usort($sortedOptions, function ($a, $b) {
            if ($a['sort_priority'] !== $b['sort_priority']) {
                return $a['sort_priority'] <=> $b['sort_priority'];
            }
            return $a['name'] <=> $b['name']; // 優先度が同じなら名前順
        });

        return $sortedOptions;
    }



    /**
     * 理由コードに対応するラベル（アイコン）を返す
     */
    protected function getReasonLabel(string $reason): string
    {
        return match($reason) {
            'recent' => '🕒', // 直近
            'frequent' => '⭐', // 実績
            'authorized' => '✅', // 権限
            default => '',
        };
    }

    // selectUser メソッドは Modelable で不要になる可能性あり
    // public function selectUser(int $userId): void { ... }

    public function render()
    {
        return view('livewire.workflow.workflow-assignee-select');
    }
}