<?php

namespace Tests\Feature\Livewire\AttachedFile;

use App\Livewire\AttachedFile\FileInspector;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class FileInspectorTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected Tenant $tenant;
    protected User $user;
    protected Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRefreshDatabaseWithTenant();

        // テナント初期化（RefreshDatabaseWithTenantがstatic::$sharedTenantを作成・初期化している）
        $this->tenant = static::$sharedTenant;
        tenancy()->initialize($this->tenant);

        // テストユーザー作成とログイン
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $define = LedgerDefine::factory()->create();
        $this->ledger = Ledger::factory()->create([
            'ledger_define_id' => $define->id,
            'creator_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function it_opens_inspector_and_loads_mock_data()
    {
        config(['mock.attachment.enabled' => true]);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->dispatch('open-file-inspector', id: 1)
            ->assertSet('open', true)
            ->assertSet('isLoading', false)
            ->assertSet('fileId', 1)
            ->assertSee('領収書_2025-12-01.jpg');
    }

    #[Test]
    public function it_opens_inspector_and_loads_real_data()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'real_data_test.pdf',
            'mime' => 'application/pdf',
            'status' => \App\Enums\AttachedFileStatus::COMPLETED->value,
            'finalized_source' => 'tika',
            'processing_finalized_at' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        // Gate::before で一括許可（Policyをバイパス）
        Gate::before(function ($user, $ability) {
            return true;
        });

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->dispatch('open-file-inspector', id: $file->id)
            ->assertSet('fileId', $file->id)
            ->assertSet('open', true)
            ->assertSet('isLoading', false)
            ->assertSee('real_data_test.pdf');
    }

    #[Test]
    public function it_shows_error_when_file_not_found()
    {
        config(['mock.attachment.enabled' => false]);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->dispatch('open-file-inspector', id: 99999)
            ->assertSet('open', false)
            ->assertSet('isLoading', false)
            ->assertDispatched('mary-toast');
    }

    #[Test]
    public function it_handles_permission_restriction()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Gate::before で一括拒否
        Gate::before(function ($user, $ability) {
            return false;
        });

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->dispatch('open-file-inspector', id: $file->id)
            ->assertSet('open', false)
            ->assertDispatched('mary-toast');
    }
}
