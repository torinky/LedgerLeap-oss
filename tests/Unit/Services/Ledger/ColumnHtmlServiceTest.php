<?php

use App\Models\ColumnDefine;
use App\Services\Ledger\ColumnHtmlService;

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
    $columnHtml = new ColumnHtmlService;
    $columnHtml->mount($columnDefine, ['aaa' => 'aaa', 'ccc' => 'ccc']);

    $result = $columnHtml->show($columnDefine, [
        'aaa' => true,
    ]);

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

    $columnHtml = new ColumnHtmlService;
    $result = $columnHtml->show($columnDefine, null);

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

    $columnHtml = new ColumnHtmlService;
    $columnHtml->mount($columnDefine, 'This is a test content with keywords');
    $columnHtml->setHighlightKeywords(['test', 'keywords']);

    $result = $columnHtml->show($columnDefine, 'This is a test content with keywords');

    $expectedHtml = 'This is a <span class="text-error font-bold text-lg">test</span> content with <span class="text-error font-bold text-lg">keywords</span>';
    expect($result->toHtml())->toBe($expectedHtml);
});