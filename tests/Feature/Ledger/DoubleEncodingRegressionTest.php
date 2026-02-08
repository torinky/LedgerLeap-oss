<?php

namespace Tests\Feature\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class DoubleEncodingRegressionTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected Tenant $tenant;
    protected User $user;
    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->tenant = Tenant::first() ?? Tenant::create(['id' => 'test-tenant-' . uniqid()]);
        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create();
        $this->be($this->user);

        // Files 型と Checkbox 型を含む台帳定義を作成
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                ['id' => 0, 'name' => 'Files', 'type' => 'files', 'order' => 1],
                ['id' => 1, 'name' => 'Checkboxes', 'type' => 'chk', 'options' => ['A', 'B'], 'order' => 2],
                ['id' => 2, 'name' => 'Text', 'type' => 'text', 'order' => 3],
            ],
        ]);
    }

    /**
     * 添付ファイルやチェックボックスが二重エンコード（文字列化されたJSONをさらにエンコード）
     * されていないことを検証する。
     */
    #[Test]
    public function it_does_not_double_encode_json_columns_in_database()
    {
        $content = [
            0 => ['hash1' => 'file1.pdf'], // Files
            1 => ['A'],                   // Checkboxes
            2 => 'Normal Text',           // Text
        ];

        // LedgerService::saveDirectly を介さず、モデルに直接セットして保存
        // (キャストの set 挙動をまず検証)
        $ledger = new Ledger([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => $content,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $ledger->save();

        // DB の生データを確認
        $rawContent = DB::table('ledgers')->where('id', $ledger->id)->value('content');

        // 二重エンコードされている場合、中身が文字列リテラルとして "\"[\"hash1\"...\"" のようになる。
        // 正常な場合、JSON配列の一部として ["hash1", ...] または ___serialized___ 形式が含まれる。

        // AsColumnArrayJson は 2階層目を ___serialized___化する仕様なので、
        // files[0] の中身は serialize されたデータであるべき。
        $this->assertStringContainsString('___serialized___', $rawContent);

        // 最も重要なのは、json_decode($rawContent) した結果の要素が「文字列」ではなく「期待される構造」であること。
        $decoded = json_decode($rawContent, true);

        // content[0] (files) は AsColumnArrayJson により '___serialized___' + serialize(['hash1' => 'file1.pdf']) になっているはず
        $this->assertStringStartsWith('___serialized___', $decoded[0]);
        $this->assertEquals(['hash1' => 'file1.pdf'], unserialize(substr($decoded[0], 16)));

        // content[1] (chk) も同様
        $this->assertStringStartsWith('___serialized___', $decoded[1]);
        $this->assertEquals(['A'], unserialize(substr($decoded[1], 16)));
    }

    /**
     * calculateAutoFillValues を経由しても二重エンコードが発生しないことを検証する。
     */
    #[Test]
    public function it_prevents_double_encoding_via_calculate_auto_fill_values()
    {
        $content = [
            0 => ['hash2' => 'file2.pdf'],
            1 => ['B'],
            2 => 'Another Text',
        ];

        // サービス層の保存処理をシミュレート
        $processedContent = $this->ledgerDefine->calculateAutoFillValues($content);

        // 以前の不具合では、ここで convertToText が呼ばれ、$processedContent[0] が JSON文字列になっていた。
        // 修正後は、生の配列のままであるべき。
        $this->assertIsArray($processedContent[0], 'Files column should remain an array before casting');
        $this->assertIsArray($processedContent[1], 'Checkbox column should remain an array before casting');

        $ledger = Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => $processedContent,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        // モデルから取得した時に正しく配列として復元されるか
        $retrieved = Ledger::find($ledger->id);
        $this->assertIsArray($retrieved->content[0]);
        $this->assertEquals(['hash2' => 'file2.pdf'], $retrieved->content[0]);
        $this->assertIsArray($retrieved->content[1]);
        $this->assertEquals(['B'], $retrieved->content[1]);
    }

    /**
     * Mroonga 全文検索が機能しているか検証する。
     */
    #[Test]
    public function it_can_search_ledger_content_using_mroonga()
    {
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                ['id' => 0, 'name' => 'Text', 'type' => 'text', 'order' => 1],
            ],
        ]);

        $ledger = Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'TargetContent'],
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        // 直接クエリで確認
        $foundMatches = Ledger::where('id', $ledger->id)
            ->whereRaw('match(`content`) against (? IN BOOLEAN MODE)', ['Target'])
            ->exists();

        $this->assertTrue($foundMatches, 'Mroonga match should find "Target" in "TargetContent"');

        // scopeSearch を介して確認
        $scopeMatches = Ledger::search('Target')->where('id', $ledger->id)->exists();
        $this->assertTrue($scopeMatches, 'scopeSearch should find "Target" in "TargetContent"');
    }
}

