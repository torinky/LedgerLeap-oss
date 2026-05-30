<?php

namespace Tests\Unit;

use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Facades\Tenancy;
use Tests\TestCase;

/**
 * DatabaseMigrations を使うため、CI では専用の db-migrations ジョブで実行される。
 * RefreshDatabaseWithTenant と混在させると他テストの DB 状態を破壊するため分離が必要。
 * ローカルでは `./vendor/bin/sail test --group=database-migrations` で実行する。
 */
#[Group('database-migrations')]
class FolderTest extends TestCase
{
    use DatabaseMigrations;

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

    #[Test]
    public function descendant_count_calculates_correctly_with_nested_folders()
    {
        // テナントAに切り替えてテスト
        $tenantA = Tenant::find('tenantA');
        $tenantA->run(function () {
            // NestedSetのツリー構造を修復
            Folder::fixTree();

            $folderA1 = Folder::where('title', 'Folder A1')->first();
            $this->assertNotNull($folderA1);

            // Folder A1 は Folder A2 を1つ持っているので descendantCount は 1
            $this->assertEquals(1, $folderA1->descendantCount());

            $folderA2 = Folder::where('title', 'Folder A2')->first();
            $this->assertNotNull($folderA2);

            // Folder A2 は子フォルダーを持たないので descendantCount は 0
            $this->assertEquals(0, $folderA2->descendantCount());
        });
    }

    #[Test]
    public function descendant_count_returns_zero_for_leaf_folders()
    {
        // テナントBに切り替えてテスト
        $tenantB = Tenant::find('tenantB');
        $tenantB->run(function () {
            // NestedSetのツリー構造を修復
            Folder::fixTree();

            $folderB1 = Folder::where('title', 'Folder B1')->first();
            $this->assertNotNull($folderB1);

            // Folder B1 は子フォルダーを持たないので descendantCount は 0
            $this->assertEquals(0, $folderB1->descendantCount());
        });
    }

    #[Test]
    public function descendant_count_uses_correct_column_names()
    {
        // テナントAに切り替えてテスト
        $tenantA = Tenant::find('tenantA');
        $tenantA->run(function () {
            // NestedSetのツリー構造を修復
            Folder::fixTree();

            $folderA1 = Folder::where('title', 'Folder A1')->first();
            $this->assertNotNull($folderA1);

            // _lft と _rgt カラムが正しく設定されていることを確認
            $this->assertNotNull($folderA1->_lft);
            $this->assertNotNull($folderA1->_rgt);
            $this->assertGreaterThan($folderA1->_lft, $folderA1->_rgt);

            // descendantCount() が正しい値を返すことを確認
            $expectedCount = ($folderA1->_rgt - $folderA1->_lft - 1) / 2;
            $this->assertEquals($expectedCount, $folderA1->descendantCount());
        });
    }
}
