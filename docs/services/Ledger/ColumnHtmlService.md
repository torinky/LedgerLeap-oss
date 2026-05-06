# ColumnHtmlService

**最終更新:** 2026年1月3日  
**Phase 3リファクタリング:** 2025年12月完了

## 1. 目的

`ColumnHtmlService`は、台帳レコードの各カラムに保存されたデータをHTML形式で整形して表示するためのサービスです。カラムのタイプ（テキスト、数値、ファイルなど）に応じて適切なHTML要素を生成します。

**Phase 3での主要変更（2025年12月）:**
- HTML文字列結合ロジック（280行）を削減し、Bladeコンポーネントベースの実装に移行
- `getFileHtml()`メソッドを93%削減（280行 → 20行）
- `prepareFilesData()`メソッドを新規追加し、データ変換ロジックを分離

## 2. クラス概要

- **名前空間**: `App\Services\Ledger`
- **役割**: 台帳カラムの値をHTMLとしてレンダリング
- **設計方針**: データ変換とHTML生成の責務を分離

## 3. 主要メソッド

### 3.1. show()

```php
public function show(
    object|array $columnDefineData,
    mixed $initialValue,
    bool $canView = true,
    array $attrs = [],
    string $idPrefix = '',
    bool $asCreate = false,
    ?Ledger $record = null,
    ?string $highlight = null,
    ?string $tenantId = null
): HtmlString
```

**役割**: カラム定義と値に基づいて、表示用のHTML文字列を生成します。

**主要引数:**
- `$columnDefineData`: カラム定義（`ColumnDefine`オブジェクトまたは配列）
- `$initialValue`: 表示する値
- `$canView`: 閲覧権限（`false`の場合は空文字列を返却）
- `$highlight`: 検索ハイライト用のキーワード（Phase 3で追加）
- `$tenantId`: テナントID（Phase 3で追加）

**カラムタイプ別の処理:**

| カラムタイプ | 処理内容 |
|------------|---------|
| `files` | `getFileHtml()`を呼び出し、`attachment-list`コンポーネントで表示 |
| `textarea` | Markdown変換 → AutoLink適用 → 展開可能コンテンツとして表示 |
| `number` | 単位（unit）を付加して表示 |
| `select` | バッジ形式で表示 |
| `chk` (チェックボックス) | 選択されたオプションをバッジ形式で表示 |
| その他 | AutoLink適用後、エスケープして表示 |

### 3.2. getFileHtml() (Phase 3リファクタリング完了)

```php
public function getFileHtml(string $mode = 'full', ?string $highlight = null): string
```

**役割**: 添付ファイルリストのHTMLを生成します。

**表示モード:**
- `full`: カード表示（最大8件表示）
- `compact`: リスト表示（最大4件表示）
- `icon-only`: アイコンのみ表示（最大5件表示）

**実装:**
- `prepareFilesData()`でデータを準備
- `components.ledger.attachment-list` Bladeコンポーネントで表示
- Phase 3で280行のHTML文字列結合ロジックを20行に削減（93%削減）

### 3.3. prepareFilesData() (Phase 3で新規追加)

```php
private function prepareFilesData(?string $highlight = null): array
```

**役割**: 添付ファイルのデータを配列形式に変換します。

**生成されるデータ構造:**
```php
[
    'id' => AttachedFile ID,
    'column_id' => カラムID,
    'filename' => 元のファイル名,
    'mime' => MIMEタイプ,
    'status' => 処理ステータス,
    'size' => ファイルサイズ,
    'thumbnailUrl' => サムネイルURL（画像の場合）,
    'primary_download' => [
        'url' => ダウンロードURL,
        'label' => 表示ラベル,
        'icon' => Font Awesomeアイコン,
    ],
    'secondary_download' => [...], // オプション
    'created_at' => 作成日時,
    'is_hit' => 検索ヒット判定（bool）,
]
```

**ダウンロードリンクの整理:**

