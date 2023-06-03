<?php

namespace App\Casts;

use App\Models\ColumnDefine;
use ArrayObject;
use Illuminate\Support\Facades\Log;
use JsonException;

class AsColumnDefinesArrayJson extends AsJson
{
    /**
     * @param $model
     * @param $key
     * @param $value
     * @param $attributes
     * @return ArrayObject|null
     */
    public function get($model, $key, $value, $attributes)
    {
        if (!isset($attributes[$key])) {
            return null;
        }

        try {
            $data = json_decode($attributes[$key], false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::alert($e);
        }

        if (is_object($data)) {
            $data = (array)$data;
        }
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $dKey => $item) {
            $data[$dKey] = new ColumnDefine($item);
        }

        return new ArrayObject($data);
    }

}
