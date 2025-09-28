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

        $query = Ledger::query();

        // 権限チェック: ユーザーが読み取り可能なフォルダに属する台帳のみを対象とする
        $query->whereHas('define.folder', function (Builder $q) use ($readableFolderIds) {
            $q->whereIn('id', $readableFolderIds);
        });

        // 'q' (キーワード) フィルタ
        if (!empty($params['q'])) {
            $query->search($params['q']);
        }

        // 'exclude_q' (除外キーワード) フィルタ
        if (!empty($params['exclude_q'])) {
            $excludeKeywords = '-' . implode(' -', explode(' ', $params['exclude_q']));
            $query->where(function (Builder $q) use ($excludeKeywords) {
                $q->whereRaw('match(`content`) against (? IN BOOLEAN MODE)', [$excludeKeywords])
                  ->whereRaw('match(`content_attached`) against (? IN BOOLEAN MODE)', [$excludeKeywords]);
            });
        }

        // 'ledger_define_id' フィルタ
        if (!empty($params['ledger_define_id'])) {
            $query->where('ledger_define_id', $params['ledger_define_id']);
        }

        // 'folder_id' フィルタ (再帰的)
        if (!empty($params['folder_id'])) {
            $folderIds = Folder::descendantsAndSelf($params['folder_id'])->pluck('id');
            $query->whereHas('define.folder', function (Builder $q) use ($folderIds) {
                $q->whereIn('id', $folderIds);
            });
        }

        // 'tags' フィルタ (AND条件)
        if (!empty($params['tags'])) {
            $tagNames = array_filter(explode(',', $params['tags']));
            if (!empty($tagNames)) {
                $query->whereHas('define.tags', function (Builder $q) use ($tagNames) {
                    $q->whereIn('name', $tagNames);
                }, '=', count($tagNames));
            }
        }

        // 'exclude_tags' フィルタ
        if (!empty($params['exclude_tags'])) {
            $excludeTagNames = array_filter(explode(',', $params['exclude_tags']));
            if (!empty($excludeTagNames)) {
                $query->whereDoesntHave('define.tags', function (Builder $q) use ($excludeTagNames) {
                    $q->whereIn('name', $excludeTagNames);
                });
            }
        }

        // 'creator_id' フィルタ
        if (!empty($params['creator_id'])) {
            $query->where('creator_id', $params['creator_id']);
        }

        // 'created_between' フィルタ
        if (!empty($params['created_from']) && !empty($params['created_to'])) {
            $fromDate = $params['created_from'] . ' 00:00:00';
            $toDate = $params['created_to'] . ' 23:59:59';
            
            $query->whereBetween('created_at', [$fromDate, $toDate]);
        }

        // ページネーション
        $limit = $params['limit'] ?? 10;
        $offset = $params['offset'] ?? 0;

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