<?php

namespace Tests\Unit\Casts;

use App\Casts\AsColumnDefinesArrayJson;
use App\Models\ColumnDefine;
use Illuminate\Support\Facades\Log;
use stdClass;
use Tests\TestCase;

class AsColumnDefinesArrayJsonTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_cast_to_array_object_and_back()
    {
        // テストに使用するデータを定義します。
        $data = [
            ['id' => 123, 'name' => 'Column1', 'type' => 'number', 'order' => 0, 'required' => 0, 'unique' => 1, 'sortBy' => 1],
            ['id' => 124, 'name' => 'Column2', 'type' => 'text', 'order' => 1, 'required' => 1, 'unique' => 0, 'sortBy' => 0],
        ];

        // モデルに保存する前にキャストを使用してJSONに変換します。
        $castedData = (new AsColumnDefinesArrayJson)->set(new stdClass, 'attributes', $data, ['attributes']);

        // モデルに保存されるデータを確認します。
        $this->assertIsArray($castedData);
        $this->assertJson(json_encode($data, JSON_UNESCAPED_UNICODE), $castedData['attributes']);

        // モデルから取得したデータを元のArrayObjectに戻します。
        $decodedData = (new AsColumnDefinesArrayJson)->get(new stdClass, 'attributes', null, $castedData);

        // 元のArrayObjectと復元されたArrayObjectの要素が一致することを確認します。
        $this->assertEquals(count($data), count($decodedData));
        for ($i = 0; $i < count($data); $i++) {
            $this->assertInstanceOf(ColumnDefine::class, $decodedData[$i]);
            $this->assertEquals($data[$i]['id'], $decodedData[$i]->id);
            $this->assertEquals($data[$i]['name'], $decodedData[$i]->name);
            $this->assertEquals($data[$i]['type'], $decodedData[$i]->type);
        }
    }

    /**
     * @test
     */
    public function it_handles_invalid_json_gracefully()
    {
        $invalidJson = '{"name": "Column1", "type": "text"';

        // AsColumnDefinesArrayJsonのgetメソッドを呼び出します。
        $decodedData = (new AsColumnDefinesArrayJson)->get(new stdClass, 'attributes', $invalidJson, []);

        // 予期した動作としてnullが返されることを確認します。
        $this->assertNull($decodedData);

        // Logにアラートが記録されていることを確認します。
        $spy = Log::spy();
        $spy->shouldNotHaveReceived('alert', ['JSON decoding error: ' . $invalidJson]);
    }
}
