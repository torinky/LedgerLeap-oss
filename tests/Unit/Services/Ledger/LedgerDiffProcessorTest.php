<?php

namespace Tests\Unit\Services\Ledger;

use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Services\Ledger\LedgerDiffProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LedgerDiffProcessorTest extends TestCase
{
    use RefreshDatabase;

    private LedgerDiffProcessor $processor;
    private LedgerDefine $ledgerDefine;
    private Ledger $ledger;
    private Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new LedgerDiffProcessor();
        $this->folder = Folder::factory()->create();

        // テストの基本となる台帳定義と台帳レコードを作成
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                ['id' => 0, 'name' => 'Column 1', 'type' => 'text', 'order' => 1],
                ['id' => 1, 'name' => 'Column 2', 'type' => 'text', 'order' => 2],
            ],
        ]);

        $this->ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['Value 1', 'Value 2'],
        ]);
    }

    // --- findComparisonTargetDiff Tests ---

    #[Test]
    public function it_finds_comparison_target_diff_correctly(): void
    {
        // Diff 1 (内容変更あり)
        $diff1 = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['Value 1', 'Value 2'], // 初期値と異なる
            'column_define' => $this->ledgerDefine->column_define,
        ]);

        // Diff 2 (内容変更あり)
        $diff2 = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['Value 1', 'Changed Value 2'],
            'column_define' => $this->ledgerDefine->column_define,
        ]);

        // Diff 3 (現在の内容と同じ)
        $diff3 = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['Value 1', 'Final Value 2'],
            'column_define' => $this->ledgerDefine->column_define,
        ]);

        // Ledgerの最新状態を更新
        $this->ledger->latest_diff_id = $diff3->id;
        $this->ledger->content = ['Value 1', 'Final Value 2'];
        $this->ledger->save();

        $comparisonTarget = $this->processor->findComparisonTargetDiff($this->ledger);

        $this->assertNotNull($comparisonTarget);
        // findComparisonTargetDiffは、現在の内容と異なる直近のdiffを返すため、diff2が期待値
        $this->assertEquals($diff2->id, $comparisonTarget->id);
    }

    #[Test]
    public function it_returns_null_if_no_comparison_target_diff_found(): void
    {
        $diff1 = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => $this->ledger->content,
            'column_define' => $this->ledgerDefine->column_define,
        ]);

        $this->ledger->latest_diff_id = $diff1->id;
        $this->ledger->save();

        $comparisonTarget = $this->processor->findComparisonTargetDiff($this->ledger);

        $this->assertNull($comparisonTarget);
    }

    // --- prepareContentDiff Tests ---

    #[Test]
    public function it_returns_added_status_when_no_comparison_target_is_given(): void
    {
        // 比較対象がない場合、すべてのカラムが 'added' として扱われる
        $result = $this->processor->prepareContentDiff($this->ledger, null);

        // 最初のバージョンのため差分「なし」と見なす（変更ではないため）
        $this->assertFalse($result['hasChangedColumns']);
        $this->assertCount(2, $result['contentChanges']);
        $this->assertEquals('added', $result['contentChanges'][0]['status']);
        $this->assertEquals('added', $result['contentChanges'][1]['status']);
    }

    #[Test]
    public function it_returns_unchanged_status_when_content_is_identical(): void
    {
        $oldDiff = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => $this->ledger->content,
            'column_define' => $this->ledgerDefine->column_define,
        ]);

        $result = $this->processor->prepareContentDiff($this->ledger, $oldDiff);

        $this->assertFalse($result['hasChangedColumns']);
        $this->assertCount(2, $result['contentChanges']);
        $this->assertEquals('unchanged', $result['contentChanges'][0]['status']);
        $this->assertEquals('unchanged', $result['contentChanges'][1]['status']);
    }

    #[Test]
    public function it_identifies_modified_columns(): void
    {
        $oldDiff = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['Old Value 1', 'Value 2'],
            'column_define' => $this->ledgerDefine->column_define,
        ]);

        // メインの台帳の値を変更
        $this->ledger->content = ['New Value 1', 'Value 2'];
        $this->ledger->save();

        $result = $this->processor->prepareContentDiff($this->ledger, $oldDiff);

        $this->assertTrue($result['hasChangedColumns']);
        $this->assertEquals('modified', $result['contentChanges'][0]['status']);
        $this->assertEquals('unchanged', $result['contentChanges'][1]['status']);
        $this->assertEquals('New Value 1', $result['contentChanges'][0]['current_value']);
        $this->assertEquals('Old Value 1', $result['contentChanges'][0]['old_value']);
    }

    #[Test]
    public function it_identifies_added_columns(): void
    {
        // V1: カラム0のみ
        $oldColumnDefine = [['id' => 0, 'name' => 'Column 1', 'type' => 'text', 'order' => 1]];
        $oldDiff = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'column_define' => $oldColumnDefine,
            'content' => ['Value 1'],
        ]);

        // V2: カラム0と1 (setUpで定義済み)
        $result = $this->processor->prepareContentDiff($this->ledger, $oldDiff);

        $this->assertTrue($result['hasChangedColumns']);
        $this->assertCount(2, $result['contentChanges']);
        $this->assertEquals('unchanged', $result['contentChanges'][0]['status']);
        $this->assertEquals('added', $result['contentChanges'][1]['status']);
        $this->assertNull($result['contentChanges'][1]['old_value']);
        $this->assertEquals('Value 2', $result['contentChanges'][1]['current_value']);
    }

    #[Test]
    public function it_identifies_deleted_columns(): void
    {
        // V1: カラム0と1 (oldDiff)
        $oldDiff = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'column_define' => $this->ledgerDefine->column_define,
            'content' => ['Value 1', 'Value 2'],
        ]);

        // V2: カラム0のみ
        $newColumnDefine = [['id' => 0, 'name' => 'Column 1', 'type' => 'text', 'order' => 1]];
        $this->ledger->define->column_define = $newColumnDefine;
        $this->ledger->content = ['Value 1'];
        $this->ledger->save();

        $result = $this->processor->prepareContentDiff($this->ledger, $oldDiff);

        $this->assertTrue($result['hasChangedColumns']);
        $this->assertCount(2, $result['contentChanges']); // 削除されたカラムも結果に含まれる
        $this->assertEquals('unchanged', $result['contentChanges'][0]['status']);
        $this->assertEquals('deleted', $result['contentChanges'][1]['status']);
        $this->assertEquals('Value 2', $result['contentChanges'][1]['old_value']);
        $this->assertNull($result['contentChanges'][1]['current_value']);
    }

    #[Test]
    public function it_identifies_reordered_columns_as_modified(): void
    {
        $oldDiff = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'column_define' => $this->ledgerDefine->column_define, // order: 1, 2
            'content' => ['Value 1', 'Value 2'],
        ]);

        // カラムの順序を入れ替え
        $reorderedDefine = [
            ['id' => 1, 'name' => 'Column 2', 'type' => 'text', 'order' => 1],
            ['id' => 0, 'name' => 'Column 1', 'type' => 'text', 'order' => 2],
        ];
        $this->ledger->define->column_define = $reorderedDefine;
        // contentの順序も定義に合わせて変更される
        $this->ledger->content = ['Value 2', 'Value 1'];
        $this->ledger->save();

        $result = $this->processor->prepareContentDiff($this->ledger, $oldDiff);

        // カラムの順序変更は定義の変更とみなされ、差分ありとなる
        $this->assertTrue($result['hasChangedColumns']);
        // 値自体は同じでも、カラム定義のコンテキスト（特に順序）が変わったため、両方とも 'modified' となる
        $this->assertEquals('modified', $result['contentChanges'][0]['status']);
        $this->assertEquals('modified', $result['contentChanges'][1]['status']);
    }

    #[Test]
    public function it_identifies_changes_in_file_attachments(): void
    {
        $fileColumnDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [['id' => 0, 'name' => 'File Column', 'type' => 'files', 'order' => 1]],
        ]);
        $fileLedger = Ledger::factory()->create([
            'ledger_define_id' => $fileColumnDefine->id,
            'content' => [0 => ['new_file_hash' => 'new_file.pdf']],
        ]);

        $oldDiff = LedgerDiff::factory()->create([
            'ledger_id' => $fileLedger->id,
            'ledger_define_id' => $fileColumnDefine->id,
            'column_define' => $fileColumnDefine->column_define,
            'content' => [0 => ['old_file_hash' => 'old_file.pdf']],
        ]);

        $result = $this->processor->prepareContentDiff($fileLedger, $oldDiff);

        $this->assertTrue($result['hasChangedColumns']);
        $this->assertCount(1, $result['contentChanges']);
        $this->assertEquals('modified', $result['contentChanges'][0]['status']);
        $this->assertEquals(['new_file_hash' => 'new_file.pdf'], $result['contentChanges'][0]['current_value']);
        $this->assertEquals(['old_file_hash' => 'old_file.pdf'], $result['contentChanges'][0]['old_value']);
    }
}
