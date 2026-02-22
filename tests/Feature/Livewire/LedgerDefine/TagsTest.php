<?php

namespace Tests\Feature\Livewire\LedgerDefine;

use App\Livewire\LedgerDefine\Tags;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Livewire\LedgerDefine\Tags テスト
 *
 * タグの追加・削除動作を検証する。
 */
#[CoversClass(Tags::class)]
class TagsTest extends TestCase
{
    protected bool $tenancy = true;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $folder = Folder::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'folder_id' => $folder->id,
        ]);
    }

    // ================================================================
    // mount / render
    // ================================================================

    #[Test]
    public function component_renders_successfully(): void
    {
        Livewire::test(Tags::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->assertStatus(200);
    }

    #[Test]
    public function mount_loads_existing_tags(): void
    {
        Tag::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'name' => '既存タグ',
            'folder_id' => $this->ledgerDefine->folder_id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $component = Livewire::test(Tags::class, ['ledgerDefineId' => $this->ledgerDefine->id]);

        $tags = $component->get('tags');
        $this->assertCount(1, $tags);
    }

    #[Test]
    public function mount_accepts_tags_from_parent(): void
    {
        $existingTags = Tag::factory()->count(2)->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'folder_id' => $this->ledgerDefine->folder_id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        // 親から tags を渡した場合はクエリ不要
        $component = Livewire::test(Tags::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
            'tags' => $existingTags,
        ]);

        $this->assertCount(2, $component->get('tags'));
    }

    // ================================================================
    // addTag
    // ================================================================

    #[Test]
    public function add_tag_creates_new_tag_in_db(): void
    {
        Livewire::test(Tags::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('newTag', 'NewTag')
            ->call('addTag');

        $this->assertDatabaseHas('tags', [
            'ledger_define_id' => $this->ledgerDefine->id,
            'name' => 'NewTag',
        ]);
    }

    #[Test]
    public function add_tag_updates_tags_list(): void
    {
        $component = Livewire::test(Tags::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('newTag', 'TestTag')
            ->call('addTag');

        $tags = $component->get('tags');
        $this->assertCount(1, $tags);
    }

    #[Test]
    public function add_tag_resets_new_tag_input(): void
    {
        Livewire::test(Tags::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('newTag', 'ResetTest')
            ->call('addTag')
            ->assertSet('newTag', '');
    }

    #[Test]
    public function add_tag_does_nothing_when_empty(): void
    {
        Livewire::test(Tags::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('newTag', '')
            ->call('addTag');

        $this->assertDatabaseMissing('tags', ['ledger_define_id' => $this->ledgerDefine->id]);
    }

    #[Test]
    public function add_tag_normalizes_full_width_characters(): void
    {
        // 全角スペース・全角英数を正規化
        Livewire::test(Tags::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('newTag', 'Ａｂｃ') // 全角英字
            ->call('addTag');

        // mb_convert_kana('askV') で半角に変換されること
        $this->assertDatabaseHas('tags', [
            'ledger_define_id' => $this->ledgerDefine->id,
            'name' => 'Abc',
        ]);
    }

    // ================================================================
    // removeTag
    // ================================================================

    #[Test]
    public function remove_tag_deletes_tag_from_db(): void
    {
        $tag = Tag::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'folder_id' => $this->ledgerDefine->folder_id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        Livewire::test(Tags::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->call('removeTag', $tag->id);

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    #[Test]
    public function remove_tag_updates_tags_list(): void
    {
        $tag = Tag::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'folder_id' => $this->ledgerDefine->folder_id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $component = Livewire::test(Tags::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->call('removeTag', $tag->id);

        $this->assertCount(0, $component->get('tags'));
    }
}
