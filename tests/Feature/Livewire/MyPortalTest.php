<?php

namespace Tests\Feature\Livewire;

use App\Livewire\MyPortal;
use App\Models\Folder;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class MyPortalTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = false;

    protected Tenant $tenant;

    private User $user;

    private Folder $rootFolder;

    private Folder $childFolder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->tenant = $this->getTenant();

        if ($this->tenant) {
            tenancy()->initialize($this->tenant);
        }

        $this->user = User::factory()->create([
            'pending_inspection_count' => 0,
            'pending_approval_count' => 0,
        ]);

        $this->tenant->run(function () {
            $this->rootFolder = Folder::factory()->create([
                'title' => 'Root',
                'parent_id' => null,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);

            $this->childFolder = Folder::factory()->create([
                'title' => 'Child',
                'parent_id' => $this->rootFolder->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);

            Folder::fixTree();
        });

        $this->actingAs($this->user);
    }

    #[Test]
    public function it_shows_a_clear_folder_to_ledger_list_handoff(): void
    {
        Livewire::test(MyPortal::class)
            ->assertStatus(200)
            ->assertSee(__('ledger.portal_folder_handoff_title'))
            ->assertSee(__('ledger.portal_folder_tree_hint'))
            ->assertSeeHtml('wire:ignore')
            ->assertDontSeeHtml('tooltip-right')
            ->assertSee(__('ledger.roles_and_affiliations_title'))
            ->assertSee(__('ledger.portal_roles_subtitle'))
            ->assertSee(__('ledger.portal_primary_organization_label'))
            ->assertSee(__('ledger.portal_primary_role_label'))
            ->assertSee(route('ledgersByFolderId', [
                'tenant' => $this->tenant->getTenantKey(),
                'folderId' => $this->childFolder->id,
            ]));
    }
}
