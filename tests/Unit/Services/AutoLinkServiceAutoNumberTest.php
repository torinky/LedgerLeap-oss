<?php

namespace Tests\Unit\Services;

use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Services\AutoLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AutoLinkServiceAutoNumberTest extends TestCase
{
    use RefreshDatabase;

    protected AutoLinkService $service;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // テナントを作成して初期化（既存テストのパターンに従う）
        $this->tenant = Tenant::factory()->create(['id' => 'test-tenant']);
        tenancy()->initialize($this->tenant);

        $this->service = app(AutoLinkService::class);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_it_generates_correct_pattern_for_auto_number_with_prefix_and_digits()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateAutoNumberPattern');
        $method->setAccessible(true);

        $options = (object) [
            'prefix' => 'DOC-',
            'digits' => 3,
            'revision' => '',
        ];

        $pattern = $method->invoke($this->service, $options, false);

        // パターンが正しくマッチするか確認
        $this->assertEquals(1, preg_match($pattern, 'DOC-001'));
        $this->assertEquals(1, preg_match($pattern, 'DOC-123'));
        $this->assertEquals(1, preg_match($pattern, 'DOC-0001')); // 桁数超過もOK
        $this->assertEquals(0, preg_match($pattern, 'NOTMATCH-001'));
    }

    public function test_it_generates_correct_pattern_for_unique_auto_number()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateAutoNumberPattern');
        $method->setAccessible(true);

        $options = (object) [
            'prefix' => 'SPEC-',
            'digits' => 4,
            'revision' => '-A',
        ];

        $pattern = $method->invoke($this->service, $options, true); // unique=true

        // unique の場合、版記号は無視される
        $this->assertEquals(1, preg_match($pattern, 'SPEC-0001-A'));
        $this->assertEquals(1, preg_match($pattern, 'SPEC-0001-B'));
        $this->assertEquals(1, preg_match($pattern, 'SPEC-0001-XYZ'));
    }

    public function test_it_creates_virtual_auto_number_links_from_ledger_defines()
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'title' => 'テスト台帳',
            'column_define' => [
                [
                    'id' => 0,
                    'name' => '文書番号',
                    'type' => 'auto_number',
                    'order' => 0,
                    'options' => [
                        'prefix' => 'TEST-',
                        'digits' => 3,
                        'revision' => '',
                    ],
                    'unique' => false,
                ],
            ],
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getVirtualAutoNumberLinks');
        $method->setAccessible(true);

        $virtualLinks = $method->invoke($this->service);

        $this->assertGreaterThan(0, $virtualLinks->count());
        $matchingLink = $virtualLinks->first(function ($link) use ($ledgerDefine) {
            return str_contains($link->label, $ledgerDefine->title) && str_contains($link->label, '文書番号');
        });

        $this->assertNotNull($matchingLink);
        $this->assertEquals(-1000, $matchingLink->priority);
        $this->assertEquals('/l/$1', $matchingLink->url_template);
    }

    public function test_it_invalidates_cache_when_ledger_define_column_define_changes()
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
        ]);

        Cache::tags(['auto_links'])->put('test_key', 'test_value', 60);
        $this->assertEquals('test_value', Cache::tags(['auto_links'])->get('test_key'));

        // column_define を変更
        $ledgerDefine->column_define = [
            [
                'id' => 0,
                'name' => '仕様書番号',
                'type' => 'auto_number',
                'order' => 0,
                'options' => ['prefix' => 'NEW-', 'digits' => 4],
            ],
        ];
        $ledgerDefine->save();

        // キャッシュがクリアされたか確認
        $this->assertNull(Cache::tags(['auto_links'])->get('test_key'));
    }

    public function test_it_converts_standalone_auto_number_value_to_link()
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'title' => 'テスト台帳',
            'column_define' => [
                [
                    'id' => 0,
                    'name' => '文書番号',
                    'type' => 'auto_number',
                    'order' => 0,
                    'options' => [
                        'prefix' => 'TEST-',
                        'digits' => 3,
                        'revision' => '',
                    ],
                    'unique' => false,
                ],
            ],
        ]);

        $ledger = \App\Models\Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['TEST-001'],
        ]);

        // 自動ナンバリング値のみのテキストを変換
        $html = $this->service->convert('TEST-001', null, $ledger);

        $this->assertStringContainsString('<a href', $html);
        $this->assertStringContainsString('http://localhost/l/TEST-001', $html);
        $this->assertStringContainsString('TEST-001', $html);
    }

    public function test_it_converts_auto_number_value_at_text_boundary()
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'title' => 'テスト台帳',
            'column_define' => [
                [
                    'id' => 0,
                    'name' => '文書番号',
                    'type' => 'auto_number',
                    'order' => 0,
                    'options' => [
                        'prefix' => 'DOC-',
                        'digits' => 4,
                        'revision' => '',
                    ],
                    'unique' => false,
                ],
            ],
        ]);

        $ledger = \App\Models\Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['DOC-0001'],
        ]);

        // テキストの先頭に自動ナンバリング値がある場合
        $htmlStart = $this->service->convert('DOC-0001の修正', null, $ledger);
        $this->assertStringContainsString('<a href', $htmlStart);
        $this->assertStringContainsString('http://localhost/l/DOC-0001', $htmlStart);

        // テキストの末尾に自動ナンバリング値がある場合
        $htmlEnd = $this->service->convert('修正対象: DOC-0001', null, $ledger);
        $this->assertStringContainsString('<a href', $htmlEnd);
        $this->assertStringContainsString('http://localhost/l/DOC-0001', $htmlEnd);
    }

    public function test_it_handles_empty_string_parts_correctly()
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'title' => 'テスト台帳',
            'column_define' => [
                [
                    'id' => 0,
                    'name' => '番号',
                    'type' => 'auto_number',
                    'order' => 0,
                    'options' => [
                        'prefix' => 'NUM-',
                        'digits' => 2,
                        'revision' => '',
                    ],
                    'unique' => false,
                ],
            ],
        ]);

        $ledger = \App\Models\Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['NUM-01'],
        ]);

        // preg_split が空文字列を含む配列を返す場合でも正しく処理される
        $html = $this->service->convert('NUM-01', null, $ledger);

        // リンクが正しく生成されていることを確認
        $this->assertStringContainsString('<a href', $html);
        $this->assertStringContainsString('http://localhost/l/NUM-01', $html);
        $this->assertStringContainsString('NUM-01', $html);

        // リンクタグとテキストが正しく配置されている
        $this->assertStringContainsString('NUM-01</a>', $html);
    }
}
