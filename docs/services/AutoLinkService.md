# AutoLinkService

## 1. 責務

`AutoLinkService` は、与えられた文字列（Markdown形式を含む）を受け取り、システムに登録されている自動リンク定義に基づいて文字列内の特定パターンをハイパーリンクに変換した上で、最終的なHTML文字列を返す責務を持ちます。

このサービスは、台帳のカラム表示や台帳定義の説明文表示など、システム内の様々な箇所で再利用されます。

## 2. 主要メソッド

### `convert(string $text, ?ColumnDefine $column = null, $context = null): string`

- **`$text`**: 変換対象の文字列。Markdown形式である可能性があります。
- **`$column`**: (オプション) `ColumnDefine` オブジェクト。カラムの型が `auto_number` の場合に特別処理を行うために使用します。
- **`$context`**: (オプション) 適用範囲を絞り込むためのコンテキスト情報（例: `Folder`, `LedgerDefine`, `Ledger` の各モデルインスタンス）。

## 3. ロジックフロー

`convert` メソッドは、以下の順序で処理を実行します。

1.  **MarkdownからHTMLへの変換:**
    - まず、`Spatie\LaravelMarkdown\MarkdownRenderer` を利用して、入力された `$text` をMarkdownからHTMLへ変換します。これにより、Markdownの構文（例: `*bold*`）が先に解釈され、リンク変換処理がHTMLタグを壊すのを防ぎます。

2.  **自動採番カラムの特別処理:**
    - `$column` が提供され、かつその型が `auto_number` である場合、他のどのルールよりも優先して、文字列全体を台帳内検索へのリンクに変換し、即座に結果を返します。

3.  **適用ルールの取得とキャッシュ:**
    - `$context` 情報に基づいて、この変換に適用すべき `AutoLink` 定義のリストをデータベースから取得します。
    - **適用範囲の解決:**
        - `$context` が `Folder` や `LedgerDefine`、`Ledger` の場合、そのコンテキストが属するフォルダ階層（自身とすべての子孫フォルダ）を特定します。
        - 適用範囲が指定されている定義と、グローバルな定義（適用範囲が未指定）の両方を効率的に取得します。
    - **キャッシュ:**
        - パフォーマンス向上のため、取得した `AutoLink` 定義のリストは、コンテキストに基づいたキャッシュキー（例: `auto_links_folder_123`）でキャッシュされます。

4.  **リンクの置換:**
    - 取得した `AutoLink` 定義を `priority` の昇順（優先度が高い順）でループ処理します。
    - `preg_replace_callback` を使用して、HTML文字列内から各定義の `pattern` に一致する部分を探し、`url_template` に基づいて `<a>` タグに置換します。
    - 一度置換されたHTML文字列が次のループの入力となるため、優先度の高いルールが先に適用されます。

5.  **最終的なHTMLの返却:**
    - すべての変換処理が終わったHTML文字列を返します。

## 4. キャッシュ戦略

パフォーマンスを確保するため、以下のキャッシュ戦略を採用しています。

- **キャッシュの保存:**
    - `AutoLink` 定義のリストは、`Cache::tags(['auto_links'])->remember(...)` を使用して保存されます。
    - `tags()` を使うことで、関連するキャッシュを一括で無効化できます。
- **キャッシュの無効化（パージ）:**
    - 以下のイベントが発生した際に、`Cache::tags('auto_links')->flush()` を呼び出してキャッシュをクリアし、常に最新の定義が使われることを保証します。
        1.  **`AutoLinkObserver`**: `AutoLink` 定義が作成・更新・削除された時。または、中間テーブル `auto_link_scopes` が変更された時（Pivotモデルの `$touches` 機能による）。
        2.  **`FolderObserver`**: `Folder` の親子関係（`parent_id`）が変更された時、またはフォルダが削除された時。

## 5. 依存性

- **`Spatie\LaravelMarkdown\MarkdownRenderer`**: コンストラクタインジェクションにより注入され、Markdownのレンダリングに使用されます。
- **`AutoNumberPatternService`**: 2026年3月にDI追加。`auto_number` カラムからの正規表現パターン生成ロジックを委譲しています（詳細は下記参照）。

## 6. AutoNumberPatternService との分担（2026年3月追加）

`auto_number` カラムのパターン生成と収集は `AutoNumberPatternService`（`app/Services/AutoNumberPatternService.php`）に切り出されています。

| メソッド | 移動先 | 役割 |
|---|---|---|
| `generateAutoNumberPattern()` | `AutoNumberPatternService::generatePattern()` | 正規表現文字列の生成 |
| `getVirtualAutoNumberLinks()` | `AutoNumberPatternService::getPatterns()` を内部利用 | パターン収集・キャッシュ |

`AutoNumberPatternService` は `AutoLinkService` と `RelatedLedgers` Livewire コンポーネントの両方から共用されます。
キャッシュキーはテナントIDを含む形式（`"auto_number_patterns:{$tenantId}"`）を採用しており、
マルチテナント環境でのキャッシュ混在を防止しています。

詳細は [関連案件タブ機能](../features/related-ledgers.md) を参照。
