<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\Ledger;
use App\Repositories\WritableFolderRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // spatie/laravel-query-builder を使用してクエリを構築
        $query = QueryBuilder::for(Ledger::class)
            ->allowedFilters([
                // 完全一致フィルタ
                AllowedFilter::exact('creator_id'),
                AllowedFilter::exact('ledger_define_id'),
                
                // スコープベースフィルタ
                AllowedFilter::scope('created_between'),
                AllowedFilter::scope('updated_between'),
                AllowedFilter::scope('folder_hierarchy', 'folder_id'),
                AllowedFilter::scope('with_tags', 'tags'),
                AllowedFilter::scope('without_tags', 'exclude_tags'),
                
                // カスタムコールバックフィルタ
                AllowedFilter::callback('q', function ($query, $value) {
                    $query->search($value);
                }),
                AllowedFilter::callback('exclude_q', function ($query, $value) {
                    $excludeKeywords = '-' . implode(' -', explode(' ', $value));
                    $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($excludeKeywords) {
                        $q->whereRaw('match(`content`) against (? IN BOOLEAN MODE)', [$excludeKeywords])
                          ->orWhereRaw('match(`content_attached`) against (? IN BOOLEAN MODE)', [$excludeKeywords]);
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'updated_at', 'id'])
            ->defaultSort('-created_at') // デフォルトは作成日時の降順
            ->whereHas('define.folder', function (\Illuminate\Database\Eloquent\Builder $q) use ($readableFolderIds) {
                $q->whereIn('id', $readableFolderIds);
            });

        // 手動で created_from/created_to を created_between に変換
        if (!empty($params['created_from']) && !empty($params['created_to'])) {
            $params['created_between'] = $params['created_from'] . ',' . $params['created_to'];
        }

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
            'modifier:id,name'
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

            if (!empty($data['tags'])) {
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