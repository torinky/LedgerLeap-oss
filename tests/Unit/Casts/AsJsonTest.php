<?php

namespace Tests\Unit\Casts;

use App\Casts\AsJson;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

/**
 * AsJson のユニットテスト
 *
 * Phase 1.5: Castsテスト整備
 *
 * @see app/Casts/AsJson.php
 */
class AsJsonTest extends TestCase
{
    #[Test]
    public function it_can_cast_to_json_and_back()
    {
        // テストに使用するデータを定義します。
        $data = ['name' => 'John Doe', 'age' => 30, 'city' => '東京'];

        $cast = new AsJson;
        $key = 'attributes';

        // setでJSON文字列に変換
        $result = $cast->set(new stdClass, $key, $data, [$key => $data]);

        // JSON文字列が返される
        $this->assertIsArray($result);
        $this->assertArrayHasKey($key, $result);
        $this->assertIsString($result[$key]);
        $this->assertJson($result[$key]);

        // getで元の配列に戻る
        $retrieved = $cast->get(new stdClass, $key, null, $result);
        $this->assertEquals($data, $retrieved);
    }

    #[Test]
    public function it_handles_invalid_json_gracefully()
    {
        $cast = new AsJson;
        $invalidJson = '{"name": "John Doe", "age": 30'; // 不正なJSON

        // getメソッドで不正なJSONを処理
        $result = $cast->get(new stdClass, 'attributes', null, ['attributes' => $invalidJson]);

        // nullが返される
        $this->assertNull($result);
    }

    #[Test]
    public function it_handles_null_value()
    {
        $cast = new AsJson;
        $key = 'attributes';

        // null値をset
        $result = $cast->set(new stdClass, $key, null, [$key => null]);

        // JSON文字列"null"が返される
        $this->assertEquals([$key => 'null'], $result);

        // getでnullが返される
        $retrieved = $cast->get(new stdClass, $key, null, $result);
        $this->assertNull($retrieved);
    }

    #[Test]
    public function it_handles_empty_array()
    {
        $cast = new AsJson;
        $key = 'attributes';

        // 空配列をset
        $data = [];
        $result = $cast->set(new stdClass, $key, $data, [$key => $data]);

        // JSON文字列"[]"が返される
        $this->assertEquals([$key => '[]'], $result);

        // getで空配列が返される
        $retrieved = $cast->get(new stdClass, $key, null, $result);
        $this->assertEquals([], $retrieved);
    }

    #[Test]
    public function it_handles_string_value()
    {
        $cast = new AsJson;
        $key = 'attributes';

        // 文字列をset
        $data = 'simple string';
        $result = $cast->set(new stdClass, $key, $data, [$key => $data]);

        // JSON文字列が返される
        $this->assertIsString($result[$key]);

        // getで元の文字列が返される
        $retrieved = $cast->get(new stdClass, $key, null, $result);
        $this->assertEquals($data, $retrieved);
    }

    #[Test]
    public function it_handles_number_value()
    {
        $cast = new AsJson;
        $key = 'attributes';

        // 数値をset
        $data = 42;
        $result = $cast->set(new stdClass, $key, $data, [$key => $data]);

        // JSON文字列が返される
        $this->assertEquals([$key => '42'], $result);

        // getで元の数値が返される
        $retrieved = $cast->get(new stdClass, $key, null, $result);
        $this->assertEquals($data, $retrieved);
    }

    #[Test]
    public function it_handles_boolean_value()
    {
        $cast = new AsJson;
        $key = 'attributes';

        // boolean値をset
        $data = true;
        $result = $cast->set(new stdClass, $key, $data, [$key => $data]);

        // JSON文字列が返される
        $this->assertEquals([$key => 'true'], $result);

        // getで元のboolean値が返される
        $retrieved = $cast->get(new stdClass, $key, null, $result);
        $this->assertTrue($retrieved);

        // false の場合もテスト
        $data = false;
        $result = $cast->set(new stdClass, $key, $data, [$key => $data]);
        $retrieved = $cast->get(new stdClass, $key, null, $result);
        $this->assertFalse($retrieved);
    }

    #[Test]
    public function it_handles_nested_array()
    {
        $cast = new AsJson;
        $key = 'attributes';

        // ネストされた配列をset
        $data = [
            'user' => [
                'name' => 'John',
                'profile' => [
                    'age' => 30,
                    'city' => '東京',
                ],
            ],
            'settings' => ['theme' => 'dark'],
        ];

        $result = $cast->set(new stdClass, $key, $data, [$key => $data]);

        // JSON文字列が返される
        $this->assertIsString($result[$key]);

        // getで元のネスト配列が返される
        $retrieved = $cast->get(new stdClass, $key, null, $result);
        $this->assertEquals($data, $retrieved);
    }

    #[Test]
    public function it_preserves_unicode_characters()
    {
        $cast = new AsJson;
        $key = 'attributes';

        // Unicode文字を含むデータ
        $data = [
            'japanese' => 'こんにちは世界',
            'emoji' => '🌸🎌',
            'chinese' => '你好世界',
        ];

        $result = $cast->set(new stdClass, $key, $data, [$key => $data]);

        // Unicode文字が正しくエンコードされている（エスケープされていない）
        $this->assertStringContainsString('こんにちは世界', $result[$key]);
        $this->assertStringContainsString('🌸🎌', $result[$key]);

        // getで元のUnicode文字が返される
        $retrieved = $cast->get(new stdClass, $key, null, $result);
        $this->assertEquals($data, $retrieved);
    }

    #[Test]
    public function it_handles_malformed_json_exception()
    {
        $cast = new AsJson;

        // 完全に壊れたJSONでJsonExceptionをトリガー
        $malformedJson = '{invalid json structure}';

        $result = $cast->get(new stdClass, 'attributes', null, ['attributes' => $malformedJson]);

        // JsonException がキャッチされてnullが返される
        $this->assertNull($result);
    }
}
