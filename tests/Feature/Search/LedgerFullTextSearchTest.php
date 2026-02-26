<?php

namespace Tests\Feature\Search;

use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mroonga 全文検索 統合テスト
 *
 * 全文検索が絡むテストは RefreshDatabase ではなく DatabaseMigrations を使用すること。
 * （docs/development/coding_standards.md 制約）
 *
 * テスト方針:
 *   - Ledger::scopeSearch() の MATCH() AGAINST() 実動作を検証
 *   - 単独インデックスに対して OR 結合でマルチカラム検索（Mroonga 制約準拠）
 *   - 日本語キーワード・空キーワード・特殊文字のエッジケース
 */
#[CoversClass(Ledger::class)]
class LedgerFullTextSearchTest extends TestCase
{
    use DatabaseMigrations;

    protected bool $tenancy = true;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // テキストカラムを持つ台帳定義を作成
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, 'タイトル', 'text', 1, [], false, false, 1, '', [], 1, null),
                new ColumnDefine(1, '説明', 'textarea', 2, [], false, false, 2, '', [], 1, null),
            ],
        ]);
    }

    // ================================================================
    // 基本的なキーワード検索
    // ================================================================

    #[Test]
    public function search_returns_ledger_matching_ascii_keyword(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'ProjectAlpha Document', 1 => 'This is a test ledger'],
        ]);
        // ヒットしないレコード
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'Unrelated Record', 1 => 'Nothing here'],
        ]);

        $results = Ledger::search('ProjectAlpha')->get();

        $this->assertTrue($results->contains('id', $ledger->id));
    }

    // ================================================================
    // 日本語キーワード検索
    // ================================================================

    #[Test]
    public function search_returns_ledger_matching_japanese_keyword(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '株式会社テスト', 1 => '日本語の説明文です'],
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '別の会社', 1 => '関係のないデータ'],
        ]);

        $results = Ledger::search('株式会社')->get();

        $this->assertTrue($results->contains('id', $ledger->id));
    }

    // ================================================================
    // content_attached カラムへの検索（OR 検索）
    // ================================================================

    #[Test]
    public function search_returns_ledger_matching_keyword_in_content_attached(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '普通のタイトル', 1 => '普通の説明'],
            'content_attached' => ['添付ファイルの内容はこちら SpecialKeyword99'],
        ]);

        $results = Ledger::search('SpecialKeyword99')->get();

        $this->assertTrue($results->contains('id', $ledger->id));
    }

    // ================================================================
    // 空キーワード → 全件返す（early return）
    // ================================================================

    #[Test]
    public function search_with_empty_keyword_returns_all_ledgers(): void
    {
        Ledger::factory()->count(3)->create([
            'ledger_define_id' => $this->ledgerDefine->id,
        ]);

        $results = Ledger::search('')->get();

        // 空キーワードは scopeSearch が早期 return するため全件が返る
        $this->assertGreaterThanOrEqual(3, $results->count());
    }

    // ================================================================
    // スペースのみキーワード → 全件返す
    // ================================================================

    #[Test]
    public function search_with_whitespace_only_returns_all_ledgers(): void
    {
        Ledger::factory()->count(2)->create([
            'ledger_define_id' => $this->ledgerDefine->id,
        ]);

        $results = Ledger::search('   ')->get();

        $this->assertGreaterThanOrEqual(2, $results->count());
    }

    // ================================================================
    // 複数キーワード（OR 検索）
    // ================================================================

    #[Test]
    public function search_with_multiple_keywords_uses_or_logic(): void
    {
        $ledgerA = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'Alpha Project', 1 => ''],
        ]);
        $ledgerB = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'Beta Project', 1 => ''],
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'Gamma Unrelated', 1 => ''],
        ]);

        // Mroonga では複数単語をスペース区切りにすると OR 検索
        $results = Ledger::search('Alpha Beta')->get();

        $this->assertTrue($results->contains('id', $ledgerA->id));
        $this->assertTrue($results->contains('id', $ledgerB->id));
    }

    // ================================================================
    // 存在しないキーワード → 0件
    // ================================================================

    #[Test]
    public function search_with_nonexistent_keyword_returns_empty(): void
    {
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'Normal Content', 1 => ''],
        ]);

        $results = Ledger::search('ZZZNOMATCH_XYZ_9999')->get();

        $this->assertCount(0, $results);
    }

    // ================================================================
    // LedgerService::searchLedgers() の統合確認
    // ================================================================

    #[Test]
    public function ledger_service_search_ledgers_returns_matching_results(): void
    {
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'ServiceSearch UniqueWord888', 1 => ''],
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'ServiceSearch OtherWord', 1 => ''],
        ]);

        /** @var \App\Services\LedgerService $service */
        $service = app(\App\Services\LedgerService::class);
        $results = $service->searchLedgers('UniqueWord888');

        $this->assertGreaterThanOrEqual(1, $results->count());
        $found = $results->first(fn ($l) => str_contains((string) ($l->content[0] ?? ''), 'UniqueWord888'));
        $this->assertNotNull($found, 'UniqueWord888 を含む台帳が検索結果に含まれること');
    }

    // ================================================================
    // 権限フィルタ付き全文検索（searchLedgersForApi）
    // ================================================================

    #[Test]
    public function search_for_api_with_keyword_respects_folder_permissions(): void
    {
        $folder = Folder::factory()->create();

        $define = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // 権限なしユーザーには見えないレコード
        Ledger::factory()->create([
            'ledger_define_id' => $define->id,
            'content' => [$define->column_define[0]->id ?? 0 => 'PermTestKeyword777'],
        ]);

        /** @var \App\Services\LedgerService $service */
        $service = app(\App\Services\LedgerService::class);

        // 権限なしユーザーには結果が返らないこと
        $result = $service->searchLedgersForApi($this->user, ['q' => 'PermTestKeyword777']);

        // フォルダ権限がないため 0 件
        $this->assertEquals(0, $result['total']);
    }
}
