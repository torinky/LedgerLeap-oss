<?php

namespace App\Casts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use JsonException;

class AsColumnArrayJson extends AsJson
{
    /**
     * モデルの属性から値を取得します。
     *
     * @param Model $model モデルのインスタンス
     * @param string $key 属性のキー
     * @param mixed $value 属性の値
     * @param array $attributes モデルの全ての属性
     * @return array|mixed
     * @throws JsonException
     */
    public function get($model, $key, $value, $attributes)
    {
        try {
            // JSON文字列をデコードして連想配列に変換します。
            $content = json_decode($attributes[$key], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // JSONデコードに失敗した場合はログを出力します。
            Log::alert($e);
            return null;
        }

        // デコードされた連想配列の各要素を処理します。
        foreach ($content as $index => $item) {
            if (empty($content[$index])) {
                // 空文字列の場合は空文字列として扱います。
                $content[$index] = '';
            } elseif (strpos($item, 'json_encoded_array_') !== false) {
                // json_encoded_array_ で始まる場合は、JSONエンコードされた配列としてデコードします。
                $temp = substr($item, 19);
                $temp = stripslashes($temp);
                $content[$index] = json_decode($temp, true);
            } elseif (strpos($item, 'json_encoded_object_') !== false) {
                // json_encoded_object_ で始まる場合は、JSONエンコードされたオブジェクトとしてデコードします。
                $temp = substr($item, 20);
                $temp = stripslashes($temp);
                $content[$index] = json_decode($temp);
            }
        }

        return $content;
    }

    /**
     * モデルの属性に値を設定します。
     *
     * @param Model $model モデルのインスタンス
     * @param string $key 属性のキー
     * @param mixed $value 属性の値
     * @param array $attributes モデルの全ての属性
     * @return array
     */
    public function set($model, $key, $value, $attributes): array
    {
        $content = $value;
        foreach ($content as $index => $item) {
            // カラムがヌルになるとcreate時に削除され、カラムがずれてしまうため明示的に空文字を入れます。
            if (empty($content[$index])) {
                $content[$index] = '';
            } elseif (is_array($content[$index])) {
                // 配列の場合は json_encoded_array_ で始まる文字列に変換します。
                $content[$index] = 'json_encoded_array_' . addslashes(json_encode($item,
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE));
            } elseif (is_object($content[$index])) {
                // オブジェクトの場合は json_encoded_object_ で始まる文字列に変換します。
                $content[$index] = 'json_encoded_object_' . addslashes(json_encode($item,
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE));
            }
        }
        return [$key => json_encode($content, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)];
    }
}
