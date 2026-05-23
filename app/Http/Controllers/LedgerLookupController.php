<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\Tenant;
use App\Services\Config\SynonymServiceConfig;
use App\Services\Ledger\LedgerShareUrlService;
use App\Services\Ledger\SearchContext;
use App\Services\SynonymService;
use Illuminate\Http\Request;

class LedgerLookupController extends Controller
{
    public function handle(Request $request, string $query)
    {
        if (empty($query)) {
            $url = app(LedgerShareUrlService::class)->buildAbsoluteRouteUrl(
                'ledger.index',
                ['tenant' => tenant()->id]
            );

            return redirect($url);
        }

        // Create a search context to find the ledger
        $synonymServiceConfig = new SynonymServiceConfig(['useSynonym' => true, 'useTechnicalTerm' => true]);
        $synonymService = new SynonymService($synonymServiceConfig);
        $searchContext = new SearchContext($synonymService);
        $searchContext->setSearch($query);

        $results = Ledger::query()->searchContext($searchContext)->get();

        // Force list mode
        if ($request->input('mode') === 'list') {
            $url = app(LedgerShareUrlService::class)->buildAbsoluteRouteUrl(
                'ledger.index',
                ['tenant' => tenant()->id],
                ['q' => $query]
            );

            return redirect($url);
        }

        // Unique match, redirect to show page
        if ($results->count() === 1) {
            $url = app(LedgerShareUrlService::class)->buildAbsoluteRouteUrl(
                'ledger.show',
                ['tenant' => tenant()->id, 'ledgerId' => $results->first()->id],
                ['highlight' => $query]
            );

            return redirect($url);
        }

        // 0 or multiple matches, redirect to index page
        $url = app(LedgerShareUrlService::class)->buildAbsoluteRouteUrl(
            'ledger.index',
            ['tenant' => tenant()->id],
            ['q' => $query]
        );

        return redirect($url);
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
                $ledgers = Ledger::query()->search($query)->with('define')->get();

                foreach ($ledgers as $ledger) {
                    // defineリレーションがロードされているか確認
                    if ($ledger->define) {
                        // content 配列内にクエリと完全一致する値があるか再検証
                        $contentValues = is_array($ledger->content) ? array_values($ledger->content) : [];
                        if (in_array($query, $contentValues, true)) {
                            // パスベーステナントの場合、正しいURLを生成
                            // tenant_route()は誤ったホスト名を生成するため、直接URLを構築
                            $url = app(LedgerShareUrlService::class)->buildAbsoluteRouteUrl(
                                'ledger.show',
                                ['tenant' => $tenant->id, 'ledgerId' => $ledger->id],
                                ['highlight' => $query]
                            );

                            $allResults->push([
                                'tenant_id' => $tenant->id,
                                'tenant_name' => $tenant->name,
                                'ledger_id' => $ledger->id,
                                'ledger_title' => $ledger->define->title,
                                'url' => $url,
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
