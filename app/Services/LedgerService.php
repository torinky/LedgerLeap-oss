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
        // ユーザーが読み取り可能なフォルダIDのリストを取得
        $readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($user);

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

        // 一時的にリクエストを作成してQueryBuilderが使用できるように
        $request = new Request($queryParams);
        app()->instance('request', $request);

        // spatie/laravel-query-builder を使用してクエリを構築
        $query = QueryBuilder::for(Ledger::class)
            ->allowedFilters([
                // 完全一致フィルタ
                AllowedFilter::exact('creator_id'),
                AllowedFilter::exact('ledger_define_id'),

                // スコープベースフィルタ
                AllowedFilter::scope('created_between'),
                AllowedFilter::scope('updated_between'),
                AllowedFilter::callback('with_tags', function ($query, $value) {
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
                    $query->search($value);
                }),
                AllowedFilter::callback('exclude_q', function ($query, $value) {
                    // 除外キーワードを含まない結果のみを返すように修正
                    $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($value) {
                        $q->whereRaw('not match(`content`) against (? IN BOOLEAN MODE)', [$value])
                            ->whereRaw('not match(`content_attached`) against (? IN BOOLEAN MODE)', [$value]);
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'updated_at', 'id'])
            ->defaultSort('-created_at') // デフォルトは作成日時の降順
            ->whereHas('define.folder', function (\Illuminate\Database\Eloquent\Builder $q) use ($readableFolderIds) {
                $q->whereIn('id', $readableFolderIds);
            });

        // パフォーマンス最適化: countクエリを分離
        $countQuery = clone $query;
        $total = $countQuery->count();

        // ページネーション
        $limit = $params['limit'] ?? 10;
        $offset = $params['offset'] ?? 0;

        // 結果取得
        $ledgers = $query->offset($offset)->limit($limit)->get();

        // Eager Loading を追加（N+1問題回避）
        $ledgers->load([
            'define',
            'define.folder',
            'define.folder.ancestors',
            'define.tags',
            'creator:id,name',
            'modifier:id,name',
        ]);

        return [
            'ledgers' => $ledgers,
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
