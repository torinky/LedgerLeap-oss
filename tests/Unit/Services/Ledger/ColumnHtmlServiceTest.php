<?php

namespace tests\Unit\Services\Ledger;

use App\Models\ColumnDefine;
use App\Services\Ledger\ColumnHtmlService;
use PHPUnit\Framework\TestCase;

class ColumnHtmlServiceTest extends TestCase
{
    public function test_column_value_is_array()
    {
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
            'aaa' => 'aaa',

        ]);

        $expectedHtml = '<span class="' . ColumnHtmlService::BADGE_CLASS_NAME . '">aaa</span>';
        $this->assertEquals($expectedHtml, $result->toHtml());
    }

    public function test_show_returns_empty_string_when_no_initial_value()
    {
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

        $this->assertEquals('', $result->toHtml());
    }

    public function test_highlight_keywords_in_html_output()
    {
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
        $this->assertEquals($expectedHtml, $result->toHtml());
    }
}
