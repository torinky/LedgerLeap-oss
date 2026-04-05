<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\GetRelatedLedgersTool;
use App\Mcp\Tools\GetSearchTermsTool;
use App\Mcp\Tools\SearchLedgersTool;
use App\Services\Ledger\RelatedLedgerService;
use App\Services\LedgerService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class McpToolNameConventionTest extends TestCase
{
    #[Test]
    public function searchLedgersToolUsesTheDefaultKebabCasePublicName(): void
    {
        $tool = new SearchLedgersTool(Mockery::mock(LedgerService::class));

        $this->assertSame('search-ledgers-tool', $tool->toArray()['name']);
    }

    #[Test]
    public function getRelatedLedgersToolUsesTheDefaultKebabCasePublicName(): void
    {
        $tool = new GetRelatedLedgersTool(Mockery::mock(RelatedLedgerService::class));

        $this->assertSame('get-related-ledgers-tool', $tool->toArray()['name']);
    }

    #[Test]
    public function getSearchTermsToolUsesTheDefaultKebabCasePublicName(): void
    {
        $tool = new GetSearchTermsTool(Mockery::mock(\App\Services\SynonymService::class));

        $this->assertSame('get-search-terms-tool', $tool->toArray()['name']);
    }
}
