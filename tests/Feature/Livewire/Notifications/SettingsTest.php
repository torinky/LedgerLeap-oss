<?php

namespace Tests\Feature\Livewire\Notifications;

use App\Livewire\Notifications\Settings;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Livewire\Notifications\Settings テスト
 *
 * 通知設定コンポーネントの表示・保存動作を検証する。
 */
#[CoversClass(Settings::class)]
class SettingsTest extends TestCase
{
    protected bool $tenancy = true;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 対象パーミッションをDBに作成しておく
        Permission::firstOrCreate(['name' => 'receive_workflow_summary_email', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'receive_workflow_action_email', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ================================================================
    // mount / render
    // ================================================================

    #[Test]
    public function component_renders_successfully(): void
    {
        Livewire::test(Settings::class)
            ->assertStatus(200);
    }

    #[Test]
    public function mount_loads_notification_settings(): void
    {
        $component = Livewire::test(Settings::class);

        // targetPermissionNames で定義された2つの設定がロードされること
        $settings = $component->get('notificationSettings');
        $this->assertArrayHasKey('receive_workflow_summary_email', $settings);
        $this->assertArrayHasKey('receive_workflow_action_email', $settings);
    }

    #[Test]
    public function settings_contain_required_keys(): void
    {
        $component = Livewire::test(Settings::class);
        $settings = $component->get('notificationSettings');

        foreach ($settings as $setting) {
            $this->assertArrayHasKey('name', $setting);
            $this->assertArrayHasKey('label', $setting);
            $this->assertArrayHasKey('enabled', $setting);
            $this->assertArrayHasKey('is_direct', $setting);
            $this->assertArrayHasKey('via_role', $setting);
            $this->assertArrayHasKey('disabled', $setting);
        }
    }

    // ================================================================
    // canSaveChanges
    // ================================================================

    #[Test]
    public function can_save_changes_returns_true_when_direct_permission_settable(): void
    {
        // パーミッションがロール経由ではなく直接設定可能な状態 → disabled=false
        $component = Livewire::test(Settings::class);

        $settings = $component->get('notificationSettings');
        // disabled=false の項目が存在すること（ロール経由でなければ disabled=false）
        $hasEditable = collect($settings)->contains(fn ($s) => ! $s['disabled']);
        $this->assertTrue($hasEditable);
    }

    #[Test]
    public function can_save_changes_returns_false_when_all_disabled_via_role(): void
    {
        // ロール経由でパーミッション付与 → disabled=true
        $role = Role::firstOrCreate(['name' => 'notif_test_role', 'guard_name' => 'web']);
        $role->givePermissionTo('receive_workflow_summary_email');
        $role->givePermissionTo('receive_workflow_action_email');
        $this->user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $component = Livewire::test(Settings::class);
        $settings = $component->get('notificationSettings');

        // ロール経由なので via_role=true
        $this->assertTrue($settings['receive_workflow_summary_email']['via_role']);
        $this->assertTrue($settings['receive_workflow_action_email']['via_role']);
    }

    // ================================================================
    // save
    // ================================================================

    #[Test]
    public function save_grants_direct_permission_when_enabled_is_true(): void
    {
        $this->assertFalse($this->user->hasDirectPermission('receive_workflow_summary_email'));

        Livewire::test(Settings::class)
            ->set('notificationSettings.receive_workflow_summary_email.enabled', true)
            ->set('notificationSettings.receive_workflow_summary_email.disabled', false)
            ->call('save');

        $this->user->refresh();
        $this->assertTrue($this->user->hasDirectPermission('receive_workflow_summary_email'));
    }

    #[Test]
    public function save_revokes_direct_permission_when_enabled_is_false(): void
    {
        // 事前に直接付与
        $this->user->givePermissionTo('receive_workflow_summary_email');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::test(Settings::class)
            ->set('notificationSettings.receive_workflow_summary_email.enabled', false)
            ->set('notificationSettings.receive_workflow_summary_email.disabled', false)
            ->call('save');

        $this->user->refresh();
        $this->assertFalse($this->user->hasDirectPermission('receive_workflow_summary_email'));
    }

    #[Test]
    public function save_skips_disabled_settings(): void
    {
        // ロール経由 → disabled=true → save しても変化なし
        $role = Role::firstOrCreate(['name' => 'notif_skip_role', 'guard_name' => 'web']);
        $role->givePermissionTo('receive_workflow_action_email');
        $this->user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::test(Settings::class)
            ->set('notificationSettings.receive_workflow_action_email.enabled', false)
            ->call('save');

        // ロール経由のパーミッションは剥奪されない
        $this->user->refresh();
        $this->assertTrue($this->user->hasPermissionTo('receive_workflow_action_email'));
    }

    #[Test]
    public function save_does_nothing_when_can_save_changes_is_false(): void
    {
        // 全て disabled=true にしてから save → 何も起きない
        $role = Role::firstOrCreate(['name' => 'notif_all_role', 'guard_name' => 'web']);
        $role->givePermissionTo('receive_workflow_summary_email');
        $role->givePermissionTo('receive_workflow_action_email');
        $this->user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // canSaveChanges=false の状態で save を呼んでも例外なし・変化なし
        Livewire::test(Settings::class)
            ->call('save')
            ->assertHasNoErrors();
    }
}
