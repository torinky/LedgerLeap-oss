<?php

namespace Tests\Unit\Services\Ledger;

use App\Models\AttachedFile;
use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Services\Ledger\LedgerDiffProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LedgerDiffProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected LedgerDiffProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new LedgerDiffProcessor();
    }

    /** @test */
    public function it_finds_comparison_target_diff_correctly()
    {
        // テストデータの準備
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['col1' => 'initial'],
        ]);

        // Diff 1 (内容変更なし)
        LedgerDiff::factory()->for($ledger)->create([
            'ledger_id' => $ledger->id,
            'content' => ['col1' => 'initial'],
        ]);

        // Diff 2 (内容変更あり)
        $diff2 = LedgerDiff::factory()->for($ledger)->create([
            'ledger_id' => $ledger->id,
            'content' => ['col1' => 'changed'],
        ]);

        // Diff 3 (内容変更なし)
        $diff3 = LedgerDiff::factory()->for($ledger)->create([
            'ledger_id' => $ledger->id,
            'content' => ['col1' => 'changed'],
        ]);

        // Ledger の latest_diff_id を設定
        $ledger->latest_diff_id = $diff3->id;
        $ledger->save();

        // Ledger の content を最新の状態に更新
        $ledger->content = ['col1' => 'changed'];

        // 実行
        $comparisonTarget = $this->processor->findComparisonTargetDiff($ledger);

        // 検証: diff2 が比較対象として返されるべき
        $this->assertNotNull($comparisonTarget);
        $this->assertEquals($diff2->id, $comparisonTarget->id);
    }

    /** @test */
    public function it_returns_null_if_no_comparison_target_diff_found()
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['col1' => 'initial'],
        ]);

        // Diff を一つだけ作成 (内容変更なし)
        $diff1 = LedgerDiff::factory()->for($ledger)->create([
            'ledger_id' => $ledger->id,
            'content' => ['col1' => 'initial'],
        ]);

        $ledger->latest_diff_id = $diff1->id;
        $ledger->save();
        $ledger->content = ['col1' => 'initial'];

        $comparisonTarget = $this->processor->findComparisonTargetDiff($ledger);
        $this->assertNull($comparisonTarget);
    }

    /** @test */
    public function it_prepares_content_diff_with_changes()
    {
        // テストデータの準備
        $columnDefinesForTest = [
            ['id' => 0, 'name' => 'Column 1', 'type' => 'text', 'order' => 1],
            ['id' => 1, 'name' => 'Column 2', 'type' => 'text', 'order' => 2],
        ];

        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => $columnDefinesForTest,
        ]);
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [0 => 'new_value', 1 => 'value2'],
        ]);

        $oldDiff = LedgerDiff::factory()->for($ledger)->create([
            'ledger_id' => $ledger->id,
            'content' => [0 => 'old_value', 1 => 'value2'],
            'column_define' => $columnDefinesForTest,
        ]);
        $ledger->latest_diff_id = $oldDiff->id + 1; // 最新のDiff IDを設定 (実際には存在しないが、ロジック上必要)
        $ledger->save();

        // 実行
        $result = $this->processor->prepareContentDiff($ledger, $ledger->define, $oldDiff);

        // 検証
        $this->assertTrue($result['hasChangedColumns']);
        $this->assertCount(2, $result['contentChanges']);
        $this->assertTrue($result['contentChanges'][0]['changed']); // Column 0 changed
        $this->assertFalse($result['contentChanges'][1]['changed']); // Column 1 not changed
        $this->assertEquals('new_value', $result['contentChanges'][0]['current_value']); // Column 0 changed
        $this->assertEquals('old_value', $result['contentChanges'][0]['old_value']); // Column 0 changed
    }

    /** @test */
    public function it_prepares_content_diff_with_no_changes()
    {
        $columnDefinesForTest = [
            ['id' => 0, 'name' => 'Column 1', 'type' => 'text', 'order' => 1],
            ['id' => 1, 'name' => 'Column 2', 'type' => 'text', 'order' => 2],
        ];

        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => $columnDefinesForTest,
        ]);
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [0 => 'value1', 1 => 'value2'],
        ]);

        $oldDiff = LedgerDiff::factory()->for($ledger)->create([
            'ledger_id' => $ledger->id,
            'content' => [0 => 'value1', 1 => 'value2'],
            'column_define' => $columnDefinesForTest,
        ]);
        $ledger->latest_diff_id = $oldDiff->id + 1;
        $ledger->save();

        $result = $this->processor->prepareContentDiff($ledger, $ledger->define, $oldDiff);

        $this->assertFalse($result['hasChangedColumns']);
        $this->assertCount(2, $result['contentChanges']);
        $this->assertFalse($result['contentChanges'][0]['changed']);
        $this->assertFalse($result['contentChanges'][1]['changed']);
    }

    /** @test */
    public function it_prepares_content_diff_with_added_column()
    {
        $columnDefinesForTest = [
            ['id' => 0, 'name' => 'Column 1', 'type' => 'text', 'order' => 1],
            ['id' => 1, 'name' => 'Column 2', 'type' => 'text', 'order' => 2],
        ];

        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => $columnDefinesForTest,
        ]);
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [0 => 'value1', 1 => 'value2'],
        ]);

        $oldDiff = LedgerDiff::factory()->for($ledger)->create([
            'ledger_id' => $ledger->id,
            'content' => [0 => 'value1'],
            'column_define' => [['id' => 0, 'name' => 'Column 1', 'type' => 'text', 'order' => 1]],
        ]);
        $ledger->latest_diff_id = $oldDiff->id + 1;
        $ledger->save();

        $result = $this->processor->prepareContentDiff($ledger, $ledger->define, $oldDiff);

        $this->assertTrue($result['hasChangedColumns']); // 新しいカラムが追加されたので変更あり
        $this->assertCount(2, $result['contentChanges']);
        $this->assertFalse($result['contentChanges'][0]['changed']); // Column 0 not changed
        $this->assertTrue($result['contentChanges'][1]['changed']); // New column (Column 1) is changed
    }

    /** @test */
    public function it_prepares_content_diff_with_removed_column()
    {
        $columnDefinesForTest = [
            ['id' => 0, 'name' => 'Column 1', 'type' => 'text', 'order' => 1],
        ];

        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => $columnDefinesForTest,
        ]);
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [0 => 'value1'],
        ]);

        $oldDiff = LedgerDiff::factory()->for($ledger)->create([
            'ledger_id' => $ledger->id,
            'content' => [0 => 'value1', 1 => 'value2'],
            'column_define' => [['id' => 0, 'name' => 'Column 1', 'type' => 'text', 'order' => 1], ['id' => 1, 'name' => 'Column 2', 'type' => 'text', 'order' => 2]],
        ]);
        $ledger->latest_diff_id = $oldDiff->id + 1;
        $ledger->save();

        $result = $this->processor->prepareContentDiff($ledger, $ledger->define, $oldDiff);

        $this->assertTrue($result['hasChangedColumns']); // カラムが削除されたので変更あり
        $this->assertCount(2, $result['contentChanges']); // 削除されたカラムも含まれる
        $this->assertFalse($result['contentChanges'][0]['changed']); // Column 0 not changed
        $this->assertTrue($result['contentChanges'][1]['changed']); // Removed column (Column 1) is changed
    }

    /** @test */
    public function it_prepares_content_diff_with_attached_files()
    {
        // テストデータの準備
        $columnDefinesForTest = [
            ['id' => 0, 'name' => 'File Column', 'type' => 'files', 'order' => 1],
        ];

        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => $columnDefinesForTest,
        ]);
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [0 => ['file_new_hash' => 'new_file.pdf']],
        ]);
        $ledger->define->column_define = collect([
            new ColumnDefine(['id' => 0, 'name' => 'File Column', 'type' => 'files', 'order' => 1]),
        ]);

        // 添付ファイルを作成
        AttachedFile::factory()->for($ledger)->create([
            'ledger_define_id' => $ledgerDefine->id,
            'hashedbasename' => 'file_new_hash',
            'ledger_id' => $ledger->id,
        ]);
        AttachedFile::factory()->for($ledger)->create([
            'ledger_define_id' => $ledgerDefine->id,
            'hashedbasename' => 'file_old_hash',
            'ledger_id' => $ledger->id, // 同じLedger IDだが、古いDiffにのみ関連
        ]);

        $oldDiff = LedgerDiff::factory()->for($ledger)->create([
            'ledger_id' => $ledger->id,
            'content' => [0 => ['file_old_hash' => 'old_file.pdf']],
            'column_define' => [['id' => 0, 'name' => 'File Column', 'type' => 'files', 'order' => 1]],
        ]);
        $ledger->latest_diff_id = $oldDiff->id + 1;
        $ledger->save();

        // 実行
        $result = $this->processor->prepareContentDiff($ledger, $ledger->define, $oldDiff);

        // 検証
        $this->assertTrue($result['hasChangedColumns']);
        $this->assertCount(1, $result['contentChanges']);
        $this->assertTrue($result['contentChanges'][0]['changed']); // Column 0 changed

        // 添付ファイルの検証
        $this->assertArrayHasKey('file_new_hash', $result['contentChanges'][0]['current_attachments']);
        $this->assertArrayHasKey('file_old_hash', $result['contentChanges'][0]['old_attachments']);
    }
}
