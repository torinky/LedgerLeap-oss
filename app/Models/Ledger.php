<?php

namespace App\Models;

// use App\Casts\AsCollection;

use App\Casts\AsColumnArrayJson;
use App\Enums\WorkflowStatus;
use App\Services\Ledger\SearchContext;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property array $content_attached
 * @property array $content
 * @property LedgerDefine $define
 *
 * @method static create(array $array)
 * @method static find(string $ledgerId)
 */
class Ledger extends Model
{
    use HasFactory, LogsActivity, \Stancl\Tenancy\Database\Concerns\BelongsToTenant;

    protected $casts = [
        'content' => AsColumnArrayJson::class,
        'content_attached' => AsColumnArrayJson::class,
        'status' => WorkflowStatus::class,
    ];

    protected $fillable = [
        // インポート（upsert）時に id を明示的にセットできるよう追加。
        // 通常の Ledger::create() 呼び出しは id を渡さないため autoincrement には影響しない。
        'id',
        'content', 'content_attached', 'ledger_define_id', 'creator_id', 'modifier_id', 'status', 'latest_diff_id', 'version',
        'activity_score', 'composite_score', 'default_sort_value',
    ];

    /**
     * モデルの作成と更新イベントを処理するメソッドです。
     */
    protected static function booted(): void
    {
        static::saving(static function ($ledger) {
            $ledger->normalizeContent();
        });
    }

    /**
     * content / content_attached を LedgerDefine の column_define に基づいて正規化する。
     * 保存時に欠番を埋め、DB に正規化済みデータを永続化する。
     */
    public function normalizeContent(): void
    {
        $define = $this->define ?? LedgerDefine::find($this->ledger_define_id);

        if (! $define) {
            return;
        }

        $this->content = $define->normalizeByColumnDefine($this->content ?? []);
        $this->content_attached = $define->normalizeByColumnDefine($this->content_attached ?? []);
    }

    /**
     * 指定されたフリーワードで content を検索するスコープです。
     *
     * @return void
     */
    public function scopeSearch(EloquentBuilder $query, string $freeWord)
    {
        $freeWord = trim($freeWord);
        if (empty($freeWord)) {
            return $query;
        }

        $keywords = preg_split('/[\s,]+/', $freeWord, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($keywords)) {
            return $query;
        }

        // OR検索: 各キーワードをスペース区切りで渡す（+を付けない）
        // Mroongaでは複数単語をスペース区切りにするとOR検索になる
        $searchString = implode(' ', $keywords);

        /* DEBUG: 一時調査用（必要時にコメント解除）
        $connection = $query->getConnection();
        $dbName = $connection->getDatabaseName();
        $tenantId = tenancy()->tenant?->id ?? 'central';

        \Log::info('[MCP Search Debug] scopeSearch START', [
            'freeWord' => $freeWord,
            'tenantId' => $tenantId,
            'dbName' => $dbName,
            'connection' => $connection->getName(),
        ]);
        \Log::info('[MCP Search Debug] scopeSearch: extracted keywords: '.json_encode($keywords, JSON_UNESCAPED_UNICODE));
        \Log::info('[MCP Search Debug] scopeSearch: searchString for MATCH AGAINST: '.$searchString);
        \Log::info('[MCP Search Debug] scopeSearch: applying MATCH AGAINST on content and content_attached');
        $count = (clone $query)->count();
        \Log::info("[MCP Search Debug] scopeSearch: found {$count} records", [
            'tenantId' => $tenantId,
            'dbName' => $dbName,
        ]);
        \Log::info('[MCP Search Debug] scopeSearch: completed successfully');
        */

        // 複合インデックスではなく、個別のインデックスを利用するように orWhereRaw を使用
        $query->where(function (EloquentBuilder $q) use ($searchString) {
            $q->whereRaw('match(`content`) against (? IN BOOLEAN MODE)', [$searchString])
                ->orWhereRaw('match(`content_attached`) against (? IN BOOLEAN MODE)', [$searchString]);
        });
    }

