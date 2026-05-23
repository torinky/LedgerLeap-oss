<?php

namespace Tests\Feature\Ledger;

use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Services\Ledger\LedgerDiffProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerDiffProcessorDefensiveTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    private LedgerDiffProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new LedgerDiffProcessor;
    }

    #[Test]
    public function it_handles_invalid_column_define_string_in_ledger_diff(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'column_define' => [
                ['id' => 1, 'name' => 'Column 1', 'type' => 'text', 'order' => 1],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['Value 1'],
        ]);

        // 不正な column_define (JSON エンコードされた空文字列) を持つ Diff を作成
        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['Value 1'],
        ]);

        // キャストを介さずに直接DBを更新して不正なデータ状態を作る
        \DB::table('ledger_diffs')->where('id', $diff->id)->update([
            'column_define' => '"\"\""',
        ]);

        // 修正前はこの呼び出しで "Call to a member function toArray() on string" エラーが発生するはず
        // 比較対象を null にし、baseDiffId として不正なデータを持つ diff を指定することで map ブロックを実行させる
        $result = $this->processor->prepareContentDiff($ledger, null, $diff->id);

        $this->assertIsArray($result);
    }
}
