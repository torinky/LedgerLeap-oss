<?php

namespace Tests\Unit\Casts;

use App\Casts\AsColumnDefinesArrayJson;
use App\Models\ColumnDefine;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

/**
 * AsColumnDefinesArrayJson のユニットテスト
 *
 * Phase 1.5: Castsテスト整備
 *
 * @see app/Casts/AsColumnDefinesArrayJson.php
 */
class AsColumnDefinesArrayJsonTest extends TestCase
{
    #[Test]
    public function it_can_cast_to_collection_keyed_by_id()
    {
        // 基本的なプロパティのみを持つテストデータ
        $data = [
            ['id' => 123, 'name' => 'Column1', 'type' => 'number', 'order' => 0],
            ['id' => 124, 'name' => 'Column2', 'type' => 'text', 'order' => 1],
        ];

        $cast = new AsColumnDefinesArrayJson;
        $key = 'attributes';

        // setメソッドでJSON文字列に変換
        $result = $cast->set(new stdClass, $key, $data, [$key => $data]);

        // JSON文字列が返される
        $this->assertIsArray($result);
        $this->assertArrayHasKey($key, $result);
        $this->assertIsString($result[$key]);
        $this->assertJson($result[$key]);

        // getメソッドでCollectionに変換
        $retrieved = $cast->get(new stdClass, $key, null, $result);

        // IDでキー化されたCollectionが返される
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $retrieved);
        $this->assertEquals(2, $retrieved->count());
        $this->assertTrue($retrieved->has(123));
        $this->assertTrue($retrieved->has(124));

