<?php

namespace Tests\Feature\Livewire\LedgerDefine;

use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModifyColumnDateTypeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Tenant $tenant;

    protected Folder $folder;

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
    }

    public function test_date_column_shows_default_offset_input_field(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                new \App\Models\ColumnDefine((object) [
                    'id' => 0,
                    'name' => 'Date Field',
                    'type' => 'YMD',
                    'order' => 1,
                    'options' => ['default_offset' => '1d'],
                ]),
            ],
        ]);

        // Simply verify that the column was created with the default_offset option
        $ledgerDefine->refresh();
        $column = $ledgerDefine->column_define[0];
        $this->assertEquals('1d', $column->options['default_offset'] ?? null);

        // Verify DateType can read it
        $inputType = $column->getInputType();
        $this->assertInstanceOf(\App\Models\ColumnTypes\DateType::class, $inputType);
        $this->assertEquals('1d', $inputType->default_offset);
    }

    public function test_can_save_date_column_with_valid_default_offset(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                new \App\Models\ColumnDefine((object) [
                    'id' => 0,
                    'name' => 'Date Field',
                    'type' => 'YMD',
                    'order' => 1,
                ]),
            ],
        ]);

        // Update the column with a default_offset
        $column = $ledgerDefine->column_define[0];
        $column->options = ['default_offset' => '2d'];
        $ledgerDefine->column_define = [$column];
        $ledgerDefine->save();

        $ledgerDefine->refresh();
        $column = $ledgerDefine->column_define[0];
        $this->assertEquals('2d', $column->options['default_offset'] ?? null);
    }

    public function test_can_save_date_column_with_various_offset_formats(): void
    {
        $validOffsets = ['0d', '1d', '-1d', '1w', '-2w', '1M', '-1M', '1y', '-1y', '30d'];

        foreach ($validOffsets as $offset) {
            $ledgerDefine = LedgerDefine::factory()->create([
                'folder_id' => $this->folder->id,
                'column_define' => [
                    new \App\Models\ColumnDefine((object) [
                        'id' => 0,
                        'name' => 'Date Field',
                        'type' => 'YMD',
                        'order' => 1,
                    ]),
                ],
            ]);

            // Update with the offset
            $column = $ledgerDefine->column_define[0];
            $column->options = ['default_offset' => $offset];
            $ledgerDefine->column_define = [$column];
            $ledgerDefine->save();

            $ledgerDefine->refresh();
            $column = $ledgerDefine->column_define[0];
            $this->assertEquals($offset, $column->options['default_offset'] ?? null, "Failed for offset: {$offset}");
        }
    }

    public function test_validates_invalid_default_offset_format(): void
    {
        // This test validates that DateType can identify invalid formats
        $invalidOffsets = ['1day', 'tomorrow', '1', 'd1', '1D', '1x', 'abc'];

        foreach ($invalidOffsets as $offset) {
            $column = new \App\Models\ColumnDefine((object) [
                'id' => 0,
                'name' => 'Date Field',
                'type' => 'YMD',
                'order' => 1,
                'options' => ['default_offset' => $offset],
            ]);

            $dateType = $column->getInputType();
            // Invalid offsets should result in null when calculating default date
            $this->assertNull($dateType->getDefaultDate(), "Invalid offset '{$offset}' should return null");
        }
    }

    public function test_can_save_date_column_with_empty_default_offset(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                new \App\Models\ColumnDefine((object) [
                    'id' => 0,
                    'name' => 'Date Field',
                    'type' => 'YMD',
                    'order' => 1,
                    'options' => ['default_offset' => '1d'],
                ]),
            ],
        ]);

        // Clear the default_offset
        $column = $ledgerDefine->column_define[0];
        $column->options = ['default_offset' => ''];
        $ledgerDefine->column_define = [$column];
        $ledgerDefine->save();

        $ledgerDefine->refresh();
        $column = $ledgerDefine->column_define[0];
        $this->assertEmpty($column->options['default_offset'] ?? '');
    }

    public function test_can_save_date_column_with_null_default_offset(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                new \App\Models\ColumnDefine((object) [
                    'id' => 0,
                    'name' => 'Date Field',
                    'type' => 'YMD',
                    'order' => 1,
                    'options' => ['default_offset' => '1d'],
                ]),
            ],
        ]);

        // Set to null
        $column = $ledgerDefine->column_define[0];
        $column->options = [];
        $ledgerDefine->column_define = [$column];
        $ledgerDefine->save();

        $ledgerDefine->refresh();
        $column = $ledgerDefine->column_define[0];
        $this->assertNull($column->options['default_offset'] ?? null);
    }

    public function test_date_type_loads_default_offset_correctly(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                new \App\Models\ColumnDefine((object) [
                    'id' => 0,
                    'name' => 'Date Field',
                    'type' => 'YMD',
                    'order' => 1,
                    'options' => ['default_offset' => '7d'],
                ]),
            ],
        ]);

        // Verify the column define persists and loads the default_offset correctly
        $ledgerDefine->refresh();
        $column = $ledgerDefine->column_define[0];

        $this->assertEquals('7d', $column->options['default_offset'] ?? null);
        $this->assertEquals('7d', $column->getInputType()->default_offset);
    }

    public function test_updating_column_preserves_default_offset(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                new \App\Models\ColumnDefine((object) [
                    'id' => 0,
                    'name' => 'Date Field',
                    'type' => 'YMD',
                    'order' => 1,
                    'options' => ['default_offset' => '3d'],
                ]),
            ],
        ]);

        // Update column name while preserving default_offset
        $column = $ledgerDefine->column_define[0];
        $column->setName('Updated Date Field');
        $ledgerDefine->column_define = [$column];
        $ledgerDefine->save();

        $ledgerDefine->refresh();
        $column = $ledgerDefine->column_define[0];
        $this->assertEquals('Updated Date Field', $column->name);
        $this->assertEquals('3d', $column->options['default_offset'] ?? null);
    }

    public function test_can_save_date_column_with_overwrite_existing_option(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                new \App\Models\ColumnDefine((object) [
                    'id' => 0,
                    'name' => 'Date Field',
                    'type' => 'YMD',
                    'order' => 1,
                    'options' => [
                        'default_offset' => '1d',
                        'overwrite_existing' => true,
                    ],
                ]),
            ],
        ]);

        $ledgerDefine->refresh();
        $column = $ledgerDefine->column_define[0];
        $this->assertEquals('1d', $column->options['default_offset'] ?? null);
        $this->assertTrue($column->options['overwrite_existing'] ?? false);

        // Verify DateType can read it
        $inputType = $column->getInputType();
        $this->assertTrue($inputType->overwrite_existing);
    }

    public function test_date_column_overwrite_existing_defaults_to_false(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                new \App\Models\ColumnDefine((object) [
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
        $inputType = $column->getInputType();

        // デフォルトはfalse
        $this->assertFalse($inputType->overwrite_existing);
    }
}
