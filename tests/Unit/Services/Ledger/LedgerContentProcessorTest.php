<?php

namespace Tests\Unit\Services\Ledger;

use App\Models\AutoLink;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Services\AutoLinkService;
use App\Services\Ledger\ColumnHtmlService;
use App\Services\Ledger\LedgerContentProcessor;
use App\Services\Ledger\LedgerDiffProcessor;
use App\Services\Util\HtmlProcessorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerContentProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    protected Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = Folder::factory()->create();
    }

    private function makeColumnDefine(int $id, string $name, int $displayLevel, string $group = 'Group'): array
    {
        return [
            'id' => $id, 'name' => $name, 'type' => 'text', 'order' => $id,
            'display_level' => $displayLevel, 'group' => $group, 'required' => false, 'hint' => '',
        ];
    }

    #[Test]
    public function it_filters_and_groups_columns_correctly_without_diff(): void
    {
        // 1. Arrange: 依存サービスをモック
        $columnHtmlServiceMock = Mockery::mock(ColumnHtmlService::class);
        $columnHtmlServiceMock->shouldReceive('setAttachmentCollection')->andReturnSelf();
        $columnHtmlServiceMock->shouldReceive('setAttachmentContents')->andReturnSelf();
        $columnHtmlServiceMock->shouldReceive('show')->andReturnUsing(fn ($def, $val) => new HtmlString(strval($val)));

        $ledgerDiffProcessorMock = Mockery::mock(LedgerDiffProcessor::class);
        $ledgerDiffProcessorMock->shouldReceive('prepareContentDiff')
            ->andReturnUsing(function (Ledger $ledger) {
                $changes = [];
                foreach ($ledger->define->column_define as $column) {
                    $value = $ledger->content[$column->id] ?? null;
                    $changes[$column->id] = [
                        'status' => 'unchanged',
                        'current_value' => $value,
                        'old_value' => $value,
                    ];
                }

                return ['contentChanges' => $changes, 'hasChangedColumns' => false];
            });

        $processor = new LedgerContentProcessor($columnHtmlServiceMock, $ledgerDiffProcessorMock);

        // テストデータ準備
        $columnDefines = [
            $this->makeColumnDefine(1, 'Col 1', 1, 'Group A'),
            $this->makeColumnDefine(2, 'Col 2', 2, 'Group A'),
            $this->makeColumnDefine(3, 'Col 3', 2, 'Group B'),
            $this->makeColumnDefine(4, 'Col 4', 3, 'Group B'),
        ];
        $ledgerDefine = LedgerDefine::factory()->create(['column_define' => $columnDefines, 'folder_id' => $this->folder->id]);
        $ledger = Ledger::factory()->for($ledgerDefine, 'define')->create([
            'content' => [1 => 'A1', 2 => 'A2', 3 => 'B3', 4 => 'B4'],
        ]);

        // 2. Act: サービスを実行 (displayLevel = 2)
        $result = $processor->processContentForDisplay($ledger, null, 2, new \Illuminate\Database\Eloquent\Collection);
        $displayData = $result['displayData'];

        // 3. Assert: 結果を検証
        $this->assertFalse($result['hasChangedColumns']);
        $this->assertCount(2, $displayData); // 2つのグループがあるか

        // Group A の検証
        $this->assertEquals('Group A', $displayData[0]['group_name']);
        $this->assertCount(2, $displayData[0]['columns']); // 2つのカラムがあるか
        $this->assertEquals('Col 1', $displayData[0]['columns'][0]['name']);
        $this->assertEquals('Col 2', $displayData[0]['columns'][1]['name']);

        // Group B の検証
        $this->assertEquals('Group B', $displayData[1]['group_name']);
        $this->assertCount(1, $displayData[1]['columns']); // 1つのカラムがあるか
        $this->assertEquals('Col 3', $displayData[1]['columns'][0]['name']);

        // displayLevel = 3 の Col 4 が表示されないことを確認
        $this->assertFalse(collect($displayData[1]['columns'])->contains('name', 'Col 4'));
    }

    #[Test]
    public function it_identifies_changed_statuses_correctly(): void
    {
        // 1. Arrange
        // モックの準備
        $columnHtmlServiceMock = Mockery::mock(ColumnHtmlService::class);
        $columnHtmlServiceMock->shouldReceive('setAttachmentCollection')->andReturnSelf();
        $columnHtmlServiceMock->shouldReceive('setAttachmentContents')->andReturnSelf();
        $columnHtmlServiceMock->shouldReceive('show')->andReturnUsing(fn ($def, $val) => new HtmlString(strval($val)));

        // ★このテストの核心：LedgerDiffProcessorが返す値をコントロールする★
        $ledgerDiffProcessorMock = Mockery::mock(LedgerDiffProcessor::class);
        $mockContentChanges = [
            1 => ['status' => 'unchanged', 'current_value' => 'Same', 'old_value' => 'Same'],
            2 => ['status' => 'modified', 'current_value' => 'New', 'old_value' => 'Old'],
            3 => ['status' => 'deleted', 'current_value' => null, 'old_value' => 'Deleted'],
            4 => ['status' => 'added', 'current_value' => 'Added', 'old_value' => null],
        ];
        $ledgerDiffProcessorMock->shouldReceive('prepareContentDiff')
            ->andReturn([
                'contentChanges' => $mockContentChanges,
                'hasChangedColumns' => true,
            ]);

        $processor = new LedgerContentProcessor($columnHtmlServiceMock, $ledgerDiffProcessorMock);

        // テストデータ（台帳と定義）
        $columnDefines = [
            $this->makeColumnDefine(1, 'Unchanged', 1),
            $this->makeColumnDefine(2, 'Modified', 1),
            $this->makeColumnDefine(3, 'Deleted', 1),
            $this->makeColumnDefine(4, 'Added', 1),
        ];
        $ledgerDefine = LedgerDefine::factory()->create(['column_define' => $columnDefines, 'folder_id' => $this->folder->id]);
        $ledger = Ledger::factory()->for($ledgerDefine, 'define')->create();
        $diff = LedgerDiff::factory()->for($ledger)->create(); // 比較対象のDiff（内容はモックで上書きされる）

        // 2. Act
        $result = $processor->processContentForDisplay($ledger, $diff, 3, new \Illuminate\Database\Eloquent\Collection);
        $columns = $result['displayData'][0]['columns'];

        // 3. Assert
        $this->assertTrue($result['hasChangedColumns']);
        $this->assertCount(4, $columns);

        // 各カラムのステータスを検証
        $this->assertEquals('unchanged', $columns[0]['status']);
        $this->assertEquals('modified', $columns[1]['status']);
        $this->assertEquals('deleted', $columns[2]['status']);
        $this->assertEquals('added', $columns[3]['status']);
    }

    #[Test]
    public function it_handles_file_type_columns_correctly(): void
    {
        // 1. Arrange
        $fileHash = 'some_file_hash';
        $fileName = 'document.pdf';
        $expectedHtml = "<a href='/path/to/{$fileName}'>{$fileName}</a>";

        // ColumnHtmlServiceのモックを、ファイル処理に特化して設定
        $columnHtmlServiceMock = Mockery::mock(ColumnHtmlService::class);
        $columnHtmlServiceMock->shouldReceive('setAttachmentCollection')->andReturnSelf();
        $columnHtmlServiceMock->shouldReceive('setAttachmentContents')->andReturnSelf();
        $columnHtmlServiceMock->shouldReceive('show')
            ->twice()
            ->andReturn(new HtmlString($expectedHtml));

        // 他の依存はデフォルトの振る舞いでOK
        $ledgerDiffProcessorMock = Mockery::mock(LedgerDiffProcessor::class);
        $ledgerDiffProcessorMock->shouldReceive('prepareContentDiff')->andReturn(['contentChanges' => [
            1 => ['status' => 'unchanged', 'current_value' => [$fileHash => $fileName], 'old_value' => null],
        ], 'hasChangedColumns' => false]);

        $processor = new LedgerContentProcessor($columnHtmlServiceMock, $ledgerDiffProcessorMock);

        // ファイルカラムを持つテストデータ
        $columnDefines = [
            $this->makeColumnDefine(1, 'Attachment', 1, 'Files'),
        ];
        $ledgerDefine = LedgerDefine::factory()->create(['column_define' => $columnDefines, 'folder_id' => $this->folder->id]);
        $ledger = Ledger::factory()->for($ledgerDefine, 'define')->create([
            'content' => [1 => [$fileHash => $fileName]],
            'content_attached' => [1 => [$fileHash => ['name' => $fileName, 'path' => '...']]],
        ]);

        // 添付ファイルレコードを作成
        $attachment = \App\Models\AttachedFile::factory()->create(['hashedbasename' => $fileHash, 'filename' => $fileName]);
        $attachments = new \Illuminate\Database\Eloquent\Collection([$attachment]);

        // 2. Act
        $result = $processor->processContentForDisplay($ledger, null, 1, $attachments);
        $html = $result['displayData'][0]['columns'][0]['current_value_html'];

        // 3. Assert
        $this->assertEquals($expectedHtml, $html);
    }

    #[Test]
    public function it_highlights_keywords_in_html(): void
    {
        // 1. Arrange
        $keyword = 'highlight';

        // AutoLinkServiceはモック化し、ハイライト機能への影響をなくす
        $autoLinkServiceMock = Mockery::mock(AutoLinkService::class);
        $autoLinkServiceMock->shouldReceive('convert')->andReturnUsing(fn ($html, $def, $rec) => $html);
        $this->app->instance(AutoLinkService::class, $autoLinkServiceMock);

        // HtmlProcessorService をモック化し、ハイライト処理をシミュレート
        $htmlProcessorServiceMock = Mockery::mock(HtmlProcessorService::class);
        $expectedHtml = "Some text to <mark class='text-error font-bold text-lg'>{$keyword}</mark> here.";
        $htmlProcessorServiceMock->shouldReceive('processTextNodes')->andReturn($expectedHtml);
        $this->app->instance(HtmlProcessorService::class, $htmlProcessorServiceMock);

        // ColumnHtmlServiceは本物を使用
        $columnHtmlService = $this->app->make(ColumnHtmlService::class);

        // LedgerDiffProcessorはモック化
        $ledgerDiffProcessorMock = Mockery::mock(LedgerDiffProcessor::class);
        $ledgerDiffProcessorMock->shouldReceive('prepareContentDiff')->andReturnUsing(function (Ledger $ledger) {
            $changes = [];
            foreach ($ledger->define->column_define as $column) {
                $value = $ledger->content[$column->id] ?? null;
                $changes[$column->id] = ['status' => 'unchanged', 'current_value' => $value, 'old_value' => $value];
            }

            return ['contentChanges' => $changes, 'hasChangedColumns' => false];
        });

        $processor = new LedgerContentProcessor($columnHtmlService, $ledgerDiffProcessorMock);

        // テストデータ
        $columnDefines = [$this->makeColumnDefine(1, 'Text', 1)];
        $ledgerDefine = LedgerDefine::factory()->create(['column_define' => $columnDefines, 'folder_id' => $this->folder->id]);
        $ledger = Ledger::factory()->for($ledgerDefine, 'define')->create([
            'content' => [1 => "Some text to {$keyword} here."],
        ]);

        // 2. Act
        $result = $processor->processContentForDisplay($ledger, null, 1, new \Illuminate\Database\Eloquent\Collection, $keyword);
        $html = $result['displayData'][0]['columns'][0]['current_value_html'];

        // 3. Assert
        $this->assertStringContainsString("<mark class='text-error font-bold text-lg'>{$keyword}</mark>", $html);
    }

    #[Test]
    public function it_applies_auto_links_in_html(): void
    {
        // 1. Arrange
        $folder = $this->folder;
        $columnDefines = [$this->makeColumnDefine(1, 'Text', 1)];
        $ledgerDefine = LedgerDefine::factory()->for($folder)->create(['column_define' => $columnDefines]);
        $ledger = Ledger::factory()->for($ledgerDefine, 'define')->create([
            'content' => [1 => 'Please see DOC-123 for details.'],
        ]);

        // ★台帳が属するフォルダにスコープを限定したAutoLinkルールを作成★
        $autoLink = AutoLink::factory()->create([
            'pattern' => '/(DOC-\d{3})/',
            'url_template' => '/docs/$1',
            'is_enabled' => true,
        ]);
        // AutoLinkScope を直接作成し、AutoLink と Folder を関連付ける
        \App\Models\AutoLinkScope::create([
            'auto_link_id' => $autoLink->id,
            'scopeable_type' => (new Folder)->getMorphClass(),
            'scopeable_id' => $ledger->define->folder->id,
        ]);
        Cache::tags('auto_links')->flush();

        // 依存サービスのセットアップ
        $htmlProcessorServiceMock = Mockery::mock(HtmlProcessorService::class);
        $htmlProcessorServiceMock->shouldReceive('processTextNodes')->andReturnUsing(fn ($html, $callback) => $html);
        $this->app->instance(HtmlProcessorService::class, $htmlProcessorServiceMock);

        // AutoLinkService のモック
        $autoLinkServiceMock = Mockery::mock(AutoLinkService::class);
        $autoLinkServiceMock->shouldReceive('convert')
            ->andReturn('<a href="/docs/DOC-123">DOC-123</a>'); // 期待するHTMLを直接返す
        $this->app->instance(AutoLinkService::class, $autoLinkServiceMock);
        $columnHtmlService = $this->app->make(ColumnHtmlService::class);

        $ledgerDiffProcessorMock = Mockery::mock(LedgerDiffProcessor::class);
        $ledgerDiffProcessorMock->shouldReceive('prepareContentDiff')->andReturnUsing(function (Ledger $ledger) {
            $changes = [];
            foreach ($ledger->define->column_define as $column) {
                $value = $ledger->content[$column->id] ?? null;
                $changes[$column->id] = ['status' => 'unchanged', 'current_value' => $value, 'old_value' => $value];
            }

            return ['contentChanges' => $changes, 'hasChangedColumns' => false];
        });

        $processor = new LedgerContentProcessor($columnHtmlService, $ledgerDiffProcessorMock);

        // 2. Act
        $result = $processor->processContentForDisplay($ledger, null, 1, new \Illuminate\Database\Eloquent\Collection);
        $html = $result['displayData'][0]['columns'][0]['current_value_html'];

        // 3. Assert
        $this->assertStringContainsString('<a href="/docs/DOC-123"', $html);
    }

    #[Test]
    public function it_applies_auto_links_directly_without_context(): void
    {
        // 1. Arrange
        AutoLink::factory()->create([
            'pattern' => '/(DOC-\d{3})/',
            'url_template' => '/docs/$1',
            'is_enabled' => true,
        ]);
        Cache::tags('auto_links')->flush();

        $service = $this->app->make(AutoLinkService::class);
        $text = 'Please see DOC-123 for details.';

        // 2. Act: コンテキストなしでサービスを直接呼び出す
        $html = $service->convert($text);

        // 3. Assert
        $this->assertStringContainsString('<a href="/docs/DOC-123"', $html);
    }
}
