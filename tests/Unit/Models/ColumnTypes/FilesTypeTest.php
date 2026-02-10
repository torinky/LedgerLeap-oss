<?php

namespace Tests\Unit\Models\ColumnTypes;

use App\Models\ColumnTypes\FilesType;
use PHPUnit\Framework\TestCase;

class FilesTypeTest extends TestCase
{
    private FilesType $filesType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesType = new FilesType;
    }

    public function test_restore_from_empty_value(): void
    {
        $this->assertEquals([], $this->filesType->restoreFromString(null));
        $this->assertEquals([], $this->filesType->restoreFromString(''));
        $this->assertEquals([], $this->filesType->restoreFromString([]));
    }

    public function test_restore_from_array(): void
    {
        $data = ['hash1' => 'file1.pdf', 'hash2' => 'file2.pdf'];
        $this->assertEquals($data, $this->filesType->restoreFromString($data));
    }

    public function test_restore_from_json_string(): void
    {
        $data = ['hash1' => 'file1.pdf', 'hash2' => 'file2.pdf'];
        $json = json_encode($data);
        $this->assertEquals($data, $this->filesType->restoreFromString($json));
    }

    public function test_restore_from_non_json_string(): void
    {
        // JSONでない文字列（単一のハッシュなど）の場合、配列にラップされること
        $hash = 'some_file_hash_without_json_format.pdf';
        $this->assertEquals([$hash], $this->filesType->restoreFromString($hash));
    }

    public function test_restore_from_invalid_json_string(): void
    {
        // 壊れたJSONの場合、文字列としてラップされること
        $invalidJson = '{"key": "value"'; // 閉じ括弧なし
        $this->assertEquals([$invalidJson], $this->filesType->restoreFromString($invalidJson));
    }
}
