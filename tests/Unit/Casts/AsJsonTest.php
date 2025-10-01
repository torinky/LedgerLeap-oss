<?php

namespace Tests\Unit\Casts;

use App\Casts\AsJson;
use stdClass;
use Tests\TestCase;

class AsJsonTest extends TestCase
{
    #[Test]
    public function it_can_cast_to_json_and_back()
    {
        // テストに使用するデータを定義します。
        $data = ['name' => 'John Doe', 'age' => 30];

        // モデルに保存する前にキャストを使用してJSONに変換します。
        $castedData = (new AsJson)->set(new stdClass, 'attributes', $data, ['attributes']);
        // モデルに保存されるデータを確認します。
        $this->assertIsArray($castedData);
        $this->assertJson(json_encode($data, JSON_UNESCAPED_UNICODE), $castedData['attributes']);

        // モデルから取得したデータを元の配列に戻します。
        $decodedData = (new AsJson)->get(new stdClass, 'attributes', null, $castedData);

        // 元の配列と復元された配列が一致することを確認します。
        $this->assertEquals($data, $decodedData);
    }

    #[Test]
    public function it_handles_invalid_json_gracefully()
    {
        $invalidJson = '{"name": "John Doe", "age": 30';

        // AsJsonのgetメソッドを呼び出すためのテストデータを作成します。
        $model = new stdClass;
        $model->attributes = $invalidJson;

        // モデルの属性を取得し、キャストされたデータがnullであることを確認します。
        $decodedData = (new AsJson)->get($model, 'attributes', null, (array) $model);
        $this->assertNull($decodedData);
    }
}
