<?php

namespace App\Casts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;

/**
 * Mroonga全文検索対応のJSON配列キャストクラス
 *
 * このクラスはMroongaの制約に対応するため、以下の特別な処理を行います：
 *
 * 1. 配列の1階層目は強制的にインデックス配列にする
 * 2. 2階層目以降の配列/オブジェクトは___serialized___プレフィックス付きでシリアライズ
 *
 * **重要な注意事項（Mroongaのベクターカラム処理）：**
 *
 * Mroongaは数値キーのJSON配列内に整数値がある場合、ベクターカラムの前処理で
 * その配列をさらにJSON配列としてエンコードする副作用があります。
 *
 * 例：
 * - 正常: ["EXP-0001", "2025-10-11", "交通費", "1000", "説明", []]
 * - 異常: ["EXP-0001", "2025-10-11", "交通費", 1000, "説明", []]
 *         ↓ Mroongaのベクターカラム処理
 *         ["[\"EXP-00","01\",\"2025-","10-11\",..."]"] （二重配列化＋分割）
 *
 * この問題を回避するため、contentに格納する値はすべて文字列として渡す必要があります。
 * 特に数値型（number）のカラムは、シーダーやテストデータ作成時に
 * 必ず (string) キャストして文字列として渡してください。
 *
 * UIからのフォーム入力は自動的に文字列として渡されるため問題ありませんが、
 * プログラムから直接整数を渡すと上記の問題が発生します。
 */
class AsColumnArrayJson extends AsJson
{
    /**
     * モデルの属性から値を取得します。
     *
     * @param  Model  $model  モデルのインスタンス
     * @param  string  $key  属性のキー
     * @param  mixed  $value  属性の値
     * @param  array  $attributes  モデルの全ての属性
     * @return mixed|null
     */
    public function get($model, $key, $value, $attributes): mixed
    {
        $content = $attributes[$key] ?? $value;

        // データベースからの値が空文字列の場合、空配列を返します。
        // これは、空配列に対して '' を保存した場合のケースを処理します。
        if ($content === '') {
            return []; // または、空文字列読み取り時の望ましい動作に応じて null を返す
        }
        // すでに null であるか、文字列でない場合は、JSON デコード試行前にそのまま返します。
        if ($content === null || ! is_string($content)) {
            return $content;
        }

        try {
            // JSON文字列をデコードします。
            // 必要であれば、set の動作により合わせるために連想配列 (true) を使用しますが、
            // 現在のコードはオブジェクト (false) を使用しています。今のところ一貫性を保ちます。
            $decodedContent = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // JSONデコードに失敗した場合はログを出力します。
            Log::alert(sprintf(
                'AsColumnArrayJson: JSON decode error: %s for value: %s',
                $e->getMessage(),
                Str::limit($content, 500)
            ));

            return null; // または空配列 [] を返す？
        }

        // デコードされた配列/オブジェクトの各要素を処理します。
        if (is_array($decodedContent) || is_object($decodedContent)) {
            // 配列/オブジェクトの要素を参照で変更するか、新しいものを作成する方法が必要です。
            $processedContent = is_object($decodedContent) ? clone $decodedContent : $decodedContent;
            foreach ($decodedContent as $index => $item) {
                if (is_object($processedContent)) {
                    $processedContent->{$index} = $this->getContent($item);
                } else {
                    $processedContent[$index] = $this->getContent($item);
                }
            }

            return $processedContent;
        }

        // JSON デコードされたが配列/オブジェクトではなかった場合 (例: 単なる文字列 "foo")、それを返します。
        return $decodedContent;
    }

