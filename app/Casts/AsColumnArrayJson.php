<?php /** @noinspection UnknownInspectionInspection */

namespace App\Casts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
     * @return mixed|null
     */
    public function get($model, $key, $value, $attributes): mixed
    {
        $content = $attributes[$key] ?? $value;
        try {
            // JSON文字列をデコードして連想配列に変換します。
            $content = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // JSONデコードに失敗した場合はログを出力します。
//            dd($value,$key,$attributes,$e);
            Log::alert($e);
            return null;
        }

        // デコードされた連想配列の各要素を処理します。
        if (is_array($content) || is_object($content)) {
            foreach ($content as $index => $item) {
                $content[$index] = $this->getContent($item);
            }
        }

        return $content;
    }

    /**
     * @param mixed $item
     * @return mixed|string
     * @noinspection UnserializeExploitsInspection
     */
    public function getContent(mixed $item): mixed
    {
        if (empty($item)) {
            // 空文字列の場合は空文字列として扱います。
            $item = '';
        } elseif (Str::startsWith($item, "___serialized___")) {
            $temp = substr($item, 16);
            $item = unserialize($temp);
        }
        return $item;
    }

    /**
     * モデルの属性に値を設定します。
     *
     * @param Model $model モデルのインスタンス
     * @param string $key 属性のキー
     * @param mixed $value 属性の値
     * @param array $attributes モデルの全ての属性
     * @return array
     * @throws JsonException
     */
    public function set($model, $key, $value, $attributes): array
    {
        $content = $value ?? $attributes[$key];
        if (is_array($content)) {
            // 1階層目はjson配列にする
            $content = array_values($content);
            foreach ($content as $index => $item) {
                $content[$index] = $this->setContent($item);
            }
        }

        return [$key => json_encode($content, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
        ];
    }

    /**
     * @param mixed $item
     * @return mixed|string
     */
    public function setContent(mixed $item): mixed
    {
        if (empty($item)) {
            $item = '';
        } elseif (is_array($item) || is_object($item)) {
//            Mroongaの仕様でjson2階層目以降は第1階層に展開されて保存されてしまうため、serializeする
            $item = "___serialized___" . serialize($item);
        }

        return $item;

    }

}
