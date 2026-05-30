<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AdminAnnouncementResource;
use App\Filament\Resources\AdminAnnouncementResource\Pages\CreateAdminAnnouncement;
use App\Filament\Resources\AdminAnnouncementResource\Pages\EditAdminAnnouncement;
use App\Filament\Resources\AdminAnnouncementResource\Pages\ListAdminAnnouncements;
use App\Filament\Widgets\DashboardLinksWidget;
use App\Models\AdminAnnouncement;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[CoversClass(AdminAnnouncementResource::class)]
class AdminAnnouncementResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-04-28 12:00:00');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'announcement-banner-test.localhost']);
        tenancy()->initialize($this->tenant);

        /** @var Role $adminRole */
        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN, 'guard_name' => 'web']);
        $this->seedAdminAnnouncementPermissions($adminRole);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);
        $this->actingAs($this->adminUser);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function seedAdminAnnouncementPermissions(Role $role): void
    {
        $permissions = collect([
            'create_admin_announcements' => '管理者お知らせを作成できる',
            'update_admin_announcements' => '管理者お知らせを更新できる',
            'delete_admin_announcements' => '管理者お知らせを削除できる',
        ])->map(function (string $description, string $name): Permission {
            /** @var Permission $permission */
            $permission = Permission::updateOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'description' => $description,
            ]);

            return $permission;
        });

        $role->givePermissionTo($permissions->all());
    }

    private function loginAsRoleWithAnnouncementPermissions(string $roleName, array $permissionNames): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect($permissionNames)->map(function (string $name): Permission {
            /** @var Permission $permission */
            $permission = Permission::updateOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'description' => match ($name) {
                    'create_admin_announcements' => '管理者お知らせを作成できる',
                    'update_admin_announcements' => '管理者お知らせを更新できる',
                    'delete_admin_announcements' => '管理者お知らせを削除できる',
                    default => $name,
                },
            ]);

            return $permission;
        });

        /** @var Role $role */
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($permissions->all());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
    }

    private function makePermissionCheckAnnouncement(): AdminAnnouncement
    {
        return AdminAnnouncement::query()->forceCreate([
            'title' => '権限確認用のお知らせ',
            'body' => '権限ごとの挙動確認用です。',
            'level' => 'warning',
            'status' => 'draft',
            'scope' => ['current_tenant'],
            'sticky' => false,
            'priority' => 1,
            'starts_at' => '2026-04-28 12:00:00',
            'ends_at' => '2026-04-28 13:00:00',
        ]);
    }

    #[Test]
    public function resource_list_page_renders_successfully(): void
    {
        $this->get(AdminAnnouncementResource::getUrl('index'))->assertSuccessful();
    }

    #[Test]
    public function create_only_role_can_access_create_entry_point_and_cannot_edit_or_delete(): void
    {
        $announcement = $this->makePermissionCheckAnnouncement();
        $this->loginAsRoleWithAnnouncementPermissions('Announcement Creator', ['create_admin_announcements']);

        $this->assertTrue(AdminAnnouncementResource::canViewAny());
        $this->assertTrue(AdminAnnouncementResource::canCreate());
        $this->assertFalse(AdminAnnouncementResource::canEdit($announcement));
        $this->assertFalse(AdminAnnouncementResource::canDelete($announcement));
        $this->assertFalse(AdminAnnouncementResource::canDeleteAny());

        $this->get(AdminAnnouncementResource::getUrl('index'))->assertSuccessful();
        $this->get(AdminAnnouncementResource::getUrl('create'))->assertSuccessful();

        Livewire::test(ListAdminAnnouncements::class)
            ->assertActionVisible('create')
            ->assertTableActionHidden('edit', $announcement)
            ->assertTableActionHidden('delete', $announcement)
            ->assertTableBulkActionHidden('delete');
    }

    #[Test]
    public function update_only_role_can_access_edit_entry_point_and_cannot_create_or_delete(): void
    {
        $announcement = $this->makePermissionCheckAnnouncement();
        $this->loginAsRoleWithAnnouncementPermissions('Announcement Editor', ['update_admin_announcements']);

        $this->assertTrue(AdminAnnouncementResource::canViewAny());
        $this->assertFalse(AdminAnnouncementResource::canCreate());
        $this->assertTrue(AdminAnnouncementResource::canEdit($announcement));
        $this->assertFalse(AdminAnnouncementResource::canDelete($announcement));
        $this->assertFalse(AdminAnnouncementResource::canDeleteAny());

        $this->get(AdminAnnouncementResource::getUrl('index'))->assertSuccessful();
        $this->get(AdminAnnouncementResource::getUrl('edit', ['record' => $announcement]))->assertSuccessful();

        Livewire::test(ListAdminAnnouncements::class)
            ->assertActionHidden('create')
            ->assertTableActionVisible('edit', $announcement)
            ->assertTableActionHidden('delete', $announcement)
            ->assertTableBulkActionHidden('delete');
    }

    #[Test]
    public function delete_only_role_can_see_delete_actions_and_bulk_delete_but_not_create_or_edit(): void
    {
        $announcement = $this->makePermissionCheckAnnouncement();
        $this->loginAsRoleWithAnnouncementPermissions('Announcement Deleter', ['delete_admin_announcements']);

        $this->assertTrue(AdminAnnouncementResource::canViewAny());
        $this->assertFalse(AdminAnnouncementResource::canCreate());
        $this->assertFalse(AdminAnnouncementResource::canEdit($announcement));
        $this->assertTrue(AdminAnnouncementResource::canDelete($announcement));
        $this->assertTrue(AdminAnnouncementResource::canDeleteAny());

        $this->get(AdminAnnouncementResource::getUrl('index'))->assertSuccessful();

        Livewire::test(ListAdminAnnouncements::class)
            ->assertActionHidden('create')
            ->assertTableActionHidden('edit', $announcement)
            ->assertTableActionVisible('delete', $announcement)
            ->assertTableBulkActionVisible('delete');
    }

    #[Test]
    public function list_page_shows_existing_announcements(): void
    {
        $published = AdminAnnouncement::query()->forceCreate([
            'title' => '公開中のお知らせ',
            'body' => '公開中の本文です。',
            'level' => 'warning',
            'status' => 'published',
            'scope' => ['all_tenants'],
            'sticky' => true,
            'priority' => 10,
            'starts_at' => '2026-04-28 10:00:00',
            'links' => [
                ['label' => '詳細', 'url' => 'https://example.com/published'],
            ],
        ]);

        $draft = AdminAnnouncement::query()->forceCreate([
            'title' => '下書きのお知らせ',
            'body' => '下書きの本文です。',
            'level' => 'info',
            'status' => 'draft',
            'scope' => ['current_tenant'],
            'sticky' => false,
            'priority' => 0,
            'starts_at' => '2026-04-28 11:00:00',
        ]);

        Livewire::test(ListAdminAnnouncements::class)
            ->assertSeeHtmlInOrder([
                __('ledger.admin_announcement_banner_status_label'),
                __('ledger.admin_announcement_banner_field_title'),
                __('ledger.admin_announcement_banner_level_label'),
                __('ledger.admin_announcement_banner_publish_scope'),
                __('ledger.admin_announcement_banner_starts_at'),
                __('ledger.admin_announcement_banner_ends_at'),
                __('ledger.updated_at'),
                __('ledger.admin_announcement_banner_creator_label'),
                __('ledger.admin_announcement_banner_modifier_label'),
            ])
            ->assertCanSeeTableRecords([$published, $draft]);
    }

    #[Test]
    public function list_page_shows_display_status_labels(): void
    {
        AdminAnnouncement::query()->forceCreate([
            'title' => '公開中のお知らせ',
            'body' => '公開中の本文です。',
            'level' => 'warning',
            'status' => 'published',
            'scope' => ['all_tenants'],
            'sticky' => true,
            'priority' => 10,
            'starts_at' => '2026-04-28 10:00:00',
            'ends_at' => '2026-04-28 13:00:00',
        ]);

        AdminAnnouncement::query()->forceCreate([
            'title' => '公開予定のお知らせ',
            'body' => '公開予定の本文です。',
            'level' => 'info',
            'status' => 'published',
            'scope' => ['current_tenant'],
            'sticky' => false,
            'priority' => 20,
            'starts_at' => '2026-04-28 13:00:00',
            'ends_at' => '2026-04-28 14:00:00',
        ]);

        AdminAnnouncement::query()->forceCreate([
            'title' => '公開終了のお知らせ',
            'body' => '公開終了の本文です。',
            'level' => 'critical',
            'status' => 'published',
            'scope' => ['current_tenant'],
            'sticky' => true,
            'priority' => 30,
            'starts_at' => '2026-04-28 10:00:00',
            'ends_at' => '2026-04-28 11:00:00',
        ]);

        Livewire::test(ListAdminAnnouncements::class)
            ->assertSeeText(__('ledger.admin_announcement_banner_status_published'))
            ->assertSeeText(__('ledger.admin_announcement_banner_status_scheduled'))
            ->assertSeeText(__('ledger.admin_announcement_banner_status_ended'));
    }

    #[Test]
    public function list_page_shows_status_indicators_and_can_replicate_announcements(): void
    {
        $published = AdminAnnouncement::query()->forceCreate([
            'title' => '複製元のお知らせ',
            'body' => '複製したい本文です。',
            'level' => 'warning',
            'status' => 'published',
            'scope' => ['all_tenants'],
            'sticky' => true,
            'priority' => 15,
            'starts_at' => '2026-04-28 10:00:00',
            'ends_at' => '2026-04-29 10:00:00',
            'links' => [
                ['label' => '詳細', 'url' => 'https://example.com/source'],
            ],
        ]);

        Livewire::test(ListAdminAnnouncements::class)
            ->assertSeeText(__('ledger.admin_announcement_banner_status_published'))
            ->assertSeeHtml(__('ledger.admin_announcement_banner_status_published'));

        Livewire::test(ListAdminAnnouncements::class)
            ->callTableAction('replicate', $published);

        $this->assertSame(2, AdminAnnouncement::query()->count());
        $this->assertTrue(AdminAnnouncement::query()->where([
            'title' => '複製元のお知らせ',
            'body' => '複製したい本文です。',
            'level' => 'warning',
            'status' => 'draft',
            'sticky' => true,
            'priority' => 15,
        ])->exists());
        $this->assertTrue(AdminAnnouncement::query()->where([
            'title' => '複製元のお知らせ',
            'body' => '複製したい本文です。',
            'level' => 'warning',
            'status' => 'published',
        ])->exists());
    }

    #[Test]
    public function create_page_validates_required_fields(): void
    {
        Livewire::test(CreateAdminAnnouncement::class)
            ->fillForm([
                'title' => '',
                'body' => '',
                'level' => '',
                'scope' => [],
                'starts_at' => null,
                'ends_at' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'title' => 'required',
                'body' => 'required',
                'level' => 'required',
                'scope' => 'required',
                'starts_at' => 'required',
                'ends_at' => 'required',
            ]);
    }

    #[Test]
    public function create_page_validates_ends_at_is_after_starts_at(): void
    {
        Livewire::test(CreateAdminAnnouncement::class)
            ->fillForm([
                'title' => '期間確認',
                'body' => '期間の整合性を確認します。',
                'level' => 'warning',
                'scope' => ['current_tenant'],
                'sticky' => false,
                'priority' => 3,
                'starts_at' => '2026-04-28 13:00:00',
                'ends_at' => '2026-04-28 12:00:00',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'ends_at' => 'after_or_equal',
            ]);
    }

    #[Test]
    public function create_page_can_persist_draft_to_list(): void
    {
        Livewire::test(CreateAdminAnnouncement::class)
            ->fillForm([
                'title' => '新しい下書き',
                'body' => '保存される本文です。',
                'level' => 'warning',
                'scope' => ['current_tenant', 'all_tenants'],
                'sticky' => false,
                'priority' => 3,
                'starts_at' => '2026-04-28 12:00:00',
                'ends_at' => '2026-04-28 13:00:00',
                'cta_label' => '詳細を見る',
                'cta_url' => 'https://example.com/draft',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(AdminAnnouncement::query()->where([
            'title' => '新しい下書き',
            'status' => 'draft',
            'priority' => 3,
        ])->exists());

        $this->get(AdminAnnouncementResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSeeText('新しい下書き');
    }

    #[Test]
    public function edit_page_prefills_existing_values(): void
    {
        $announcement = AdminAnnouncement::query()->forceCreate([
            'title' => '編集対象',
            'body' => '編集対象の本文です。',
            'level' => 'critical',
            'status' => 'published',
            'scope' => ['all_tenants'],
            'sticky' => true,
            'priority' => 7,
            'starts_at' => '2026-04-28 14:00:00',
            'ends_at' => '2026-04-28 15:00:00',
            'links' => [
                ['label' => '詳細を見る', 'url' => 'https://example.com/edit'],
            ],
        ]);

        Livewire::test(EditAdminAnnouncement::class, ['record' => $announcement->getRouteKey()])
            ->assertFormSet([
                'title' => '編集対象',
                'body' => '編集対象の本文です。',
                'level' => 'critical',
                'status' => 'scheduled',
                'scope' => ['all_tenants'],
                'sticky' => true,
                'priority' => 7,
                'cta_label' => '詳細を見る',
                'cta_url' => 'https://example.com/edit',
            ]);
    }

    #[Test]
    public function edit_page_renders_preview_section(): void
    {
        $announcement = AdminAnnouncement::query()->forceCreate([
            'title' => 'プレビュー確認',
            'body' => 'プレビューの本文です。',
            'level' => 'warning',
            'status' => 'published',
            'scope' => ['current_tenant'],
            'sticky' => false,
            'priority' => 2,
            'starts_at' => '2026-04-28 16:00:00',
            'ends_at' => '2026-04-28 17:00:00',
            'links' => [
                ['label' => '詳細', 'url' => 'https://example.com/preview'],
            ],
        ]);

        Livewire::test(EditAdminAnnouncement::class, ['record' => $announcement->getRouteKey()])
            ->assertSeeText(__('ledger.admin_announcement_banner_preview_title'))
            ->assertSeeText(__('ledger.admin_announcement_banner_preview_hint'))
            ->assertSeeText(__('ledger.admin_announcement_banner_display_state_title'))
            ->assertSeeText(__('ledger.admin_announcement_banner_preview_reset'))
            ->assertSeeHtml('data-admin-announcement-banner');
    }

    #[Test]
    public function edit_page_can_save_updates(): void
    {
        $announcement = AdminAnnouncement::query()->forceCreate([
            'title' => '元タイトル',
            'body' => '元本文',
            'level' => 'info',
            'status' => 'draft',
            'scope' => ['current_tenant'],
            'starts_at' => '2026-04-28 12:00:00',
            'ends_at' => '2026-04-28 13:00:00',
        ]);

        Livewire::test(EditAdminAnnouncement::class, ['record' => $announcement->getRouteKey()])
            ->fillForm([
                'title' => '更新後タイトル',
                'body' => '更新後本文',
                'level' => 'warning',
                'scope' => ['all_tenants'],
                'sticky' => true,
                'priority' => 5,
                'starts_at' => '2026-04-28 12:00:00',
                'ends_at' => '2026-04-28 13:00:00',
                'cta_label' => '詳細',
                'cta_url' => 'https://example.com/update',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(AdminAnnouncement::query()->where([
            'id' => $announcement->id,
            'title' => '更新後タイトル',
            'status' => 'draft',
            'priority' => 5,
        ])->exists());
    }

    #[Test]
    public function dashboard_links_widget_includes_announcement_banner_link(): void
    {
        session()->put('filament_from_tenant_id', $this->tenant->id);

        $component = Livewire::test(DashboardLinksWidget::class);
        $instance = $component->instance();

        $ref = new \ReflectionMethod($instance, 'getGroups');
        $groups = $ref->invoke($instance);
        $contentsGroup = collect($groups)->firstWhere('title', __('ledger.settings.contents'));

        $this->assertNotNull($contentsGroup);
        $expectedTitle = __('ledger.admin_announcement_banner_title').' '.__('ledger.setting');
        $expectedUrl = AdminAnnouncementResource::getUrl('index').'?tenant='.$this->tenant->id;

        $this->assertContains($expectedTitle, array_column($contentsGroup['links'], 'title'));
        $this->assertContains($expectedUrl, array_column($contentsGroup['links'], 'url'));
    }
}
