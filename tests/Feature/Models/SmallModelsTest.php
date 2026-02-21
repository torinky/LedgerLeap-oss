<?php

namespace Tests\Feature\Models;

use App\Models\AutoLink;
use App\Models\AutoLinkScope;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerChunk;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\NotificationType;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\RoleTag;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserOrganization;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(Tag::class)]
#[CoversClass(NotificationType::class)]
#[CoversClass(LedgerChunk::class)]
#[CoversClass(RoleTag::class)]
#[CoversClass(UserOrganization::class)]
#[CoversClass(LedgerDiff::class)]
#[CoversClass(AutoLink::class)]
#[CoversClass(AutoLinkScope::class)]
#[CoversClass(Permission::class)]
#[CoversClass(RoleFolderPermission::class)]
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

    public function test_tag_belongs_to_folder(): void
    {
        // Sprint 5 修正: hasOne(Folder,'folder_id') → belongsTo(Folder,'folder_id')
        $define = LedgerDefine::factory()->create();
        $tag = Tag::factory()->create(['ledger_define_id' => $define->id]);

        $this->assertInstanceOf(Folder::class, $tag->folder);
    }

    public function test_tag_define_relation_returns_ledger_when_exists(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        $tag = Tag::factory()->create(['ledger_define_id' => $define->id]);

        $this->assertNotNull($tag->define);
        $this->assertEquals($ledger->id, $tag->define->id);
    }

    public function test_tag_roles_relation_returns_collection(): void
    {
        $define = LedgerDefine::factory()->create();
        $tag = Tag::factory()->create(['ledger_define_id' => $define->id]);
        $role = Role::create(['name' => 'tag-role-'.uniqid()]);
        $tag->roles()->attach($role->id);

        $this->assertTrue($tag->roles->contains('id', $role->id));
    }

    public function test_tag_creator_belongs_to_user(): void
    {
        $define = LedgerDefine::factory()->create();
        $tag = Tag::factory()->create(['ledger_define_id' => $define->id]);

        $this->assertInstanceOf(User::class, $tag->creator);
    }

    // ================================================================
    // NotificationType
    // ================================================================

    public function test_notification_type_is_for_model_returns_true(): void
    {
        $nt = new NotificationType;
        $nt->model = Ledger::class;

        $this->assertTrue($nt->isForModel(Ledger::factory()->make()));
    }

    public function test_notification_type_is_for_model_returns_false(): void
    {
        $nt = new NotificationType;
        $nt->model = Ledger::class;

        $this->assertFalse($nt->isForModel(Folder::factory()->make()));
    }

    public function test_notification_type_folder_returns_null_when_no_folder_relation(): void
    {
        $nt = new NotificationType;
        // folder_relation が未設定なら null を返す
        $this->assertNull($nt->folder());
    }

    public function test_notification_type_can_be_saved(): void
    {
        $nt = new NotificationType;
        $nt->name = 'test_event_'.uniqid();
        $nt->model = Ledger::class;
        $nt->event = 'created';
        $nt->save();

        $this->assertNotNull($nt->id);
        $this->assertDatabaseHas('notification_types', ['id' => $nt->id]);
    }

    // ================================================================
    // LedgerChunk
    // ================================================================

    public function test_ledger_chunk_belongs_to_ledger(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);

        $chunk = LedgerChunk::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $define->id,
            'folder_id' => $define->folder_id,
            'chunk_index' => 0,
            'content' => 'test chunk content',
            'chunk_text' => 'test chunk content',
            'tenant_id' => tenant()->id,
        ]);

        $this->assertInstanceOf(Ledger::class, $chunk->ledger);
        $this->assertEquals($ledger->id, $chunk->ledger->id);
    }

    public function test_ledger_chunk_belongs_to_ledger_define(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);

        $chunk = LedgerChunk::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $define->id,
            'folder_id' => $define->folder_id,
            'chunk_index' => 0,
            'content' => 'define relation test',
            'chunk_text' => 'define relation test',
            'tenant_id' => tenant()->id,
        ]);

        $this->assertInstanceOf(LedgerDefine::class, $chunk->ledgerDefine);
        $this->assertEquals($define->id, $chunk->ledgerDefine->id);
    }

    public function test_ledger_chunk_belongs_to_folder(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);

        $chunk = LedgerChunk::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $define->id,
            'folder_id' => $define->folder_id,
            'chunk_index' => 0,
            'content' => 'folder relation test',
            'chunk_text' => 'folder relation test',
            'tenant_id' => tenant()->id,
        ]);

        $this->assertInstanceOf(Folder::class, $chunk->folder);
    }

    // ================================================================
    // RoleTag (Pivot)
    // ================================================================

    public function test_role_tag_belongs_to_role(): void
    {
        $define = LedgerDefine::factory()->create();
        $tag = Tag::factory()->create(['ledger_define_id' => $define->id]);
        $role = Role::create(['name' => 'rt-role-'.uniqid()]);
        $tag->roles()->attach($role->id);

        $roleTag = RoleTag::where('role_id', $role->id)->where('tag_id', $tag->id)->first();
        $this->assertNotNull($roleTag);
        $this->assertInstanceOf(Role::class, $roleTag->role);
    }

    public function test_role_tag_belongs_to_tag(): void
    {
        $define = LedgerDefine::factory()->create();
        $tag = Tag::factory()->create(['ledger_define_id' => $define->id]);
        $role = Role::create(['name' => 'rt-role2-'.uniqid()]);
        $tag->roles()->attach($role->id);

        $roleTag = RoleTag::where('role_id', $role->id)->where('tag_id', $tag->id)->first();
        $this->assertInstanceOf(Tag::class, $roleTag->tag);
    }

    // ================================================================
    // UserOrganization (Pivot)
    // ================================================================

    public function test_user_organization_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'uo-org-'.uniqid()]);
        $user->organizations()->attach($org->id);

        $uo = UserOrganization::where('user_id', $user->id)->where('organization_id', $org->id)->first();
        $this->assertNotNull($uo);
        $this->assertInstanceOf(User::class, $uo->user);
    }

    public function test_user_organization_belongs_to_organization(): void
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'uo-org2-'.uniqid()]);
        $user->organizations()->attach($org->id);

        $uo = UserOrganization::where('user_id', $user->id)->where('organization_id', $org->id)->first();
        $this->assertInstanceOf(Organization::class, $uo->organization);
    }

    // ================================================================
    // LedgerDiff
    // ================================================================

    public function test_ledger_diff_belongs_to_ledger(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        $diff = LedgerDiff::factory()->create(['ledger_id' => $ledger->id]);

        $this->assertInstanceOf(Ledger::class, $diff->ledger);
        $this->assertEquals($ledger->id, $diff->ledger->id);
    }

    public function test_ledger_diff_belongs_to_define(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        $diff = LedgerDiff::factory()->create(['ledger_id' => $ledger->id, 'ledger_define_id' => $define->id]);

        $this->assertInstanceOf(LedgerDefine::class, $diff->define);
    }

    public function test_ledger_diff_belongs_to_creator(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        $diff = LedgerDiff::factory()->create(['ledger_id' => $ledger->id]);

        $this->assertInstanceOf(User::class, $diff->creator);
    }

    public function test_ledger_diff_belongs_to_modifier(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        $diff = LedgerDiff::factory()->create(['ledger_id' => $ledger->id]);

        $this->assertInstanceOf(User::class, $diff->modifier);
    }

    public function test_ledger_diff_inspector_and_approver_relations(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        $user = User::factory()->create();
        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'inspector_id' => $user->id,
            'approver_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $diff->inspector);
        $this->assertInstanceOf(User::class, $diff->approver);
    }

    // ================================================================
    // AutoLink
    // ================================================================

    public function test_auto_link_can_create_instance(): void
    {
        $autoLink = new AutoLink;
        $autoLink->label = 'test';
        $autoLink->pattern = 'https://example.com';
        $autoLink->url_template = 'https://example.com/{0}';

        $this->assertEquals('test', $autoLink->label);
        $this->assertFalse($autoLink->is_enabled ?? false);
    }

    public function test_auto_link_scopes_relation_returns_has_many(): void
    {
        $relation = (new AutoLink)->scopes();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
    }

    public function test_auto_link_creator_relation_returns_belongs_to(): void
    {
        $autoLink = new AutoLink;
        $relation = $autoLink->creator();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }

    public function test_auto_link_modifier_relation_returns_belongs_to(): void
    {
        $autoLink = new AutoLink;
        $relation = $autoLink->modifier();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }

    public function test_auto_link_get_activitylog_options(): void
    {
        $autoLink = new AutoLink;
        $autoLink->label = 'テストリンク';
        $options = $autoLink->getActivitylogOptions();
        $this->assertNotNull($options);
    }

    public function test_auto_link_scopeable_relation(): void
    {
        $autoLink = new AutoLink;
        $relation = $autoLink->scopeable();
        $this->assertNotNull($relation);
    }

    public function test_auto_link_folders_relation(): void
    {
        $autoLink = new AutoLink;
        $relation = $autoLink->folders();
        $this->assertNotNull($relation);
    }

    // ================================================================
    // AutoLinkScope (MorphPivot)
    // ================================================================

    public function test_auto_link_scope_auto_link_relation(): void
    {
        $scope = new AutoLinkScope;
        $relation = $scope->autoLink();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }

    // ================================================================
    // Permission
    // ================================================================

    public function test_permission_get_description_for_event(): void
    {
        $perm = new Permission;
        $perm->name = 'test-permission';
        $perm->guard_name = 'web';

        // getDescriptionForEvent はイベント名の文字列を返す
        $desc = $perm->getDescriptionForEvent('created');
        $this->assertIsString($desc);
    }

    public function test_permission_get_activitylog_options(): void
    {
        $perm = new Permission;
        $perm->name = 'test-permission';
        $perm->guard_name = 'web';

        $options = $perm->getActivitylogOptions();
        $this->assertNotNull($options);
    }

    // ================================================================
    // RoleFolderPermission
    // ================================================================

    public function test_role_folder_permission_notification_type_relation(): void
    {
        $rfp = new RoleFolderPermission;
        $relation = $rfp->notificationType();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }

    public function test_role_folder_permission_folder_relation(): void
    {
        $rfp = new RoleFolderPermission;
        $relation = $rfp->folder();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }

    public function test_role_folder_permission_role_relation(): void
    {
        $rfp = new RoleFolderPermission;
        $relation = $rfp->role();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }

    public function test_role_folder_permission_get_description_for_event(): void
    {
        $rfp = new RoleFolderPermission;
        $desc = $rfp->getDescriptionForEvent('created');
        $this->assertIsString($desc);
    }

    public function test_role_folder_permission_get_activitylog_options(): void
    {
        $rfp = new RoleFolderPermission;
        $options = $rfp->getActivitylogOptions();
        $this->assertNotNull($options);
    }

    public function test_role_folder_permission_attribute_values_to_be_logged(): void
    {
        $rfp = new RoleFolderPermission;
        $rfp->forceFill(['role_id' => 1, 'folder_id' => 2, 'permission' => 'read']);
        $values = $rfp->attributeValuesToBeLogged();
        $this->assertIsArray($values);
    }
}
