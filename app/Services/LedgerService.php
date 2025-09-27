<?php

namespace App\Services;

use App\Models\Ledger;
use App\Repositories\WritableFolderRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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

        // ベースとなるクエリに権限チェックを追加
        $query = \App\Models\Ledger::query()
            ->whereHas('define.folder', function (Builder $q) use ($readableFolderIds) {
                $q->whereIn('id', $readableFolderIds);
            })
            ->apiSearch($params);

        if (($params['mode'] ?? 'search') === 'count') {
            return ['total' => $query->count()];
        }

        $total = $query->count(); // ページネーション前に総件数を取得
        $limit = $params['limit'] ?? 10;
        $offset = $params['offset'] ?? 0;

        // Eager Loading を追加
        $ledgers = $query->with(['define', 'define.folder', 'define.folder.ancestors', 'define.tags'])
            ->offset($offset)->limit($limit)->get();

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
