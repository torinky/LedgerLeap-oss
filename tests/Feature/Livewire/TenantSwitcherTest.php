<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TenantSwitcher;
use App\Models\Folder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantSwitcherTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->withoutVite(); // このテストクラス全体でViteを無効化
    }

    #[Test]
    public function component_is_not_visible_to_guests(): void
    {
        $tenant = Tenant::create(['id' => 'dummy']);
        $this->get(route('my-portal', ['tenant' => $tenant->id]))
            ->assertDontSeeLivewire('tenant-switcher');
    }

    #[Test]
    public function component_is_visible_to_authenticated_users(): void
    {
        $tenant = Tenant::create(['id' => 'test-tenant']);
        $this->actingAs($this->user);

        tenancy()->initialize($tenant);

        $this->get(route('my-portal', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee(__('ledger.navigation.my_tenants'));
    }

    #[Test]
    public function it_shows_all_tenants_and_distinguishes_membership(): void
    {
        $tenantA = Tenant::create(['id' => 'tenant-a']);
        $tenantB = Tenant::create(['id' => 'tenant-b']);
        $tenantC = Tenant::create(['id' => 'tenant-c']);

        $this->user->tenants()->attach([$tenantA->id, $tenantB->id]);

        $this->actingAs($this->user);
        tenancy()->initialize($tenantA);

        Livewire::test(TenantSwitcher::class)
            ->assertSee($tenantA->name)
            ->assertSee($tenantB->name)
            ->assertSee($tenantC->name)
            ->assertSeeHtml('<li class="disabled">')
            ->assertSeeHtmlInOrder([__('ledger.navigation.my_tenants'), $tenantA->name, $tenantB->name, __('ledger.navigation.other_tenants'), $tenantC->name]);
    }

    #[Test]
    public function it_shows_folder_hierarchy_for_member_tenants(): void
    {
        $tenant = Tenant::create(['id' => 'member-tenant']);
        $this->user->tenants()->attach($tenant);

        $this->actingAs($this->user);
        tenancy()->initialize($tenant);

        $parentFolder = Folder::create(['title' => 'Parent Folder', 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);
        $childFolder = Folder::create(['title' => 'Child Folder', 'parent_id' => $parentFolder->id, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);

        Livewire::test(TenantSwitcher::class)
            ->assertSee($parentFolder->title)
            ->assertSee($childFolder->title);
    }

    #[Test]
    public function links_are_generated_correctly(): void
    {
        $tenant = Tenant::create(['id' => 'link-tenant']);
        $this->user->tenants()->attach($tenant);

        $this->actingAs($this->user);
        tenancy()->initialize($tenant);

        $folder = Folder::create(['title' => 'Test Folder', 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);

        Livewire::test(TenantSwitcher::class)
            ->assertSee(route('my-portal', ['tenant' => $tenant->id]))
            ->assertSee(route('ledgersByFolderId', ['tenant' => $tenant->id, 'folderId' => $folder->id]));
    }
}
