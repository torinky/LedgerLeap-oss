<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\GetRelatedLedgersTool;
use App\Mcp\Tools\GetSearchTermsTool;
use App\Mcp\Tools\SearchLedgersTool;
use App\Services\Ledger\RelatedLedgerService;
use App\Services\LedgerService;
use App\Services\SynonymService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class McpToolNameConventionTest extends TestCase
{
    #[Test]
    public function search_ledgers_tool_uses_the_default_kebab_case_public_name(): void
    {
        $tool = new SearchLedgersTool(Mockery::mock(LedgerService::class));

        $this->assertSame('search-ledgers-tool', $tool->toArray()['name']);
    }

    #[Test]
    public function get_related_ledgers_tool_uses_the_default_kebab_case_public_name(): void
    {
        $tool = new GetRelatedLedgersTool(Mockery::mock(RelatedLedgerService::class));

        $this->assertSame('get-related-ledgers-tool', $tool->toArray()['name']);
    }

    #[Test]
    public function get_search_terms_tool_uses_the_default_kebab_case_public_name(): void
    {
        $tool = new GetSearchTermsTool(Mockery::mock(SynonymService::class));

        $this->assertSame('get-search-terms-tool', $tool->toArray()['name']);
    }
}
