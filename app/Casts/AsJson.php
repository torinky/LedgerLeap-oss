<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;

class AsJson implements CastsAttributes
{
    /**
     * 属性を取得します。
     *
     * @param  Model  $model  モデルのインスタンス
     * @param  string  $key  属性のキー
     * @param  mixed  $value  属性の値
     * @param  array  $attributes  モデルの全ての属性
     * @return mixed
     */
    public function get($model, $key, $value, $attributes)
    {
        try {
            // JSON文字列をデコードして配列に変換します。
            // JSONデータが不正な場合はJsonExceptionが発生します。
            $data = json_decode($attributes[$key], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // 例外が発生した場合、$dataはnullとなります。
            $data = null;
        }

        return $data;
    }

    /**
     * 属性を設定します。
     *
     * @param  Model  $model  モデルのインスタンス
     * @param  string  $key  属性のキー
     * @param  mixed  $value  属性の値
     * @param  array  $attributes  モデルの全ての属性
     * @return array
     */
    public function set($model, $key, $value, $attributes)
    {
        // JSON_UNESCAPED_UNICODEフラグを使って、UTF-8エンコードせずに配列をJSON文字列にエンコードします。
        return [$key => json_encode($value, JSON_UNESCAPED_UNICODE)];
    }

    /*    public function serialize($model, string $key, $value, array $attributes)
        {
            return $value->getArrayCopy();
        }*/
}
