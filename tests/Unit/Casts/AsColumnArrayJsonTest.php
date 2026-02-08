<?php

namespace Tests\Unit\Casts;

use App\Casts\AsColumnArrayJson;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AsColumnArrayJsonTest extends TestCase
{
    public function test_can_cast_to_array_and_back()
    {
        // テストに使用するデータを定義します。
        $data = [
            'value1',
            ['subkey' => 'subvalue', 'subkey2' => '日本語でも大丈夫'],
        ];

        // モデルに保存する前にキャストを使用してJSONに変換します。
        $castedData = (new AsColumnArrayJson())->set(new \stdClass, 'attributes', $data, ['attributes' => $data]);
        // モデルに保存されるデータを確認します。
        $this->assertIsArray($castedData);
        $this->assertIsString($castedData['attributes']);
        $this->assertJson($castedData['attributes']);

        // モデルから取得したデータを元の配列に戻します。
        $decodedData = (new AsColumnArrayJson())->get(new \stdClass, 'attributes', null, ['attributes' => $castedData['attributes']]);

        // 元の配列と復元された配列が一致することを確認します。
        $this->assertEquals($data, $decodedData);
    }

    public function test_handles_invalid_json_gracefully()
    {
        $invalidJson = '{"key": "value", "missing_key":';

        // AsColumnArrayJsonのgetメソッドを呼び出します。
        $decodedData = (new AsColumnArrayJson())->get(new \stdClass, 'attributes', null, ['attributes' => $invalidJson]);

        // 予期した動作としてnullが返されることを確認します。
        $this->assertNull($decodedData);

        // Log にアラートが記録されているか（spy）を確認する場合は Log::spy() を使います。
        $spy = Log::spy();
        $spy->shouldNotHaveReceived('alert');
    }

    public function test_does_not_double_encode_when_given_already_encoded_json()
    {
        $cast = new AsColumnArrayJson();
        $key = 'attributes';

        // Arrange: 既に JSON 文字列としてエンコードされた配列
        $original = ['file1.jpg', 'file2.png'];
        $jsonString = json_encode($original, JSON_UNESCAPED_UNICODE);

        // Act: set に既成 JSON 文字列を渡す
        $result = $cast->set(new \stdClass(), $key, $jsonString, [$key => $jsonString]);

        // Assert: 返却値はそのままの JSON 文字列である（再エンコードされていない）
        $this->assertIsArray($result);
        $this->assertArrayHasKey($key, $result);
        $this->assertIsString($result[$key]);
        $this->assertEquals($jsonString, $result[$key]);
    }

    public function test_encodes_array_into_json_string_when_given_array()
    {
        $cast = new AsColumnArrayJson();
        $key = 'attributes';

        // Arrange: 単純配列を渡す
        $input = ['alpha', 'beta', 'gamma'];

        // Act
        $result = $cast->set(new \stdClass(), $key, $input, [$key => $input]);

        // Assert: 返却は JSON 文字列で、デコードすると元の配列に一致する
        $this->assertIsArray($result);
        $this->assertArrayHasKey($key, $result);
        $this->assertIsString($result[$key]);

        $decoded = json_decode($result[$key], true);
        $this->assertIsArray($decoded);
        $this->assertEquals($input, $decoded);
    }
}
