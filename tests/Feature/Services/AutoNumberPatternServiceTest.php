<?php

namespace Tests\Feature\Services;

use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Services\AutoNumberPatternService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * AutoNumberPatternService のユニット／フィーチャーテスト
 *
 * - generatePattern(): prefix/digits/revision から正規表現を生成する
 * - getPatterns(): 全テナントの auto_number カラム定義からパターン Collection を返す
 */
class AutoNumberPatternServiceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private AutoNumberPatternService $service;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        $this->tenant = $this->getTenant();

        $this->service = app(AutoNumberPatternService::class);
    }

    // ─────────────────────────────────────────────
    // generatePattern()
    // ─────────────────────────────────────────────

    #[Test]
    public function it_generates_pattern_from_prefix_and_digits(): void
    {
        $options = (object) ['prefix' => 'EQ-', 'digits' => 3, 'revision' => ''];

        $pattern = $this->service->generatePattern($options, false);

        // パターンが正規表現として有効
        $this->assertNotFalse(@preg_match($pattern, ''));
        // EQ-042 にマッチする
        $this->assertSame(1, preg_match($pattern, 'EQ-042', $m));
        $this->assertSame('EQ-042', $m[1]);
        // EQ-42 (2桁) にはマッチしない（digits=3 なので 3桁以上）
        $this->assertSame(0, preg_match($pattern, 'EQ-42'));
    }

    #[Test]
    public function it_generates_pattern_with_revision(): void
    {
        $options = (object) ['prefix' => 'DOC-', 'digits' => 4, 'revision' => '-R'];

        $pattern = $this->service->generatePattern($options, false);

        // DOC-0001-R にマッチする
        $this->assertSame(1, preg_match($pattern, 'DOC-0001-R', $m));
        $this->assertSame('DOC-0001-R', $m[1]);
        // 版記号なしはマッチしない
        $this->assertSame(0, preg_match($pattern, 'DOC-0001'));
    }

    #[Test]
    public function it_generates_pattern_for_unique_column(): void
    {
        $options = (object) ['prefix' => 'WO-', 'digits' => 3, 'revision' => ''];

        $pattern = $this->service->generatePattern($options, true);

        // unique=true の場合、後続文字があってもマッチする（.*? で任意文字許容）
        $this->assertSame(1, preg_match($pattern, 'WO-099-追記あり', $m));
        $this->assertStringStartsWith('WO-', $m[1]);
    }

    // ─────────────────────────────────────────────
    // getPatterns()
    // ─────────────────────────────────────────────

    #[Test]
    public function it_returns_patterns_collection_for_auto_number_columns(): void
    {
        $folder = Folder::factory()->create();
        LedgerDefine::factory()
            ->for($folder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, '設備番号', 'auto_number', 1),
                    new ColumnDefine(1, 'タイトル', 'text', 2),
                ],
            ]);

        // キャッシュをクリアして確実に DB から取得
        Cache::tags(['auto_links'])->flush();

        $patterns = $this->service->getPatterns();

        $this->assertGreaterThan(0, $patterns->count());
        // 各エントリに必要なキーが揃っている
        $first = $patterns->first();
        $this->assertArrayHasKey('pattern', $first);
        $this->assertArrayHasKey('column_name', $first);
        $this->assertArrayHasKey('define_id', $first);
        $this->assertArrayHasKey('define_title', $first);
        // text 型は含まれない
        $columnNames = $patterns->pluck('column_name')->all();
        $this->assertContains('設備番号', $columnNames);
        $this->assertNotContains('タイトル', $columnNames);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_auto_number_columns(): void
    {
        $folder = Folder::factory()->create();
        LedgerDefine::factory()
            ->for($folder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, '件名', 'text', 1),
                    new ColumnDefine(1, '内容', 'textarea', 2),
                ],
            ]);

        Cache::tags(['auto_links'])->flush();

        $patterns = $this->service->getPatterns();

        $this->assertCount(0, $patterns);
    }

    #[Test]
    public function it_caches_patterns_on_second_call(): void
    {
        $folder = Folder::factory()->create();
        LedgerDefine::factory()
            ->for($folder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, '管理番号', 'auto_number', 1),
                ],
            ]);

        Cache::tags(['auto_links'])->flush();

        // 1 回目: DB から取得してキャッシュに保存
        $first = $this->service->getPatterns();

        // 2 回目: キャッシュから取得（同一インスタンスが返る）
        $second = $this->service->getPatterns();

        $this->assertEquals($first->toArray(), $second->toArray());
    }
}
