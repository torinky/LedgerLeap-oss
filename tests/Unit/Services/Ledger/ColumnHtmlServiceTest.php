<?php

use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Services\AutoLinkService;
use App\Services\Ledger\ColumnHtmlService;
use App\Services\Util\HtmlProcessorService;
use Illuminate\Support\HtmlString;
use Spatie\LaravelMarkdown\MarkdownRenderer;
use Tests\TestCase;

uses(TestCase::class);

it('column value is array', function () {
    $columnDefine = new ColumnDefine(
        1,
        'aaa',
        'chk',
        1,
        ['aaa', 'bbb', 'ccc'],
        false,
        false,
        null,
        '',
        [],
        3,
        null
    );

    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')->andReturnUsing(fn ($text) => $text);
    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockHtmlProcessor = mock(HtmlProcessorService::class);
    $mockHtmlProcessor->shouldReceive('processTextNodes')->andReturnUsing(fn ($html, $callback) => $html);

    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);
    $columnHtml->mount($columnDefine, ['aaa' => 'aaa', 'ccc' => 'ccc']);

    $result = $columnHtml->show($columnDefine, [
        'aaa' => true,
    ], true, [], '', false, null);

    $expectedHtml = '<div class="flex flex-wrap gap-1">'
        .'<span class="'.ColumnHtmlService::BADGE_CLASS_NAME.'">aaa</span>'
        .'</div>';
    expect($result->toHtml())->toBe($expectedHtml);
});

it('show returns empty string when no initial value', function () {
    $columnDefine = new ColumnDefine(
        1,
        'test_column',
        'text',
        1,
        [],
        false,
        false,
        null,
        '',
        [],
        3,
        null
    );

    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')->andReturnUsing(fn ($text) => $text);
    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockHtmlProcessor = mock(HtmlProcessorService::class);
    $mockHtmlProcessor->shouldReceive('processTextNodes')->andReturnUsing(fn ($html, $callback) => $html);

    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);
    $result = $columnHtml->show($columnDefine, null, true, [], '', false, null);

    expect($result->toHtml())->toBe('');
});

it('highlight keywords in html output', function () {
    $columnDefine = new ColumnDefine(
        1,
        'test_column',
        'text',
        1,
        [],
        false,
        false,
        null,
        '',
        [],
        3,
        null
    );
    $inputValue = 'This is a test content with keywords';
    $highlightKeyword = 'test';
    $expectedHtml = 'This is a <mark class="text-error font-bold text-lg">test</mark> content with keywords';

    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')->andReturn($inputValue);
    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockHtmlProcessor = mock(HtmlProcessorService::class);

    // Expect processTextNodes to be called and simulate its effect
    $mockHtmlProcessor->shouldReceive('processTextNodes')
        ->once()
        ->with($inputValue, Mockery::type(Closure::class))
        ->andReturn($expectedHtml); // For simplicity, we return the final expected HTML.

    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);

    $result = $columnHtml->show($columnDefine, $inputValue, true, [], '', false, null, $highlightKeyword);

    expect($result->toHtml())->toBe($expectedHtml);
});

it('renders textarea with markdown and applies auto links', function () {
    // 1. Setup
    $columnDefine = new ColumnDefine(
        1,
        'test_textarea',
        'textarea',
        1,
        [],
        false,
        false,
        null,
        '',
        [],
        3,
        null
    );

    $markdownInput = '**Hello** `World`! See ticket #123.';
    $htmlFromMarkdown = '<p><strong>Hello</strong> <code>World</code>! See ticket #123.</p>';
    $linkedHtml = '<p><strong>Hello</strong> <code>World</code>! See ticket <a href="/tickets/123">#123</a>.</p>';
    $finalHtml = '<div class="prose dark:prose-invert max-w-none"><div class="expandable-textarea-content">'.$linkedHtml.'</div></div>';

    // 2. Mocks
    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockMarkdownRenderer->shouldReceive('toHtml')
        ->with($markdownInput)
        ->andReturn($htmlFromMarkdown);

    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')
        ->with($htmlFromMarkdown, $columnDefine, null, null)
        ->andReturn($linkedHtml);

    $mockHtmlProcessor = mock(HtmlProcessorService::class);
    $mockHtmlProcessor->shouldReceive('processTextNodes')->andReturnUsing(fn ($html, $callback) => $html);

    // 3. Execution
    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);
    $result = $columnHtml->show($columnDefine, $markdownInput, true, [], '', false, null);

    // 4. Assertion
    expect($result->toHtml())->toBe($finalHtml);
});

