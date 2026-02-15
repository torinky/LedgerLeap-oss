<?php

namespace Tests\Unit\Casts;

use App\Casts\AsColumnArrayJson;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AsColumnArrayJson のユニットテスト
 *
 * Phase 1: Castsテスト整備（最優先）
 *
 * @see app/Casts/AsColumnArrayJson.php
 */
class AsColumnArrayJsonTest extends TestCase
{
    #[Test]
    public function it_can_cast_to_array_and_back()
    {
        // テストに使用するデータを定義します。
        $data = [
            'value1',
            ['subkey' => 'subvalue', 'subkey2' => '日本語でも大丈夫'],
        ];

        // モデルに保存する前にキャストを使用してJSONに変換します。
        $castedData = (new AsColumnArrayJson)->set(new \stdClass, 'attributes', $data, ['attributes' => $data]);
        // モデルに保存されるデータを確認します。
        $this->assertIsArray($castedData);
        $this->assertIsString($castedData['attributes']);
        $this->assertJson($castedData['attributes']);

        // モデルから取得したデータを元の配列に戻します。
        $decodedData = (new AsColumnArrayJson)->get(new \stdClass, 'attributes', null, ['attributes' => $castedData['attributes']]);

        // 元の配列と復元された配列が一致することを確認します。
        $this->assertEquals($data, $decodedData);
    }

    #[Test]
    public function it_handles_invalid_json_gracefully()
    {
        $invalidJson = '{"key": "value", "missing_key":';

        // AsColumnArrayJsonのgetメソッドを呼び出します。
        $decodedData = (new AsColumnArrayJson)->get(new \stdClass, 'attributes', null, ['attributes' => $invalidJson]);

        // 予期した動作としてnullが返されることを確認します。
        $this->assertNull($decodedData);

        // Log にアラートが記録されているか（spy）を確認する場合は Log::spy() を使います。
        $spy = Log::spy();
        $spy->shouldNotHaveReceived('alert');
    }

    #[Test]
    public function it_does_not_double_encode_when_given_already_encoded_json()
    {
        $cast = new AsColumnArrayJson;
        $key = 'attributes';

        // Arrange: 既に JSON 文字列としてエンコードされた配列
        $original = ['file1.jpg', 'file2.png'];
        $jsonString = json_encode($original, JSON_UNESCAPED_UNICODE);

        // Act: set に既成 JSON 文字列を渡す
        $result = $cast->set(new \stdClass, $key, $jsonString, [$key => $jsonString]);

        // Assert: 返却値はそのままの JSON 文字列である（再エンコードされていない）
        $this->assertIsArray($result);
        $this->assertArrayHasKey($key, $result);
        $this->assertIsString($result[$key]);
        $this->assertEquals($jsonString, $result[$key]);
    }

    #[Test]
    public function it_encodes_array_into_json_string_when_given_array()
    {
        $cast = new AsColumnArrayJson;
        $key = 'attributes';

        // Arrange: 単純配列を渡す
        $input = ['alpha', 'beta', 'gamma'];

        // Act
        $result = $cast->set(new \stdClass, $key, $input, [$key => $input]);

        // Assert: 返却は JSON 文字列で、デコードすると元の配列に一致する
        $this->assertIsArray($result);
        $this->assertArrayHasKey($key, $result);
        $this->assertIsString($result[$key]);

        $decoded = json_decode($result[$key], true);
        $this->assertIsArray($decoded);
        $this->assertEquals($input, $decoded);
    }

    #[Test]
    public function it_handles_null_value()
    {
        $cast = new AsColumnArrayJson;
        $key = 'attributes';

        // null値をset
        $result = $cast->set(new \stdClass, $key, null, [$key => null]);

        // 空のJSON配列が返される
        $this->assertEquals([$key => '[]'], $result);
    }

    #[Test]
    public function it_handles_empty_string_value()
    {
        $cast = new AsColumnArrayJson;
        $key = 'attributes';

        // 空文字列をset
        $result = $cast->set(new \stdClass, $key, '', [$key => '']);

        // 空のJSON配列が返される
        $this->assertEquals([$key => '[]'], $result);
    }

    #[Test]
    public function it_handles_empty_array()
    {
        $cast = new AsColumnArrayJson;
        $key = 'attributes';

        // 空配列をset
        $result = $cast->set(new \stdClass, $key, [], [$key => []]);

        // 空のJSON配列が返される
        $this->assertEquals([$key => '[]'], $result);
    }

