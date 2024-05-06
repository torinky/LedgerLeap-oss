<?php

namespace Tests\Unit\Services\Ledger;

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
            false);
        $columnHtml = new ColumnHtmlService();
        $result = $columnHtml->show($columnDefine,
            [
                'aaa',
                //                'bbb',
            ]);
        //        dd($result->toHtml());
        $this->assertEquals($result->toHtml(), '<span class="badge badge-secondary py-4 mx-1 my-1">aaa</span>');
    }
}
