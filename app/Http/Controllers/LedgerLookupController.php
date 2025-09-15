<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\Tenant;
use App\Services\Config\SynonymServiceConfig;
use App\Services\Ledger\SearchContext;
use App\Services\SynonymService;
use Illuminate\Http\Request;

class LedgerLookupController extends Controller
{
    public function handle(Request $request, string $query)
    {
        if (empty($query)) {
            return redirect()->route('ledger.index', ['tenant' => tenant()->id]);
        }

        // Create a search context to find the ledger
        $synonymServiceConfig = new SynonymServiceConfig(['useSynonym' => true, 'useTechnicalTerm' => true]);
        $synonymService = new SynonymService($synonymServiceConfig);
        $searchContext = new SearchContext($synonymService);
        $searchContext->setSearch($query);

        $results = Ledger::query()->searchContext($searchContext)->get();

        // Force list mode
        if ($request->input('mode') === 'list') {
            return redirect()->route('ledger.index', ['tenant' => tenant()->id, 'q' => $query, 'highlight' => $query, 'l' => '', 'f' => '']);
        }

        // Unique match, redirect to show page
        if ($results->count() === 1) {
            return redirect()->route('ledger.show', ['tenant' => tenant()->id, 'ledgerId' => $results->first()->id, 'highlight' => $query]);
        }

        // 0 or multiple matches, redirect to index page
        return redirect()->route('ledger.index', ['tenant' => tenant()->id, 'q' => $query, 'highlight' => $query, 'l' => '', 'f' => '']);
    }

    public function searchAllTenants(string $query)
    {
        if (empty($query)) {
            return redirect()->route('global.my-portal'); // 検索クエリがない場合はグローバルなマイポータルにリダイレクト
        }

        $allResults = collect();
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($query, &$allResults, $tenant) {
                // handle メソッド内の検索ロジックを流用
                $synonymServiceConfig = new SynonymServiceConfig(['useSynonym' => true, 'useTechnicalTerm' => true]);
                $synonymService = new SynonymService($synonymServiceConfig);
                $searchContext = new SearchContext($synonymService);
                $searchContext->setSearch($query);

                // LedgerDefine の title で検索
                $ledgerDefines = \App\Models\LedgerDefine::query()
                    ->where('title', 'LIKE', '%' . $query . '%')
                    ->get();

                foreach ($ledgerDefines as $ledgerDefine) {
                    // 関連する Ledger を取得
                    $ledgers = $ledgerDefine->ledgers;
                    foreach ($ledgers as $ledger) {
                        $allResults->push([
                            'tenant_id' => $tenant->id, // 現在のテナントID
                            'tenant_name' => $tenant->name, // 現在のテナント名
                            'ledger_id' => $ledger->id,
                            'ledger_title' => $ledgerDefine->title, // LedgerDefine の title を使用
                            'url' => tenant_route($tenant->id, 'ledger.show', ['ledgerId' => $ledger->id, 'highlight' => $query]),
                        ]);
                    }
                }
            });
        }

        if ($allResults->count() === 1) {
            // 1件のみ見つかった場合は、その台帳のURLにリダイレクト
            return redirect($allResults->first()['url']);
        } elseif ($allResults->count() > 1) {
            // 複数件見つかった場合は、結果一覧ビューを表示 (タスク3で作成)
            return view('ledger.lookup.results', ['results' => $allResults, 'query' => $query]);
        } else {
            // 0件の場合は、見つかりませんでしたビューを表示 (タスク3で作成)
            return view('ledger.lookup.no-results', ['query' => $query]);
        }
    }
}