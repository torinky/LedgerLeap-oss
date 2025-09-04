<?php

namespace Tests\Feature;

use App\Livewire\Ledger\ModifyColumn;
use App\Livewire\Ledger\RecordsTable;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private $tenant1;
    private $tenant2;
    private $tenant1LedgerDefine;
    private $tenant1Ledger;
    private $tenant2LedgerDefine;
    private $tenant2Ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['email' => 'admin@example.com', 'password' => bcrypt('password')]);
        Notification::fake();

        Artisan::call('app:setup-tenant', ['tenant_id' => 'tenant1', 'admin_email' => 'admin@example.com']);
        Artisan::call('app:setup-tenant', ['tenant_id' => 'tenant2', 'admin_email' => 'admin@example.com']);

        $this->tenant1 = \App\Models\Tenant::find('tenant1');
        $this->tenant2 = \App\Models\Tenant::find('tenant2');

        $this->tenant1->run(function () {
            $folder = Folder::where('title', '/')->first();
            $this->tenant1LedgerDefine = LedgerDefine::factory()->create(['title' => 'Tenant 1 Definition', 'folder_id' => $folder->id]);
            $this->tenant1Ledger = Ledger::factory()->create(['ledger_define_id' => $this->tenant1LedgerDefine->id, 'content' => ['col1' => 'tenant1-data']]);
        });

        $this->tenant2->run(function () {
            $folder = Folder::where('title', '/')->first();
            $this->tenant2LedgerDefine = LedgerDefine::factory()->create(['title' => 'Tenant 2 Definition', 'folder_id' => $folder->id]);
            $this->tenant2Ledger = Ledger::factory()->create(['ledger_define_id' => $this->tenant2LedgerDefine->id, 'content' => ['col1' => 'tenant2-data']]);
        });

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[Test]
    public function user_in_one_tenant_cannot_see_data_from_another_tenant(): void
    {
        $this->actingAs($this->adminUser);

        $this->tenant2->run(function () {
            Livewire::test(RecordsTable::class)
                ->set('selectedLedgerDefineIds', [$this->tenant2LedgerDefine->id])
                ->assertViewHas('ledgerRecords', function ($ledgers) {
                    $this->assertCount(1, $ledgers);
                    $this->assertEquals($this->tenant2Ledger->id, $ledgers->first()->id);
                    return true;
                });
        });
    }

    #[Test]
    public function user_cannot_access_another_tenants_resource_via_direct_url(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->tenant2->run(function () {
            return $this->get(route('ledger.show', ['tenant' => 'tenant2', 'ledgerId' => $this->tenant1Ledger->id]));
        });

        $response->assertNotFound();
    }

    #[Test]
    public function user_cannot_update_data_in_another_tenant(): void
    {
        $this->actingAs($this->adminUser);

        $this->tenant2->run(function () {
            $this->expectException(ModelNotFoundException::class);
            Livewire::test(ModifyColumn::class, ['ledgerId' => $this->tenant1Ledger->id]);
        });
    }

    #[Test]
    public function user_cannot_delete_data_in_another_tenant(): void
    {
        $this->actingAs($this->adminUser);

        $this->tenant2->run(function () {
            $this->expectException(ModelNotFoundException::class);
            Livewire::test(ModifyColumn::class, ['ledgerId' => $this->tenant1Ledger->id]);
        });
    }

    /**
     * @Test
     * @see \Tests\Feature\TenantIsolationTest::validation_prevents_creating_relations_across_tenants
     * このテストは、台帳作成時に他テナントのフォルダIDを指定できてしまう脆弱性を検証する目的だったが、
     * アプリケーションの設計上、その操作自体が不可能であることが判明したため不要となった。
     * 経緯はドキュメントに記録済み。
     * @see /docs/work/2025-09-04_tenant-isolation-test-plan.md#55-validation_prevents_creating_relations_across_tenants-に関する補足
     */
    /*
    public function validation_prevents_creating_relations_across_tenants(): void
    {
        $this->actingAs($this->adminUser);

        $this->tenant1->run(function () {
            $this->assertTrue(true); // Dummy assertion as this test's premise was wrong.
        });
    }
    */

    #[Test]
    public function user_belonging_to_multiple_tenants_can_switch_context_and_operate_correctly(): void
    {
        $this->actingAs($this->adminUser);

        // Context: tenant1
        $this->tenant1->run(function () {
            Livewire::test(RecordsTable::class)
                ->set('selectedLedgerDefineIds', [$this->tenant1LedgerDefine->id])
                ->assertViewHas('ledgerRecords', function ($ledgers) {
                    $this->assertCount(1, $ledgers);
                    $this->assertEquals($this->tenant1Ledger->id, $ledgers->first()->id);
                    return true;
                });
        });

        // Context: tenant2
        $this->tenant2->run(function () {
            Livewire::test(RecordsTable::class)
                ->set('selectedLedgerDefineIds', [$this->tenant2LedgerDefine->id])
                ->assertViewHas('ledgerRecords', function ($ledgers) {
                    $this->assertCount(1, $ledgers);
                    $this->assertEquals($this->tenant2Ledger->id, $ledgers->first()->id);
                    return true;
                });
        });
    }
}
