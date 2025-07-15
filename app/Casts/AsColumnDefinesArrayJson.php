<?php

namespace App\Casts;

use App\Models\ColumnDefine;
use ArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use JsonException;

class AsColumnDefinesArrayJson extends AsJson
{
    /**
     * モデルの属性から値を取得します。
     *
     * @param Model $model モデルのインスタンス
     * @param string $key 属性のキー
     * @param mixed $value 属性の値
     * @param array $attributes モデルの全ての属性
     * @return ArrayObject|null
     */
    public function get($model, $key, $value, $attributes)
    {
        // 属性が存在しない場合はnullを返します。
        if (!isset($attributes[$key])) {
            return null;
        }

        try {
            // JSON文字列をデコードして連想配列に変換します。
            $data = json_decode($attributes[$key], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // JSONデコードに失敗した場合はログを出力します。
            Log::alert($e);
            return null;
        }

        // $dataがオブジェクトの場合、配列に変換します。
        if (is_object($data)) {
            $data = (array)$data;
        }

        // $dataが配列でない場合、そのまま返します。
        if (!is_array($data)) {
            return $data;
        }

        // $dataの各要素をColumnDefineモデルのインスタンスに変換します。
        foreach ($data as $dKey => $item) {
            $data[$dKey] = new ColumnDefine($item);
        }

        // 変換された連想配列を、IDをキーにしたコレクションに変換して返します。
        return collect($data)->keyBy('id');
    }
}
