<?php

namespace App\Services;

use App\Models\Ledger;
use App\Repositories\WritableFolderRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LedgerService
{
    protected WritableFolderRepository $writableFolderRepository;

    public function __construct(WritableFolderRepository $writableFolderRepository)
    {
        $this->writableFolderRepository = $writableFolderRepository;
    }

    /**
     * @return Builder[]|Collection
     */
    public function getLedgers()
    {
        return Ledger::orderBy('created_at', 'DESC')->get();
    }

    /**
     * @return Builder[]|Collection
     */
    public function searchLedgers(string $keyword)
    {
        //        return Ledger::freeword($keyword)->orderBy('created_at', 'DESC')->get();
        $result = Ledger::scopeSearch($keyword)->orderBy('created_at', 'DESC')->get();

        //        var_dump(DB::getQueryLog());
        return $result;

    }

    public function searchLedgersForApi(\App\Models\User $user, array $params)
    {
        \Log::info('[MCP Search Debug] === Start searchLedgersForApi ===');
        \Log::info('[MCP Search Debug] User ID: '.$user->id);
        \Log::info('[MCP Search Debug] Input params: '.json_encode($params, JSON_UNESCAPED_UNICODE));

        // ユーザーが読み取り可能なフォルダIDのリストを取得
        $startTime = microtime(true);
        $readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($user);
        $folderIdsTime = microtime(true) - $startTime;
        \Log::info('[MCP Search Debug] Readable folder IDs: '.json_encode($readableFolderIds));
        \Log::info('[MCP Search Debug] Time to get folder IDs: '.round($folderIdsTime * 1000, 2).'ms');

        // QueryBuilderが期待する形式にパラメータを変換
        $queryParams = [];
        if (isset($params['creator_id'])) {
            $queryParams['filter']['creator_id'] = $params['creator_id'];
        }
        if (isset($params['ledger_define_id'])) {
            $queryParams['filter']['ledger_define_id'] = $params['ledger_define_id'];
        }
        if (isset($params['tags'])) {
            $queryParams['filter']['with_tags'] = $params['tags'];
        }
        if (isset($params['exclude_tags'])) {
            $queryParams['filter']['without_tags'] = $params['exclude_tags'];
        }
        if (isset($params['folder_id'])) {
            $queryParams['filter']['folder_hierarchy'] = $params['folder_id'];
        }
        if (isset($params['q'])) {
            $queryParams['filter']['q'] = $params['q'];
        }
        if (isset($params['exclude_q'])) {
            $queryParams['filter']['exclude_q'] = $params['exclude_q'];
        }

        // 手動で created_from/created_to を created_between に変換
        if (! empty($params['created_from']) && ! empty($params['created_to'])) {
            $queryParams['filter']['created_between'] = $params['created_from'].','.$params['created_to'];
        }

        \Log::info('[MCP Search Debug] Transformed query params: '.json_encode($queryParams, JSON_UNESCAPED_UNICODE));

        // 一時的にリクエストを作成してQueryBuilderが使用できるように
        $request = new Request($queryParams);
        app()->instance('request', $request);

        // spatie/laravel-query-builder を使用してクエリを構築
        \Log::info('[MCP Search Debug] Building QueryBuilder...');
        $buildStartTime = microtime(true);

        $query = QueryBuilder::for(Ledger::class)
            ->allowedFilters([
                // 完全一致フィルタ
                AllowedFilter::exact('creator_id'),
                AllowedFilter::exact('ledger_define_id'),

                // スコープベースフィルタ
                AllowedFilter::scope('created_between'),
                AllowedFilter::scope('updated_between'),
                AllowedFilter::callback('with_tags', function ($query, $value) {
                    \Log::info('[MCP Search Debug] Applying with_tags filter: '.json_encode($value));
                    // カンマ区切り文字列または配列を処理
                    $tagNames = is_string($value) ? array_filter(explode(',', $value)) : $value;
                    if (! empty($tagNames)) {
                        $query->whereHas('define.tags', function (\Illuminate\Database\Eloquent\Builder $q) use ($tagNames) {
                            $q->whereIn('name', $tagNames);
                        }, '=', count($tagNames));
                    }
                }),
                AllowedFilter::scope('without_tags'),
                AllowedFilter::scope('folder_hierarchy'),

                // カスタムコールバックフィルタ
                AllowedFilter::callback('q', function ($query, $value) {
                    \Log::info('[MCP Search Debug] Applying full-text search filter with keyword: '.$value);
                    $searchStartTime = microtime(true);
                    $query->search($value);
                    $searchTime = microtime(true) - $searchStartTime;
                    \Log::info('[MCP Search Debug] Search scope applied in: '.round($searchTime * 1000, 2).'ms');
                }),
                AllowedFilter::callback('exclude_q', function ($query, $value) {
                    \Log::info('[MCP Search Debug] Applying exclude_q filter: '.$value);
                    // 除外キーワードを含まない結果のみを返すように修正
                    $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($value) {
                        $q->whereRaw('not match(`content`) against (? IN BOOLEAN MODE)', [$value])
                            ->whereRaw('not match(`content_attached`) against (? IN BOOLEAN MODE)', [$value]);
                    });
                }),
            ])
            ->allowedSorts(['composite_score', 'activity_score', 'created_at', 'updated_at', 'id'])
            ->whereHas('define.folder', function (\Illuminate\Database\Eloquent\Builder $q) use ($readableFolderIds) {
                $q->whereIn('id', $readableFolderIds);
            });

        // ソート処理を適用
        $orderBy = $params['order_by'] ?? 'composite_score';
        $orderDirection = $params['order_direction'] ?? 'desc';

        \Log::info('[MCP Search Debug] Applying sort: order_by='.$orderBy.', order_direction='.$orderDirection);

        if ($orderBy === 'composite_score' || $orderBy === 'activity_score') {
            // スコアカラムの場合、NULL（0）を最後にソート
            $query->orderByRaw("{$orderBy} = 0")
                ->orderBy($orderBy, $orderDirection);
        } else {
            $query->orderBy($orderBy, $orderDirection);
        }

        // 同点の場合の第2ソートキー
        if ($orderBy !== 'created_at') {
            $query->orderBy('created_at', 'desc');
        }

        $buildTime = microtime(true) - $buildStartTime;
        \Log::info('[MCP Search Debug] QueryBuilder built in: '.round($buildTime * 1000, 2).'ms');

        // パフォーマンス最適化: countクエリを分離
        \Log::info('[MCP Search Debug] Executing count query...');
        $countStartTime = microtime(true);
        $countQuery = clone $query;
        $total = $countQuery->count();
        $countTime = microtime(true) - $countStartTime;
        \Log::info('[MCP Search Debug] Count query result: '.$total.' (took '.round($countTime * 1000, 2).'ms)');

        // ページネーション
        $limit = $params['limit'] ?? 10;
        $offset = $params['offset'] ?? 0;
        \Log::info('[MCP Search Debug] Applying pagination: limit='.$limit.', offset='.$offset);

        // 結果取得
        \Log::info('[MCP Search Debug] Executing main query...');
        $mainQueryStartTime = microtime(true);
        $ledgers = $query->offset($offset)->limit($limit)->get();
        $mainQueryTime = microtime(true) - $mainQueryStartTime;
        \Log::info('[MCP Search Debug] Main query result: '.$ledgers->count().' items (took '.round($mainQueryTime * 1000, 2).'ms)');

        // Eager Loading を追加（N+1問題回避）
        \Log::info('[MCP Search Debug] Loading relationships...');
        $eagerLoadStartTime = microtime(true);
        $ledgers->load([
            'define',
            'define.folder',
            'define.folder.ancestors',
            'define.tags',
            'creator:id,name',
            'modifier:id,name',
        ]);
        $eagerLoadTime = microtime(true) - $eagerLoadStartTime;
        \Log::info('[MCP Search Debug] Relationships loaded in: '.round($eagerLoadTime * 1000, 2).'ms');

        // メタデータを構築
        \Log::info('[MCP Search Debug] Building metadata...');
        $metaStartTime = microtime(true);
        $ledgerDefines = $ledgers->pluck('define')->filter()->unique('id');
        $creators = $ledgers->pluck('creator')->filter()->unique('id');
        $modifiers = $ledgers->pluck('modifier')->filter()->unique('id');
        $users = $creators->union($modifiers)->keyBy('id');

        // フォルダ情報を収集
        $folders = collect();
        $ledgerDefines->each(function ($define) use (&$folders) {
            if ($define && $define->folder) {
                // フォルダ自身とその祖先をコレクションに追加
                $folders->push($define->folder);
                if ($define->folder->relationLoaded('ancestors')) {
                    $folders = $folders->merge($define->folder->ancestors);
                }
            }
        });
        $uniqueFolders = $folders->unique('id');

        // 各フォルダのフルパスを構築
        $uniqueFolders->each(function ($folder) {
            // ancestorsリレーションは親方向（子→親）のリストなので、逆順にしてからつなげる
            if ($folder->relationLoaded('ancestors')) {
                $path = $folder->ancestors->reverse()->pluck('name')->push($folder->name)->implode('/');
                $folder->setAttribute('path', '/'.$path); // ルートからのパスとしてスラッシュを追加
            }
        });

        $meta = [
            'ledger_defines' => $ledgerDefines->keyBy('id'),
            'folders' => $uniqueFolders->keyBy('id'),
            'users' => $users,
        ];
        $metaTime = microtime(true) - $metaStartTime;
        \Log::info('[MCP Search Debug] Metadata built in: '.round($metaTime * 1000, 2).'ms');

        \Log::info('[MCP Search Debug] === End searchLedgersForApi (Success) ===');

        return [
            'ledgers' => $ledgers,
            'meta' => $meta,
            'total' => $total,
        ];
    }

    public function createLedger(array $data): Ledger
    {
        return \DB::transaction(function () use ($data) {
            $ledgerDefine = \App\Models\LedgerDefine::findOrFail($data['ledger_define_id']);

            $ledger = Ledger::create([
                'ledger_define_id' => $ledgerDefine->id,
                'content' => $data['content'],
                'creator_id' => auth()->id(),
                'modifier_id' => auth()->id(),
            ]);

            if (! empty($data['tags'])) {
                foreach ($data['tags'] as $tagName) {
                    \App\Models\Tag::firstOrCreate([
                        'name' => $tagName,
                        'ledger_define_id' => $ledgerDefine->id,
                        'folder_id' => $ledgerDefine->folder_id,
                        'creator_id' => auth()->id(), // creator_id を追加
                        'modifier_id' => auth()->id(), // modifier_id も追加
                    ]);
                }
            }

            return $ledger->load('define');
        });
    }
}