    /**
     * 指定されたSearchContextで content を検索するスコープです。
     *
     * @return void
     */
    public function scopeSearchContext(EloquentBuilder $query, SearchContext $searchContext)
    {

        foreach ($searchContext->keywords as $keyword) {
            $queryText = $searchContext->getFlattenedSynonymsForKeyword($keyword);
            $this->scopeSearch($query, implode(' ', $queryText));
        }

    }

    /**
     * 指定されたフィルタ条件で content をフィルタリングするスコープです。
     */
    public static function scopeContentsFilter(EloquentBuilder $query, array $filter): void
    {
        if (empty($filter)) {
            return;
        }

        foreach ($filter as $column => $filterStr) {
            $filterStr = trim($filterStr);
            if (empty($filterStr)) {
                continue;
            }

            // シングルクォート内のバイドがカウントされていないっぽいのでプレースフォルダが使えない
            // https://qiita.com/keizokeizo3/items/112f4785acd8bcf165d2
            //            ->whereRaw("match(`content`) against ('*W? +?' IN BOOLEAN MODE)", [$column, $filter]);

            // Mroongaの列番号は1始まり
            $mroongaColumnCount = $column + 1;
            //            $query->whereRaw("match(`content`) against ('*W" . $mroongaColumnCount . " +" . $filterStr . "' IN BOOLEAN MODE)");
            $query->where(function ($q) use ($mroongaColumnCount, $filterStr) {
                $q->whereRaw("match(`content`) against ('*W".$mroongaColumnCount.' +"'.$filterStr."\"' IN BOOLEAN MODE)")
                    ->orWhereRaw("match(`content_attached`) against ('*W".$mroongaColumnCount.' +'.$filterStr."' IN BOOLEAN MODE)");
            });
        }

    }

