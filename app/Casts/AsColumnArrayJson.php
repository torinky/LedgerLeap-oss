<?php

namespace App\Casts;

use JsonException;

class AsColumnArrayJson extends AsJson
{

    /**
     * @param $model
     * @param $key
     * @param $value
     * @param $attributes
     * @return array|mixed
     * @throws JsonException
     */
    public function get($model, $key, $value, $attributes)
    {
        try {
            $content = json_decode($attributes[$key], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::alert($e);
        }

        foreach ($content as $index => $item) {
            if (empty($content[$index])) {
                $content[$index] = '';
            } elseif (strpos($item, 'json_encoded_array_') !== false) {
                $temp = substr($item, 19);
                $temp = stripslashes($temp);
                $content[$index] = json_decode($temp, true);
            } elseif (strpos($item, 'json_encoded_object_') !== false) {
                $temp = substr($item, 20);
                $temp = stripslashes($temp);
                $content[$index] = json_decode($temp);
            }
        }

        return $content;
    }

    /**
     * @param $model
     * @param $key
     * @param $value
     * @param $attributes
     * @return array
     */
    public function set($model, $key, $value, $attributes): array
    {

        $content = $value;
        foreach ($content as $index => $item) {
            //カラムがヌルになるとcreate時に削除されカラムがずれてしまうため明示的に空文字を入れる
            if (empty($content[$index])) {
                $content[$index] = '';
            } elseif (is_array($content[$index])) {
                $content[$index] = 'json_encoded_array_' . addslashes(json_encode($item,
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE));
            } elseif (is_object($content[$index])) {
                $content[$index] = 'json_encoded_object_' . addslashes(json_encode($item,
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE));
            }
        }
        return [$key => json_encode($content, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)];

    }

}