        // 各要素がColumnDefineインスタンス
        foreach ($retrieved as $id => $item) {
            $this->assertInstanceOf(ColumnDefine::class, $item);
            $this->assertTrue(in_array($id, [123, 124]));
        }
    }

    #[Test]
    public function it_handles_invalid_json_gracefully()
    {
        $cast = new AsColumnDefinesArrayJson;
        $invalidJson = '{"name": "Column1", "type": "text"'; // 不正なJSON

        $result = $cast->get(new stdClass, 'attributes', null, ['attributes' => $invalidJson]);

        // nullが返される
        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_when_attribute_not_set()
    {
        $cast = new AsColumnDefinesArrayJson;

        // 属性が存在しない場合
        $result = $cast->get(new stdClass, 'attributes', null, []);

        // nullが返される
        $this->assertNull($result);
    }

    #[Test]
    public function it_handles_empty_array()
    {
        $cast = new AsColumnDefinesArrayJson;
        $key = 'attributes';

        // 空配列をset
        $data = [];
        $result = $cast->set(new stdClass, $key, $data, [$key => $data]);

        // JSON文字列が返される
        $this->assertIsString($result[$key]);
        $this->assertEquals('[]', $result[$key]);

        // getで空のCollectionが返される
        $retrieved = $cast->get(new stdClass, $key, null, $result);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $retrieved);
        $this->assertEquals(0, $retrieved->count());
    }

    #[Test]
    public function it_converts_object_to_array_before_processing()
    {
        $cast = new AsColumnDefinesArrayJson;

        // オブジェクトを含むJSON（必須プロパティを含む）
        $jsonObject = '{"123":{"id":123,"name":"Column1","type":"text","order":0},"124":{"id":124,"name":"Column2","type":"number","order":1}}';

        $result = $cast->get(new stdClass, 'attributes', null, ['attributes' => $jsonObject]);

        // Collectionが返される
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());

        // 各要素がColumnDefineインスタンス
        foreach ($result as $item) {
            $this->assertInstanceOf(ColumnDefine::class, $item);
        }
    }

    #[Test]
    public function it_handles_non_array_json_data()
    {
        $cast = new AsColumnDefinesArrayJson;

        // 配列でもオブジェクトでもないJSON
        $jsonString = '"simple_string"';

        $result = $cast->get(new stdClass, 'attributes', null, ['attributes' => $jsonString]);

        // そのまま文字列が返される
        $this->assertEquals('simple_string', $result);
    }

    #[Test]
    public function it_handles_single_column_define()
    {
        $cast = new AsColumnDefinesArrayJson;
        $key = 'attributes';

        // 単一のColumnDefine（基本プロパティのみ）
        $data = [
            ['id' => 1, 'name' => 'SingleColumn', 'type' => 'text', 'order' => 0],
        ];

        $result = $cast->set(new stdClass, $key, $data, [$key => $data]);
        $retrieved = $cast->get(new stdClass, $key, null, $result);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $retrieved);
        $this->assertEquals(1, $retrieved->count());
        $this->assertTrue($retrieved->has(1));
        $this->assertInstanceOf(ColumnDefine::class, $retrieved->get(1));
    }

    #[Test]
    public function it_handles_multiple_column_defines_with_different_types()
    {
        $cast = new AsColumnDefinesArrayJson;
        $key = 'attributes';

        // 様々な型のColumnDefine（有効な型のみ）
        $data = [
            ['id' => 1, 'name' => 'TextColumn', 'type' => 'text', 'order' => 0],
            ['id' => 2, 'name' => 'NumberColumn', 'type' => 'number', 'order' => 1],
            ['id' => 3, 'name' => 'DateColumn', 'type' => 'YMD', 'order' => 2],
        ];

        $result = $cast->set(new stdClass, $key, $data, [$key => $data]);
        $retrieved = $cast->get(new stdClass, $key, null, $result);

        // 全てのカラムが取得できる
        $this->assertEquals(3, $retrieved->count());

        // 各カラムの型が正しく保持されている
        $this->assertEquals('text', $retrieved->get(1)->type);
        $this->assertEquals('number', $retrieved->get(2)->type);
        $this->assertEquals('YMD', $retrieved->get(3)->type);
    }

    #[Test]
    public function it_handles_null_value()
    {
        $cast = new AsColumnDefinesArrayJson;
        $key = 'attributes';

        // null値をset
        $result = $cast->set(new stdClass, $key, null, [$key => null]);

        // JSON文字列"null"が返される
        $this->assertIsString($result[$key]);
        $this->assertEquals('null', $result[$key]);

        // getでnullが返される
        $retrieved = $cast->get(new stdClass, $key, null, $result);
        $this->assertNull($retrieved);
    }

    #[Test]
    public function it_preserves_basic_column_define_properties()
    {
        $cast = new AsColumnDefinesArrayJson;
        $key = 'attributes';

        // 基本プロパティを持つColumnDefine
        $data = [
            [
                'id' => 999,
                'name' => 'TestColumn',
                'type' => 'textarea',
                'order' => 5,
                'required' => 1,
                'unique' => 0,
            ],
        ];

        $result = $cast->set(new stdClass, $key, $data, [$key => $data]);
        $retrieved = $cast->get(new stdClass, $key, null, $result);

        $column = $retrieved->get(999);
        $this->assertInstanceOf(ColumnDefine::class, $column);
        $this->assertEquals('TestColumn', $column->name);
        $this->assertEquals('textarea', $column->type);
        $this->assertEquals(5, $column->order);
        $this->assertEquals(1, $column->required);
        $this->assertEquals(0, $column->unique);
    }

    #[Test]
    public function it_converts_decoded_object_to_array()
    {
        $cast = new AsColumnDefinesArrayJson;

        // JSONオブジェクト形式（is_object分岐をカバーするため）
        // 連想配列形式のJSONをオブジェクトとしてデコードするケース
        $jsonObject = '{"item":{"id":123,"name":"TestColumn","type":"text","order":0}}';

        $result = $cast->get(new stdClass, 'attributes', null, ['attributes' => $jsonObject]);

        // オブジェクト→配列変換後、Collectionが返される
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());

        // keyBy('id')によってIDでキー化されている
        $this->assertTrue($result->has(123));
        $column = $result->get(123);
        $this->assertInstanceOf(ColumnDefine::class, $column);
    }
}
