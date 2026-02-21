<?php

namespace Tests\Feature\Models;

use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\NotificationType;
use App\Models\Role;
use App\Models\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(Tag::class)]
#[CoversClass(NotificationType::class)]
class SmallModelsTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
    }

    // ================================================================
    // Tag
    // ================================================================

    public function test_tag_belongs_to_ledger_define(): void
    {
        $define = LedgerDefine::factory()->create();
        $tag = Tag::factory()->create(['ledger_define_id' => $define->id]);

        $this->assertInstanceOf(LedgerDefine::class, $tag->ledgerDefine);
        $this->assertEquals($define->id, $tag->ledgerDefine->id);
    }

    public function test_tag_define_relation_returns_ledger_when_exists(): void
    {
        // define() は ledger_define_id を外部キーとして Ledger を返す
        $define = LedgerDefine::factory()->create();
        $ledger = \App\Models\Ledger::factory()->create(['ledger_define_id' => $define->id]);
        $tag = Tag::factory()->create(['ledger_define_id' => $define->id]);

        // define() は Ledger::hasOne で ledger_define_id をキーに返す
        // Tag 作成後に Ledger が存在すれば non-null になる
        $this->assertNotNull($tag->define);
    }

    public function test_tag_roles_relation_returns_collection(): void
    {
        $define = LedgerDefine::factory()->create();
        $tag = Tag::factory()->create(['ledger_define_id' => $define->id]);
        $role = Role::create(['name' => 'tag-role-'.uniqid()]);
        $tag->roles()->attach($role->id);

        $this->assertTrue($tag->roles->contains('id', $role->id));
    }

    // ================================================================
    // NotificationType
    // ================================================================

    public function test_notification_type_is_for_model_returns_true_for_matching_class(): void
    {
        $nt = new NotificationType;
        $nt->model = \App\Models\Ledger::class;

        $ledger = \App\Models\Ledger::factory()->make();
        $this->assertTrue($nt->isForModel($ledger));
    }

    public function test_notification_type_is_for_model_returns_false_for_different_class(): void
    {
        $nt = new NotificationType;
        $nt->model = \App\Models\Ledger::class;

        $folder = Folder::factory()->make();
        $this->assertFalse($nt->isForModel($folder));
    }

    public function test_notification_type_can_be_saved_with_valid_fields(): void
    {
        // folder_id は fillable 外なので DB::statement で直接テスト
        $nt = new NotificationType;
        $nt->name = 'test_event_'.uniqid();
        $nt->model = \App\Models\Ledger::class;
        $nt->event = 'created';
        $nt->save();

        $this->assertNotNull($nt->id);
        $this->assertDatabaseHas('notification_types', ['id' => $nt->id]);
    }
}