    #[Test]
    public function it_handles_array_with_only_empty_elements()
    {
        $cast = new AsColumnArrayJson;
        $key = 'attributes';

        // 空要素のみの配列
        $result = $cast->set(new \stdClass, $key, ['', [], ''], [$key => ['', [], '']]);

        // 空のJSON配列が返される
        $this->assertEquals([$key => '[]'], $result);
    }

    #[Test]
    public function it_returns_empty_array_when_getting_empty_string_from_db()
    {
        $cast = new AsColumnArrayJson;

        // DBから空文字列を取得した場合
        $result = $cast->get(new \stdClass, 'attributes', '', ['attributes' => '']);

        // 空配列が返される
        $this->assertEquals([], $result);
    }

    #[Test]
    public function it_returns_null_when_getting_null_from_db()
    {
        $cast = new AsColumnArrayJson;

        // DBからnullを取得した場合
        $result = $cast->get(new \stdClass, 'attributes', null, ['attributes' => null]);

        // nullが返される
        $this->assertNull($result);
    }

    #[Test]
    public function it_serializes_nested_arrays_with_prefix()
    {
        $cast = new AsColumnArrayJson;
        $key = 'attributes';

        // ネストされた配列を含むデータ
        $data = [
            'value1',
            ['nested' => 'array'],
            ['another' => ['deep' => 'nesting']],
        ];

        $result = $cast->set(new \stdClass, $key, $data, [$key => $data]);

        // JSON文字列が返される
        $this->assertIsString($result[$key]);

        // デコードして確認
        $decoded = json_decode($result[$key], true);
        $this->assertIsArray($decoded);
        $this->assertEquals('value1', $decoded[0]);

        // 2階層目以降は___serialized___プレフィックス付きでシリアライズされる
        $this->assertStringStartsWith('___serialized___', $decoded[1]);
        $this->assertStringStartsWith('___serialized___', $decoded[2]);

        // getメソッドでデシリアライズして元に戻る
        $retrieved = $cast->get(new \stdClass, $key, null, [$key => $result[$key]]);
        $this->assertEquals($data, $retrieved);
    }

    #[Test]
    public function it_converts_integers_to_strings_to_avoid_mroonga_issue()
    {
        $cast = new AsColumnArrayJson;
        $key = 'attributes';

        // 整数を含むデータ
        $data = ['text', 123, 456.78, 'more text'];

        $result = $cast->set(new \stdClass, $key, $data, [$key => $data]);

        // JSON文字列が返される
        $this->assertIsString($result[$key]);

        // デコードして整数が文字列に変換されていることを確認
        $decoded = json_decode($result[$key], true);
        $this->assertEquals('text', $decoded[0]);
        $this->assertEquals('123', $decoded[1]);
        $this->assertEquals('456.78', $decoded[2]);
        $this->assertEquals('more text', $decoded[3]);
    }

    #[Test]
    public function it_handles_objects_by_converting_to_array()
    {
        $cast = new AsColumnArrayJson;
        $key = 'attributes';

        // stdClassオブジェクトを渡す
        $obj = new \stdClass;
        $obj->prop1 = 'value1';
        $obj->prop2 = 'value2';

        $result = $cast->set(new \stdClass, $key, $obj, [$key => $obj]);

        // JSON文字列が返される
        $this->assertIsString($result[$key]);

        // オブジェクトは配列に変換され、array_values()でインデックス配列になる
        $decoded = json_decode($result[$key], true);
        $this->assertIsArray($decoded);
        // array_values()でインデックス配列になっているので、数値キーで値を確認
        $this->assertContains('value1', $decoded);
        $this->assertContains('value2', $decoded);
    }

    #[Test]
    public function it_handles_serialized_string_in_get_method()
    {
        $cast = new AsColumnArrayJson;

        // ___serialized___プレフィックス付きの文字列
        $serialized = '___serialized___'.serialize(['key' => 'value']);

        // getContentメソッドを通してデシリアライズ
        $jsonData = json_encode([$serialized]);
        $result = $cast->get(new \stdClass, 'attributes', null, ['attributes' => $jsonData]);

        // デシリアライズされた配列が返される
        $this->assertIsArray($result);
        $this->assertIsArray($result[0]);
        $this->assertEquals(['key' => 'value'], $result[0]);
    }

    #[Test]
    public function it_handles_invalid_serialized_string_gracefully()
    {
        $cast = new AsColumnArrayJson;

        // 不正なシリアライズ文字列
        $invalid = '___serialized___invalid_data';

        // getContentメソッドを通して処理
        $jsonData = json_encode([$invalid]);
        $result = $cast->get(new \stdClass, 'attributes', null, ['attributes' => $jsonData]);

        // 元の文字列がそのまま返される（ログに警告は出る）
        $this->assertIsArray($result);
        $this->assertEquals($invalid, $result[0]);
    }

