<?php

namespace App\Livewire\Common;

use App\Helpers\ActivityLogFormatter;
use App\Models\CustomActivity;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Livewire\WithPagination;

class ActivityHistoryDisplay extends Component
{
    use WithPagination;

    // リソースタイプとIDが指定されない場合、全件表示モードとなる
    public ?int $resourceId = null;
    public ?string $resourceType = null; // 'Ledger', 'LedgerDefine', 'Folder'
    public bool $includeRelatedResources = false; // レコードのアクティビティ表示時に、親の台帳定義とフォルダのアクティビティも含めるか
//    public string $paginationTheme = 'app';

    // フィルタリング用プロパティ (MVPでは非実装だが定義しておく)
    public ?string $filterCauserName = null;
    public ?string $filterEventType = null;
    public ?string $filterStartDate = null;
    public ?string $filterEndDate = null;
    public ?string $searchQuery = null;

    // DaisyUI Theme Colors for Changes Display
    private const TEXT_COLOR_SUCCESS = 'text-success';         // For added items, success states, etc.
    private const TEXT_COLOR_ERROR = 'text-error';           // For removed items, error states, etc.
    private const TEXT_COLOR_INFO = 'text-info';             // For general changes, informational content
    private const TEXT_COLOR_NEUTRAL = 'text-neutral-content'; // For neutral or less emphasized information
    private const TEXT_STYLE_MUTED = 'text-base-content/70'; // For muted text (e.g., null values)
    private const TEXT_STYLE_ITALIC_MUTED = 'italic ' . self::TEXT_STYLE_MUTED; // For italic muted text

    /**
     * コンポーネントの初期化
     *
     * @param int|null $resourceId
     * @param string|null $resourceType
     * @param bool $includeRelatedResources
     * @return void
     */
    public function mount(
        ?int $resourceId = null,
        ?string $resourceType = null,
        bool $includeRelatedResources = false
    ): void {
        $this->resourceId = $resourceId;
        $this->resourceType = $resourceType;
        $this->includeRelatedResources = $includeRelatedResources;
    }

    /**
     * ソート機能 (MVPでは非実装だが定義しておく)
     * @param string $field
     * @return void
     */
    public function sortBy(string $field): void
    {
        // ToDo: ソートロジックを実装
    }

