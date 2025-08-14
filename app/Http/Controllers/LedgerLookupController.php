<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Services\Config\SynonymServiceConfig;
use App\Services\Ledger\SearchContext;
use App\Services\SynonymService;
use Illuminate\Http\Request;

class LedgerLookupController extends Controller
{
    public function handle(Request $request, string $query)
    {
        if (empty($query)) {
            return redirect()->route('ledger.index');
        }

        // Create a search context to find the ledger
        $synonymServiceConfig = new SynonymServiceConfig(['useSynonym' => true, 'useTechnicalTerm' => true]);
        $synonymService = new SynonymService($synonymServiceConfig);
        $searchContext = new SearchContext($synonymService);
        $searchContext->setSearch($query);

        $results = Ledger::query()->searchContext($searchContext)->get();

        // Force list mode
        if ($request->input('mode') === 'list') {
            return redirect()->route('ledger.index', ['q' => $query, 'highlight' => $query, 'l' => [], 'f' => []]);
        }

        // Unique match, redirect to show page
        if ($results->count() === 1) {
            return redirect()->route('ledger.show', ['ledgerId' => $results->first()->id, 'highlight' => $query]);
        }

        // 0 or multiple matches, redirect to index page
        return redirect()->route('ledger.index', ['q' => $query, 'highlight' => $query, 'l' => [], 'f' => []]);
    }
}
