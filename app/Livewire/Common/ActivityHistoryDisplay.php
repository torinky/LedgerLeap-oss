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
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
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

    // ★★★ 非表示にするカラムのキーを格納する配列 ★★★
    public array $hiddenColumns = [];

    // フィルタリング用プロパティ
    public ?string $filterCauserName = null;
    public ?string $filterEventType = null;
    public ?string $filterStartDate = null;
    public ?string $filterEndDate = null;
    public ?string $searchQuery = null;
    public ?int $filterByUserId = null;
    public ?string $filterByEvent = ''; // 初期値を空文字列に

    // ★★★ 新規追加: フィルタ選択肢用プロパティ ★★★
    public Collection $userOptions;
    public Collection $eventOptions;
    public Collection $descriptionOptions;

    // DaisyUI Theme Colors for Changes Display
    private const TEXT_COLOR_SUCCESS = 'text-success';         // For added items, success states, etc.
    private const TEXT_COLOR_ERROR = 'text-error';           // For removed items, error states, etc.
    private const TEXT_COLOR_INFO = 'text-info';             // For general changes, informational content
    private const TEXT_COLOR_NEUTRAL = 'text-neutral-content'; // For neutral or less emphasized information
    private const TEXT_STYLE_MUTED = 'text-base-content/70'; // For muted text (e.g., null values)
    private const TEXT_STYLE_ITALIC_MUTED = 'italic ' . self::TEXT_STYLE_MUTED; // For italic muted text
    public ?string $filterByDescription='';

    /**
     * コンポーネントの初期化
     *
     * @param int|null $resourceId
     * @param string|null $resourceType
     * @param bool $includeRelatedResources
     * @return void
     */
    public function mount(
        ?int    $resourceId = null,
        ?string $resourceType = null,
        bool    $includeRelatedResources = false,
        array   $hiddenColumns = []
    ): void
    {
        $this->resourceId = $resourceId;
        $this->resourceType = $resourceType;
        $this->includeRelatedResources = $includeRelatedResources;
        $this->hiddenColumns = $hiddenColumns;

        // ★★★ フィルタ選択肢を初期化 ★★★
        $this->userOptions = User::orderBy('name')->get(['id', 'name']);
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
     * (★☆★ このメソッドを全面的に修正 ★☆★)
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getActivitiesQuery()
    {
        $query = CustomActivity::query()
            ->with(['causer', 'subject'])
            ->orderBy('created_at', 'desc');

        // 特定のリソースに絞り込む場合
        if ($this->resourceId !== null && $this->resourceType !== null) {
            switch ($this->resourceType) {
                case 'Folder':
                    $folder = Folder::find($this->resourceId);
                    if (!$folder) {
                        return $query->whereRaw('0=1');
                    }

                    // 対象フォルダとその全ての子孫フォルダのIDリストを取得
                    $folderIds = $folder->descendantsAndSelf($folder->id)->pluck('id');

                    $query->where(function (EloquentBuilder $q) use ($folderIds) {
                        // 1. フォルダ自身のログ
                        $q->where(function (EloquentBuilder $subQ) use ($folderIds) {
                            $subQ->where('subject_type', Folder::class)
                                ->whereIn('subject_id', $folderIds);
                        })
                            // 2. 配下の台帳定義のログ
                            ->orWhereHasMorph('subject', [LedgerDefine::class], function (EloquentBuilder $subjectQuery) use ($folderIds) {
                                $subjectQuery->whereIn('folder_id', $folderIds);
                            })
                            // 3. 配下の台帳レコードのログ
                            ->orWhereHasMorph('subject', [Ledger::class], function (EloquentBuilder $subjectQuery) use ($folderIds) {
                                $subjectQuery->whereHas('define', function (EloquentBuilder $defineQuery) use ($folderIds) {
                                    $defineQuery->whereIn('folder_id', $folderIds);
                                });
                            });
                    });
                    break;

                case 'LedgerDefine':
                    $query->where(function (EloquentBuilder $q) {
                        // 1. 台帳定義自身のログ
                        $q->where(function (EloquentBuilder $subQ) {
                            $subQ->where('subject_type', LedgerDefine::class)
                                ->where('subject_id', $this->resourceId);
                        })
                            // 2. その定義に紐づく全レコードのログ
                            ->orWhereHasMorph('subject', [Ledger::class], function (EloquentBuilder $subjectQuery) {
                                $subjectQuery->where('ledger_define_id', $this->resourceId);
                            });
                    });
                    break;

                case 'Ledger':
                    $query->where(function (EloquentBuilder $q) {
                        // 1. 台帳レコード自身のログ
                        $q->where(function (EloquentBuilder $subQ) {
                            $subQ->where('subject_type', Ledger::class)
                                ->where('subject_id', $this->resourceId);
                        });

                        // 2. 関連リソースのログを含める場合 (親の台帳定義とフォルダ)
                        if ($this->includeRelatedResources) {
                            $ledger = Ledger::find($this->resourceId);
                            if ($ledger && $ledger->define) {
                                $q->orWhere(function (EloquentBuilder $subQ) use ($ledger) {
                                    $subQ->where('subject_type', LedgerDefine::class)
                                        ->where('subject_id', $ledger->ledger_define_id);
                                });
                                if ($ledger->define->folder) {
                                    $q->orWhere(function (EloquentBuilder $subQ) use ($ledger) {
                                        $subQ->where('subject_type', Folder::class)
                                            ->where('subject_id', $ledger->define->folder_id);
                                    });
                                }
                            }
                        }
                    });
                    break;

                default:
                    // 未知のリソースタイプの場合は空のクエリを返す
                    return $query->whereRaw('0=1');
            }
        }

        // subject が null のログは、このコンテキストでは表示しない (ログイン/ログアウトなど)
        // 全件表示モード以外の場合のみ適用
        if ($this->resourceId !== null && $this->resourceType !== null) {
            $query->whereNotNull('subject_id');
        }

        // ★★★ フィルタリング機能の適用 ★★★
        if ($this->filterByUserId) {
            $query->where('causer_id', $this->filterByUserId);
        }
        if ($this->filterByEvent) {
            $query->where('event', $this->filterByEvent);
        }
        if ($this->filterByDescription) {
            $query->where('description', $this->filterByDescription);
        }
        if ($this->filterStartDate) {
            $query->where('created_at', '>=', $this->filterStartDate . ' 00:00:00');
        }
        if ($this->filterEndDate) {
            $query->where('created_at', '<=', $this->filterEndDate . ' 23:59:59');
        }
        // TODO: 検索機能 (searchQuery)

        return $query;
    }

    /**
     * フィルタが更新された際にページネーションをリセットする
     */
    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['filterByUserId', 'filterByEvent', 'filterByDescription', 'filterStartDate', 'filterEndDate'])) {
            $this->resetPage();
        }
    }

    /**
     * フィルタをリセットする
     */
    public function resetFilters(): void
    {
        $this->reset(['filterByUserId', 'filterByEvent', 'filterByDescription', 'filterStartDate', 'filterByDescription', 'filterEndDate']);
        $this->resetPage();
    }

    /**
     * コンポーネントのレンダリング
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        // ログ閲覧権限チェック
        if (!auth()->check() || !auth()->user()->can('viewAny', CustomActivity::class)) {
            return view('livewire.common.activity-history-display-no-permission');
        }

        $this->eventOptions = CustomActivity::select('event')
            ->whereNotNull('event')
            ->distinct()
            ->orderBy('event')
            ->get();
        $this->descriptionOptions = CustomActivity::select('description')
            ->whereNotNull('description')
            ->distinct()
            ->orderBy('description')
            ->get();

        $activities = $this->getActivitiesQuery()->paginate(10);

        // ★★★ Bladeに渡すヘッダー情報を動的に生成 ★★★
        $headers = $this->getVisibleHeaders();

        return view('livewire.common.activity-history-display', [
            'activities' => $activities,
            'headers' => $headers,
        ]);
    }

    /**
     * 表示するヘッダーのリストを生成する
     *
     * @return array
     */
    protected function getVisibleHeaders(): array
    {
        $allHeaders = [
            ['key' => 'time', 'label' => __('ledger.activity.column.time'), 'class' => 'min-w-[10rem]'],
            ['key' => 'causer', 'label' => __('ledger.activity.column.causer'), 'class' => 'min-w-[6rem]'],
            ['key' => 'subject', 'label' => __('ledger.activity.column.subject'), 'class' => 'min-w-[10rem]'],
            ['key' => 'operation', 'label' => __('ledger.activity.column.operation'), 'class' => 'min-w-[10rem]'],
            ['key' => 'changes', 'label' => __('ledger.activity.column.changes')],
            ['key' => 'comment', 'label' => __('ledger.activity.column.comment'), 'class' => 'min-w-[10rem]'],
        ];

        return array_filter($allHeaders, function ($header) {
            return !in_array($header['key'], $this->hiddenColumns);
        });
    }

    /**
     * `x-mary-choices` 用の検索
     *
     * @return void
     */
    public function userSearch(string $value = ''): void
    {
        // 検索クエリを更新するだけで、userOptions computed プロパティが自動的に再計算される
        $query = User::orderBy('name');

        if ($value) {
            $query->where('name', 'like', "%{$value}%");
        }

        // 常に現在選択されているユーザーを検索結果に含める
        if ($this->filterByUserId) {
            $query->orWhere('id', $this->filterByUserId);
        }

        $this->userOptions = $query->take(10)->get();
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