it('renders auto_number with link', function () {
    // 1. Setup
    $columnDefine = new ColumnDefine([
        'id' => 1,
        'name' => 'Spec ID',
        'type' => 'auto_number',
        'order' => 1,
        'options' => [],
        'required' => false,
        'unique' => true,
        'sort_index' => null,
        'hint' => '',
        'file' => [],
        'display_level' => 3,
        'group' => null,
    ]);

    $inputValue = 'SPEC-001';
    $expectedLink = '<a href="/ledgers?query=SPEC-001">SPEC-001</a>'; // Simplified for clarity

    // $ledgerRecord = new Ledger(); // ← リレーションに触れてしまう可能性があるため使わない

    // 2. Mocks
    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')
        ->once()
        ->with(
            htmlspecialchars($inputValue, ENT_QUOTES, 'UTF-8'),
            $columnDefine,
            null, // ← record を null に変更
            null
        )
        ->andReturn($expectedLink);

    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockHtmlProcessor = mock(HtmlProcessorService::class);
    $mockHtmlProcessor->shouldReceive('processTextNodes')->andReturnUsing(fn ($html, $callback) => $html);

    // 3. Execution
    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);

    // tenantId を明示して record->define 参照を回避
    $tenantId = 'test_tenant_id';
    $result = $columnHtml->show($columnDefine, $inputValue, true, [], '', false, null, null, $tenantId);

    // 4. Assertion
    expect($result->toHtml())->toBe($expectedLink);
});

it('renders textarea with expandable content component', function () {
    // 1. Setup
    $columnDefine = new ColumnDefine(
        1,
        'test_textarea',
        'textarea',
        1,
        [],
        false,
        false,
        null,
        '',
        [],
        3,
        null
    );

    $markdownInput = 'This is a long text that should be wrapped in an expandable component.';
    $htmlFromMarkdown = '<p>This is a long text that should be wrapped in an expandable component.</p>';
    $linkedHtml = '<p>This is a long text that should be wrapped in an expandable component.</p>';

    // 2. Mocks
    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockMarkdownRenderer->shouldReceive('toHtml')
        ->with($markdownInput)
        ->andReturn($htmlFromMarkdown);

    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')
        ->with($htmlFromMarkdown, $columnDefine, null, null)
        ->andReturn($linkedHtml);

    $mockHtmlProcessor = mock(HtmlProcessorService::class);
    $mockHtmlProcessor->shouldReceive('processTextNodes')->andReturnUsing(fn ($html, $callback) => $html);

    // 3. Execution
    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);
    $result = $columnHtml->show($columnDefine, $markdownInput, true, [], '', false, null);

    // 4. Assertion - expandable-textarea-contentマーカーが含まれていることを確認
    expect($result->toHtml())
        ->toContain('expandable-textarea-content')
        ->toContain($linkedHtml);
});

// ================================================================
// show() — canView=false のとき空文字を返す
// ================================================================
it('show returns empty html when canView is false', function () {
    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockHtmlProcessor = mock(HtmlProcessorService::class);

    $columnDefine = new ColumnDefine(
        1, 'col', 'text', 1, [], false, false, null, '', [], 1, null
    );

    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);
    $result = $columnHtml->show($columnDefine, 'some value', false);

    expect($result->toHtml())->toBe('');
});

// ================================================================
// show() — type=select のとき SELECT_BADGE_CLASS_NAME でラップされる
// ================================================================
it('show wraps select value with select badge class', function () {
    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockHtmlProcessor = mock(HtmlProcessorService::class);

    $columnDefine = new ColumnDefine(
        1, 'col', 'select', 1, ['opt1', 'opt2'], false, false, null, '', [], 1, null
    );

    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);
    $result = $columnHtml->show($columnDefine, 'opt1', true);

    expect($result->toHtml())->toContain(ColumnHtmlService::SELECT_BADGE_CLASS_NAME);
});

