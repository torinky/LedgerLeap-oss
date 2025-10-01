<?php

namespace Tests\Unit;

use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Facades\Tenancy;
use Tests\TestCase;

class FolderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用のユーザーを作成
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // テナントAを作成
        $tenantA = Tenant::factory()->create(['id' => 'tenantA', 'name' => 'Tenant A']);
        $tenantA->run(function () {
            // テナントAのフォルダと台帳定義を作成
            $folderA1 = Folder::factory()->create(['title' => 'Folder A1', 'creator_id' => 1, 'modifier_id' => 1]);
            $folderA2 = Folder::factory()->create(['title' => 'Folder A2', 'parent_id' => $folderA1->id, 'creator_id' => 1, 'modifier_id' => 1]);
            LedgerDefine::factory()->create(['title' => 'Ledger A1', 'folder_id' => $folderA1->id, 'creator_id' => 1, 'modifier_id' => 1]);
            LedgerDefine::factory()->create(['title' => 'Ledger A2', 'folder_id' => $folderA2->id, 'creator_id' => 1, 'modifier_id' => 1]);
        });

        // テナントBを作成
        $tenantB = Tenant::factory()->create(['id' => 'tenantB', 'name' => 'Tenant B']);
        $tenantB->run(function () {
            // テナントBのフォルダと台帳定義を作成
            $folderB1 = Folder::factory()->create(['title' => 'Folder B1', 'creator_id' => 1, 'modifier_id' => 1]);
            LedgerDefine::factory()->create(['title' => 'Ledger B1', 'folder_id' => $folderB1->id, 'creator_id' => 1, 'modifier_id' => 1]);
        });
    }

    #[Test]
    public function descendant_ledger_defines_count_respects_tenant_scope()
    {
        // テナントAに切り替えてテスト
        $tenantA = Tenant::find('tenantA');
        $tenantA->run(function () {
            $folderA1 = Folder::where('title', 'Folder A1')->first();
            $this->assertNotNull($folderA1);
            $this->assertEquals(2, $folderA1->descendantLedgerDefinesCount()); // Folder A1 と Folder A2 の LedgerDefine をカウント
        });

        // テナントBに切り替えてテスト
        $tenantB = Tenant::find('tenantB');
        $tenantB->run(function () {
            $folderB1 = Folder::where('title', 'Folder B1')->first();
            $this->assertNotNull($folderB1);
            $this->assertEquals(1, $folderB1->descendantLedgerDefinesCount()); // Folder B1 の LedgerDefine をカウント
        });

        // 中央コンテキストに戻ってテスト (テナントスコープが適用されないことを確認)
        Tenancy::end();
        $this->assertNull(tenant()); // 中央コンテキストであることを確認
    }

    #[Test]
    public function test_folder_has_correct_ledger_defines_in_tenant_scope()
    {
        // テナントAに切り替えてテスト
        $tenantA = Tenant::find('tenantA');
        $tenantA->run(function () {
            $folderA1 = Folder::where('title', 'Folder A1')->first();
            $this->assertNotNull($folderA1);
            $this->assertCount(1, $folderA1->ledgerDefines); // Folder A1 直下の LedgerDefine は1つ
            $this->assertEquals('Ledger A1', $folderA1->ledgerDefines->first()->title);

            $folderA2 = Folder::where('title', 'Folder A2')->first();
            $this->assertNotNull($folderA2);
            $this->assertCount(1, $folderA2->ledgerDefines); // Folder A2 直下の LedgerDefine は1つ
            $this->assertEquals('Ledger A2', $folderA2->ledgerDefines->first()->title);
        });

        // テナントBに切り替えてテスト
        $tenantB = Tenant::find('tenantB');
        $tenantB->run(function () {
            $folderB1 = Folder::where('title', 'Folder B1')->first();
            $this->assertNotNull($folderB1);
            $this->assertCount(1, $folderB1->ledgerDefines); // Folder B1 直下の LedgerDefine は1つ
            $this->assertEquals('Ledger B1', $folderB1->ledgerDefines->first()->title);
        });
    }
}