    /**
     * @return mixed|string
     *
     * @noinspection UnserializeExploitsInspection
     */
    public function getContent(mixed $item): mixed
    {
        // 空文字列のみを特別扱いしたい場合は、厳密な比較を使用します。
        if ($item === '') {
            return '';
        }
        // json_decode 後に発生する可能性のある null 値を処理します。
        if ($item === null) {
            return null; // または望ましい動作に応じて ''
        }

        if (is_string($item) && Str::startsWith($item, '___serialized___')) {
            $temp = substr($item, 16);
            // unserialize のエラーハンドリングを追加します。
            $unserialized = @unserialize($temp);
            if ($unserialized === false && $temp !== serialize(false)) {
                Log::warning('AsColumnArrayJson: getContent: Failed to unserialize value: '.Str::limit($temp, 500));

                return $item; // 失敗時には元のシリアライズされた文字列を返す？ それとも null？
            }

            return $unserialized;
        }

        return $item;
    }

    /**
     * モデルの属性に値を設定します。
     *
     * @param  Model  $model  モデルのインスタンス
     * @param  string  $key  属性のキー
     * @param  mixed  $value  属性の値
     * @param  array  $attributes  モデルの全ての属性
     *
     * @throws JsonException
     */
    public function set($model, $key, $value, $attributes): array
    {
        // 利用可能であれば $value を直接使用します。
        $content = $value ?? $attributes[$key];

        // 値が配列かどうかを確認します。
        if (is_array($content)) {
            // 配列内のすべての要素が空配˚または空文字列であるかを確認します。
            $allEmpty = true;
            foreach ($content as $item) {
                // 要素が空配列ではなく、かつ空文字列でもない場合、空でないとみなします。
                if ($item !== [] && $item !== '') {
                    $allEmpty = false;
                    // 空でない要素が見つかったので、これ以上確認する必要はありません。
                    break;
                }
            }

            // すべての要素が空だった場合、キーに対して空のJSON配列を返します。
            if ($allEmpty) {
                return [$key => '[]'];
            }

            // すべてが空ではなかった場合、元の処理ロジックに進みます。
            // 1階層目はjson配列にする (強制的にインデックス配列にする)
            $processedContent = array_values($content);
            foreach ($processedContent as $index => $item) {
                $processedContent[$index] = $this->setContent($item);
            }
            // 処理された配列で content を更新します。
            $content = $processedContent;
        } elseif ($content === null || $content === '') {
            // 入力値自体が null または空文字列の場合も空のJSON配列を返します。
            return [$key => '[]']; // Changed from '' to '[]'
        }

        // $content が配列でなかった場合、または空でない要素を含む配列だった場合、
        // またはその他の空でない値だった場合、JSON としてエンコードします。
        // 注意: json_encode(null) は文字列 'null' になります。
        // 上記の `elseif ($content === null)` チェックは、代わりに '' が必要な場合にこれを防ぎます。

        $jsonString = json_encode(
            // (処理された可能性のある) content をエンコードします。
            $content,
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

        // Check if content is already JSON encoded (this check is before encoding, so should check $value instead)
        if (is_string($value) && (Str::startsWith($value, '[') || Str::startsWith($value, '{'))) {
            Log::warning('AsColumnArrayJson: set: Input value appears to be already JSON encoded!');
            Log::warning('AsColumnArrayJson: set: Input value: '.Str::limit($value, 200));
        }

        return [$key => $jsonString];
    }

    /**
     * @return mixed|string
     */
    public function setContent(mixed $item): mixed
    {
        // JSON 配列内での保存において、null と空文字列を同じように扱いますか？
        // null を JSON 内で null として保持する必要がある場合は、明示的に処理します。
        // 現在のロジックでは、null と '' の両方を '' に変換します。
        if ($item === null || $item === '') {
            // null または空文字列を配列内で空文字列として保存します。
            return '';
        }

        // Mroongaのベクターカラム処理の副作用を回避するため、
        // 整数・浮動小数点数は文字列に変換する
        if (is_int($item) || is_float($item)) {
            return (string) $item;
        }

        // メイン配列内の配列/オブジェクトに対するシリアライズロジックを保持します。
        if (is_array($item) || is_object($item)) {
            // Mroongaの仕様でjson2階層目以降は第1階層に展開されて保存されてしまうため、serializeする
            return '___serialized___'.serialize($item);
        }

        // 他の型 (文字列、ブール値) はそのまま返します。
        return $item;
    }
}