// ================================================================
// show() — 配列渡し時に ColumnDefine オブジェクトに変換される
// ================================================================
it('show accepts array as column define data', function () {
    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')->andReturnUsing(fn ($text) => $text);
    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockHtmlProcessor = mock(HtmlProcessorService::class);

    // 配列形式で渡す
    $columnDefineArray = [
        'id' => 99,
        'name' => 'col',
        'type' => 'text',
        'required' => false,
        'options' => [],
        'order' => 1,
        'unique' => false,
    ];

    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);
    $result = $columnHtml->show($columnDefineArray, 'hello', true);

    // エラーなく処理されれば OK
    expect($result)->toBeInstanceOf(HtmlString::class);
});

// ================================================================
// getFileIconClass — private メソッドを Reflection でテスト
// ================================================================
it('getFileIconClass returns correct class for pdf', function () {
    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockHtmlProcessor = mock(HtmlProcessorService::class);

    $service = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);
    $ref = new ReflectionClass($service);
    $method = $ref->getMethod('getFileIconClass');
    $method->setAccessible(true);

    expect($method->invoke($service, 'document.pdf'))->toBe('fa-solid fa-file-pdf');
    expect($method->invoke($service, 'report.docx'))->toBe('fa-solid fa-file-word');
    expect($method->invoke($service, 'sheet.xlsx'))->toBe('fa-solid fa-file-excel');
    expect($method->invoke($service, 'slide.pptx'))->toBe('fa-solid fa-file-powerpoint');
    expect($method->invoke($service, 'archive.zip'))->toBe('fa-solid fa-file-archive');
    expect($method->invoke($service, 'notes.txt'))->toBe('fa-solid fa-file-lines');
    expect($method->invoke($service, 'photo.jpg'))->toBe('fa-solid fa-file-image');
    expect($method->invoke($service, 'sound.mp3'))->toBe('fa-solid fa-file-audio');
    expect($method->invoke($service, 'video.mp4'))->toBe('fa-solid fa-file-video');
    expect($method->invoke($service, 'script.php'))->toBe('fa-solid fa-file-code');
    expect($method->invoke($service, 'unknown.xyz'))->toBe('fa-solid fa-file');
});

// ================================================================
// getColumnDefineProperty — 配列からプロパティを取得するパス
// ================================================================
it('getColumnDefineProperty returns value from array column define', function () {
    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')->andReturnUsing(fn ($text) => $text);
    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockHtmlProcessor = mock(HtmlProcessorService::class);

    $columnDefineArray = [
        'id' => 42,
        'label' => 'test',
        'type' => 'text',
    ];

    $service = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);
    $service->mount($columnDefineArray, 'value');

    $ref = new ReflectionClass($service);
    $method = $ref->getMethod('getColumnDefineProperty');
    $method->setAccessible(true);

    // 配列から取得
    expect($method->invoke($service, 'id'))->toBe(42);
    // 存在しないキーはデフォルト値
    expect($method->invoke($service, 'nonexistent', 'default'))->toBe('default');
});

// ================================================================
// キャッシュ機能テスト
// ================================================================

it('caches textarea html and returns cached result on second call', function () {
    $columnDefine = new ColumnDefine(
        1, 'test_textarea', 'textarea', 1,
        [], false, false, null, '', [], 3, null
    );

    $markdownInput = '**Hello** World';
    $htmlFromMarkdown = '<p><strong>Hello</strong> World</p>';
    $linkedHtml = '<p><strong>Hello</strong> World</p>';

    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockMarkdownRenderer->shouldReceive('toHtml')
        ->once()
        ->with($markdownInput)
        ->andReturn($htmlFromMarkdown);

    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')
        ->once()
        ->andReturn($linkedHtml);

    $mockHtmlProcessor = mock(HtmlProcessorService::class);
    $mockHtmlProcessor->shouldReceive('processTextNodes')
        ->andReturnUsing(fn ($html, $callback) => $html);

    $ledger = new Ledger;
    $ledger->id = 1;
    $ledger->updated_at = now();
    $ledger->define = (object) ['tenant_id' => 'test-tenant'];

    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);

    // 1回目の呼び出し（キャッシュMISS）
    $result1 = $columnHtml->show($columnDefine, $markdownInput, true, [], '', false, $ledger);

    // 2回目の呼び出し（キャッシュHIT）
    $result2 = $columnHtml->show($columnDefine, $markdownInput, true, [], '', false, $ledger);

    expect($result1->toHtml())->toBe($result2->toHtml());
    expect($result1->toHtml())->toContain('expandable-textarea-content');
});

