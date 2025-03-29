<?php

namespace App\Livewire;

use App\Models\Folder;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Illuminate\Support\Collection;

// Collection を use
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// View を use
use Livewire\Component;
use Log;

class MyPortal extends Component
{
    public User $user;
    public ?Organization $primaryOrganization; // Nullable に
    public Collection $otherOrganizations;
    public Collection $activeRoles;
    public string $roleDisplayString = ''; // 表示用の役割文字列
    // ステップ2で追加: 主な権限とその有無を保持する配列
    public array $majorPermissions = [];
    // ステップ3で追加: 担当フォルダリスト
    public Collection $assignedFolders;
    // ステップ3で追加: 管理可能フォルダID (ビューでの権限判定用)
    public array $manageableFolderIds = [];
    // ステップ3で追加: 書き込み可能フォルダID (ビューでの権限判定用)
    public array $writableFolderIds = []; // 読み取り専用の場合もあるため、書き込み可能も保持

    // ステップ4で追加: 全フォルダツリー表示用データ
    public Collection $allRootFolders; // ルートフォルダのコレクション
    public array $readableFolderIds = []; // 読み取り可能フォルダID (Write/Manage も含む)


    // 表示する主要権限リスト (ここで定義)
    protected array $permissionsToCheck = [
        'create_ledgers',         // 台帳を作成できる
        'update_ledgers',         // 台帳を更新できる
        'create_ledger_defines',  // 台帳定義を作成できる
        'create_folders',         // フォルダーを作成できる
        'update_folders',         // フォルダーを更新できる
        'manage_user',            // ユーザーを管理できる (Seederにはないが、想定として)
        'manage_organization',    // 組織を管理できる (Seederにはないが、想定として)
        'view_activity_logs',     // アクティビティログを閲覧できる
    ];
    // WritableFolderRepository をインジェクト
    protected WritableFolderRepository $writableFolderRepository;

    // Livewire 8+ の場合、コンストラクタインジェクションより boot() や mount() でのインジェクトが推奨される場合あり
    public function boot(WritableFolderRepository $repository): void
    {
        $this->writableFolderRepository = $repository;
    }

    /**
     * コンポーネントのマウント時にデータを準備
     */
    public function mount(): void
    {
        $this->user = Auth::user();
        $this->primaryOrganization = $this->user->primaryOrganization(); // 主所属を取得 (NULL可能性あり)
        $otherOrgIds = $this->primaryOrganization ? [$this->primaryOrganization->id] : [];
        $this->otherOrganizations = $this->user->organizations()->whereNotIn('organizations.id', $otherOrgIds)->orderBy('name')->get();
        $this->activeRoles = $this->user->getAllUniqueRoles(); // 既存のメソッドを利用

        // 「主な役割/担当」文字列の生成 (初期版ロジック)
        $this->prepareRoleDisplayString();
        $this->prepareMajorPermissions();
        // ステップ3で追加: 担当フォルダの準備
        $this->prepareAssignedFolders();

        // ステップ4で追加: 全フォルダツリー用データの準備
        $this->prepareAllFolderTreeData();

    }

    /**
     * 「主な役割/担当」の表示文字列を生成するヘルパーメソッド
     */
    protected function prepareRoleDisplayString(): void
    {
        $orgName = $this->primaryOrganization?->name ?? __('ledger.no_primary_organization'); // 主所属がない場合の代替テキスト
        $roleName = '';

        // 代表的なロール名を決定 (例: 最初のロール、または特定の優先度)
        if ($this->activeRoles->isNotEmpty()) {
            // ここでは単純に最初のロール名を使う例
            // より高度なロジックが必要な場合 (例: 'Admin' ロールを優先) は調整
            $representativeRole = $this->activeRoles->first();
            // labelがあればlabelを、なければnameを使い、翻訳を試みる
            $roleKey = $representativeRole->label ?? $representativeRole->name;
            // 翻訳キーを生成 (例: 'ledger.role_label.admin' や 'ledger.role_label.メンバー')
            $translationKey = 'ledger.role_label.' . $roleKey;
            // 翻訳が存在すれば翻訳を、なければキー自体を使う
            $roleName = trans()->has($translationKey) ? __($translationKey) : $roleKey;
            // $roleName = __('ledger.role_label.' . ($representativeRole->label ?? $representativeRole->name)); // labelを優先する場合
        } else {
            $roleName = __('ledger.no_specific_role'); // ロールがない場合の代替テキスト
        }

        // 主所属がある場合は "(主所属)" を付ける
        $primaryBadge = $this->primaryOrganization ? ' (' . __('ledger.organization_primary_badge') . ')' : '';

        $this->roleDisplayString = sprintf('%s %s%s', $orgName, $roleName, $primaryBadge);

        // 主所属がなく、その他の所属がある場合は、最初のその他の所属を使うなどの代替ロジックも検討可能
        if (!$this->primaryOrganization && $this->otherOrganizations->isNotEmpty()) {
            $firstOtherOrg = $this->otherOrganizations->first();
            $this->roleDisplayString = sprintf('%s %s', $firstOtherOrg->name, $roleName);
            // この場合、主所属ではないことを示す何らかの表示が必要かもしれない
        } elseif (!$this->primaryOrganization && $this->otherOrganizations->isEmpty()) {
            // 主所属もその他の所属もない場合の表示
            $this->roleDisplayString = __('ledger.no_organization_assigned') . ' ' . $roleName;
        }
    }

