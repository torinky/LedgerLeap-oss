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
        // Log::info('AsColumnArrayJson: get method called for key: ' . $key);
        // Log::info('AsColumnArrayJson: Raw value from DB: ' . Str::limit(json_encode($value), 500));
        // Log::info('AsColumnArrayJson: Attributes value for key: ' . Str::limit(json_encode($attributes[$key] ?? null), 500));

        $content = $attributes[$key] ?? $value;
        // Log::info('AsColumnArrayJson: Content to process: ' . Str::limit(json_encode($content), 500));

        // データベースからの値が空文字列の場合、空配列を返します。
        // これは、空配列に対して '' を保存した場合のケースを処理します。
        if ($content === '') {
            // Log::info('AsColumnArrayJson: Content is empty string, returning empty array.');
            return []; // または、空文字列読み取り時の望ましい動作に応じて null を返す
        }
        // すでに null であるか、文字列でない場合は、JSON デコード試行前にそのまま返します。
        if ($content === null || ! is_string($content)) {
            // Log::info('AsColumnArrayJson: Content is null or not a string, returning as is.');
            return $content;
        }

        try {
            // JSON文字列をデコードします。
            // 必要であれば、set の動作により合わせるために連想配列 (true) を使用しますが、
            // 現在のコードはオブジェクト (false) を使用しています。今のところ一貫性を保ちます。
            $decodedContent = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
            // Log::info('AsColumnArrayJson: Decoded content: ' . Str::limit(json_encode($decodedContent), 500));
        } catch (JsonException $e) {
            // JSONデコードに失敗した場合はログを出力します。
            Log::alert('AsColumnArrayJson: JSON decode error: '.$e->getMessage().' for value: '.Str::limit($content, 500));

            return null; // または空配列 [] を返す？
        }

        // デコードされた配列/オブジェクトの各要素を処理します。
        if (is_array($decodedContent) || is_object($decodedContent)) {
            // Log::info('AsColumnArrayJson: Decoded content is array or object, processing elements.');
            // 配列/オブジェクトの要素を参照で変更するか、新しいものを作成する方法が必要です。
            $processedContent = is_object($decodedContent) ? clone $decodedContent : $decodedContent;
            foreach ($decodedContent as $index => $item) {
                if (is_object($processedContent)) {
                    $processedContent->{$index} = $this->getContent($item);
                } else {
                    $processedContent[$index] = $this->getContent($item);
                }
            }

            // Log::info('AsColumnArrayJson: Processed content: ' . Str::limit(json_encode($processedContent), 500));
            return $processedContent;
        }

        // JSON デコードされたが配列/オブジェクトではなかった場合 (例: 単なる文字列 "foo")、それを返します。
        // Log::info('AsColumnArrayJson: Decoded content is not array/object, returning as is.');
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
            // Log::info('AsColumnArrayJson: getContent: Attempting to unserialize: ' . Str::limit($temp, 500));
            // unserialize のエラーハンドリングを追加します。
            $unserialized = @unserialize($temp);
            if ($unserialized === false && $temp !== serialize(false)) {
                Log::warning('AsColumnArrayJson: getContent: Failed to unserialize value: '.Str::limit($temp, 500));

                return $item; // 失敗時には元のシリアライズされた文字列を返す？ それとも null？
            }

            // Log::info('AsColumnArrayJson: getContent: Successfully unserialized: ' . Str::limit(json_encode($unserialized), 500));
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
        $content = $value ?? $attributes[$key] ?? null;

        // ----- 新規ガード: 入力が既に JSON 文字列の場合は再エンコードを行わない -----
        // 文字列かつ '[' または '{' で始まる場合に JSON と仮定し、デコード可能か検証する。
        if (is_string($content)) {
            // BOMや前後空白を除去して判定
            $trimmed = preg_replace('/^\x{FEFF}|\s+$/u', '', $content);
            if (Str::startsWith($trimmed, '[') || Str::startsWith($trimmed, '{')) {
                try {
                    // JSON_THROW_ON_ERROR を使って厳密に検証
                    $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                    // デコードに成功し配列またはオブジェクトとなる場合は、そのまま DB に保存する
                    if (is_array($decoded) || is_object($decoded)) {
                        // 既に JSON 文字列なのでそのまま戻す（再エンコードを防止）
                        return [$key => $trimmed];
                    }
                    // 数値や文字列単体が返る場合は通常の処理にフォールスルー
                } catch (\JsonException $e) {
                    // 無効な JSON であれば通常の処理に進む（ログに警告を残す）
                    Log::warning('AsColumnArrayJson: set: Input value appears to be invalid JSON despite starting with [ or {');
                    Log::warning('AsColumnArrayJson: set: Input value: '.Str::limit($content, 200));
                }
            }
        }
        // ----- ガードここまで -----

        // 追加ガード: stdClass 等のオブジェクトがトップレベルで渡されるケースに対応
        // テストや AsColumnArrayJson を模擬するコードでは、content_attached を stdClass として
        // 直接代入することがあるため、その場合は連想配列に変換して配列処理に委ねます。
        if (is_object($content)) {
            $content = (array) $content;
        }

        // 値が配列かどうかを確認します。
        if (is_array($content)) {
            // Mroonga 用にトップレベルはインデックス配列であることが望まれるため、
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
                // Log::info('AsColumnArrayJson: set: All elements are empty, returning empty JSON array.');
                return [$key => '[]'];
            }

            // すべてが空ではなかった場合、元の処理ロジックに進みます。
            // 1階層目はjson配列にする (強制的にインデックス配列にする)
            $processedContent = array_values($content);
            // Log::info('AsColumnArrayJson: set: Content after array_values: ' . Str::limit(json_encode($processedContent), 500));
            foreach ($processedContent as $index => $item) {
                $processedContent[$index] = $this->setContent($item);
            }
            // 処理された配列で content を更新します。
            $content = $processedContent;
            // Log::info('AsColumnArrayJson: set: Content after setContent loop: '
            //     . Str::limit(json_encode($content), 500));

        } elseif ($content === null || $content === '') {
            // 入力値自体が null または空文字列の場合も空のJSON配列を返します。
            // Log::info('AsColumnArrayJson: set: Input content is null or empty string, returning empty JSON array.');
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

//        Log::info('AsColumnArrayJson: set: Final JSON string to be saved: '.Str::limit($jsonString, 500));

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

        // 他の型 (文字列、数値、ブール値) はそのまま返します。
        return $item;
    }
}
