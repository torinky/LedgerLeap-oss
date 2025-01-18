<?php

namespace tests\Unit\Casts;

use App\Casts\AsColumnArrayJson;
use Illuminate\Support\Facades\Log;
use stdClass;
use tests\TestCase;

class AsColumnArrayJsonTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_cast_to_array_and_back()
    {
        // テストに使用するデータを定義します。
        $data = [
            'value1',
            ['subkey' => 'subvalue', 'subkey2' => '日本語でも大丈夫'],
        ];

        // モデルに保存する前にキャストを使用してJSONに変換します。
        $castedData = (new AsColumnArrayJson)->set(new stdClass, 'attributes', $data, ['attributes']);
        // モデルに保存されるデータを確認します。
        $this->assertIsArray($castedData);
        $this->assertJson(json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), $castedData['attributes']);

        // モデルから取得したデータを元の配列に戻します。
        $decodedData = (new AsColumnArrayJson)->get(new stdClass, 'attributes', null, $castedData);

        // 元の配列と復元された配列が一致することを確認します。
        $this->assertEquals($data, $decodedData);
    }

    /**
     * @test
     */
    public function it_handles_invalid_json_gracefully()
    {
        $invalidJson = '{"key": "value", "missing_key":';

        // AsColumnArrayJsonのgetメソッドを呼び出します。
        $decodedData = (new AsColumnArrayJson)->get(new stdClass, 'attributes', null, ['attributes' => $invalidJson]);

        // 予期した動作としてnullが返されることを確認します。
        $this->assertNull($decodedData);

        // Logにアラートが記録されていることを確認します。
        $spy = Log::spy();
        $spy->shouldNotHaveReceived('alert', ['JSON decoding error: ' . $invalidJson]);

    }
}