it('does not share cache across tenants', function () {
    $columnDefine = new ColumnDefine(
        1, 'test_textarea', 'textarea', 1,
        [], false, false, null, '', [], 3, null
    );

    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockMarkdownRenderer->shouldReceive('toHtml')
        ->twice()
        ->andReturn('<p>test</p>');

    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')
        ->twice()
        ->andReturn('<p>test</p>');

    $mockHtmlProcessor = mock(HtmlProcessorService::class);
    $mockHtmlProcessor->shouldReceive('processTextNodes')
        ->andReturnUsing(fn ($html, $callback) => $html);

    $ledgerA = new Ledger;
    $ledgerA->id = 1;
    $ledgerA->updated_at = now();
    $ledgerA->define = (object) ['tenant_id' => 'tenant-a'];

    $ledgerB = new Ledger;
    $ledgerB->id = 1;
    $ledgerB->updated_at = now();
    $ledgerB->define = (object) ['tenant_id' => 'tenant-b'];

    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);

    // テナントAで呼び出し
    $columnHtml->show($columnDefine, 'test', true, [], '', false, $ledgerA);

    // テナントBで呼び出し（同じledger_idでも別キャッシュのため再計算）
    $columnHtml->show($columnDefine, 'test', true, [], '', false, $ledgerB);
});

it('bypasses cache when highlight is provided', function () {
    $columnDefine = new ColumnDefine(
        1, 'test_textarea', 'textarea', 1,
        [], false, false, null, '', [], 3, null
    );

    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockMarkdownRenderer->shouldReceive('toHtml')
        ->twice()
        ->andReturn('<p>test</p>');

    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')
        ->twice()
        ->andReturn('<p>test</p>');

    $mockHtmlProcessor = mock(HtmlProcessorService::class);
    $mockHtmlProcessor->shouldReceive('processTextNodes')
        ->andReturnUsing(fn ($html, $callback) => $html);

    $ledger = new Ledger;
    $ledger->id = 1;
    $ledger->updated_at = now();
    $ledger->define = (object) ['tenant_id' => 'test-tenant'];

    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);

    // ハイライトありで2回呼び出し → キャッシュを使わないので2回計算される
    $columnHtml->show($columnDefine, 'test', true, [], '', false, $ledger, 'keyword');
    $columnHtml->show($columnDefine, 'test', true, [], '', false, $ledger, 'keyword');
});

it('invalidates cache when ledger updated_at changes', function () {
    $columnDefine = new ColumnDefine(
        1, 'test_textarea', 'textarea', 1,
        [], false, false, null, '', [], 3, null
    );

    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockMarkdownRenderer->shouldReceive('toHtml')
        ->twice()
        ->andReturn('<p>test</p>');

    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')
        ->twice()
        ->andReturn('<p>test</p>');

    $mockHtmlProcessor = mock(HtmlProcessorService::class);
    $mockHtmlProcessor->shouldReceive('processTextNodes')
        ->andReturnUsing(fn ($html, $callback) => $html);

    $ledger = new Ledger;
    $ledger->id = 1;
    $ledger->updated_at = now();
    $ledger->define = (object) ['tenant_id' => 'test-tenant'];

    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer, $mockHtmlProcessor);

    // 1回目の呼び出し（キャッシュ作成）
    $columnHtml->show($columnDefine, 'test', true, [], '', false, $ledger);

    // updated_at を変更
    $ledger->updated_at = now()->addSecond();

    // 2回目の呼び出し（キャッシュキーが変わるので再計算）
    $columnHtml->show($columnDefine, 'test', true, [], '', false, $ledger);
});
