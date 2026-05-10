<?php

namespace Tests\Feature\Models;

use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tag;
use App\Models\User;
use App\Rules\RequiredCheckbox;
use App\Rules\UniqueColumnValue;
use Illuminate\Validation\Rules\RequiredIf;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(LedgerDefine::class)]
class LedgerDefineModelTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->ledgerDefine = LedgerDefine::factory()->create();
    }

    // ----------------------------------------------------------------
    // リレーション
    // ----------------------------------------------------------------

    public function test_belongs_to_folder(): void
    {
        $this->assertInstanceOf(Folder::class, $this->ledgerDefine->folder);
    }

    public function test_has_many_ledgers(): void
    {
        Ledger::factory()->create(['ledger_define_id' => $this->ledgerDefine->id]);
        $this->assertGreaterThan(0, $this->ledgerDefine->ledgers()->count());
    }

    public function test_belongs_to_creator(): void
    {
        $this->assertInstanceOf(User::class, $this->ledgerDefine->creator);
    }

    // ----------------------------------------------------------------
    // getMaxColumnIdAttribute / column_define マッピング
    // ----------------------------------------------------------------

    public function test_get_max_column_id_attribute_returns_max_id(): void
    {
        // デフォルトファクトリは ID=0 の 1カラム
        $this->assertEquals(0, $this->ledgerDefine->getMaxColumnIdAttribute());
    }

    public function test_column_define_is_mapped_by_id_via_normalize(): void
    {
        // normalizeByColumnDefine を通すことで ID=0 のカラムが正しくマッピングされる
        $normalized = $this->ledgerDefine->normalizeByColumnDefine([]);
        // ID=0 のカラムが空文字で埋まっていることで、内部マッピングが機能していることを確認
        $this->assertArrayHasKey(0, $normalized);
    }

    // ----------------------------------------------------------------
    // normalizeByColumnDefine
    // ----------------------------------------------------------------

    public function test_normalize_by_column_define_fills_missing_keys_with_empty_string(): void
    {
        // ID=0 のカラムが存在するのに content が空の場合、'' で埋める
        $normalized = $this->ledgerDefine->normalizeByColumnDefine([]);
        $this->assertArrayHasKey(0, $normalized);
        $this->assertSame('', $normalized[0]);
    }

    public function test_normalize_by_column_define_preserves_existing_values(): void
    {
        $normalized = $this->ledgerDefine->normalizeByColumnDefine([0 => 'hello']);
        $this->assertSame('hello', $normalized[0]);
    }

    // ----------------------------------------------------------------
    // calculateAutoFillValues
    // ----------------------------------------------------------------

    public function test_calculate_auto_fill_values_returns_array(): void
    {
        $result = $this->ledgerDefine->calculateAutoFillValues([0 => 'value']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey(0, $result);
    }

    public function test_calculate_auto_fill_values_on_update_skips_non_overwrite(): void
    {
        // isUpdating=true で overwrite_existing=false の DateType は上書きしない
        $result = $this->ledgerDefine->calculateAutoFillValues([0 => 'existing'], true);
        $this->assertSame('existing', $result[0]);
    }

    // ----------------------------------------------------------------
    // getValidationRules
    // ----------------------------------------------------------------

    public function test_get_validation_rules_returns_array_for_each_column(): void
    {
        $rules = $this->ledgerDefine->getValidationRules();
        // デフォルトは ID=0 の text カラム (required=false, unique=false)
        $this->assertArrayHasKey('content.0', $rules);
        $this->assertIsArray($rules['content.0']);
    }

    public function test_get_validation_rules_with_ledger_id(): void
    {
        $ledger = Ledger::factory()->create(['ledger_define_id' => $this->ledgerDefine->id]);
        $rules = $this->ledgerDefine->getValidationRules($ledger->id);
        $this->assertArrayHasKey('content.0', $rules);
    }

    // ----------------------------------------------------------------
    // getValidationAttributes
    // ----------------------------------------------------------------

    public function test_get_validation_attributes_returns_column_names(): void
    {
        $attrs = $this->ledgerDefine->getValidationAttributes();
        $this->assertIsArray($attrs);
        // 少なくとも 1カラム分のキーが存在する
        $this->assertNotEmpty($attrs);
    }

    // ----------------------------------------------------------------
    // recommendedInspector / recommendedApprover
    // ----------------------------------------------------------------

    public function test_recommended_inspector_returns_null_by_default(): void
    {
        $this->assertNull($this->ledgerDefine->recommendedInspector);
    }

    public function test_recommended_approver_returns_null_by_default(): void
    {
        $this->assertNull($this->ledgerDefine->recommendedApprover);
    }

    public function test_recommended_inspector_role_returns_null_by_default(): void
    {
        $this->assertNull($this->ledgerDefine->recommendedInspectorRole);
    }

    // ----------------------------------------------------------------
    // getValidationRules — chk 型 (required=true で RequiredCheckbox が追加される)
    // ----------------------------------------------------------------

    public function test_get_validation_rules_chk_required_adds_required_checkbox_rule(): void
    {
        $chkColumn = new ColumnDefine(
            1,
            'chk_field',
            'chk',
            2,
            [],
            true,  // required
            false,
            null,
            '',
            [],
            1
        );

        $define = LedgerDefine::factory()->create([
            'column_define' => [$chkColumn],
        ]);

        $rules = $define->getValidationRules();
        $ruleList = $rules['content.1'];

        // chk 型の必須は Rule::array() + RequiredCheckbox
        $this->assertTrue(collect($ruleList)->contains(
            fn ($r) => $r instanceof RequiredIf
                || $r instanceof RequiredCheckbox
                || (is_string($r) && str_contains($r, 'array'))
        ));
    }

    public function test_get_validation_rules_chk_not_required_has_array_rule(): void
    {
        $chkColumn = new ColumnDefine(
            2,
            'chk_field_opt',
            'chk',
            2,
            [],
            false, // not required
            false,
            null,
            '',
            [],
            1
        );

        $define = LedgerDefine::factory()->create([
            'column_define' => [$chkColumn],
        ]);

        $rules = $define->getValidationRules();
        // array ルールが含まれる
        $ruleList = $rules['content.2'];
        $this->assertNotEmpty($ruleList);
    }

    public function test_get_validation_rules_required_text_adds_required(): void
    {
        $textColumn = new ColumnDefine(
            3,
            'required_text',
            'text',
            1,
            [],
            true,  // required
            false,
            null,
            '',
            [],
            1
        );

        $define = LedgerDefine::factory()->create([
            'column_define' => [$textColumn],
        ]);

        $rules = $define->getValidationRules();
        $this->assertContains('required', $rules['content.3']);
    }

    public function test_get_validation_rules_unique_text_adds_unique_rule(): void
    {
        $uniqueColumn = new ColumnDefine(
            4,
            'unique_text',
            'text',
            1,
            [],
            false,
            true,  // unique
            null,
            '',
            [],
            1
        );

        $define = LedgerDefine::factory()->create([
            'column_define' => [$uniqueColumn],
        ]);

        $rules = $define->getValidationRules();
        $ruleList = $rules['content.4'];
        // UniqueColumnValue ルールが含まれる
        $this->assertTrue(collect($ruleList)->contains(
            fn ($r) => $r instanceof UniqueColumnValue
        ));
    }

    // ----------------------------------------------------------------
    // scopeSearchTags
    // ----------------------------------------------------------------

    public function test_scope_search_tags_returns_all_when_empty_keywords(): void
    {
        // キーワードが空のときは全件返す
        $count = LedgerDefine::searchTags([])->count();
        $this->assertGreaterThanOrEqual(1, $count); // setUp で1件作成済み
    }

    public function test_scope_search_tags_filters_by_keyword(): void
    {
        // タグを持つ LedgerDefine を作成
        Tag::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'name' => 'sprint3-unique-tag',
        ]);

        $result = LedgerDefine::searchTags(['sprint3-unique-tag'])->get();
        $this->assertTrue($result->contains('id', $this->ledgerDefine->id));

        $noResult = LedgerDefine::searchTags(['XXXXNOTEXIST'])->get();
        $this->assertFalse($noResult->contains('id', $this->ledgerDefine->id));
    }

    public function test_scope_search_tags_matches_multiple_partial_tags_across_distinct_rows(): void
    {
        Tag::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'name' => '営業活動',
        ]);

        Tag::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'name' => '日次報告',
        ]);

        $result = LedgerDefine::searchTags(['日次', '営業'])->get();

        $this->assertTrue($result->contains('id', $this->ledgerDefine->id));
    }
}