    public function scopeCreatedBetween(EloquentBuilder $query, $value)
    {
        // $value が配列の場合と文字列の場合の両方に対応
        if (is_array($value)) {
            $dates = $value;
        } else {
            // $value は 'YYYY-MM-DD,YYYY-MM-DD' の形式を想定
            $dates = explode(',', $value);
        }

        $startDate = $dates[0] ?? null;
        $endDate = $dates[1] ?? null;

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * 更新日時範囲での絞り込みスコープ
     * spatie/laravel-query-builder 用
     */
    public function scopeUpdatedBetween(EloquentBuilder $query, $value)
    {
        if (is_array($value)) {
            $dates = $value;
        } else {
            $dates = explode(',', $value);
        }

        $startDate = $dates[0] ?? null;
        $endDate = $dates[1] ?? null;

        if ($startDate) {
            $query->whereDate('updated_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('updated_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * フォルダIDでの階層的絞り込みスコープ
     * spatie/laravel-query-builder 用
     */
    public function scopeFolderHierarchy(EloquentBuilder $query, $folderId)
    {
        if (! empty($folderId)) {
            $folderIds = Folder::descendantsAndSelf($folderId)->pluck('id');
            $query->whereHas('define.folder', function (Builder $q) use ($folderIds) {
                $q->whereIn('id', $folderIds);
            });
        }

        return $query;
    }

    /**
     * タグでの絞り込みスコープ (AND条件)
     * spatie/laravel-query-builder 用
     */
    public function scopeWithTags(EloquentBuilder $query, $tags)
    {
        if (! empty($tags)) {
            $tagNames = is_string($tags) ? array_filter(explode(',', $tags)) : $tags;
            if (! empty($tagNames)) {
                $query->whereHas('define.tags', function (Builder $q) use ($tagNames) {
                    $q->whereIn('name', $tagNames);
                }, '=', count($tagNames));
            }
        }

        return $query;
    }

    /**
     * 除外タグでの絞り込みスコープ
     * spatie/laravel-query-builder 用
     */
    public function scopeWithoutTags(EloquentBuilder $query, $excludeTags)
    {
        if (! empty($excludeTags)) {
            $excludeTagNames = is_string($excludeTags) ? array_filter(explode(',', $excludeTags)) : $excludeTags;
            if (! empty($excludeTagNames)) {
                $query->whereDoesntHave('define.tags', function (Builder $q) use ($excludeTagNames) {
                    $q->whereIn('name', $excludeTagNames);
                });
            }
        }

        return $query;
    }

    public function scopeApiSearch(Builder $query, array $params)
    {
        // キーワード検索
        if (! empty($params['q'])) {
            $query->where(function (Builder $q) use ($params) {
                $q->whereRaw('match(`content`) against (? IN BOOLEAN MODE)', [$params['q']])
                    ->orWhereRaw('match(`content_attached`) against (? IN BOOLEAN MODE)', [$params['q']]);
            });
        }

        // 除外キーワード検索 (全文検索のNOT演算子を利用)
        if (! empty($params['exclude_q'])) {
            $excludeKeywords = '-'.implode(' -', explode(' ', $params['exclude_q']));
            $query->where(function (Builder $q) use ($excludeKeywords) {
                $q->whereRaw('match(`content`) against (? IN BOOLEAN MODE)', [$excludeKeywords])
                    ->whereRaw('match(`content_attached`) against (? IN BOOLEAN MODE)', [$excludeKeywords]);
            });
        }

        // 台帳定義IDでの絞り込み
        if (! empty($params['ledger_define_id'])) {
            $query->where('ledger_define_id', $params['ledger_define_id']);
        }

        // フォルダIDでの絞り込み (再帰的)
        if (! empty($params['folder_id'])) {
            $query->whereHas('define.folder', function (Builder $q) use ($params) {
                $folderIds = Folder::descendantsAndSelf($params['folder_id'])->pluck('id');
                $q->whereIn('id', $folderIds);
            });
        }

        // タグでの絞り込み (AND条件)
        if (! empty($params['tags'])) {
            $tagNames = array_filter(explode(',', $params['tags']));
            if (! empty($tagNames)) {
                $query->whereHas('define.tags', function (Builder $q) use ($tagNames) {
                    $q->whereIn('name', $tagNames);
                }, '=', count($tagNames));
            }
        }

        // 除外タグでの絞り込み
        if (! empty($params['exclude_tags'])) {
            $excludeTagNames = array_filter(explode(',', $params['exclude_tags']));
            if (! empty($excludeTagNames)) {
                $query->whereDoesntHave('define.tags', function (Builder $q) use ($excludeTagNames) {
                    $q->whereIn('name', $excludeTagNames);
                });
            }
        }

        return $query;
    }

    /**
     * LedgerDefine モデルへのリレーションを定義します。
     */
    public function define(): BelongsTo
    {
        return $this->belongsTo(LedgerDefine::class, 'ledger_define_id');
    }

    /**
     * LedgerDiff モデルへのリレーションを定義します。
     */
    public function ledgerDiff(): HasMany
    {
        return $this->hasMany(LedgerDiff::class, 'ledger_id');
    }

    /**
     * User モデルへの creator リレーションを定義します。
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * User モデルへの modifier リレーションを定義します。
     */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }

    /**
     * Ledgerに関連するAttachedFileを取得する。
     *
     * @return HasMany
     */
    public function attachedFiles()
    {
        return $this->hasMany(AttachedFile::class);
    }

    /**
     * Ledgerを削除する際に関連するAttachedFileも削除する。
     *
     * @return bool|null
     *
     * @throws Exception
     */
    public function delete()
    {
        // まず関連するAttachedFileを削除する
        $this->attachedFiles()->delete();

        // 差分テーブルのレコードも削除
        $this->ledgerDiff()->delete();

        // 親のdeleteメソッドを呼び出す
        return parent::delete();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'content', 'ledger_define_id', 'status', 'version', 'modifier_id']) // 変更を監視する属性
            ->logOnlyDirty() // 変更があった場合のみ記録
            ->dontSubmitEmptyLogs() // 空のログは記録しない
            ->setDescriptionForEvent(fn (string $eventName) => $this->getLogDescriptionForEvent($eventName))
            ->logFillable()
            // ->logUnguarded() // ガードされていないすべての属性をログに記録 (fillable の逆)
            ->dontLogIfAttributesChangedOnly(['latest_diff_id', 'activity_score', 'composite_score']); // 特定の属性のみが変更された場合はログを記録しない
    }

    /**
     * ログに記録する際の追加情報
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        // 言語ファイルからdescriptionを取得
        $key = "activitylog.ledger_{$eventName}";

        return trans($key);
    }

    /**
     * ログに記録する際のメッセージを取得
     */
    protected function getLogDescriptionForEvent(string $eventName): string
    {
        $key = "activitylog.default_message.ledger_{$eventName}";

        // 言語ファイルにキーがあれば、言語ファイルから取得。なければ、デフォルト値を返す
        return Lang::has($key) ? trans($key) : "台帳が{$eventName}されました";
    }

    public function folder()
    {
        return $this->define->folder();
    }

    /**
     * 最新の LedgerDiff レコードへのリレーション
     */
    public function latestDiff(): BelongsTo
    {
        return $this->belongsTo(LedgerDiff::class, 'latest_diff_id');
    }

    /**
     * 現在のバージョンに対応する LedgerDiff レコードへのリレーション (オプション)
     * $ledger->diffForVersion(3) のように使う場合。より複雑。
     * 通常は LedgerDiff 側から ledger_id で検索する方が多い。
     */
    // public function diffForVersion(int $version) { ... }

    /**
     * 編集がロックされているか (APPROVED 状態か) を判定するヘルパー
     */
    public function isLocked(): bool
    {
        return $this->status === WorkflowStatus::APPROVED;
    }

    // クラス定数として必要なリレーションを定義
    public const NEEDED_RELATIONS = [
        'define:id,title,workflow_enabled,folder_id',
        'creator:id,name',
        'latestDiff.inspector:id,name',
        'latestDiff.approver:id,name',
    ];

    public function scopeWithNeededRelations(EloquentBuilder $query): EloquentBuilder
    {
        // 定数を使ってリレーションを指定
        return $query->with(self::NEEDED_RELATIONS);
    }

    /**
     * 現在の内容に対して、全ての必須点検ロールによる点検が完了しているか
     */
    public function areAllRequiredInspectionsCompleted(): bool
    {
        if (! $this->define?->workflow_enabled || ! $this->define?->folder) {
            return true; // ワークフロー無効またはフォルダ未設定なら、点検は不要とみなす
        }
        $progress = $this->getRequiredRolesProgressDetails();

        return $progress['inspection']['is_all_completed'];
    }

    /**
     * 現在の内容に対して、全ての必須承認ロールによる承認が完了しているか
     */
    public function areAllRequiredApprovalsCompleted(): bool
    {
        if (! $this->define?->workflow_enabled || ! $this->define?->folder) {
            return true; // ワークフロー無効またはフォルダ未設定なら、承認は不要とみなす
        }
        $progress = $this->getRequiredRolesProgressDetails();

        return $progress['approval']['is_all_completed'];
    }

    /**
     * ワークフローを次の承認ステップに進められるか
     * (全ての必須点検が完了しているか)
     */
    public function canProceedToApprovalStep(): bool
    {
        $progress = $this->getRequiredRolesProgressDetails();

        return $progress['inspection']['is_all_completed'];
    }

    /**
     * この台帳を最終承認できるか
     * (全ての必須点検と全ての必須承認が完了しているか)
     */
    public function canBeFinallyApproved(): bool
    {
        $progress = $this->getRequiredRolesProgressDetails();

        return $progress['inspection']['completed_count'] > 0
        && (
            ! $progress['inspection']['is_all_completed'] || ! $progress['approval']['is_all_completed']
        );
    }

    /**
     * この台帳のワークフローにおいて、必須ロールの処理進捗状況を取得する。
     *
     * @return array ['inspection' => ['total_roles' => Collection<Role>, 'completed_roles' => Collection<Role>, 'pending_roles' => Collection<Role>, 'completed_count' => int, 'total_count' => int],
     *               'approval' => ['total_roles' => Collection<Role>, 'completed_roles' => Collection<Role>, 'pending_roles' => Collection<Role>, 'completed_count' => int, 'total_count' => int]]
     */
    public function getRequiredRolesProgressDetails(): array
    {
        $result = [
            'inspection' => ['total_roles' => collect(), 'completed_roles' => collect(), 'pending_roles' => collect(), 'completed_count' => 0, 'total_count' => 0, 'is_all_completed' => false],
            'approval' => ['total_roles' => collect(), 'completed_roles' => collect(), 'pending_roles' => collect(), 'completed_count' => 0, 'total_count' => 0, 'is_all_completed' => false],
        ];

        if (! $this->define?->workflow_enabled || ! $this->define?->folder || ! $this->latestDiff) {
            // フォルダ設定がない場合や、まだDiffが一度も作られていない場合はデフォルト値を返す
            if ($this->define?->folder) { // フォルダ設定はあるが進捗がない場合
                $result['inspection']['total_roles'] = $this->define->folder->requiredInspectorRoles;
                $result['inspection']['total_count'] = $result['inspection']['total_roles']->count();
                $result['inspection']['pending_roles'] = $result['inspection']['total_roles'];
                $result['inspection']['is_all_completed'] = $result['inspection']['total_count'] === 0;

                $result['approval']['total_roles'] = $this->define->folder->requiredApproverRoles;
                $result['approval']['total_count'] = $result['approval']['total_roles']->count();
                $result['approval']['pending_roles'] = $result['approval']['total_roles'];
                $result['approval']['is_all_completed'] = $result['approval']['total_count'] === 0;
            }

            return $result;
        }

        $folder = $this->define->folder;
        $latestDiff = $this->latestDiff;

        // --- 点検進捗 ---
        $requiredInspectorRoles = $folder->requiredInspectorRoles;
        $result['inspection']['total_roles'] = $requiredInspectorRoles;
        $result['inspection']['total_count'] = $requiredInspectorRoles->count();
        $completedInspectorRoleIds = $latestDiff->completed_inspector_role_ids ?? [];

        foreach ($requiredInspectorRoles as $role) {
            //                        dd($role, $completedInspectorRoleIds);
            if (in_array($role->id, $completedInspectorRoleIds)) {
                $result['inspection']['completed_roles']->push($role);
            } else {
                $result['inspection']['pending_roles']->push($role);
            }
        }
        $result['inspection']['completed_count'] = $result['inspection']['completed_roles']->count();
        $result['inspection']['is_all_completed'] = ($result['inspection']['total_count'] > 0
                && $result['inspection']['completed_count'] >= $result['inspection']['total_count'])
            || $result['inspection']['total_count'] === 0;
        //                dd($result);

        // --- 承認進捗 ---
        $requiredApproverRoles = $folder->requiredApproverRoles;
        $result['approval']['total_roles'] = $requiredApproverRoles;
        $result['approval']['total_count'] = $requiredApproverRoles->count();
        $completedApproverRoleIds = $latestDiff->completed_approver_role_ids ?? [];

        foreach ($requiredApproverRoles as $role) {
            if (in_array($role->id, $completedApproverRoleIds)) {
                $result['approval']['completed_roles']->push($role);
            } else {
                $result['approval']['pending_roles']->push($role);
            }
        }
        $result['approval']['completed_count'] = $result['approval']['completed_roles']->count();
        $result['approval']['is_all_completed'] = ($result['approval']['total_count'] > 0 && $result['approval']['completed_count'] >= $result['approval']['total_count']) || $result['approval']['total_count'] === 0;

        return $result;
    }

    /**
     * 現在の内容に対して、いずれかの必須点検ロールによる点検が完了した実績があるか
     * (getRequiredRolesProgressDetails の結果を利用)
     */
    public function hasAnyRequiredInspectionBeenDoneForCurrentContent(): bool
    {
        // ワークフローが無効、またはフォルダ/必須点検ロールが未設定なら、
        // この条件は「常に満たされている」または「考慮不要」とみなす。
        // 承認アクションの前提条件として使うため、「満たされている」と解釈するのが自然か。
        if (! $this->define?->workflow_enabled
            || ! $this->define?->folder
            || $this->define->folder->requiredInspectorRoles->isEmpty()
        ) {
            return true;
        }

        $progress = $this->getRequiredRolesProgressDetails(); // 内部で latestDiff を参照

        return $progress['inspection']['completed_count'] > 0;
    }

    /**
     * デフォルトソート用正規化文字列を生成します。
     * ColumnDefine の sort_index に基づき、優先順位に従ってカラム値を連結します。
     */
    public function generateDefaultSortValue(): ?string
    {
        $define = $this->define;
        if (! $define || ! $define->column_define) {
            return null;
        }

        // sort_index が設定されているカラムを優先順に取得
        $columns = collect($define->column_define)
            ->filter(fn ($col) => ! is_null($col->sort_index))
            ->sortBy('sort_index');

        if ($columns->isEmpty()) {
            return null;
        }

        $normalizedValues = [];
        foreach ($columns as $column) {
            $value = $this->content[$column->id] ?? null;
            $normalizedValues[] = $this->normalizeValueForSort($column, $value);
        }

        $result = implode('|', $normalizedValues);

        // キー長制限(512文字)を考慮
        return mb_strimwidth($result, 0, 512);
    }

    /**
     * カラム型に応じた正規化処理
     */
    protected function normalizeValueForSort(ColumnDefine $column, $value): string
    {
        if (is_null($value) || $value === '') {
            return '';
        }

        switch ($column->type) {
            case 'number':
                // 正負、整数20桁、小数10桁のゼロパディング
                if (! is_numeric($value)) {
                    return str_repeat(' ', 32);
                }
                $num = (float) $value;
                $sign = $num >= 0 ? '+' : '-';
                $abs = abs($num);
                $parts = explode('.', sprintf('%.10f', $abs));
                $intPart = str_pad($parts[0], 20, '0', STR_PAD_LEFT);
                $decPart = str_pad($parts[1] ?? '', 10, '0', STR_PAD_RIGHT);

                return "{$sign}{$intPart}.{$decPart}";

            case 'auto_number':
                // そのまま使用（既にパディング済み）
                return (string) $value;

            case 'YMD':
            case 'YMDHM':
                try {
                    return Carbon::parse($value)->format('Y-m-d');
                } catch (Exception $e) {
                    return '0000-00-00';
                }

            case 'files':
                // 最初のオリジナルファイル名を取得
                if (is_array($value) && ! empty($value)) {
                    $originalName = reset($value);

                    return $this->normalizeTextForSort($originalName);
                }

                return '';

            case 'chk':
                return $value ? '1' : '0';

            default:
                // text, textarea, select, phone, user_name 等
                return $this->normalizeTextForSort($value);
        }
    }

    /**
     * テキスト型の正規化
     */
    protected function normalizeTextForSort($value): string
    {
        $text = (string) $value;
        $text = strip_tags($text);
        // Markdown簡易除去（あくまでソート用なので厳密でなくて良い）
        // _ はファイル名等で一般的かつソートに有用なため除外対象から外す
        $text = preg_replace('/[#*`~\[\]]/', '', $text);
        // 改行、タブを空白置換
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        // 連続空白を1つに
        $text = preg_replace('/\s+/', ' ', $text);

        return mb_substr(trim($text), 0, 50);
    }
}
