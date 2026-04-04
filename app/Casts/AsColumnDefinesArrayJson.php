<?php

namespace App\Casts;

use App\Models\ColumnDefine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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
     * @return Collection|mixed|null
     */
    public function get($model, $key, $value, $attributes)
    {
        if (! array_key_exists($key, $attributes)) {
            return null;
        }

        try {
            $data = json_decode($attributes[$key], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return $data;
        }

        return collect($data)
            ->map(static fn ($item): ColumnDefine => new ColumnDefine($item))
            ->keyBy('id');
    }
}
