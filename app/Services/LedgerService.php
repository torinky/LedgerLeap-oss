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

    public function searchLedgersForApi(array $params)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

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
        $ledgers = $query->with(['define.folder', 'define.tags'])
            ->offset($offset)->limit($limit)->get();

        return [
            'ledgers' => $ledgers,
            'total' => $total,
        ];
    }
}
