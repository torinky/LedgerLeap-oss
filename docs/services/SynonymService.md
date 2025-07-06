# SynonymService

## 目的

`SynonymService` は、主に検索機能の精度向上を目的として、類義語の管理と形態素解析、およびテキスト間の類似度計算を提供します。これにより、ユーザーが入力したキーワードだけでなく、その類義語や関連する技術用語も考慮した検索が可能になります。

## クラス概要

* **クラス名**: `App\Services\SynonymService`
* **役割**: 類義語関連の処理を提供します。

## ユースケース

* 検索機能の精度向上（類義語での検索対応）
* 文章の類似性チェック

## メソッド

### `getSynonymsFromWord($word, array $options = [])`

*   目的・機能: 指定された単語の類義語を取得します。オプションで技術用語を含めるかどうかも制御できます。
*   **役割**: 指定された単語の類義語を取得します。
*   **引数**:
    *   `$word` (string): 類義語を取得する単語
    *   `$options` (array): オプション
        *   `'useSynonym'`: (bool) 類義語を使用するかどうか。
        *   `'useTechnicalTerm'`: (bool) 技術用語を使用するかどうか。
*   **戻り値**: `array`: 指定された単語の類義語の配列

### `wakati($inputText)`

*   目的・機能: 日本語の文章を形態素解析し、単語の配列に分割します。検索インデックスの作成や類似度計算の前処理に利用されます。
*   **役割**: 日本語の文章を形態素解析します。
*   **引数**:
    *   `$inputText`: 対象の文字列
*   **戻り値**: `array`

### `cosineSimilarity(string $text1, string $text2): float`

*   目的・機能: 2つのテキスト間のコサイン類似度を計算します。テキストの内容的な近さを数値で評価します。
*   **役割**: 文章のコサイン類似度を計算します。
*   **引数**:
    *   `$text1` : 対象の文字列1
    *   `$text2` : 対象の文字列2
*   **戻り値**: float

### `levenshteinNormalizedUtf8($s1, $s2, $cost_ins = 1, $cost_rep = 1, $cost_del = 1)`

*   目的・機能: 2つのUTF-8文字列間の正規化されたレーベンシュタイン距離を計算します。文字列の編集距離を基にした類似度を評価します。
*   **役割**: 正規化されたレーベンシュタイン距離を計算します。
*   **引数**:
    *   `$s1`: 対象の文字列1
    *   `$s2`: 対象の文字列2
    *   `$cost_ins` : デフォルト値1
    *   `$cost_rep` : デフォルト値1
    *   `$cost_del`: デフォルト値1
*   **戻り値**: float | int

### `levenshteinUtf8($s1, $s2, $cost_ins = 1, $cost_rep = 1, $cost_del = 1)`

*   目的・機能: 2つのUTF-8文字列間のレーベンシュタイン距離を計算します。文字列を別の文字列に変換するために必要な最小編集操作数を返します。
*   **役割**: レーベンシュタイン距離を計算します。
*   **引数**:
    *   `$s1`: 対象の文字列1
    *   `$s2`: 対象の文字列2
    *   `$cost_ins` : デフォルト値1
    *   `$cost_rep` : デフォルト値1
    *   `$cost_del`: デフォルト値1
*   **戻り値**: float

## 関連するクラス

*   `Igo\Tagger`
*   `App\Models\Synonym\TechnicalTermGroup`
*   `App\Models\Synonym\Word`
*   `App\Services\Config\SynonymServiceConfig`
