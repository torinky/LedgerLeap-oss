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

    public function searchAllTenants(?string $query = null)
    {
        if (empty($query)) {
            return redirect()->route('global.my-portal');
        }

        $allResults = collect();
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($query, &$allResults, $tenant) {
                // 全文検索を実行
                $ledgers = \App\Models\Ledger::query()->search($query)->with('define')->get();

                foreach ($ledgers as $ledger) {
                    // defineリレーションがロードされているか確認
                    if ($ledger->define) {
                        // content 配列内にクエリと完全一致する値があるか再検証
                        $contentValues = is_array($ledger->content) ? array_values($ledger->content) : [];
                        if (in_array($query, $contentValues, true)) {
                            $allResults->push([
                                'tenant_id' => $tenant->id,
                                'tenant_name' => $tenant->name,
                                'ledger_id' => $ledger->id,
                                'ledger_title' => $ledger->define->title,
                                'url' => tenant_route($tenant->id, 'ledger.show', ['tenant' => $tenant->id, 'ledgerId' => $ledger->id, 'highlight' => $query]),
                            ]);
                        }
                    }
                }
            });
        }

        if ($allResults->count() === 1) {
            return redirect($allResults->first()['url']);
        } elseif ($allResults->count() > 1) {
            return view('ledger.lookup.results', ['results' => $allResults, 'query' => $query]);
        } else {
            return view('ledger.lookup.no-results', ['query' => $query]);
        }
    }
}
