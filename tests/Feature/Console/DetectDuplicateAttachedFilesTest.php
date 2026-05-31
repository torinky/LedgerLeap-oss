<?php

namespace Tests\Feature\Console;

use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

// 注: attached_files.hashedbasename には DB レベルの UNIQUE 制約があるため、
// 重複レコードを作成するテスト (detects_duplicate_hashedbasenames,
// fix_option_regenerates_hashedbasename_for_duplicates,
// dry_run_does_not_modify_records) は実行不可能です。
// SET unique_checks = 0 や raw insert でも InnoDB は制約を評価するため、
// このコマンドはレガシーデータ専用のユーティリティとして残し、
// テスト対象外とします。

class DetectDuplicateAttachedFilesTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $fakeQueue = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
    }

    private function tenantId(): string
    {
        return (string) tenant('id');
    }

    #[Test]
    public function no_duplicates_reports_success(): void
    {
        $tid = $this->tenantId();

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'unique1.pdf',
            'hashedbasename' => 'unique_hash_a.pdf',
            'mime' => 'application/pdf',
            'path' => "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/unique_hash_a.pdf",
            'size' => 1000,
            'status' => 'completed',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'unique2.pdf',
            'hashedbasename' => 'unique_hash_b.pdf',
            'mime' => 'application/pdf',
            'path' => "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/unique_hash_b.pdf",
            'size' => 2000,
            'status' => 'completed',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $this->artisan('attached-files:detect-duplicates')
            ->expectsOutput('=== 集計 ===')
            ->assertExitCode(0);
    }

}
