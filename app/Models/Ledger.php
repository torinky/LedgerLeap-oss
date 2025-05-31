<?php

namespace App\Models;

// use App\Casts\AsCollection;

use App\Casts\AsColumnArrayJson;
use App\Enums\WorkflowStatus;
use App\Services\Ledger\SearchContext;
use Exception;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property array $content_attached
 * @property array $content
 * @property BelongsTo $define
 *
 * @method static create(array $array)
 * @method static find(string $ledgerId)
 */
class Ledger extends Model
{
    use HasFactory, LogsActivity;

    protected $casts = [
        'content' => AsColumnArrayJson::class,
        'content_attached' => AsColumnArrayJson::class,
        'status' => WorkflowStatus::class,
    ];

    protected $fillable = [
        'content', 'content_attached', 'ledger_define_id', 'creator_id', 'modifier_id', 'status', 'latest_diff_id', 'version'
    ];

    /**
     * モデルの作成と更新イベントを処理するメソッドです。
     *
     * @return void
     */
    /*    protected static function booted(): void
        {
            static::saving(static function ($ledger) {
                // update イベントの処理
                $ledger->normalizeContent();
            });

        }*/

    /**
     * 指定されたフリーワードで content を検索するスコープです。
     *
     * @return void
     */
    public function scopeSearch(EloquentBuilder $query, string $freeWord)
    {
        $freeWord = trim($freeWord);
        if (empty($freeWord)) {
            return;
        }
        //        dd($freeWord);
        //        $query->whereRaw("match(`content`) against (? IN BOOLEAN MODE)", [$freeWord]);
        //        $query->whereRaw("match(`content`,`content_attached`) against (? IN BOOLEAN MODE)", [$freeWord]);
        $query->where(function (EloquentBuilder $q) use ($freeWord) {
            $q->whereRaw('match(`content`) against (? IN BOOLEAN MODE)', [$freeWord])
                ->orWhereRaw('match(`content_attached`) against (? IN BOOLEAN MODE)', [$freeWord]);
        });

        //        dd($query->toSql(), $query->getBindings());

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
            $query->where(function (Builder $q) use ($mroongaColumnCount, $filterStr) {
                $q->whereRaw("match(`content`) against ('*W" . $mroongaColumnCount . ' +"' . $filterStr . "\"' IN BOOLEAN MODE)")
                    ->orWhereRaw("match(`content_attached`) against ('*W" . $mroongaColumnCount . ' +' . $filterStr . "' IN BOOLEAN MODE)");
            });
        }

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
            ->setDescriptionForEvent(fn(string $eventName) => $this->getLogDescriptionForEvent($eventName))
            ->logFillable()
            // ->logUnguarded() // ガードされていないすべての属性をログに記録 (fillable の逆)
            ->dontLogIfAttributesChangedOnly(['latest_diff_id']) // 特定の属性のみが変更された場合はログを記録しない
            ;
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
     * この台帳のワークフローにおいて、必須ロールの処理進捗状況を取得する。
     *
     * @return array ['inspection' => ['total_roles' => Collection<Role>, 'completed_roles' => Collection<Role>, 'pending_roles' => Collection<Role>, 'completed_count' => int, 'total_count' => int],
     *                'approval' => ['total_roles' => Collection<Role>, 'completed_roles' => Collection<Role>, 'pending_roles' => Collection<Role>, 'completed_count' => int, 'total_count' => int]]
     */
    public function getRequiredRolesProgressDetails(): array
    {
        $result = [
            'inspection' => [
                'total_roles' => collect(),
                'completed_roles' => collect(),
                'pending_roles' => collect(),
                'completed_count' => 0,
                'total_count' => 0,
                'is_all_completed' => false,
            ],
            'approval' => [
                'total_roles' => collect(),
                'completed_roles' => collect(),
                'pending_roles' => collect(),
                'completed_count' => 0,
                'total_count' => 0,
                'is_all_completed' => false,
            ],
        ];

        if (!($this->define?->workflow_enabled) || !($this->define?->folder)) {
            return $result; // ワークフロー無効、または定義やフォルダがなければ進捗計算をスキップ
        }

        $folder = $this->define?->folder;
        $requiredInspectorRoles = $folder->requiredInspectorRoles; // Folderモデルのリレーション
        $requiredApproverRoles = $folder->requiredApproverRoles;   // Folderモデルのリレーション
        $result['inspection']['total_roles'] = $requiredInspectorRoles;
        $result['inspection']['total_count'] = $requiredInspectorRoles->count();
        $result['approval']['total_roles'] = $requiredApproverRoles;
        $result['approval']['total_count'] = $requiredApproverRoles->count();

        // 比較の基準となるDiff (最後に内容が変更されたDiff) を特定
        $baselineDiff = $this->findBaselineDiffForProgressCheck();
        $relevantDiffs = collect();

        if ($baselineDiff) {
            // ベースラインDiff以降の全てのDiffを取得 (ステータス変更やコメントのみのDiffも含む)
            $relevantDiffs = $this->ledgerDiff()
                ->where('id', '>=', $baselineDiff->id)
                ->orderBy('id', 'asc') // 古い順から処理するため
                ->with('modifier.roles') // modifierとそのロールをEager Load
                ->get();
        } else {
            // ベースラインが見つからない場合 (例: contentを持つDiffがまだない) は、
            // 全てのDiffを対象にするか、あるいは進捗0とするか。
            // ここでは一旦全てのDiffを対象とする (最初の点検からカウントするため)
            $relevantDiffs = $this->ledgerDiff()
                ->orderBy('id', 'asc')
                ->with('modifier.roles')
                ->get();
        }

        if ($relevantDiffs->isEmpty()) {
            $result['inspection']['pending_roles'] = $requiredInspectorRoles;
            $result['approval']['pending_roles'] = $requiredApproverRoles;
            return $result;
        }

        // 点検進捗の計算
        $completedInspectorRoleIds = [];
        foreach ($requiredInspectorRoles as $inspectorRole) {
            foreach ($relevantDiffs as $diff) {
                // modifier が必須点検ロールを持っていて、
                // かつ、そのDiffが点検完了を示すアクションの結果であるか
                // (例: PENDING_APPROVAL になったDiff、または APPROVED になったDiff)
                if ($diff->modifier && $diff->modifier->hasRole($inspectorRole->name) &&
                    ($diff->status === WorkflowStatus::PENDING_APPROVAL || $diff->status === WorkflowStatus::APPROVED)) {
                    if (!in_array($inspectorRole->id, $completedInspectorRoleIds)) {
                        $completedInspectorRoleIds[] = $inspectorRole->id;
                        $result['inspection']['completed_roles']->push($inspectorRole);
                    }
                    break; // このロールの点検は完了
                }
            }
        }
        $result['inspection']['completed_count'] = $result['inspection']['completed_roles']->count();
        $result['inspection']['pending_roles'] = $requiredInspectorRoles->filter(function ($role) use ($completedInspectorRoleIds) {
            return !in_array($role->id, $completedInspectorRoleIds);
        });


        // 承認進捗の計算
        $completedApproverRoleIds = [];
        // 承認は、全ての必須点検が完了していることが前提となるか？ (今回は考慮しない)
        foreach ($requiredApproverRoles as $approverRole) {
            foreach ($relevantDiffs as $diff) {
                // modifier が必須承認ロールを持っていて、
                // かつ、そのDiffが承認アクションの結果であるか (status が APPROVED)
                if ($diff->modifier && $diff->modifier->hasRole($approverRole->name) &&
                    $diff->status === WorkflowStatus::APPROVED) {
                    if (!in_array($approverRole->id, $completedApproverRoleIds)) {
                        $completedApproverRoleIds[] = $approverRole->id;
                        $result['approval']['completed_roles']->push($approverRole);
                    }
                    break; // このロールの承認は完了
                }
            }
        }
        $result['approval']['completed_count'] = $result['approval']['completed_roles']->count();
        $result['approval']['pending_roles'] = $requiredApproverRoles->filter(function ($role) use ($completedApproverRoleIds) {
            return !in_array($role->id, $completedApproverRoleIds);
        });

        return $result;
    }

