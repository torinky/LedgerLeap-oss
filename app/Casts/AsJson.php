<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use JsonException;

class AsJson implements CastsAttributes
{
    /**
     * @param $model
     * @param $key
     * @param $value
     * @param $attributes
     * @return mixed
     */
    public function get($model, $key, $value, $attributes)
    {
        try {
            $data = json_decode($attributes[$key], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
        }

        return $data;
    }

    /**
     * @param $model
     * @param $key
     * @param $value
     * @param $attributes
     * @return
     */
    public function set($model, $key, $value, $attributes)
    {
//        文字列をUTFコードに変換しない
        return [$key => json_encode($value, JSON_UNESCAPED_UNICODE)];
    }


    /*    public function serialize($model, string $key, $value, array $attributes)
        {
            return $value->getArrayCopy();
        }*/
}
