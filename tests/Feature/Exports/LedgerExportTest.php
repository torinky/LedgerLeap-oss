<?php

namespace Tests\Feature\Exports;

use App\Exports\LedgerExport;
use App\Jobs\Ledger\ExportJob;
use App\Livewire\Ledger\Export as LedgerExportComponent;
use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\Ledger\ExportCacheService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(LedgerExport::class)]
class LedgerExportTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->ledgerDefine = LedgerDefine::factory()->create();
    }

    // ----------------------------------------------------------------
    // headings
    // ----------------------------------------------------------------

    public function test_headings_includes_column_names(): void
    {
        $export = new LedgerExport(
            ledgerDefineId: $this->ledgerDefine->id,
            keywords: [],
            filter: [],
            columnDefines: $this->ledgerDefine->column_define
        );

        $headings = $export->headings();

        $this->assertIsArray($headings);
        // カラム名 + システム列が含まれる
        $this->assertContains('[[[id]]]', $headings);
        $this->assertContains('[[[ledger_define_id]]]', $headings);
        $this->assertContains('[[[updated_at]]]', $headings);
        $this->assertContains('[[[created_at]]]', $headings);
    }

    public function test_headings_includes_column_define_names(): void
    {
        $textColumn = new ColumnDefine(0, 'テスト項目', 'text', 1, [], false, false, null, '', [], 1);
        $define = LedgerDefine::factory()->create(['column_define' => [$textColumn]]);

        $export = new LedgerExport(
            ledgerDefineId: $define->id,
            keywords: [],
            filter: [],
            columnDefines: $define->column_define
        );

        $headings = $export->headings();
        $this->assertContains('テスト項目', $headings);
    }

    public function test_headings_count_equals_columns_plus_system_fields(): void
    {
        // デフォルト 1カラム + システム列 6つ = 7
        $export = new LedgerExport(
            ledgerDefineId: $this->ledgerDefine->id,
            keywords: [],
            filter: [],
            columnDefines: $this->ledgerDefine->column_define
        );

        $this->assertCount(7, $export->headings());
    }

    // ----------------------------------------------------------------
    // map
    // ----------------------------------------------------------------

    public function test_map_returns_array_with_correct_count(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'test value'],
        ]);

        $export = new LedgerExport(
            ledgerDefineId: $this->ledgerDefine->id,
            keywords: [],
            filter: [],
            columnDefines: $this->ledgerDefine->column_define
        );

        $row = $export->map($ledger);

        $this->assertIsArray($row);
        // カラム数(1) + システム列(6) = 7
        $this->assertCount(7, $row);
    }

    public function test_map_includes_ledger_content_value(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'exported value'],
        ]);

        $export = new LedgerExport(
            ledgerDefineId: $this->ledgerDefine->id,
            keywords: [],
            filter: [],
            columnDefines: $this->ledgerDefine->column_define
        );

        $row = $export->map($ledger);
        $this->assertContains('exported value', $row);
    }

    public function test_map_includes_ledger_id(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
        ]);

        $export = new LedgerExport(
            ledgerDefineId: $this->ledgerDefine->id,
            keywords: [],
            filter: [],
            columnDefines: $this->ledgerDefine->column_define
        );

        $row = $export->map($ledger);
        $this->assertContains($ledger->id, $row);
    }

    // ----------------------------------------------------------------
    // query
    // ----------------------------------------------------------------

    public function test_query_returns_ledgers_for_define(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
        ]);

        $export = new LedgerExport(
            ledgerDefineId: $this->ledgerDefine->id,
            keywords: [],
            filter: [],
            columnDefines: $this->ledgerDefine->column_define
        );

        $results = $export->query()->get();
        $this->assertTrue($results->contains('id', $ledger->id));
    }

    public function test_query_excludes_other_define_ledgers(): void
    {
        $otherDefine = LedgerDefine::factory()->create();
        Ledger::factory()->create(['ledger_define_id' => $otherDefine->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $this->ledgerDefine->id]);

        $export = new LedgerExport(
            ledgerDefineId: $this->ledgerDefine->id,
            keywords: [],
            filter: [],
            columnDefines: $this->ledgerDefine->column_define
        );

        $results = $export->query()->get();
        $this->assertTrue($results->contains('id', $ledger->id));
        $this->assertFalse($results->contains('ledger_define_id', $otherDefine->id));
    }

    // ----------------------------------------------------------------
    // getCsvSettings
    // ----------------------------------------------------------------

    public function test_get_csv_settings_returns_expected_keys(): void
    {
        $export = new LedgerExport(
            ledgerDefineId: $this->ledgerDefine->id,
            keywords: [],
            filter: [],
            columnDefines: $this->ledgerDefine->column_define
        );

        $settings = $export->getCsvSettings();
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('use_bom', $settings);
        $this->assertArrayHasKey('delimiter', $settings);
        $this->assertEquals(',', $settings['delimiter']);
    }

    // ----------------------------------------------------------------
    // Excel::fake() による出力確認
    // ----------------------------------------------------------------

    public function test_export_can_be_downloaded_via_excel_facade(): void
    {
        Excel::fake();

        Ledger::factory()->create(['ledger_define_id' => $this->ledgerDefine->id]);

        $export = new LedgerExport(
            ledgerDefineId: $this->ledgerDefine->id,
            keywords: [],
            filter: [],
            columnDefines: $this->ledgerDefine->column_define
        );

        Excel::download($export, 'test.csv');

        Excel::assertDownloaded('test.csv');
    }

    public function test_export_job_stores_csv_on_public_disk(): void
    {
        Storage::fake('public');

        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'exported value'],
        ]);

        $cacheService = app(ExportCacheService::class);
        $filename = $cacheService->buildFilename($this->ledgerDefine->id, [], []);

        $job = new ExportJob(
            ledgerDefineId: $this->ledgerDefine->id,
            keywords: [],
            filter: [],
            columnDefines: $this->ledgerDefine->column_define,
            filename: $filename,
        );

        $job->handle();

        Storage::disk('public')->assertExists($filename);
        $this->assertStringContainsString('exported value', Storage::disk('public')->get($filename));
    }

    public function test_download_export_via_controller_returns_file_for_authorized_user(): void
    {
        Storage::fake('public');
        $cacheService = app(ExportCacheService::class);
        $filename = $cacheService->buildFilename($this->ledgerDefine->id, [], []);
        Storage::disk('public')->put($filename, "dummy\n");

        Gate::before(fn ($user, $ability) => true);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ledger.export.download', [
                'tenant' => $this->getTenant()->id,
                'ledgerDefineId' => $this->ledgerDefine->id,
                'filename' => $filename,
            ]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="'.$filename.'"');
    }

    public function test_download_export_via_controller_returns_403_for_unauthorized_user(): void
    {
        Storage::fake('public');
        $cacheService = app(ExportCacheService::class);
        $filename = $cacheService->buildFilename($this->ledgerDefine->id, [], []);
        Storage::disk('public')->put($filename, "dummy\n");

        Gate::define('ledgerView', fn () => false);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ledger.export.download', [
                'tenant' => $this->getTenant()->id,
                'ledgerDefineId' => $this->ledgerDefine->id,
                'filename' => $filename,
            ]))
            ->assertForbidden();
    }

    public function test_download_export_via_controller_returns_404_when_file_missing(): void
    {
        Storage::fake('public');

        Gate::before(fn ($user, $ability) => true);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ledger.export.download', [
                'tenant' => $this->getTenant()->id,
                'ledgerDefineId' => $this->ledgerDefine->id,
                'filename' => 'missing.csv',
            ]))
            ->assertNotFound();
    }

    public function test_update_export_progress_marks_export_finished_without_toast(): void
    {
        Bus::shouldReceive('findBatch')
            ->once()
            ->andReturn(new class
            {
                public function finished(): bool
                {
                    return true;
                }
            });

        Livewire::test(
            LedgerExportComponent::class,
            [$this->ledgerDefine->id, [], [], $this->ledgerDefine->title]
        )
            ->set('batchId', 'batch-1')
            ->call('updateExportProgress')
            ->assertSet('exportFinished', true)
            ->assertSet('exporting', false);
    }

    public function test_render_shows_exporting_label_then_download_link(): void
    {
        $component = Livewire::test(
            LedgerExportComponent::class,
            [$this->ledgerDefine->id, [], [], $this->ledgerDefine->title]
        )
            ->assertSeeHtml('wire:key="ledger_export_request-')
            ->assertDontSeeHtml('wire:key="ledger_export_download-');

        $component->set('exporting', true)
            ->set('exportFinished', false)
            ->assertSeeHtml('wire:key="ledger_export_request-')
            ->assertSeeHtml('disabled="disabled"')
            ->assertDontSeeHtml('wire:key="ledger_export_download-');

        $component->set('exportFinished', true)
            ->assertSeeHtml('wire:key="ledger_export_download-')
            ->assertDontSeeHtml('wire:key="ledger_export_request-')
            ->assertDontSeeHtml('disabled="disabled"');
    }

    public function test_export_skips_batch_when_cached_file_exists(): void
    {
        Storage::fake('public');
        Bus::fake();

        $cacheService = app(ExportCacheService::class);
        $filename = $cacheService->buildFilename($this->ledgerDefine->id, [], []);
        Storage::disk('public')->put($filename, "cached\n");

        Livewire::test(
            LedgerExportComponent::class,
            [$this->ledgerDefine->id, [], [], $this->ledgerDefine->title]
        )
            ->call('export')
            ->assertSet('exportFinished', true)
            ->assertSet('exporting', false);

        Bus::assertNothingDispatched();
    }

    public function test_export_dispatches_batch_when_cached_file_missing(): void
    {
        Storage::fake('public');

        Livewire::test(
            LedgerExportComponent::class,
            [$this->ledgerDefine->id, [], [], $this->ledgerDefine->title]
        )
            ->call('export')
            ->assertSet('exporting', true)
            ->assertSet('exportFinished', false);
    }

    public function test_export_filename_changes_with_search_conditions(): void
    {
        $cacheService = app(ExportCacheService::class);

        $filenameEmpty = $cacheService->buildFilename($this->ledgerDefine->id, [], []);
        $filenameWithKeyword = $cacheService->buildFilename($this->ledgerDefine->id, ['test'], []);
        $filenameWithFilter = $cacheService->buildFilename($this->ledgerDefine->id, [], ['status' => 'active']);

        $this->assertNotEquals($filenameEmpty, $filenameWithKeyword);
        $this->assertNotEquals($filenameEmpty, $filenameWithFilter);
        $this->assertNotEquals($filenameWithKeyword, $filenameWithFilter);
    }

    public function test_ledger_observer_clears_export_cache_on_create(): void
    {
        Storage::fake('public');
        $cacheService = app(ExportCacheService::class);
        $filename = $cacheService->buildFilename($this->ledgerDefine->id, [], []);
        Storage::disk('public')->put($filename, "cached\n");

        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
        ]);

        Storage::disk('public')->assertMissing($filename);
    }

    public function test_ledger_observer_clears_export_cache_on_update(): void
    {
        Storage::fake('public');
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'old'],
        ]);

        $cacheService = app(ExportCacheService::class);
        $filename = $cacheService->buildFilename($this->ledgerDefine->id, [], []);
        Storage::disk('public')->put($filename, "cached\n");

        $ledger->update(['content' => [0 => 'new']]);

        Storage::disk('public')->assertMissing($filename);
    }

    public function test_ledger_define_observer_clears_export_cache_on_column_define_change(): void
    {
        Storage::fake('public');
        $cacheService = app(ExportCacheService::class);
        $filename = $cacheService->buildFilename($this->ledgerDefine->id, [], []);
        Storage::disk('public')->put($filename, "cached\n");

        $this->ledgerDefine->update(['column_define' => $this->ledgerDefine->column_define]);

        // column_define が実際に変更されていない場合はクリアされない
        Storage::disk('public')->assertExists($filename);

        // column_define を実際に変更
        $newColumn = new ColumnDefine(99, '新規項目', 'text', 1, [], false, false, null, '', [], 1);
        $this->ledgerDefine->update(['column_define' => [$newColumn]]);

        Storage::disk('public')->assertMissing($filename);
    }
}
