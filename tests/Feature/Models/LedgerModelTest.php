<?php

namespace Tests\Feature\Models;

use App\Enums\WorkflowStatus;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(Ledger::class)]
class LedgerModelTest extends TestCase
{
    // Mroonga依存スコープ (scopeSearch の全文検索) は LedgerMroongaTest.php に分離済み
    // ここでは DB依存だが Mroonga 不要のスコープ・メソッドをカバーする
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
    }

    // ----------------------------------------------------------------
    // 基本リレーション
    // ----------------------------------------------------------------

    public function test_define_relation(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);

        $this->assertInstanceOf(LedgerDefine::class, $ledger->define);
        $this->assertEquals($define->id, $ledger->define->id);
    }

    public function test_creator_relation(): void
    {
        $ledger = Ledger::factory()->create();
        $this->assertInstanceOf(User::class, $ledger->creator);
    }

    public function test_modifier_relation(): void
    {
        $ledger = Ledger::factory()->create();
        $this->assertInstanceOf(User::class, $ledger->modifier);
    }

    public function test_folder_relation(): void
    {
        $ledger = Ledger::factory()->create();
        // define をロードしてから folder プロパティで Folder を取得
        $ledger->load('define.folder');
        $this->assertInstanceOf(Folder::class, $ledger->define->folder);
    }

    public function test_is_locked_returns_false_for_non_approved(): void
    {
        $ledger = Ledger::factory()->create(['status' => WorkflowStatus::DRAFT]);
        $this->assertFalse($ledger->isLocked());
    }

    public function test_is_locked_returns_true_for_approved(): void
    {
        $ledger = Ledger::factory()->create(['status' => WorkflowStatus::APPROVED]);
        $this->assertTrue($ledger->isLocked());
    }

    // ----------------------------------------------------------------
    // scopeCreatedBetween
    // ----------------------------------------------------------------

    public function test_scope_created_between_filters_by_date_range(): void
    {
        $define = LedgerDefine::factory()->create();

        $old = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        // タイムスタンプ自動更新を避けて直接 DB 更新
        DB::table('ledgers')
            ->where('id', $old->id)
            ->update(['created_at' => '2020-01-01 00:00:00']);

        $recent = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        DB::table('ledgers')
            ->where('id', $recent->id)
            ->update(['created_at' => '2024-06-01 00:00:00']);

        $results = Ledger::where('ledger_define_id', $define->id)
            ->createdBetween(['2024-01-01', '2024-12-31'])
            ->get();

        $this->assertTrue($results->contains('id', $recent->id));
        $this->assertFalse($results->contains('id', $old->id));
    }

    public function test_scope_created_between_with_start_only(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        DB::table('ledgers')
            ->where('id', $ledger->id)
            ->update(['created_at' => '2025-01-01 00:00:00']);

        $results = Ledger::where('ledger_define_id', $define->id)
            ->createdBetween(['2024-01-01', null])
            ->get();

        $this->assertTrue($results->contains('id', $ledger->id));
    }

    public function test_scope_created_between_with_string_format(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        DB::table('ledgers')
            ->where('id', $ledger->id)
            ->update(['created_at' => '2024-06-15 00:00:00']);

        $results = Ledger::where('ledger_define_id', $define->id)
            ->createdBetween('2024-01-01,2024-12-31')
            ->get();

        $this->assertTrue($results->contains('id', $ledger->id));
    }

    // ----------------------------------------------------------------
    // scopeUpdatedBetween
    // ----------------------------------------------------------------

    public function test_scope_updated_between_filters_by_date_range(): void
    {
        $define = LedgerDefine::factory()->create();

        $old = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        DB::table('ledgers')
            ->where('id', $old->id)
            ->update(['updated_at' => '2020-01-01 00:00:00']);

        $recent = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        DB::table('ledgers')
            ->where('id', $recent->id)
            ->update(['updated_at' => '2024-08-01 00:00:00']);

        $results = Ledger::where('ledger_define_id', $define->id)
            ->updatedBetween(['2024-01-01', '2024-12-31'])
            ->get();

        $this->assertTrue($results->contains('id', $recent->id));
        $this->assertFalse($results->contains('id', $old->id));
    }

    public function test_scope_updated_between_with_string_format(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        DB::table('ledgers')
            ->where('id', $ledger->id)
            ->update(['updated_at' => '2024-09-01 00:00:00']);

        $results = Ledger::where('ledger_define_id', $define->id)
            ->updatedBetween('2024-01-01,2024-12-31')
            ->get();

        $this->assertTrue($results->contains('id', $ledger->id));
    }

    // ----------------------------------------------------------------
    // scopeFolderHierarchy
    // ----------------------------------------------------------------

    public function test_scope_folder_hierarchy_filters_by_folder(): void
    {
        $folderA = Folder::factory()->create();
        $folderB = Folder::factory()->create();
        $defineA = LedgerDefine::factory()->create(['folder_id' => $folderA->id]);
        $defineB = LedgerDefine::factory()->create(['folder_id' => $folderB->id]);

        $ledgerA = Ledger::factory()->create(['ledger_define_id' => $defineA->id]);
        $ledgerB = Ledger::factory()->create(['ledger_define_id' => $defineB->id]);

        $results = Ledger::folderHierarchy($folderA->id)->get();

        $this->assertTrue($results->contains('id', $ledgerA->id));
        $this->assertFalse($results->contains('id', $ledgerB->id));
    }

    public function test_scope_folder_hierarchy_with_empty_value_returns_all(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);

        $results = Ledger::folderHierarchy('')->get();

        $this->assertTrue($results->contains('id', $ledger->id));
    }

    // ----------------------------------------------------------------
    // scopeWithTags / scopeWithoutTags
    // ----------------------------------------------------------------

    public function test_scope_with_tags_filters_by_tag_name(): void
    {
        $define = LedgerDefine::factory()->create();
        $tag = Tag::factory()->create([
            'ledger_define_id' => $define->id,
            'name' => 'sprint6-tag-'.uniqid(),
        ]);

        $ledgerA = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        $ledgerB = Ledger::factory()->create([
            'ledger_define_id' => LedgerDefine::factory()->create()->id,
        ]);

        $results = Ledger::withTags([$tag->name])->get();

        $this->assertTrue($results->contains('id', $ledgerA->id));
        $this->assertFalse($results->contains('id', $ledgerB->id));
    }

    public function test_scope_without_tags_excludes_tagged(): void
    {
        $define = LedgerDefine::factory()->create();
        $tag = Tag::factory()->create([
            'ledger_define_id' => $define->id,
            'name' => 'exclude-tag-'.uniqid(),
        ]);
        $defineNoTag = LedgerDefine::factory()->create();

        $ledgerTagged = Ledger::factory()->create(['ledger_define_id' => $define->id]);
        $ledgerUntagged = Ledger::factory()->create(['ledger_define_id' => $defineNoTag->id]);

        $results = Ledger::withoutTags([$tag->name])->get();

        $this->assertFalse($results->contains('id', $ledgerTagged->id));
        $this->assertTrue($results->contains('id', $ledgerUntagged->id));
    }

    public function test_scope_with_tags_empty_returns_all(): void
    {
        $ledger = Ledger::factory()->create();
        $results = Ledger::withTags([])->get();
        $this->assertTrue($results->contains('id', $ledger->id));
    }

    public function test_scope_without_tags_empty_returns_all(): void
    {
        $ledger = Ledger::factory()->create();
        $results = Ledger::withoutTags([])->get();
        $this->assertTrue($results->contains('id', $ledger->id));
    }

    // ----------------------------------------------------------------
    // scopeSearch — 空文字は全件返す
    // ----------------------------------------------------------------

    public function test_scope_search_empty_string_returns_all_records(): void
    {
        $define = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);

        $results = Ledger::where('ledger_define_id', $define->id)->search('')->get();

        $this->assertTrue($results->contains('id', $ledger->id));
    }

    // ----------------------------------------------------------------
    // normalizeValueForSort
    // ----------------------------------------------------------------

    public function test_normalize_value_for_sort_number_positive(): void
    {
        $ledger = Ledger::factory()->make();
        $col = new ColumnDefine(0, 'num', 'number', 1, [], false, false, null, '', [], 1);

        $result = invokeMethod($ledger, 'normalizeValueForSort', [$col, '123.45']);
        $this->assertStringStartsWith('+', $result);
    }

    public function test_normalize_value_for_sort_number_negative(): void
    {
        $ledger = Ledger::factory()->make();
        $col = new ColumnDefine(0, 'num', 'number', 1, [], false, false, null, '', [], 1);

        $result = invokeMethod($ledger, 'normalizeValueForSort', [$col, '-50']);
        $this->assertStringStartsWith('-', $result);
    }

    public function test_normalize_value_for_sort_number_non_numeric_returns_spaces(): void
    {
        $ledger = Ledger::factory()->make();
        $col = new ColumnDefine(0, 'num', 'number', 1, [], false, false, null, '', [], 1);

        $result = invokeMethod($ledger, 'normalizeValueForSort', [$col, 'abc']);
        $this->assertEquals(str_repeat(' ', 32), $result);
    }

    public function test_normalize_value_for_sort_ymd(): void
    {
        $ledger = Ledger::factory()->make();
        $col = new ColumnDefine(0, 'date', 'YMD', 1, [], false, false, null, '', [], 1);

        $result = invokeMethod($ledger, 'normalizeValueForSort', [$col, '2024-06-15']);
        $this->assertEquals('2024-06-15', $result);
    }

    public function test_normalize_value_for_sort_ymd_invalid_returns_zero_date(): void
    {
        $ledger = Ledger::factory()->make();
        $col = new ColumnDefine(0, 'date', 'YMD', 1, [], false, false, null, '', [], 1);

        $result = invokeMethod($ledger, 'normalizeValueForSort', [$col, 'not-a-date']);
        $this->assertEquals('0000-00-00', $result);
    }

    public function test_normalize_value_for_sort_chk_true(): void
    {
        $ledger = Ledger::factory()->make();
        $col = new ColumnDefine(0, 'chk', 'chk', 1, [], false, false, null, '', [], 1);

        $result = invokeMethod($ledger, 'normalizeValueForSort', [$col, true]);
        $this->assertEquals('1', $result);
    }

    public function test_normalize_value_for_sort_chk_false(): void
    {
        $ledger = Ledger::factory()->make();
        $col = new ColumnDefine(0, 'chk', 'chk', 1, [], false, false, null, '', [], 1);

        $result = invokeMethod($ledger, 'normalizeValueForSort', [$col, false]);
        $this->assertEquals('0', $result);
    }

    public function test_normalize_value_for_sort_null_returns_empty(): void
    {
        $ledger = Ledger::factory()->make();
        $col = new ColumnDefine(0, 'txt', 'text', 1, [], false, false, null, '', [], 1);

        $result = invokeMethod($ledger, 'normalizeValueForSort', [$col, null]);
        $this->assertEquals('', $result);
    }

    public function test_normalize_value_for_sort_text(): void
    {
        $ledger = Ledger::factory()->make();
        $col = new ColumnDefine(0, 'txt', 'text', 1, [], false, false, null, '', [], 1);

        $result = invokeMethod($ledger, 'normalizeValueForSort', [$col, 'Hello World']);
        $this->assertEquals('Hello World', $result);
    }

    public function test_normalize_value_for_sort_files_array(): void
    {
        $ledger = Ledger::factory()->make();
        $col = new ColumnDefine(0, 'f', 'files', 1, [], false, false, null, '', [], 1);

        $result = invokeMethod($ledger, 'normalizeValueForSort', [$col, ['document.pdf', 'other.pdf']]);
        $this->assertStringContainsString('document', $result);
    }

    public function test_normalize_value_for_sort_files_empty(): void
    {
        $ledger = Ledger::factory()->make();
        $col = new ColumnDefine(0, 'f', 'files', 1, [], false, false, null, '', [], 1);

        $result = invokeMethod($ledger, 'normalizeValueForSort', [$col, []]);
        $this->assertEquals('', $result);
    }
}

/**
 * protected メソッドを呼び出すヘルパー
 */
function invokeMethod(object $obj, string $method, array $args = []): mixed
{
    $ref = new \ReflectionMethod($obj, $method);
    $ref->setAccessible(true);

    return $ref->invokeArgs($obj, $args);
}