    /**
     * 主な権限の有無を確認し、表示用データを作成する
     */
    protected function prepareMajorPermissions(): void
    {
        // ユーザーの全権限名を一度だけ取得
        $userPermissionNames = $this->user->getAllUniquePermissions()->pluck('name')->all();
//dd($userPermissionNames);
        foreach ($this->permissionsToCheck as $permissionName) {
            $hasPermission = in_array($permissionName, $userPermissionNames);
            $translationKey = 'ledger.permission_description.' . $permissionName;

            // 翻訳が存在するか確認し、表示用配列に追加
            if (trans()->has($translationKey)) {
                $this->majorPermissions[] = [
                    'description' => __($translationKey),
                    'has' => $hasPermission,
                ];
            } // 翻訳がない場合はログに残すなどの処理も検討
            else {
                Log::warning("Missing translation key: " . $translationKey);
            }
        }
    }

    /**
     * 担当フォルダリストを準備する
     */
    protected function prepareAssignedFolders(): void
    {
        // 権限のあるフォルダIDを取得 (Repository を使用)
        $this->writableFolderIds = $this->writableFolderRepository->getWritableFolderIds($this->user);
        $this->manageableFolderIds = $this->writableFolderRepository->getManageableFolderIds($this->user);

        // 書き込みまたは管理権限のあるフォルダIDを結合
        $targetFolderIds = array_unique(array_merge($this->writableFolderIds, $this->manageableFolderIds));

        if (empty($targetFolderIds)) {
            $this->assignedFolders = new Collection(); // 空のコレクションをセット
            return;
        }

        // 該当するフォルダを取得し、階層でフィルタリング (depth < 2 はルート直下と第1階層)
        // ルートフォルダ(ID=1)は除外する場合が多い
        $this->assignedFolders = Folder::whereIn('id', $targetFolderIds)
            ->where('id', '!=', 1) // ルートフォルダを除外
            ->withDepth() // depth カラムを取得するためにスコープを追加
            ->having('depth', '<', 2) // having 句で階層を制限 (0 と 1)
            ->orderBy('_lft')
            ->take(5)
            ->get();
    }

    /**
     * 全フォルダツリー表示用のデータを準備する
     */
    protected function prepareAllFolderTreeData(): void
    {
        // ルートフォルダとその子孫を Eager Loading で取得 (パフォーマンス改善のため)
        // 必要なリレーション (children など) を with で指定
        $this->allRootFolders = Folder::whereIsRoot()->with(['children' => function ($query) {
            // 再帰的に子孫をロード (withDepth なども必要なら追加)
            $query->with('children')->orderBy('_lft');
        }])->orderBy('_lft')->get();

        // 読み取り可能な全フォルダIDを取得 (書き込み・管理可能も含むはず)
        // 既存のリポジトリメソッドを使用
        $this->readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($this->user);
        // $this->writableFolderIds と $this->manageableFolderIds は prepareAssignedFolders で既に取得済み
    }


    /**
     * ビューをレンダリング
     */
    public function render(): View
    {
        // ビューにデータを渡す (mount で public プロパティにセットしたので自動的に渡る)
        return view('livewire.my-portal')
            ->layout('layouts.app', ['title' => __('ledger.my_portal_title')]); // アプリケーションのレイアウトを使用
    }
}
