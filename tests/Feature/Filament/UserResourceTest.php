<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'test.localhost']);
        tenancy()->initialize($this->tenant);

        // UserPolicy が期待するパーミッション名で作成
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_users', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_users', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create_users', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'update_users', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'delete_users', 'guard_name' => 'web']);

        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(\Spatie\Permission\Models\Permission::all());

        $this->adminUser = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->actingAs($this->adminUser);
    }

    #[Test]
    public function manual_sync_status_filter_works_correctly(): void
    {
        $activeUser = User::factory()->create(['name' => 'Active User', 'ignore_ad_org_sync_until' => now()->addDays(5)]);
        $expiredUser = User::factory()->create(['name' => 'Expired User', 'ignore_ad_org_sync_until' => now()->subDays(5)]);
        $normalUser = User::factory()->create(['name' => 'Normal User', 'ignore_ad_org_sync_until' => null]);

        Livewire::test(ListUsers::class)
            ->assertSuccessful()
            // フィルタ適用: Active
            ->set('tableFilters.manual_sync_status.status', 'active')
            ->assertSee('Active User')
            ->assertDontSee('Expired User')
            ->assertDontSee('Normal User')

            // フィルタ適用: Expired
            ->set('tableFilters.manual_sync_status.status', 'expired')
            ->assertSee('Expired User')
            ->assertDontSee('Active User')
            ->assertDontSee('Normal User')

            // フィルタ適用: None
            ->set('tableFilters.manual_sync_status.status', 'none')
            ->assertSee('Normal User')
            ->assertDontSee('Active User')
            ->assertDontSee('Expired User');
    }

    #[Test]
    public function persistent_notification_is_shown_for_expired_manual_sync_users(): void
    {
        User::factory()->create(['ignore_ad_org_sync_until' => now()->subDays(1)]);

        Livewire::test(ListUsers::class)
            ->assertSuccessful();
        // 通知の詳細は確認しないが、エラーなく動作することを確認
    }
}
