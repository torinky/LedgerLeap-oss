<?php

namespace Tests\Unit\Services;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Services\Ledger\LedgerDiffProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerDiffProcessorTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $ledger;
    private LedgerDiffProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new LedgerDiffProcessor();

        // Create a base ledger record for tests
        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                ['id' => 0, 'name' => 'Col 1', 'type' => 'text', 'order' => 1],
                ['id' => 1, 'name' => 'Col 2', 'type' => 'text', 'order' => 2],
            ]
        ]);
        $this->ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['Value 1', 'Value 2'],
        ]);
    }

    public function test_it_returns_no_changes_if_no_comparison_target_is_given(): void
    {
        $result = $this->processor->prepareContentDiff($this->ledger, null);

        $this->assertFalse($result['hasChangedColumns']);
        $this->assertCount(2, $result['contentChanges']);
        $this->assertEquals('unchanged', $result['contentChanges'][0]['status']);
    }

    public function test_it_correctly_identifies_modified_columns(): void
    {
        $oldDiff = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'column_define' => $this->ledger->define->column_define,
            'content' => ['Old Value 1', 'Value 2'],
        ]);

        $this->ledger->content = ['New Value 1', 'Value 2'];
        $this->ledger->save();

        $result = $this->processor->prepareContentDiff($this->ledger, $oldDiff);

        $this->assertTrue($result['hasChangedColumns']);
        $this->assertEquals('modified', $result['contentChanges'][0]['status']);
        $this->assertEquals('unchanged', $result['contentChanges'][1]['status']);
    }

    public function test_it_correctly_identifies_reordered_columns_as_unchanged(): void
    {
        $oldDiff = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'column_define' => [
                ['id' => 0, 'name' => 'Col 1', 'type' => 'text', 'order' => 1],
                ['id' => 1, 'name' => 'Col 2', 'type' => 'text', 'order' => 2],
            ],
            'content' => ['Value 1', 'Value 2'],
        ]);

        // Reorder columns in the main definition
        $this->ledger->define->column_define = [
            ['id' => 1, 'name' => 'Col 2', 'type' => 'text', 'order' => 1],
            ['id' => 0, 'name' => 'Col 1', 'type' => 'text', 'order' => 2],
        ];
        // Content values are the same, but their effective order in the array might change based on new definition order
        $this->ledger->content = ['Value 2', 'Value 1'];
        $this->ledger->save();

        $result = $this->processor->prepareContentDiff($this->ledger, $oldDiff);

        $this->assertTrue($result['hasChangedColumns']);
        // 変更: contentChanges の status も modified になることを期待
        $this->assertEquals('modified', $result['contentChanges'][0]['status']);
        $this->assertEquals('modified', $result['contentChanges'][1]['status']);
    }

    public function test_it_correctly_identifies_added_columns(): void
    {
        // V1: Column 0 only
        $oldDiff = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'column_define' => [['id' => 0, 'name' => 'Col 1', 'type' => 'text', 'order' => 1]],
            'content' => ['Old Value 1'],
        ]);

        // V2: Column 0 and 1
        $this->ledger->define->column_define = [
            ['id' => 0, 'name' => 'Col 1', 'type' => 'text', 'order' => 1],
            ['id' => 1, 'name' => 'Col 2', 'type' => 'text', 'order' => 2],
        ];
        $this->ledger->content = ['Old Value 1', 'New Value 2'];
        $this->ledger->save();

        $result = $this->processor->prepareContentDiff($this->ledger, $oldDiff);

        $this->assertTrue($result['hasChangedColumns']);
        $this->assertEquals('unchanged', $result['contentChanges'][0]['status']);
        $this->assertEquals('added', $result['contentChanges'][1]['status']);
        $this->assertNull($result['contentChanges'][1]['old_value']);
        $this->assertEquals('New Value 2', $result['contentChanges'][1]['current_value']);
    }

    public function test_it_correctly_identifies_deleted_columns(): void
    {
        // V1: Column 0 and 1
        $oldDiff = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'column_define' => [
                ['id' => 0, 'name' => 'Col 1', 'type' => 'text', 'order' => 1],
                ['id' => 1, 'name' => 'Col 2', 'type' => 'text', 'order' => 2],
            ],
            'content' => ['Value 1', 'Value 2'],
        ]);

        // V2: Column 0 only
        $this->ledger->define->column_define = [['id' => 0, 'name' => 'Col 1', 'type' => 'text', 'order' => 1]];
        $this->ledger->content = ['Value 1'];
        $this->ledger->save();

        $result = $this->processor->prepareContentDiff($this->ledger, $oldDiff);

        // For debugging
        dump(array_keys($result['contentChanges']));

        $this->assertTrue($result['hasChangedColumns']);
        $this->assertEquals('unchanged', $result['contentChanges'][0]['status']);
        $this->assertEquals('deleted', $result['contentChanges'][1]['status']);
        $this->assertEquals('Value 2', $result['contentChanges'][1]['old_value']);
        $this->assertNull($result['contentChanges'][1]['current_value']);
    }
}
