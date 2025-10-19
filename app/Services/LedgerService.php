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

        // ▼▼▼ ここから追加 ▼▼▼
        // セマンティック検索の分岐
        if (($params['order_by'] ?? null) === 'semantic_score') {
            if (empty($params['q'])) {
                throw new \InvalidArgumentException(
                    'semantic_score sorting requires a search query (q parameter).'
                );
            }
            
            \Log::info('[MCP Search Debug] Semantic search triggered. Delegating to RagSearchService.');

            // RagSearchServiceを呼び出し、結果をAPI形式に整形して返す
            $ragResults = app(\App\Services\RagSearchService::class)->searchForApi(
                $user,
                [
                    'query' => $params['q'],
                    'limit' => $params['limit'] ?? 20,
                    'filters' => [
                        'ledger_define_id' => $params['ledger_define_id'] ?? null,
                        'folder_id' => $params['folder_id'] ?? null,
                    ]
                ]
            );

            // APIのレスポンス形式に合わせる
            $ledgers = collect($ragResults)->pluck('ledger');
            $total = $ledgers->count();
            
            // メタデータ構築 (既存ロジックを参考に簡略化)
            $meta = $this->buildMetaData($ledgers);

            \Log::info('[MCP Search Debug] === End searchLedgersForApi (Semantic Search) ===');

            return [
                'ledgers' => $ledgers,
                'meta' => $meta,
                'total' => $total,
            ];
        }
        // ▲▲▲ ここまで追加 ▲▲▲

        // 既存のキーワード検索ロジック (変更なし)
        $this->writableFolderRepository = $writableFolderRepository;
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

    private function buildMetaData(\Illuminate\Database\Eloquent\Collection $ledgers): array
    {
        $ledgerDefines = $ledgers->pluck('define')->filter()->unique('id');
        $creators = $ledgers->pluck('creator')->filter()->unique('id');
        $modifiers = $ledgers->pluck('modifier')->filter()->unique('id');
        $users = $creators->union($modifiers)->keyBy('id');

        $folders = collect();
        $ledgerDefines->each(function ($define) use (&$folders) {
            if ($define && $define->folder) {
                $folders->push($define->folder);
                if ($define->folder->relationLoaded('ancestors')) {
                    $folders = $folders->merge($define->folder->ancestors);
                }
            }
        });
        $uniqueFolders = $folders->unique('id');

        $uniqueFolders->each(function ($folder) {
            if ($folder->relationLoaded('ancestors')) {
                $path = $folder->ancestors->reverse()->pluck('name')->push($folder->name)->implode('/');
                $folder->setAttribute('path', '/'.$path);
            }
        });

        return [
            'ledger_defines' => $ledgerDefines->keyBy('id'),
            'folders' => $uniqueFolders->keyBy('id'),
            'users' => $users,
        ];
    }
}
