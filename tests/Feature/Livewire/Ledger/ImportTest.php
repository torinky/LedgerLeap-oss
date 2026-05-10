<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Imports\LedgerImport;
use App\Livewire\Ledger\Import;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * Livewire\Ledger\Import テスト
 *
 * インポートコンポーネントの render・updateImportProgress を検証する。
 * mount は Request::route() に依存するため、set() でledgerDefineを直接セット。
 */
#[CoversClass(Import::class)]
class ImportTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $folder = Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()
            ->for($folder)
            ->create();
    }

    // ================================================================
    // render
    // ================================================================

    #[Test]
    public function component_renders_with_ledger_define(): void
    {
        // ledgerDefine をセットしてからレンダリング
        Livewire::test(Import::class)
            ->set('ledgerDefine', $this->ledgerDefine)
            ->assertStatus(200);
    }

    #[Test]
    public function initial_state_is_not_importing(): void
    {
        $component = Livewire::test(Import::class)
            ->set('ledgerDefine', $this->ledgerDefine);

        $component->assertSet('importing', false);
        $component->assertSet('importFinished', false);
        $component->assertSet('totalRows', 0);
        $component->assertSet('currentRows', 0);
    }

    // ================================================================
    // updateImportProgress
    // ================================================================

    #[Test]
    public function update_import_progress_reads_from_cache(): void
    {
        \Cache::put('total_rows_'.$this->ledgerDefine->id, 100);
        \Cache::put('current_rows_'.$this->ledgerDefine->id, 50);
        \Cache::put('insert_rows_'.$this->ledgerDefine->id, 30);
        \Cache::put('update_rows_'.$this->ledgerDefine->id, 20);

        Livewire::test(Import::class)
            ->set('ledgerDefine', $this->ledgerDefine)
            ->call('updateImportProgress')
            ->assertSet('totalRows', 100)
            ->assertSet('currentRows', 50);
    }

    #[Test]
    public function update_import_progress_detects_finished_import(): void
    {
        \Cache::put('total_rows_'.$this->ledgerDefine->id, 10);
        \Cache::put('current_rows_'.$this->ledgerDefine->id, 10);
        \Cache::put('end_date_'.$this->ledgerDefine->id, now()->subSecond());

        Livewire::test(Import::class)
            ->set('ledgerDefine', $this->ledgerDefine)
            ->call('updateImportProgress')
            ->assertSet('importFinished', true)
            ->assertSet('importing', false);
    }

    #[Test]
    public function update_import_progress_not_finished_when_end_date_in_future(): void
    {
        \Cache::put('total_rows_'.$this->ledgerDefine->id, 10);
        \Cache::put('current_rows_'.$this->ledgerDefine->id, 5);
        \Cache::put('end_date_'.$this->ledgerDefine->id, now()->addMinutes(5));

        Livewire::test(Import::class)
            ->set('ledgerDefine', $this->ledgerDefine)
            ->call('updateImportProgress')
            ->assertSet('importFinished', false);
    }

    #[Test]
    public function update_import_progress_sets_import_mode_default(): void
    {
        $component = Livewire::test(Import::class)
            ->set('ledgerDefine', $this->ledgerDefine);

        // デフォルトの importMode が MODE_UPDATE であること
        $this->assertEquals(LedgerImport::MODE_UPDATE, $component->get('importMode'));
    }
}
