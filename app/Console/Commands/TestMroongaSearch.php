<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\ColumnDefine;
use App\Models\Tenant;
use App\Models\Folder;
use App\Models\User;

class TestMroongaSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-mroonga-search';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Mroonga search test...');

        // テナントを初期化
        $tenant = \App\Models\Tenant::firstOrCreate(['id' => 'tinker_tenant_id'], ['id' => 'tinker_tenant_id']);
        tenancy()->initialize($tenant);
        $this->info('Tenant initialized: ' . $tenant->id);

        // テストユーザーを作成
        $user = \App\Models\User::factory()->create(); // tenant_id を削除
        $this->info('Test User created: ' . $user->id);

        // Folder を作成
        $folder = \App\Models\Folder::create([
            'tenant_id' => $tenant->id,
            'title' => 'Test Folder',
            'creator_id' => $user->id,
            'modifier_id' => $user->id, // modifier_id を追加
        ]);
        $this->info('Folder created: ' . $folder->id);

        // LedgerDefine を作成
        $ledgerDefine = \App\Models\LedgerDefine::factory()->create([
            'tenant_id' => $tenant->id,
            'folder_id' => $folder->id, // folder_id を追加
            'title' => 'Tinker Ledger',
            'column_define' => [
                new \App\Models\ColumnDefine([
                    'id' => 1,
                    'name' => 'unique_text',
                    'label' => 'Unique Text',
                    'type' => 'text',
                    'unique' => true,
                    'order' => 1,
                ]),
            ],
        ]);
        $this->info('LedgerDefine created: ' . $ledgerDefine->id);

        // Ledger を作成
        $ledger = \App\Models\Ledger::create([
            'tenant_id' => $tenant->id,
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['1' => 'unique-id-123'], // ColumnDefineのIDをキーとして使用
            'creator_id' => $user->id, // creator_id を追加
            'modifier_id' => $user->id, // modifier_id を追加
        ]);
        $this->info('Ledger created: ' . $ledger->id . ' with content: ' . json_encode($ledger->content));

        // 検索を実行
        $query = 'unique-id-123';
        $results = \App\Models\Ledger::whereRaw('match(`content`) against (? IN BOOLEAN MODE)', [$query])->get();

        $this->info('Search results count: ' . $results->count());
        foreach ($results as $result) {
            $this->info('Found Ledger ID: ' . $result->id . ' Tenant ID: ' . $result->tenant_id . ' Content: ' . json_encode($result->content));
        }

        // テナントを終了 (クリーンアップ)
        tenancy()->end();
        $tenant->delete();
        $this->info('Tenant cleaned up.');

        $this->info('Mroonga search test finished.');
    }
}