    /**
     * 必須ロールの進捗判定の基準となるLedgerDiff (最後に内容が変更されたDiff) を特定する。
     * 見つからない場合は、バージョン1のcontentありDiffを返す。
     * それもなければnullを返す。
     */
    public function findBaselineDiffForProgressCheck(): ?LedgerDiff
    {
        // 1. 最新のDiffから遡り、最初にcontentが記録されているものを探す (これが最新の内容変更)
        $lastContentChangeDiff = $this->ledgerDiff()
            ->whereNotNull('content')
            ->where(function(EloquentBuilder $q) { // JSONカラムの空判定
                $q->where('content', '<>', '[]')
                    ->orWhere('content', '<>', '{}');
            })
            ->orderByDesc('id')
            ->first();

        if ($lastContentChangeDiff) {
            return $lastContentChangeDiff;
        }

        // 2. contentを持つDiffが一つもない場合は、最初のDiffを基準とするか、nullとする。
        //    ここでは、最初のDiff（バージョン1）でcontentがあればそれを返す。
        return $this->ledgerDiff()
            ->where('version', 1)
            ->whereNotNull('content')
            ->where(function(EloquentBuilder $q) {
                $q->where('content', '<>', '[]')
                    ->orWhere('content', '<>', '{}');
            })
            ->first();
    }
}