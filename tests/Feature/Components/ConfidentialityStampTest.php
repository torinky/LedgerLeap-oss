<?php

namespace Tests\Feature\Components;

use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Services\ConfidentialityLevelService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfidentialityStampTest extends TestCase
{
    protected bool $tenancy = true;

    #[Test]
    public function it_renders_with_direct_ledger_define_source(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'confidentiality_level' => 'confidential',
            'confidentiality_scopes' => [],
        ]);

        $effective = ConfidentialityLevelService::getEffectiveLevel($ledgerDefine);

        $view = $this->blade(
            '<x-ledger.confidentiality-stamp :level="$level" :label="$label" :scopes="$scopes" :source-type="$sourceType" :source-name="$sourceName" :source-id="$sourceId" :inherited="$inherited" />',
            [
                'level' => $effective['level'],
                'label' => $effective['label'],
                'scopes' => $effective['scope_labels'],
                'sourceType' => $effective['source']['type'] ?? null,
                'sourceName' => $effective['source']['name'] ?? null,
                'sourceId' => $effective['source']['id'] ?? null,
                'inherited' => $effective['inherited'],
            ]
        );

        $view->assertSee('社外秘');
        $view->assertSee('設定元：');
    }

    #[Test]
    public function it_renders_with_folder_source_path(): void
    {
        $grandParent = Folder::factory()->create(['title' => 'GrandParent']);
        $parent = Folder::factory()->create(['title' => 'Parent', 'parent_id' => $grandParent->id]);
        $folder = Folder::factory()->create([
            'title' => 'Child',
            'parent_id' => $parent->id,
            'confidentiality_level' => 'secret',
        ]);

        $effective = ConfidentialityLevelService::getEffectiveLevel($folder);

        $view = $this->blade(
            '<x-ledger.confidentiality-stamp :level="$level" :label="$label" :scopes="$scopes" :source-type="$sourceType" :source-name="$sourceName" :source-id="$sourceId" :source-path="$sourcePath" :inherited="$inherited" />',
            [
                'level' => $effective['level'],
                'label' => $effective['label'],
                'scopes' => $effective['scope_labels'],
                'sourceType' => $effective['source']['type'] ?? null,
                'sourceName' => $effective['source']['name'] ?? null,
                'sourceId' => $effective['source']['id'] ?? null,
                'sourcePath' => $effective['source_path'],
                'inherited' => $effective['inherited'],
            ]
        );

        $view->assertSee('極秘');
        $view->assertSee('設定元：');
        $view->assertSee('GrandParent');
        $view->assertSee('Parent');
        $view->assertSee('Child');
    }

    #[Test]
    public function it_renders_with_inherited_source(): void
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

        $view = $this->blade(
            '<x-ledger.confidentiality-stamp :level="$level" :label="$label" :scopes="$scopes" :source-type="$sourceType" :source-name="$sourceName" :source-id="$sourceId" :source-path="$sourcePath" :inherited="$inherited" />',
            [
                'level' => $effective['level'],
                'label' => $effective['label'],
                'scopes' => $effective['scope_labels'],
                'sourceType' => $effective['source']['type'] ?? null,
                'sourceName' => $effective['source']['name'] ?? null,
                'sourceId' => $effective['source']['id'] ?? null,
                'sourcePath' => $effective['source_path'],
                'inherited' => $effective['inherited'],
            ]
        );

        $view->assertSee('極秘');
        $view->assertSee('継承元：');
        $view->assertSee('SecretFolder');
    }

    #[Test]
    public function it_renders_with_scopes(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'confidentiality_level' => 'internal',
            'confidentiality_scopes' => [
                'org_ids' => [['id' => 1, 'name' => '人事部']],
                'role_ids' => [],
            ],
        ]);

        $effective = ConfidentialityLevelService::getEffectiveLevel($ledgerDefine);

        $view = $this->blade(
            '<x-ledger.confidentiality-stamp :level="$level" :label="$label" :scopes="$scopes" />',
            [
                'level' => $effective['level'],
                'label' => $effective['label'],
                'scopes' => $effective['scope_labels'],
            ]
        );

        $view->assertSee('社内限定');
        $view->assertSee('人事部');
    }

    #[Test]
    public function it_does_not_render_when_level_is_null_and_no_label(): void
    {
        $view = $this->blade(
            '<x-ledger.confidentiality-stamp :level="null" />'
        );

        $view->assertDontSee('極秘');
        $view->assertDontSee('社外秘');
        $view->assertDontSee('社内限定');
    }
}
