<?php

namespace Tests\Unit\Services;

use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Services\ConfidentialityLevelService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfidentialityLevelServiceTest extends TestCase
{
    protected bool $tenancy = true;

    #[Test]
    public function resolve_returns_fallback_for_empty_model(): void
    {
        $ledgerDefine = new LedgerDefine;
        $result = ConfidentialityLevelService::resolve($ledgerDefine);

        $this->assertIsArray($result);
        $this->assertEquals('public', $result['level']);
        $this->assertNull($result['source']);
        $this->assertFalse($result['inherited']);
    }

    #[Test]
    public function resolve_returns_ledger_define_source_when_set_directly(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'confidentiality_level' => 'confidential',
            'confidentiality_scopes' => [],
        ]);

        $result = ConfidentialityLevelService::resolve($ledgerDefine);

        $this->assertEquals('confidential', $result['level']);
        $this->assertEquals('ledger_define', $result['source']['type']);
        $this->assertFalse($result['inherited']);
    }

    #[Test]
    public function resolve_inherits_from_parent_folder(): void
    {
        $parent = Folder::factory()->create([
            'confidentiality_level' => 'secret',
            'confidentiality_scopes' => [],
        ]);
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $parent->id,
            'confidentiality_level' => null,
        ]);

        $result = ConfidentialityLevelService::resolve($ledgerDefine);

        $this->assertEquals('secret', $result['level']);
        $this->assertEquals('folder', $result['source']['type']);
        $this->assertTrue($result['inherited']);
        $this->assertArrayHasKey('path', $result['source']);
        $this->assertEquals($parent->title, $result['source']['path']);
    }

    #[Test]
    public function resolve_builds_folder_path_for_deep_nesting(): void
    {
        $grandParent = Folder::factory()->create(['title' => 'GrandParent']);
        $parent = Folder::factory()->create([
            'title' => 'Parent',
            'parent_id' => $grandParent->id,
        ]);
        $folder = Folder::factory()->create([
            'title' => 'Child',
            'parent_id' => $parent->id,
            'confidentiality_level' => 'internal',
            'confidentiality_scopes' => [],
        ]);

        $result = ConfidentialityLevelService::resolve($folder);

        $this->assertEquals('internal', $result['level']);
        $this->assertEquals('folder', $result['source']['type']);
        $this->assertArrayHasKey('path', $result['source']);
        $this->assertStringContainsString('GrandParent', $result['source']['path']);
        $this->assertStringContainsString('Parent', $result['source']['path']);
        $this->assertStringContainsString('Child', $result['source']['path']);
        $this->assertStringContainsString(' > ', $result['source']['path']);
    }

    #[Test]
    public function get_effective_level_returns_source_path(): void
    {
        $parent = Folder::factory()->create([
            'title' => 'SecretFolder',
            'confidentiality_level' => 'secret',
        ]);
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $parent->id,
            'confidentiality_level' => null,
        ]);

        $effective = ConfidentialityLevelService::getEffectiveLevel($ledgerDefine);

        $this->assertEquals('secret', $effective['level']);
        $this->assertNotNull($effective['source_path']);
        $this->assertEquals('SecretFolder', $effective['source_path']);
    }

    #[Test]
    public function get_effective_level_returns_fallback_when_no_settings(): void
    {
        $folder = Folder::factory()->create([
            'confidentiality_level' => null,
        ]);

        $effective = ConfidentialityLevelService::getEffectiveLevel($folder);

        $this->assertEquals('public', $effective['level']);
        $this->assertNull($effective['source']);
        $this->assertNull($effective['source_path']);
        $this->assertFalse($effective['inherited']);
    }
}
