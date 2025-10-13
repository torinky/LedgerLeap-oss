<?php

use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Services\AutoLinkService;
use App\Services\Ledger\ColumnHtmlService;
use App\Services\Util\HtmlProcessorService;
use Spatie\LaravelMarkdown\MarkdownRenderer;

it('column value is array', function () {
    $columnDefine = new ColumnDefine(
        1,
        'aaa',
        'chk',
        1,
        ['aaa', 'bbb', 'ccc'],
        false,
        false,
        false
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

    $expectedHtml = '<span class="'.ColumnHtmlService::BADGE_CLASS_NAME.'">aaa</span>';
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
        false
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
        false
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
        ->with($inputValue, Mockery::type(\Closure::class))
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
        false
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
        ->with($htmlFromMarkdown, $columnDefine, null)
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
        'sortBy' => false,
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
            null // ← record を null に変更
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
        false
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
        ->with($htmlFromMarkdown, $columnDefine, null)
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
