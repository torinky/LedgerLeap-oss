<?php

namespace App\Console\Commands;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Console\Command;

class CreateTestLedgerWithAttachment extends Command
{
    protected $signature = 'mcp:create-test-ledger';

    protected $description = 'Create a test ledger with attachment for structure analysis';

    public function handle()
    {
        $this->info('Creating test ledger with attachment...');

        // 既存の台帳定義を取得
        $ledgerDefine = LedgerDefine::first();
        if (! $ledgerDefine) {
            $this->error('❌ No LedgerDefine found. Please create one first.');

            return 1;
        }

        $this->line('Using LedgerDefine ID: '.$ledgerDefine->id.' ('.$ledgerDefine->title.')');

        // 添付ファイル付きのテスト台帳を作成
        $ledger = new Ledger;
        $ledger->ledger_define_id = $ledgerDefine->id;
        $ledger->creator_id = 1;
        $ledger->modifier_id = 1;
        $ledger->status = 'draft';
        $ledger->content = [0 => 'テスト台帳（添付ファイル構造確認用）'];

        // content_attachedにテストデータを設定（オブジェクトとして）
        $contentAttached = new \stdClass;
        $contentAttached->{'1'} = [
            'test_hash_12345' => [
                'name' => 'test_document.pdf',
                'path' => 'test/path/test_document.pdf',
                'size' => 102400,
                'mime' => 'application/pdf',
                // 実際のシステムではここにextracted_textなどのキーがあるはず
            ],
        ];
        $ledger->content_attached = $contentAttached;
        $ledger->save();

        $this->info('✅ Test ledger created successfully!');
        $this->line('   Ledger ID: '.$ledger->id);
        $this->line('   Content: '.$ledger->content[0]);
        $this->newLine();

        $this->info('Next step:');
        $this->line('  ./vendor/bin/sail artisan mcp:check-content-attached');

        return 0;
    }
}
