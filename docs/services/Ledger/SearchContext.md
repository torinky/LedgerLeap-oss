# SearchContext

## 目的

`SearchContext` は、ユーザーが入力した検索文字列を解析し、検索キーワード、タグ、類義語、ハイライト用の語句、フィルター条件といった検索コンテキストを管理するためのクラスです。検索ロジックの複雑性をカプセル化し、検索クエリの構築や結果の表示に必要な情報を提供します。

## クラス概要

*   **クラス名**: `App\Services\Ledger\SearchContext`
*   **役割**: 検索コンテキストを管理し、検索関連のデータを提供します。

## 主要な公開メソッド

*   **`__construct(SynonymService $synonymService)`**:
    *   目的・機能: `SearchContext` の新しいインスタンスを初期化します。`SynonymService` を注入して、類義語の取得に利用します。
    *   引数:
        *   `$synonymService`: 類義語サービス (`App\Services\SynonymService`) のインスタンス。

*   **`setSearch(string $search): void`**:
    *   目的・機能: 検索文字列を設定し、それに基づいてキーワード、タグ、類義語、ハイライト用の語句といった検索コンテキストを更新します。
    *   引数:
        *   `$search`: ユーザーが入力した検索文字列。

*   **`setKeywords(array $keywords): void`**:
    *   目的・機能: 検索キーワードを直接設定し、類義語とハイライト用の語句を更新します。
    *   引数:
        *   `$keywords`: 検索キーワードの配列。

*   **`setHighlights(array $highlights): void`**:
    *   目的・機能: ハイライト用の語句を直接設定します。
    *   引数:
        *   `$highlights`: ハイライト用の語句の配列。

*   **`setFilter(array $filter): void`**:
    *   目的・機能: フィルター条件を直接設定します。
    *   引数:
        *   `$filter`: フィルター条件の配列。

*   **`__toString(): string`**:
    *   目的・機能: オブジェクトが文字列として扱われた際に、SQLクエリなどに利用できるハイライト用の語句をスペース区切りで結合した文字列を返します。
    *   戻り値: `string` - ハイライト用の語句を結合した文字列。

*   **`getFlattenedSynonymsForKeyword(string $keyword): array`**:
    *   目的・機能: 指定されたキーワードに対応する、平坦化された類義語の配列（キーワード自身も含む）を取得します。
    *   引数:
        *   `$keyword`: 対象のキーワード。
    *   戻り値: `array` - 平坦化された類義語の配列。

## 依存する他のクラスや設定

*   **サービス**:
    *   `App\Services\SynonymService`
*   **ファサード**:
    *   `Illuminate\Support\Str`

## その他

*   検索文字列からキーワードとタグを抽出し、類義語を自動的に取得する内部ロジックを持っています。
*   `mb_convert_kana` を使用して、全角/半角の変換を行っています。
