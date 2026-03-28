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
     * @param  Model  $model  モデルのインスタンス
     * @param  string  $key  属性のキー
     * @param  mixed  $value  属性の値
     * @param  array  $attributes  モデルの全ての属性
     * @return \Illuminate\Support\Collection|null
     */
    public function get($model, $key, $value, $attributes)
    {
        // 属性が存在しない場合はnullを返します。
        if (! isset($attributes[$key])) {
            return null;
        }

        try {
            // JSON文字列をデコードして連想配列に変換します。
            $data = json_decode($attributes[$key], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // JSONデコードに失敗した場合は空のコレクションを返します。
            return collect();
        }

        // $dataが配列でない場合（オブジェクト単体系も含む）、空のコレクションを返します。
        // ※このカラムは本来 [ {id: ...}, ... ] という配列を期待しているため
        if (! is_array($data)) {
            return collect();
        }

        // $dataの各要素をColumnDefineモデルのインスタンスに変換します。
        foreach ($data as $dKey => $item) {
            // $itemが配列でない場合はスキップするか、デフォルト値でラップすることを検討
            // ここでは安全のため、配列またはオブジェクトの場合のみ処理します
            if (is_array($item) || is_object($item)) {
                $data[$dKey] = new ColumnDefine($item);
            } else {
                unset($data[$dKey]);
            }
        }

        // 変換された値を、IDをキーにしたコレクションに変換して返します。
        return collect($data)->keyBy('id');
    }
}
