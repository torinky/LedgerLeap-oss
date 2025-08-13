<?php

use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Services\AutoLinkService;
use App\Services\Ledger\ColumnHtmlService;
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

    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer);
    $columnHtml->mount($columnDefine, ['aaa' => 'aaa', 'ccc' => 'ccc']);

    $result = $columnHtml->show($columnDefine, [
        'aaa' => true,
    ], true, [], '', false, null);

    $expectedHtml = '<span class="' . ColumnHtmlService::BADGE_CLASS_NAME . '">aaa</span>';
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

    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer);
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

    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')->andReturnUsing(fn ($text) => $text);
    $mockMarkdownRenderer = mock(MarkdownRenderer::class);

    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer);
    $columnHtml->mount($columnDefine, 'This is a test content with keywords');
    $columnHtml->setHighlightKeywords(['test', 'keywords']);

    $result = $columnHtml->show($columnDefine, 'This is a test content with keywords', true, [], '', false, null);

    $expectedHtml = 'This is a <span class="text-error font-bold text-lg">test</span> content with <span class="text-error font-bold text-lg">keywords</span>';
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

    $markdownInput = "**Hello** `World`! See ticket #123.";
    $htmlFromMarkdown = "<p><strong>Hello</strong> <code>World</code>! See ticket #123.</p>";
    $linkedHtml = '<p><strong>Hello</strong> <code>World</code>! See ticket <a href="/tickets/123">#123</a>.</p>';
    $finalHtml = '<div class="prose max-w-none">' . $linkedHtml . '</div>';

    // 2. Mocks
    $mockMarkdownRenderer = mock(MarkdownRenderer::class);
    $mockMarkdownRenderer->shouldReceive('toHtml')
        ->with($markdownInput)
        ->andReturn($htmlFromMarkdown);

    $mockAutoLinkService = mock(AutoLinkService::class);
    $mockAutoLinkService->shouldReceive('convert')
        ->with($htmlFromMarkdown, $columnDefine, null)
        ->andReturn($linkedHtml);

    // 3. Execution
    $columnHtml = new ColumnHtmlService($mockAutoLinkService, $mockMarkdownRenderer);
    $result = $columnHtml->show($columnDefine, $markdownInput, true, [], '', false, null);

    // 4. Assertion
    expect($result->toHtml())->toBe($finalHtml);
});