| ファイルタイプ | primary_download | secondary_download |
|--------------|-----------------|-------------------|
| 画像ファイル | 元画像（original=true） | OCR後PDF |
| 最適化済みPDF | 最適化PDF | 元PDF（original=true） |
| その他 | 通常ダウンロード | なし |

### 3.4. setAttachmentCollection()

```php
public function setAttachmentCollection(Collection $attachments): static
```

**役割**: 添付ファイル情報（`AttachedFile`モデルのコレクション）を設定します。

**重要:** テキストプレビュー機能のため、`ledger`リレーションを自動的にEager Loadingします。

### 3.5. setAttachmentContents()

```php
public function setAttachmentContents(array $contents): static
```

**役割**: 添付ファイルの内容（テキスト抽出結果など）を設定します。ファイル内容の検索結果表示に利用されます。

## 4. 依存コンポーネント

### サービス
- **`AutoLinkService`**: テキスト内の自動リンク変換
- **`MarkdownRenderer`**: Markdown → HTML変換
- **`HtmlProcessorService`**: HTML処理

### ヘルパー
- **`AttachedFilePathHelper`**: ファイルパスの生成
- **`SearchHelper`**: 検索ヒット判定とキーワード抽出

### Bladeコンポーネント
- **`components.ledger.attachment-list`**: 添付ファイルリスト表示（Phase 3で統合）

### モデル
- **`ColumnDefine`**: カラム定義
- **`Ledger`**: 台帳レコード
- **`AttachedFile`**: 添付ファイル

## 5. Phase 3リファクタリングの成果

### 5.1. コード削減
- **getFileHtml()**: 280行 → 20行（93%削減）
- **総行数**: 旧実装280行、新実装100行（データ準備80行 + HTML生成20行）
- **削減率**: 64%

### 5.2. 設計改善
- **責務分離**: データ変換（`prepareFilesData()`）とHTML生成（Bladeコンポーネント）を分離
- **再利用性**: `attachment-list`コンポーネントを3箇所で共通利用（Show、ModifyColumn、RecordsTable）
- **保守性**: HTML文字列結合ロジックの削除により、可読性が大幅向上

### 5.3. 後方互換性
- **downloadUrlフィールド**: 旧実装との互換性のため、`primary_download.url`を保持
- **RPA対応**: `direct-download-link`クラスを維持し、自動化ツールとの互換性を確保

### 5.4. ログ方針
- `column_html_show_ms` / `column_html_prepare_files_ms` / `column_html_blade_render_ms` / `textarea_cache_hit` は、`LogPerformance` による標準監視メトリクスとして残す
- `AttachmentHtml` の詳細ログ（`[AttachmentHtml] getFileHtml` / `[AttachmentHtml] prepareFilesData`）は、通常運用では無効
- 調査時のみ `ATTACHMENT_HTML_DEBUG_LOGS=1` を設定して一時的に有効化する
- こうすることで、日常監視は軽量な性能メトリクスに寄せつつ、必要時のみ詳細ログを復元できる

### 5.5. キャッシュ方針
- キャッシュは `textarea` の専用経路と `getCachedColumnHtml()` に集約する
- `mount()` 前の早期キャッシュは採用しない
- 理由: 早期分岐は `updated_at` / `rawHtml` を含む後段のキャッシュキーと乖離しやすく、更新時の整合性と監視の一貫性を損ねるため

## 6. 関連ドキュメント

- **[添付ファイル機能](../../function/Attachment.md)** - ユーザー向け機能説明
- **[非同期処理アーキテクチャ](../../architecture/QueueProcessing.md)** - 添付ファイル処理ジョブ
- **[AttachedFileモデル](../../models/AttachedFile.md)** - データモデル仕様

## 7. テストと品質保証

### テストカバレッジ
- **`ColumnHtmlServiceTest`**: 6テスト、9アサーション（Phase 3で通過）
- **統合テスト**: RPA互換性テスト、N+1クエリ回避確認

### コード品質
- **Laravelコードスタイル**: Laravel Pint適合
- **Eager Loading**: `setAttachmentCollection()`で自動適用
- **エラーハンドリング**: テナントID未提供時のログ出力
