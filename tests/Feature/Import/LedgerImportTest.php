<?php

namespace Tests\Feature\Import;

use App\Imports\LedgerImport;
use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(LedgerImport::class)]
class LedgerImportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // text/number カラムを持つ台帳定義
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '氏名', 'text', 1, [], false, false, 1, '', [], 1, null),
                new ColumnDefine(1, '年齢', 'number', 2, [], false, false, 2, '', [], 1, null),
            ],
        ]);
    }

    // ================================================================
    // model() — 新規作成ケース（id なし → insertRows カウント）
    // ================================================================

    #[Test]
    public function model_creates_new_ledger_when_id_is_empty(): void
    {
        $import = new LedgerImport($this->ledgerDefine);

        $row = [
            '[[[id]]]' => '',
            '[[[updated_at]]]' => '',
            '[[[created_at]]]' => '',
            '[[[modifier_id]]]' => '',
            '[[[creator_id]]]' => '',
            '氏名' => '田中 花子',
            '年齢' => '25',
        ];

        $ledger = $import->model($row);

        $this->assertInstanceOf(Ledger::class, $ledger);
        $this->assertEmpty($ledger->id);
        $this->assertEquals(1, $import->getRowCount());
    }

    // ================================================================
    // model() — 更新ケース（id あり → updateRows カウント）
    // ================================================================

    #[Test]
    public function model_updates_existing_ledger_when_id_is_provided(): void
    {
        $existing = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
        ]);

        $import = new LedgerImport($this->ledgerDefine, LedgerImport::MODE_UPDATE);

        $row = [
            '[[[id]]]' => (string) $existing->id,
            '[[[updated_at]]]' => now()->toDateTimeString(),
            '[[[created_at]]]' => now()->toDateTimeString(),
            '[[[modifier_id]]]' => (string) $this->user->id,
            '[[[creator_id]]]' => (string) $this->user->id,
            '氏名' => '山田 太郎',
            '年齢' => '30',
        ];

        $ledger = $import->model($row);

        // id が fillable 経由で正しくセットされていること（upsert のキー）
        $this->assertEquals((string) $existing->id, (string) $ledger->id);
        $this->assertEquals(1, $import->getRowCount());
        $this->assertEquals('山田 太郎', $ledger->content[0]);
    }

    // ================================================================
    // MODE_DESTOROY — 既存レコードを全削除してからインポート
    // ================================================================

    #[Test]
    public function constructor_with_mode_destroy_deletes_existing_records(): void
    {
        // 既存レコードを3件作成
        Ledger::factory()->count(3)->create([
            'ledger_define_id' => $this->ledgerDefine->id,
        ]);
        // 対象の台帳定義に紐づくレコードが3件あること
        $this->assertEquals(3, Ledger::where('ledger_define_id', $this->ledgerDefine->id)->count());

        // MODE_DESTOROY で初期化
        new LedgerImport($this->ledgerDefine, LedgerImport::MODE_DESTOROY);

        // 対象の台帳定義に紐づくレコードが全削除されていること
        $this->assertEquals(0, Ledger::where('ledger_define_id', $this->ledgerDefine->id)->count());
    }

    // ================================================================
    // MODE_INSERT — 新規作成モード（id があっても無視して挿入）
    // ================================================================

    #[Test]
    public function model_in_insert_mode_ignores_id_field(): void
    {
        $import = new LedgerImport($this->ledgerDefine, LedgerImport::MODE_INSERT);

        $row = [
            '氏名' => '鈴木 一郎',
            '年齢' => '40',
        ];

        $ledger = $import->model($row);

        // MODE_INSERT では id を参照しないため空になる
        $this->assertEmpty($ledger->id);
    }

    // ================================================================
    // generateLedgerContent() — コンテンツが正しくマッピングされること
    // ================================================================

    #[Test]
    public function model_generates_correct_content_from_row(): void
    {
        $import = new LedgerImport($this->ledgerDefine);

        $row = [
            '[[[id]]]' => '',
            '[[[updated_at]]]' => '',
            '[[[created_at]]]' => '',
            '[[[modifier_id]]]' => '',
            '[[[creator_id]]]' => '',
            '氏名' => '佐藤 次郎',
            '年齢' => '35',
        ];

        $ledger = $import->model($row);

        // content はカラムIDをキーにした配列になること（仕様: AsColumnArrayJson）
        $content = $ledger->content;
        $this->assertIsArray($content);
        // ColumnDefine の id(0,1) をキーとして値が格納されていること
        $this->assertArrayHasKey(0, $content);
        $this->assertArrayHasKey(1, $content);
        $this->assertEquals('佐藤 次郎', $content[0]);
    }

    // ================================================================
    // getCsvSettings() — CSVオプションが正しく返ること
    // ================================================================

    #[Test]
    public function get_csv_settings_returns_expected_keys(): void
    {
        $import = new LedgerImport($this->ledgerDefine);
        $settings = $import->getCsvSettings();

        $this->assertArrayHasKey('delimiter', $settings);
        $this->assertArrayHasKey('enclosure', $settings);
        $this->assertArrayHasKey('input_encoding', $settings);
        $this->assertEquals('"', $settings['enclosure']);
        $this->assertEquals('UTF-8', $settings['input_encoding']);
    }

    // ================================================================
    // batchSize / chunkSize
    // ================================================================

    #[Test]
    public function batch_and_chunk_size_returns_expected_values(): void
    {
        $import = new LedgerImport($this->ledgerDefine);

        $this->assertEquals(1000, $import->batchSize());
        $this->assertEquals(1000, $import->chunkSize());
    }

    // ================================================================
    // uniqueBy() — upsert キーの確認
    // ================================================================

    #[Test]
    public function unique_by_returns_correct_keys(): void
    {
        $import = new LedgerImport($this->ledgerDefine);

        $this->assertEquals(['id', 'content'], $import->uniqueBy());
    }

    // ================================================================
    // registerEvents() — BeforeImport でキャッシュにtotal_rowsが記録される
    // ================================================================

    #[Test]
    public function register_events_returns_before_and_after_import_handlers(): void
    {
        $import = new LedgerImport($this->ledgerDefine);
        $events = $import->registerEvents();

        $this->assertArrayHasKey(\Maatwebsite\Excel\Events\BeforeImport::class, $events);
        $this->assertArrayHasKey(\Maatwebsite\Excel\Events\AfterImport::class, $events);
    }

    // ================================================================
    // キャッシュ初期化 — constructor でキャッシュがクリアされること
    // ================================================================

    #[Test]
    public function constructor_clears_progress_cache(): void
    {
        $id = $this->ledgerDefine->id;
        Cache::forever("total_rows_{$id}", 99);
        Cache::forever("current_rows_{$id}", 50);

        new LedgerImport($this->ledgerDefine);

        $this->assertNull(Cache::get("total_rows_{$id}"));
        $this->assertNull(Cache::get("current_rows_{$id}"));
    }

    // ================================================================
    // 日本語・特殊文字の往復テスト
    // ================================================================

    #[Test]
    public function model_handles_japanese_and_special_characters(): void
    {
        $import = new LedgerImport($this->ledgerDefine);

        // modifier_id/creator_id を省略すると Auth::user()->id が使われる
        $row = [
            '[[[id]]]' => '',
            '[[[updated_at]]]' => '',
            '[[[created_at]]]' => '',
            '氏名' => '山田　花子（テスト）',  // 全角スペース・括弧
            '年齢' => '0',
        ];

        $ledger = $import->model($row);
        $ledger->save();

        $this->assertDatabaseHas('ledgers', ['id' => $ledger->id]);
        $this->assertEquals('山田　花子（テスト）', $ledger->content[0]);
    }

    // ================================================================
    // Export → Import 往復テスト
    // ================================================================

    #[Test]
    public function export_then_import_round_trip_preserves_data(): void
    {
        // 1. エクスポート元のレコードを作成
        $original = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [
                0 => '往復テスト太郎',
                1 => 42,
            ],
        ]);

        // 2. LedgerImport の model() で同じ id を持つ Ledger を組み立てる
        $import = new LedgerImport($this->ledgerDefine, LedgerImport::MODE_UPDATE);

        $row = [
            '[[[id]]]' => (string) $original->id,
            '[[[updated_at]]]' => $original->updated_at?->toDateTimeString() ?? '',
            '[[[created_at]]]' => $original->created_at?->toDateTimeString() ?? '',
            '[[[modifier_id]]]' => (string) $this->user->id,
            '[[[creator_id]]]' => (string) $this->user->id,
            '氏名' => '往復テスト太郎',
            '年齢' => '42',
        ];

        $ledger = $import->model($row);

        // 3. WithUpserts 相当：既存レコードを上書き更新する
        $original->fill([
            'content' => $ledger->content,
            'default_sort_value' => $ledger->default_sort_value,
        ])->save();

        // 4. データが保持されること
        $refreshed = Ledger::find($original->id);
        $this->assertNotNull($refreshed);
        $this->assertEquals('往復テスト太郎', $refreshed->content[0]);
        $this->assertEquals('42', (string) $refreshed->content[1]);
    }

    // ================================================================
    // エラーケース — 存在しないカラム名は null になること
    // ================================================================

    #[Test]
    public function model_handles_missing_column_gracefully(): void
    {
        $import = new LedgerImport($this->ledgerDefine);

        // 「氏名」カラムが欠けているデータ
        $row = [
            '[[[id]]]' => '',
            '[[[updated_at]]]' => '',
            '[[[created_at]]]' => '',
            '[[[modifier_id]]]' => '',
            '[[[creator_id]]]' => '',
            '年齢' => '20',
            // '氏名' は意図的に省略
        ];

        $ledger = $import->model($row);

        // クラッシュしないこと、かつ欠損カラムは空文字 or null になること
        $this->assertInstanceOf(Ledger::class, $ledger);
        // restoreColumnValueFromText(null) は '' を返す（text型の仕様）
        $this->assertEmpty($ledger->content[0]);
    }
}