    #[Test]
    public function it_handles_json_object_instead_of_array()
    {
        $cast = new AsColumnArrayJson;

        // JSONオブジェクト（連想配列）を取得
        $jsonObject = '{"key":"value","another":"data"}';

        $result = $cast->get(new \stdClass, 'attributes', null, ['attributes' => $jsonObject]);

        // オブジェクトとして返される
        $this->assertIsObject($result);
        $this->assertEquals('value', $result->key);
        $this->assertEquals('data', $result->another);
    }

    #[Test]
    public function it_handles_json_string_value()
    {
        $cast = new AsColumnArrayJson;

        // JSONデコードされたが配列でもオブジェクトでもない値（単なる文字列）
        $jsonString = '"simple_string"';

        $result = $cast->get(new \stdClass, 'attributes', null, ['attributes' => $jsonString]);

        // 文字列がそのまま返される
        $this->assertEquals('simple_string', $result);
    }

    #[Test]
    public function it_handles_json_number_value()
    {
        $cast = new AsColumnArrayJson;

        // JSONデコードされたが配列でもオブジェクトでもない値（数値）
        $jsonNumber = '42';

        $result = $cast->get(new \stdClass, 'attributes', null, ['attributes' => $jsonNumber]);

        // 数値がそのまま返される
        $this->assertEquals(42, $result);
    }

    #[Test]
    public function it_handles_invalid_json_like_string_in_set()
    {
        $cast = new AsColumnArrayJson;
        $key = 'attributes';

        // [ で始まるが無効なJSON
        $invalidJson = '[invalid json';

        $result = $cast->set(new \stdClass, $key, $invalidJson, [$key => $invalidJson]);

        // JSON文字列としてエンコードされる（文字列として扱われる）
        $this->assertIsString($result[$key]);
        $decoded = json_decode($result[$key], true);
        $this->assertEquals($invalidJson, $decoded); // 文字列として保存される
    }

    #[Test]
    public function it_handles_array_with_null_elements()
    {
        $cast = new AsColumnArrayJson;
        $key = 'attributes';

        // nullを含む配列
        $data = ['value1', null, 'value2'];

        $result = $cast->set(new \stdClass, $key, $data, [$key => $data]);

        // JSON文字列が返される
        $this->assertIsString($result[$key]);

        // デコードしてnullが空文字列に変換されていることを確認
        $decoded = json_decode($result[$key], true);
        $this->assertEquals('value1', $decoded[0]);
        $this->assertEquals('', $decoded[1]); // nullは空文字列に変換
        $this->assertEquals('value2', $decoded[2]);
    }

    #[Test]
    public function it_handles_array_with_empty_string_elements()
    {
        $cast = new AsColumnArrayJson;
        $key = 'attributes';

        // 空文字列を含む配列
        $data = ['value1', '', 'value2'];

        $result = $cast->set(new \stdClass, $key, $data, [$key => $data]);

        // JSON文字列が返される
        $this->assertIsString($result[$key]);

        // デコードして空文字列が保持されていることを確認
        $decoded = json_decode($result[$key], true);
        $this->assertEquals('value1', $decoded[0]);
        $this->assertEquals('', $decoded[1]);
        $this->assertEquals('value2', $decoded[2]);
    }

    #[Test]
    public function it_handles_json_with_empty_string_elements_on_get()
    {
        $cast = new AsColumnArrayJson;

        // 空文字列を含むJSON配列
        $jsonData = '["value1","","value2"]';

        $result = $cast->get(new \stdClass, 'attributes', null, ['attributes' => $jsonData]);

        // 配列として返される
        $this->assertIsArray($result);
        $this->assertEquals('value1', $result[0]);
        $this->assertEquals('', $result[1]); // 空文字列が保持される
        $this->assertEquals('value2', $result[2]);
    }

    #[Test]
    public function it_handles_json_with_null_elements_on_get()
    {
        $cast = new AsColumnArrayJson;

        // nullを含むJSON配列
        $jsonData = '["value1",null,"value2"]';

        $result = $cast->get(new \stdClass, 'attributes', null, ['attributes' => $jsonData]);

        // 配列として返される
        $this->assertIsArray($result);
        $this->assertEquals('value1', $result[0]);
        $this->assertNull($result[1]); // nullはnullのまま
        $this->assertEquals('value2', $result[2]);
    }
}