    /**
     * アクティビティログを取得するクエリを構築
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getActivitiesQuery()
    {
        $query = CustomActivity::query()
            ->with(['causer', 'subject']) // 操作者と対象モデルをイーガーロード
            ->orderBy('created_at', 'desc'); // 最新のログが上に来るようにソート

        // 特定のリソースに絞り込む場合
        if ($this->resourceId !== null && $this->resourceType !== null) {
            $subjectTypes = [];
            $subjectIds = [];

            // 基本となるリソースのsubject_typeとsubject_idを設定
            switch ($this->resourceType) {
                case 'Ledger':
                    $subjectTypes[] = Ledger::class;
                    $subjectIds[] = $this->resourceId;
                    break;
                case 'LedgerDefine':
                    $subjectTypes[] = LedgerDefine::class;
                    $subjectIds[] = $this->resourceId;
                    break;
                case 'Folder':
                    $subjectTypes[] = Folder::class;
                    $subjectIds[] = $this->resourceId;
                    break;
                default:
                    // 未知のリソースタイプの場合は空のクエリを返す
                    return $query->whereRaw('0=1');
            }

            // 関連リソースのアクティビティを含める場合
            if ($this->includeRelatedResources) {
                $baseModel = null;
                // モデルの取得と関連リソースIDの追加
                if ($this->resourceType === 'Ledger') {
                    $baseModel = Ledger::find($this->resourceId);
                    if ($baseModel && $baseModel->define) {
                        $subjectTypes[] = LedgerDefine::class;
                        $subjectIds[] = $baseModel->define->id;
                        if ($baseModel->define->folder) {
                            $subjectTypes[] = Folder::class;
                            $subjectIds[] = $baseModel->define->folder->id;
                        }
                    }
                } elseif ($this->resourceType === 'LedgerDefine') {
                    $baseModel = LedgerDefine::find($this->resourceId);
                    if ($baseModel && $baseModel->folder) {
                        $subjectTypes[] = Folder::class;
                        $subjectIds[] = $baseModel->folder->id;
                    }
                }
            }

            // ユニークな subject_type と subject_id の組み合わせでフィルタリング
            $query->where(function ($q) use ($subjectTypes, $subjectIds) {
                $processedTypes = [];
                foreach ($subjectTypes as $index => $type) {
                    if (!in_array($type, $processedTypes)) {
                        $q->orWhere(function ($subQ) use ($type, $subjectIds, $subjectTypes) {
                            $filteredSubjectIdsForType = collect($subjectIds)
                                ->filter(fn($id, $idx) => $subjectTypes[$idx] === $type)
                                ->unique()
                                ->values()
                                ->toArray();
                            $subQ->where('subject_type', $type)
                                ->whereIn('subject_id', $filteredSubjectIdsForType);
                        });
                        $processedTypes[] = $type;
                    }
                }
            });
        }

        // subject_idとsubject_typeの両方がnullのActivityログを除外 (リソースに紐づくアクティビティの文脈では)
        // ただし、ユーザーログイン/ログアウトなどcauser_type/subject_typeがUserでsubject_idがnullのものは含める
        $query->where(function ($q) {
            $q->whereNotNull('subject_id')
                ->orWhere(function ($subQ) {
                    // subject_id が null で、causer_type が User.class の場合 (例: login/logout)
                    $subQ->whereNull('subject_id')
                        ->where('causer_type', User::class);
                });
        });


        // TODO: フィルタリング機能 (filterCauserName, filterEventType, filterStartDate, filterEndDate)
        // TODO: 検索機能 (searchQuery)

        return $query;
    }

    /**
     * コンポーネントのレンダリング
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        // ログ閲覧権限チェック
        if (!auth()->check() || !auth()->user()->can('viewAny', \App\Models\CustomActivity::class)) {
            return view('livewire.common.activity-history-display-no-permission');
        }

        $activities = $this->getActivitiesQuery()->paginate(10);

        return view('livewire.common.activity-history-display', [
            'activities' => $activities,
        ]);
    }

    // ActivityLogFormatter に移譲
    public function getOperationDescription(CustomActivity $activity): string
    {
        return ActivityLogFormatter::getOperationDescription($activity);
    }

    // ActivityLogFormatter に移譲
    public function getSubjectNameForDisplay(CustomActivity $activity): string
    {
        return ActivityLogFormatter::getSubjectNameForDisplay($activity);
    }

    // ActivityLogFormatter に移譲
    protected function getRelatedEntityNameForDisplay(CustomActivity $activity): string
    {
        return ActivityLogFormatter::getRelatedEntityNameForDisplay($activity);
    }

    // ActivityLogFormatter に移譲
    public function formatChanges(CustomActivity $activity): string|HtmlString
    {
        return ActivityLogFormatter::formatChanges($activity);
    }

    // ActivityLogFormatter に移譲
    public function formatComment(CustomActivity $activity): string
    {
        return ActivityLogFormatter::formatComment($activity);
    }

    // ActivityLogFormatter に移譲
    public function getSubjectDisplay(CustomActivity $activity): string
    {
        return ActivityLogFormatter::getSubjectDisplay($activity);
    }

    // ActivityLogFormatter に移譲
    public function getCauserDisplayName(CustomActivity $activity): string
    {
        return ActivityLogFormatter::getCauserDisplayName($activity);
    }

    // ActivityLogFormatter に移譲
    public function getSubjectDetailLink(CustomActivity $activity): ?string
    {
        return ActivityLogFormatter::getSubjectDetailLink($activity);
    }

    // ActivityLogFormatter に移譲
    public function getCauserDetailLink(CustomActivity $activity): ?string
    {
        return ActivityLogFormatter::getCauserDetailLink($activity);
    }
}
