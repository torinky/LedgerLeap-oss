<?php

namespace Tests\Feature\Livewire\LedgerDefine;

use App\Livewire\LedgerDefine\ModifyColumn;
use App\Livewire\LedgerDefine\Preview;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModifyColumnTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_mount_without_explicit_parameters()
    {
        Livewire::test(ModifyColumn::class)
            ->assertStatus(200);

        Livewire::test(Preview::class)
            ->assertStatus(200);
    }

    protected Tenant $tenant;

    protected User $user;

    protected Folder $folder;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create();

        $this->actingAs($this->user);
        tenancy()->initialize($this->tenant);

        $this->folder = Folder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
        ]);

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'folder_id' => $this->folder->id,
            'title' => 'Initial Title',
            'column_define' => [
                new ColumnDefine((object) [
                    'id' => 0,
                    'name' => 'Column 1',
                    'type' => 'text',
                    'order' => 1,
                ]),
            ],
        ]);
    }

    #[Test]
    public function it_dispatches_sync_event_when_saving_individual_column()
    {
        Livewire::test(ModifyColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
        ])
            ->set('columns.0.name', 'Updated Column Name')
            ->call('saveColumn', 0)
            ->assertDispatched('ledgerDefineRecordStored')
            ->assertHasNoErrors();

        $this->ledgerDefine->refresh();
        $this->assertEquals('Updated Column Name', $this->ledgerDefine->column_define[0]->name);
    }

    #[Test]
    public function it_dispatches_sync_event_when_saving_all_columns()
    {
        Livewire::test(ModifyColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
        ])
            ->set('columns.0.name', 'Bulk Updated Name')
            ->call('save')
            ->assertDispatched('ledgerDefineRecordStored')
            ->assertHasNoErrors();

        $this->ledgerDefine->refresh();
        $this->assertEquals('Bulk Updated Name', $this->ledgerDefine->column_define[0]->name);
    }

    #[Test]
    public function preview_component_reloads_data_on_sync_event()
    {
        // 1. Initial preview state
        $preview = Livewire::test(Preview::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
        ]);
        $preview->assertSee('Column 1');

        // 2. Modify data in background (simulate save from another component)
        $this->ledgerDefine->column_define = [
            new ColumnDefine((object) [
                'id' => 0,
                'name' => 'Injected Name',
                'type' => 'text',
                'order' => 1,
            ]),
        ];
        $this->ledgerDefine->save();

        // 3. Dispatch event and assert preview updates
        $preview->dispatch('ledgerDefineRecordStored');

        $preview->assertSee('Injected Name');
        $preview->assertDontSee('Column 1');
    }

    // --- Date Type Specific Tests (Migrated from ModifyColumnDateTypeTest) ---

    #[Test]
    public function date_column_shows_default_offset_input_field(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                new ColumnDefine((object) [
                    'id' => 0,
                    'name' => 'Date Field',
                    'type' => 'YMD',
                    'order' => 1,
                    'options' => ['default_offset' => '1d'],
                ]),
            ],
        ]);

        $ledgerDefine->refresh();
        $column = $ledgerDefine->column_define[0];
        $this->assertEquals('1d', $column->options['default_offset'] ?? null);

        $inputType = $column->getInputType();
        $this->assertInstanceOf(\App\Models\ColumnTypes\DateType::class, $inputType);
        $this->assertEquals('1d', $inputType->default_offset);
    }

    #[Test]
    public function can_save_date_column_with_valid_default_offset(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                new ColumnDefine((object) [
                    'id' => 0,
                    'name' => 'Date Field',
                    'type' => 'YMD',
                    'order' => 1,
                ]),
            ],
        ]);

        $column = $ledgerDefine->column_define[0];
        $column->options = ['default_offset' => '2d'];
        $ledgerDefine->column_define = [$column];
        $ledgerDefine->save();

        $ledgerDefine->refresh();
        $column = $ledgerDefine->column_define[0];
        $this->assertEquals('2d', $column->options['default_offset'] ?? null);
    }

    #[Test]
    public function can_save_date_column_with_various_offset_formats(): void
    {
        $validOffsets = ['0d', '1d', '-1d', '1w', '-2w', '1M', '-1M', '1y', '-1y', '30d'];

        foreach ($validOffsets as $offset) {
            $ledgerDefine = LedgerDefine::factory()->create([
                'folder_id' => $this->folder->id,
                'column_define' => [
                    new ColumnDefine((object) [
                        'id' => 0,
                        'name' => 'Date Field',
                        'type' => 'YMD',
                        'order' => 1,
                    ]),
                ],
            ]);

            $column = $ledgerDefine->column_define[0];
            $column->options = ['default_offset' => $offset];
            $ledgerDefine->column_define = [$column];
            $ledgerDefine->save();

            $ledgerDefine->refresh();
            $column = $ledgerDefine->column_define[0];
            $this->assertEquals($offset, $column->options['default_offset'] ?? null, "Failed for offset: {$offset}");
        }
    }
}
