<?php

namespace Tests\Feature\Services;

use App\Models\Synonym\TechnicalTermGroup;
use App\Services\Config\SynonymServiceConfig;
use App\Services\SynonymService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\DatabaseMigrationsOnce;

class SynonymServiceSearchTermsTest extends TestCase
{
    use DatabaseMigrationsOnce;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabaseMigrationsOnce();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabaseMigrationsOnce();
        parent::tearDown();
    }

    #[Test]
    public function get_search_terms_from_word_returns_technical_terms(): void
    {
        TechnicalTermGroup::create([
            'synonyms' => ['請求', 'インボイス'],
            'creator_id' => 1,
            'modifier_id' => 1,
        ]);

        $service = new SynonymService(new SynonymServiceConfig([
            'useSynonym' => false,
            'useTechnicalTerm' => true,
        ]));

        $terms = $service->getSearchTermsFromWord('請求');

        $this->assertContains(['term' => '請求', 'kind' => 'original'], $terms);
        $this->assertContains(['term' => 'インボイス', 'kind' => 'technical'], $terms);
        $this->assertNotContains(['term' => '請求書', 'kind' => 'synonym'], $terms);
    }
}
