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
        Log::info('LedgerService: searchLedgersForApi received params', ['params' => $params]);
        // ユーザーが読み取り可能なフォルダIDのリストを取得
        $readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($user);

        // created_from と created_to が存在する場合、created_between に変換
        if (isset($params['created_from']) && isset($params['created_to'])) {
            $params['filter']['created_between'] = $params['created_from'] . ',' . $params['created_to'];
            unset($params['created_from']);
            unset($params['created_to']);
        }

        // QueryBuilder を使用してクエリを構築
        $request = new Request($params);
        Log::info('LedgerService: QueryBuilder Request object', ['request_query' => $request->query->all(), 'request_filter' => $request->get('filter')]);
        $query = QueryBuilder::for(Ledger::class, $request)
            ->allowedFilters([
                // キーワード検索 (Mroonga)
                AllowedFilter::custom('q', new \App\QueryFilters\MroongaFullTextFilter(['content', 'content_attached'])),
                // 除外キーワード検索 (Mroonga)
                AllowedFilter::callback('exclude_q', function (Builder $query, $value) {
                    $excludeKeywords = '-' . implode(' -', explode(' ', $value));
                    $query->where(function (Builder $q) use ($excludeKeywords) {
                        $q->whereRaw('match(`content`) against (? IN BOOLEAN MODE)', [$excludeKeywords])
                            ->whereRaw('match(`content_attached`) against (? IN BOOLEAN MODE)', [$excludeKeywords]);
                    });
                }),
                // 台帳定義IDでの絞り込み
                AllowedFilter::exact('ledger_define_id'),
                // フォルダIDでの絞り込み (再帰的)
                AllowedFilter::callback('folder_id', function (Builder $query, $value) {
                    $folderIds = Folder::descendantsAndSelf($value)->pluck('id');
                    $query->whereHas('define.folder', function (Builder $q) use ($folderIds) {
                        $q->whereIn('id', $folderIds);
                    });
                }),
                // タグでの絞り込み (AND条件)
                AllowedFilter::callback('tags', function (Builder $query, $value) {
                    $tagNames = array_filter(explode(',', $value));
                    if (!empty($tagNames)) {
                        $query->whereHas('define.tags', function (Builder $q) use ($tagNames) {
                            $q->whereIn('name', $tagNames);
                        }, '=', count($tagNames));
                    }
                }),
                // 除外タグでの絞り込み
                AllowedFilter::callback('exclude_tags', function (Builder $query, $value) {
                    $excludeTagNames = array_filter(explode(',', $value));
                    if (!empty($excludeTagNames)) {
                        $query->whereDoesntHave('define.tags', function (Builder $q) use ($excludeTagNames) {
                            $q->whereIn('name', $excludeTagNames);
                        });
                    }
                }),
                // 作成者IDでの絞り込み (新規追加)
                AllowedFilter::exact('creator_id'),
                // 作成日時での期間絞り込み (新規追加)
                AllowedFilter::scope('created_between'),
            ])
            // ユーザーが読み取り可能なフォルダIDのリストで権限チェックを追加
            ->whereHas('define.folder', function (Builder $q) use ($readableFolderIds) {
                $q->whereIn('id', $readableFolderIds);
            });

        // ページネーション
        $limit = $params['limit'] ?? 10;
        $offset = $params['offset'] ?? 0;

        // QueryBuilder のページネーション機能を使用
        $total = $query->count();
        $ledgers = $query->offset($offset)->limit($limit)->get();

        // Eager Loading を追加
        $ledgers->load(['define', 'define.folder', 'define.folder.ancestors', 'define.tags']);

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