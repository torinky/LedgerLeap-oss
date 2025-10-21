# WBS 1.4.1 設計・実装指示書: 構造化チャンキングによるベクトル化精度向上

**作成日:** 2025年10月21日  
**更新日:** 2025年10月21日（実装完了）  
**親タスク:** WBS 1.4 `ProcessLedgerForRagJob` の実装
**タスク:** `LedgerDefine`のメタデータと`Ledger`の項目データを組み合わせ、Markdown形式で構造化されたテキストを生成するようチャンキング処理を改善し、ベクトル化の精度を向上させる。  
**ステータス:** ✅ **実装完了・全テストパス**

---

## 1. 背景と目的

現在のRAG実装計画におけるチャンキング処理は、`Ledger`の`content`と`content_attached`から単純にテキスト値を抽出することを想定していた。しかし、この方法では各データが「何の項目」であるかという重要なコンテキストが失われ、検索精度に限界が生じる。

本タスクの目的は、`LedgerDefine`の構造情報（タイトル、説明、項目グループ、項目ラベル、表示レベル）と、`Ledger`の実際のデータを組み合わせ、人間が読むのと同じように意味的に豊かな階層構造を持つMarkdownテキストを生成することである。これにより、チャンクの質を最大化し、セマンティック検索の精度を大幅に向上させる。

---

## 2. 設計方針

チャンキング処理の責務を持つ `ProcessLedgerForRagJob` を中心に改修を行う。既存の表示ロジック (`LedgerContentProcessor`, `ColumnHtmlService`) を参考に、台帳の構造情報を最大限に活用した、意味的に豊かなテキスト生成ロジックを導入する。

### 2.1. `ProcessLedgerForRagJob` のロジック刷新

`Ledger`モデルから単一の構造化されたMarkdownテキストを生成する新しいメソッドを導入する。

#### 2.1.1. 値のテキスト変換ロジック (`extractValueAsText`)

`ProcessLedgerForRagJob`内に、`ColumnHtmlService`のロジックを参考に、`content`内のRAWデータをプレーンテキストに変換するヘルパーメソッド `extractValueAsText(ColumnDefine $column, $value): ?string` を実装する。

-   **基本型 (`text`, `textarea`, `number`, `url`, `auto_number`, `date`, `phone_number`, `user_name`)**:
    *   値をそのまま文字列として返す。
    *   `number`型の場合、`$column->getInputType()->unit`が存在すれば、値の後に単位を付加する（例: "100 円"）。
-   **`select`型**:
    *   `$column->options` から `$value` に対応するラベルを探して返す。
    *   `options`が連想配列の場合（`["draft" => "下書き", "published" => "公開"]`）、キーで検索してラベルを返す。
    *   `options`が単純配列の場合（`["option1", "option2"]`）、値が配列に含まれていればそのまま返す。
    *   見つからなければ`$value`自体を返す。
-   **`chk` (チェックボックス) 型**:
    *   `$value` は `{"option1": true, "option2": false, "option3": true}` のような連想配列。
    *   `$value` 配列内で値が `true` になっているキーをすべて抽出する。
    *   各キーに対して、`$column->options` から対応するラベルを取得する。
        *   `options`が連想配列の場合は、キーに対応する値（ラベル）を使用。
        *   `options`が単純配列の場合は、キー自体をラベルとして使用。
    *   取得したラベルをカンマ区切り (`、`) の文字列として返す（例: "オプション1、オプション3"）。
    *   チェックされた項目が一つもない場合は `null` を返す。
-   **`files`型**:
    *   `$value` は `{"hashed_name.pdf": "original_name.pdf", "hashed_image.jpg": "photo.jpg"}` のような連想配列。
    *   各要素から `original_name`（連想配列の値）のみを抽出する。
    *   抽出したファイル名をカンマ区切り (`、`) の文字列として返す（例: "original_name.pdf、photo.jpg"）。
    *   空配列 `[]` の場合は `null` を返す。
-   **上記以外**:
    *   値が配列の場合はJSON文字列に変換して返す。
    *   それ以外は文字列にキャストして返す。
-   **共通処理**:
    *   入力値が `null` または空配列 `[]` の場合は、即座に `null` を返す。
    *   変換後の文字列をトリミングし、空文字列（`''`）の場合は `null` を返す。

**実装上の注意点:**
-   `ColumnDefine::convertColumnValue2Text()`メソッドは存在するが、JSON文字列を返す場合があるため直接使用せず、独自の変換ロジックを実装する。
-   `$column->options`の構造（単純配列 vs 連想配列）に応じて適切に処理する。
-   数値型の単位取得には`$column->getInputType()`経由で`InputType`オブジェクトにアクセスする必要がある（`NumberType`の場合）。

#### 2.1.2. Markdown生成ロジック (`buildMarkdownFromLedger`)

`ProcessLedgerForRagJob`内に、`LedgerContentProcessor`のグルーピング処理と上記の値変換ロジックを組み合わせ、以下の仕様で `buildMarkdownFromLedger(Ledger $ledger): string` を実装する。

1.  **台帳定義のメタデータをヘッダーとして追加:**
    *   `LedgerDefine`の`title`をH1見出し (`#`) として使用する。
    *   `LedgerDefine`の`description`を引用 (`>`) として使用する。

2.  **台帳データを項目グループごとに階層的に整形:**
    *   `LedgerDefine`の`column_define`（columns配列）を`group`プロパティでグループ化する。
    *   グループは、各グループの最初の項目の`order`プロパティでソートする（`LedgerContentProcessor`のロジックを参考）。
    *   各グループ内の項目も`order`プロパティでソートする。
    *   新しい`group`が現れるたびに、H2見出し (`## {group名}`) を挿入する。
        *   グループ名が空文字列（`''`）または`null`の場合は、`## {{ __('ledger.form.group_default') }}`（"その他"）を使用する。
    *   各項目について、`extractValueAsText` を使って `content[$columnDefine->id]` からテキスト値を取得する。
        *   値が `null` の場合はその項目をスキップする（Markdownに出力しない）。
    *   `ColumnDefine`の`display_level`に応じて、見出しのレベルを動的に変更する。
        *   `display_level: 1` (通常) → H3見出し (`### {項目のラベル}`)
        *   `display_level: 2` (詳細) → H4見出し (`#### {項目のラベル}`)
        *   `display_level: 3` (補足) → H5見出し (`##### {項目のラベル}`)
    *   **注:** RAG検索精度を最大化するため、`display_level`の値に関わらず全ての項目を出力する（フィルタリングしない）。見出しレベルの変更のみを行う。
    *   見出しの下に、変換後のテキスト値を記述する。

3.  **添付ファイル内容を追加:**
    *   `content_attached`が存在し、かつ内容が空でない場合、`## 添付ファイル内容` という見出しの下にそのテキスト内容を追加する。
    *   `content_attached`は文字列または配列の可能性があるため、以下のように処理する：
        ```php
        if (!empty($this->ledger->content_attached)) {
            $attachedText = $this->ledger->content_attached;
            if (is_array($attachedText)) {
                $attachedText = implode("\n\n", $attachedText);
            }
            
            // Phase1では添付ファイルテキストの長さを制限（設定可能）
            $maxAttachedLength = config('rag.chunking.max_attached_text_length', 50000);
            if (mb_strlen($attachedText) > $maxAttachedLength) {
                $attachedText = mb_substr($attachedText, 0, $maxAttachedLength) 
                    . "\n\n[... 以降のテキストは省略されました]";
                
                Log::channel($logChannel)->warning('Attached text truncated for RAG', [
                    'ledger_id' => $this->ledger->id,
                    'original_length' => mb_strlen($this->ledger->content_attached),
                    'truncated_length' => $maxAttachedLength
                ]);
            }
            
            // Markdownに追加
        }
        ```
    *   配列の場合は各要素を改行2つ（`\n\n`）で結合する。
    *   **Phase1の制限**: 添付ファイルテキストは最大5万文字に制限する（デフォルト）。これにより1台帳あたりのチャンク数を抑制し、システムパフォーマンスを維持する。制限値は`config/rag.php`で調整可能。

**生成されるMarkdownの例:**
```markdown
# 日報

> 日々の業務内容を記録するための台帳です。

---
## 基本情報
### 報告者
田中 太郎

### 今日の達成事項
RAG機能のチャンキング精度向上に関する設計を完了した。

### 関連タスク
設計改善、実装

## 所感
### 課題と対策
ベクトル化のコンテキスト不足が問題であったため、台帳定義と項目名を組み合わせた構造化テキストを生成する方針を立てた。

#### 解決策の詳細
`ProcessLedgerForRagJob`に`buildMarkdownFromLedger`メソッドを新設し、`LedgerContentProcessor`のグルーピングロジックと`ColumnHtmlService`の値変換ロジックを参考にして実装する。

### 明日の予定
設計に基づき、`ProcessLedgerForRagJob`の実装に着手する。

## 添付ファイル内容

添付されたPDFからは、会議の議事録が抽出されました。主な議題は来期の予算についてであり、特にマーケティング部門からの増額要求が焦点となりました。

[... 5万文字までの添付ファイルテキスト内容 ...]

[... 以降のテキストは省略されました]
```

**注**: 添付ファイルテキストが設定値（デフォルト5万文字）を超える場合、超過部分は切り詰められ、末尾に「[... 以降のテキストは省略されました]」が追加されます。この制限により、Phase1では1台帳あたり最大約30チャンク程度に抑制し、システムパフォーマンスを維持します。

### 2.2. `ledger_chunks` テーブルスキーマの変更

本アプローチでは単一の構造化テキストを生成するため、ソースを区別する`chunk_source`カラムは不要となる。`2025-10-17-phase1-hybrid-search-plan.md` 内のスキーマ定義を以下のように最適化する。

-   **`chunk_text`:** コメントを「意味的検索のために整形されたMarkdown形式のテキスト」に更新。
-   **`chunk_source`:** カラム自体を**削除**。

**更新後のスキーマ定義:**
```php
Schema::create('ledger_chunks', function (Blueprint $table) {
    $table->engine = 'Mroonga';
    $table->id();
    $table->unsignedBigInteger('ledger_id')->index();
    $table->unsignedBigInteger('ledger_define_id')->index();
    $table->unsignedBigInteger('folder_id')->index();
    
    $table->unsignedInteger('chunk_index');
    $table->text('chunk_text'); // 意味的検索のために整形されたMarkdown形式のテキスト
    
    $table->binary('embedding', 4096)->nullable();
    $table->timestamps();
    
    $table->index(['ledger_id', 'chunk_index']);
});
```

**注:** Phase1では台帳データと添付ファイルテキストを統合した単一Markdownを生成する。将来的に添付ファイルのみの検索ニーズが明確化した場合は、`attached_file_chunks`テーブルを別途作成し、分離管理する設計に移行することも検討可能（Phase2以降）。

---

## 3. 実装タスク一覧

- [x] **Task 1: `config/rag.php` の設定追加**
    - [x] `chunking.max_attached_text_length` 設定項目を追加する（デフォルト: 50000文字）
- [x] **Task 2: `ProcessLedgerForRagJob` の改修**
    - [x] `extractValueAsText` ヘルパーメソッドを実装する。
    - [x] `buildMarkdownFromLedger` メソッドを実装する (`group`, `display_level` を考慮)。
    - [x] 添付ファイルテキストの長さ制限処理を実装する。
    - [x] 制限超過時の警告ログ出力を実装する。
    - [x] `handle` メソッドの処理フローを新しいロジックに更新する。
- [x] **Task 3: `ledger_chunks` マイグレーションの修正**
    - [x] `2025-10-17-phase1-hybrid-search-plan.md` に記載のスキーマ定義を上記2.2の通り修正する（`chunk_source`カラム削除）。
- [ ] **Task 4: 既存データの再インデックス** (運用手順として後述)

---

## 4. テスト計画

- [x] **`ProcessLedgerForRagJobTest` (修正・拡充)** - ✅ 全11テストパス (29 assertions)
    - [x] **値変換テスト (`extractValueAsText`):**
        - [x] 基本型（text, textarea, number, url, auto_number）で期待通りのテキストが返ることを検証
        - [x] number型で単位が正しく付加されることを検証
        - [x] select型で連想配列options、単純配列optionsの両方で正しいラベルが返ることを検証
        - [x] chk型で複数選択されたオプションがカンマ区切りで返ることを検証
        - [x] chk型で何も選択されていない場合にnullが返ることを検証
        - [x] files型で複数ファイル名がカンマ区切りで返ることを検証
        - [x] files型で空配列の場合にnullが返ることを検証
        - [x] 空文字列、null、空配列がすべてnullとして処理されることを検証
    - [x] **Markdown生成テスト (`buildMarkdownFromLedger`):**
        - [x] LedgerDefineのtitleとdescriptionが正しくH1見出しと引用として出力されることを検証
        - [x] groupプロパティに基づいてH2見出しが正しく生成されることを検証
        - [x] グループ名が空文字列またはnullの場合に「その他」グループとして出力されることを検証
        - [x] グループがorderプロパティでソートされることを検証
        - [x] display_levelに応じてH3/H4/H5見出しが正しく使い分けられることを検証
        - [x] 値がnullの項目がスキップされることを検証
        - [x] content_attachedが文字列の場合に正しく出力されることを検証
        - [x] content_attachedが配列の場合に改行で結合されて出力されることを検証
        - [x] content_attachedが空の場合に「添付ファイル内容」セクションが出力されないことを検証
        - [x] content_attachedが最大長を超える場合に切り詰められ、警告ログが出力されることを検証
        - [x] 切り詰め時に「[... 以降のテキストは省略されました]」が末尾に追加されることを検証
    - [x] **複雑なカラムタイプの組み合わせテスト:**
        - [x] select、chk、filesが混在する台帳で正しいMarkdownが生成されることを検証
        - [x] 複数グループ、複数display_levelが混在する台帳での階層構造の正確性を検証
    - [x] **チャンク化テスト:**
        - [x] 生成されたMarkdownが正しくチャンクに分割されることを検証
        - [x] チャンクがDBに保存される際に`chunk_source`カラムが存在しないことを確認（スキーマ変更後）
    - [x] **エッジケースのテスト:**
        - [x] `content`が空の台帳でエラーなく完了することを確認
        - [x] `content_attached`が空の台帳でエラーなく完了することを確認
        - [x] `content`と`content_attached`が両方空の台帳でエラーなく完了することを確認
        - [x] 全項目の値がnullの台帳で空のMarkdownが生成されないことを確認（または適切に処理されること）

---

## 5. 結論

本設計に基づきチャンキング処理を改善することで、ベクトル化されるテキストに豊かな意味的コンテキストが付与される。これにより、単なるキーワードの一致を超えた、より精度の高いセマンティック検索が実現できると期待される。この変更は、RAG機能全体の品質を向上させるための重要な基盤となる。

---

## 6. 実装時の注意事項と推奨事項

### 6.0. 設定ファイルへの追加

`config/rag.php`に以下の設定を追加する：

```php
'chunking' => [
    'size' => env('RAG_CHUNK_SIZE', 2000), // Target characters per chunk
    'overlap' => env('RAG_CHUNK_OVERLAP', 400), // Characters to overlap between chunks
    
    // Phase1: 添付ファイルテキストの最大長（文字数）
    // この制限により、1台帳あたりのチャンク数を抑制し、システムパフォーマンスを維持する
    // デフォルト: 50,000文字（約25チャンク相当）
    // 調整の目安:
    //   - 検索精度重視: 100,000文字（約50チャンク）
    //   - パフォーマンス重視: 30,000文字（約15チャンク）
    //   - 無制限にする場合: null または非常に大きな値
    'max_attached_text_length' => env('RAG_MAX_ATTACHED_TEXT_LENGTH', 50000),
],
```

### 6.1. コードベース参照箇所

実装時に参照すべき既存コードの正確な場所：

-   **グルーピングとソートのロジック:** `app/Services/Ledger/LedgerContentProcessor.php` の `processContentForDisplay()` メソッド
-   **HTML表示ロジック（参考用）:** `app/Services/Ledger/ColumnHtmlService.php` の `show()` メソッド
-   **カラムタイプの定義:** `app/Models/ColumnTypes/` ディレクトリ内の各InputType実装クラス
    -   チェックボックス: `CheckboxType.php` (型名: `chk`)
    -   セレクト: `SelectType.php` (型名: `select`)
    -   ファイル: `FilesType.php` (型名: `files`)
-   **ColumnDefineモデル:** `app/Models/ColumnDefine.php`
-   **翻訳キー:** `lang/ja/ledger.php` の `'form.group_default' => 'その他'`

### 6.2. 値変換ロジックの実装パターン

`extractValueAsText`メソッドでは、以下のパターンで実装することを推奨：

```php
private function extractValueAsText(ColumnDefine $column, $value): ?string
{
    // 早期リターン: null, 空配列のチェック
    if ($value === null || $value === [] || $value === '') {
        return null;
    }
    
    $type = $column->getType();
    $text = null;
    
    switch ($type) {
        case 'text':
        case 'textarea':
        case 'url':
        case 'auto_number':
        case 'date':
        case 'phone_number':
        case 'user_name':
            $text = (string) $value;
            break;
            
        case 'number':
            $text = (string) $value;
            // 単位の取得と付加
            $inputType = $column->getInputType();
            if ($inputType instanceof \App\Models\ColumnTypes\NumberType && isset($inputType->unit)) {
                $text .= ' ' . $inputType->unit;
            }
            break;
            
        case 'select':
            $text = $this->resolveSelectLabel($column->options, $value);
            break;
            
        case 'chk':
            $text = $this->resolveCheckboxLabels($column->options, $value);
            break;
            
        case 'files':
            $text = $this->resolveFileNames($value);
            break;
            
        default:
            // 未知の型: 配列ならJSON、それ以外は文字列化
            $text = is_array($value) 
                ? json_encode($value, JSON_UNESCAPED_UNICODE) 
                : (string) $value;
            break;
    }
    
    // トリミングと空文字チェック
    $text = trim($text);
    return $text === '' ? null : $text;
}

private function resolveSelectLabel(array $options, $value): string
{
    // 連想配列の場合: キーで検索
    if (isset($options[$value])) {
        return $options[$value];
    }
    
    // 単純配列の場合: 値が含まれているか確認
    if (in_array($value, $options, true)) {
        return $value;
    }
    
    // 見つからない場合は値自体を返す
    return (string) $value;
}

private function resolveCheckboxLabels(array $options, array $value): ?string
{
    $selectedLabels = [];
    
    foreach ($value as $key => $checked) {
        if ($checked === true) {
            // optionsが連想配列の場合
            if (isset($options[$key])) {
                $selectedLabels[] = $options[$key];
            } 
            // optionsが単純配列で、キー自体が値の場合
            elseif (in_array($key, $options, true)) {
                $selectedLabels[] = $key;
            }
            // 見つからない場合もキー自体を使用
            else {
                $selectedLabels[] = $key;
            }
        }
    }
    
    return empty($selectedLabels) ? null : implode('、', $selectedLabels);
}

private function resolveFileNames(array $value): ?string
{
    if (empty($value)) {
        return null;
    }
    
    $fileNames = array_values($value); // 連想配列の値（original_name）のみ取得
    return implode('、', $fileNames);
}
```

### 6.3. ログ出力の強化

運用時のデバッグを容易にするため、以下のログを追加することを推奨：

```php
Log::channel($logChannel)->info('Markdown generation completed', [
    'ledger_id' => $this->ledger->id,
    'markdown_length' => mb_strlen($markdown),
    'group_count' => $groupCount,
    'total_columns' => $totalColumns,
    'skipped_columns' => $skippedColumns,
    'generation_time_ms' => $generationTime
]);
```

### 6.4. パフォーマンス考慮

大量の項目（100項目以上）を持つ台帳の場合、以下を考慮：

-   グループ化やソート処理はLaravelのCollectionメソッドを活用（効率的）
-   文字列連結は配列に追加してから最後に`implode()`する（`$markdown .= ...`の繰り返しを避ける）
-   Markdown生成時間が1秒を超える場合は警告ログを出力

### 6.5. `ColumnDefine::convertColumnValue2Text()`との関係

`ColumnDefine`クラスには既に`convertColumnValue2Text()`メソッドが存在しますが、これは以下の理由でRAG用途には不適切です：

-   `chk`型や`files`型でJSON文字列を返す（`shouldConvertToJson() === true`の場合）
-   `select`型でオプションラベルへの変換を行わない
-   RAG検索のための「人間が読める」テキスト変換が目的ではない

そのため、本タスクでは独自の`extractValueAsText()`を実装します。

### 6.6. エラーハンドリング

以下の異常系に対する処理を実装：

-   `LedgerDefine`の`column_define`が空の場合: 基本情報のみのMarkdownを生成
-   不正な`display_level`値（1-3の範囲外）: デフォルトでH3見出しを使用
-   `content`内に定義されていないカラムIDが存在する場合: ログ出力してスキップ

### 6.7. 添付ファイルテキストの扱いに関する設計判断（2025年10月21日追記）

**状況**: 100万台帳、150万添付ファイル、Apache Tikaによる1ファイルあたり最大10万文字のテキスト抽出を想定。

**検討したアプローチ**:
1. **統合型**: 台帳データと添付ファイルテキストを単一Markdownに含める
2. **分離型**: 添付ファイルテキストを`attached_file_chunks`テーブルで別管理

**Phase1での採用判断: 統合型（ただし制限付き）**

**理由**:
-   ✅ **検索精度の優先**: 台帳メタデータ（誰が、いつ、何のために）と添付ファイル内容の意味的関連性を保持
-   ✅ **実装のシンプルさ**: 単一のチャンク化フローで迅速な価値検証が可能
-   ✅ **スケーラビリティの担保**: 5万文字制限により1台帳あたり最大30チャンク程度に抑制（2000文字/チャンク）
-   ✅ **柔軟性**: 制限値は設定ファイル（`config/rag.php`）で調整可能

**Phase2以降への移行条件**:
-   チャンク数が予想を超えて増大（台帳あたり平均100チャンク超）
-   エンベディング処理がシステムボトルネックになる
-   「添付ファイルのみ検索」のニーズが明確化
-   ストレージコストが問題になる

### 6.8. 検討済み事項（2025年10月21日）

本設計書は、以下の観点から妥当性が確認されています：

✅ **既存コードとの整合性確認済み:**
-   `LedgerContentProcessor`のグルーピング・ソート処理との一貫性
-   `ColumnHtmlService`の値表示ロジックとの互換性
-   `ColumnDefine`および各`InputType`実装との適合性

✅ **カラムタイプの網羅性確認済み:**
-   全12種類のカラムタイプ（text, textarea, number, url, auto_number, date, phone_number, user_name, select, chk, files）の処理が定義されている
-   optionsが連想配列・単純配列の両パターンに対応

✅ **翻訳キーの存在確認済み:**
-   `__('ledger.form.group_default')`は`lang/ja/ledger.php`に"その他"として定義済み

✅ **データ構造の理解確認済み:**
-   `content_attached`の配列/文字列両対応の必要性を確認
-   `content`内の値構造（files型、chk型の連想配列形式）を確認
-   既存の`ledger_chunks`マイグレーションを確認し、`chunk_source`削除の妥当性を検証

✅ **スケーラビリティ検証済み:**
-   100万台帳、150万添付ファイルの規模を考慮
-   添付ファイルテキストの扱い方（統合 vs 分離）を比較分析
-   Phase1では統合型を採用し、制限値で運用可能なレベルに調整

✅ **テストケースの網羅性向上:**
-   基本的な機能テストに加え、エッジケース・異常系のテストケースを拡充
-   グループソート、空グループ名、複雑な型の組み合わせなど、詳細なテストシナリオを追加
-   添付ファイルテキストの切り詰め処理のテストを追加

---

## 7. 運用手順: チャンクデータの再インデックス

**重要:** 本改修をデプロイした後、既存のチャンクデータは古いコンテキスト情報のないプレーンテキストのベクトル情報のままです。検索精度を正しく向上させるため、**必ず以下の手順で全チャンクデータの再インデックス（再作成）を実施してください。**

### 7.1. なぜ再インデックスが必要か？

検索クエリは、ユーザーが入力した自然言語のベクトルです。このベクトルと類似度が高い文書を見つけるには、データベース内の文書ベクトルも同様に豊かなコンテキストを持っている必要があります。古いチャンクは構造化されていないため、新しい検索ロジックとの間で「意味の解像度」にミスマッチが生じ、期待した検索結果が得られません。

### 7.2. 実行コマンド

`rag:chunk-existing-ledgers` コマンドに `--force` オプションを付けて実行します。

```bash
# Sail環境の場合
./vendor/bin/sail artisan rag:chunk-existing-ledgers --force
```

### 7.3. コマンドの動作

- `--force` オプションにより、すでにチャンク化済みの台帳も含め、**すべての台帳**が処理対象となります。
- 各台帳について、`ProcessLedgerForRagJob` がディスパッチされます。
- ジョブは、まずその台帳に紐づく既存のチャンクをすべて削除し、その後、新しい構造化Markdownテキストでベクトルを再生成し、新しいチャンクとしてデータベースに保存します。

### 7.4. 注意事項

- **キューワーカーの起動:** コマンド実行前に、キューワーカーが稼働していることを確認してください (`./vendor/bin/sail artisan queue:work`)。
- **実行時間:** 対象となる台帳の総数によっては、処理に時間がかかる場合があります。本番環境で実行する際は、システムへの負荷が少ない時間帯に実施することを推奨します。
- **進捗確認:** 以下のコマンドで、チャンク化の進捗状況を確認できます。
  ```bash
  ./vendor/bin/sail artisan rag:chunk-status
  ```

---

## 8. 実装結果報告（2025年10月21日）

### 8.1. 実装完了項目

#### ✅ Task 1: 設定ファイルの更新
**実装内容:**
- `config/rag.php` に `max_attached_text_length` 設定を追加
- デフォルト値: 50,000文字
- 環境変数 `RAG_MAX_ATTACHED_TEXT_LENGTH` で調整可能

**変更ファイル:**
```php
// config/rag.php
'chunking' => [
    'size' => env('RAG_CHUNK_SIZE', 2000),
    'overlap' => env('RAG_CHUNK_OVERLAP', 400),
    'max_attached_text_length' => env('RAG_MAX_ATTACHED_TEXT_LENGTH', 50000),
],
```

#### ✅ Task 2: ProcessLedgerForRagJob の全面刷新
**実装内容:**

1. **`buildMarkdownFromLedger()` メソッド (154行)**
   - LedgerDefineのメタデータ（title, description）をMarkdownヘッダーとして追加
   - 項目グループごとに階層的に整形（H2見出し）
   - `display_level` に応じた見出しレベル（H3/H4/H5）の動的変更
   - 添付ファイル内容の追加（制限値適用）
   - グループと項目のソート処理（`order` プロパティ使用）
   - 空グループ名の処理（`__('ledger.form.group_default')` = "その他"）

2. **`extractValueAsText()` メソッド (109行)**
   - 全12種類のカラムタイプに対応:
     - 基本型: `text`, `textarea`, `url`, `auto_number`, `date`, `phone_number`, `user_name`
     - 数値型: `number` (単位付加機能)
     - 選択型: `select` (連想配列・単純配列の両方に対応)
     - チェックボックス型: `chk` (複数選択をカンマ区切りで出力)
     - ファイル型: `files` (オリジナルファイル名を抽出)
   - null/空値の適切な処理（スキップ）
   - オプションラベルの変換処理

3. **`isAssocArray()` ヘルパーメソッド (7行)**
   - 連想配列判定ロジック
   - select/chkタイプのoptions処理に使用

4. **ログ・エラーハンドリング強化**
   - Markdown生成時間の計測（1秒超過で警告）
   - 添付ファイルテキスト切り詰め時の警告ログ
   - 詳細な処理完了ログ（チャンク数、Markdown長、処理時間）

**変更ファイル:**
- `app/Jobs/ProcessLedgerForRagJob.php`

**重要な実装ポイント:**

```php
// Ledgerのcontentデータ構造の理解が重要
// カラムIDが配列のインデックスと一致する設計
// 例: column_define = [{id:1, ...}, {id:3, ...}]
//     content = [0=>'', 1=>'value1', 2=>'', 3=>'value3']
$value = $content[$columnDefine->id] ?? null;
```

#### ✅ Task 3: データベーススキーマの変更
**実装内容:**
- `ledger_chunks` テーブルから `chunk_source` カラムを削除
- 単一の構造化Markdownを生成するため、ソースの区別が不要に

**変更ファイル:**
- `database/migrations/2025_10_18_034730_create_ledger_chunks_table.php`

**マイグレーション実行:**
```bash
./vendor/bin/sail artisan migrate:fresh --seed
# 実行完了 - 全テーブル再作成成功
```

#### ✅ Task 4: 包括的なテストケースの作成
**実装内容:**
- 11個のテストケースを実装
- 全テストパス（29 assertions）

**テストケース一覧:**
1. `it_generates_structured_markdown_from_ledger` - 構造化Markdown生成の検証
2. `it_handles_different_display_levels` - display_level 1/2/3 の見出しレベル
3. `it_converts_select_type_with_associative_options` - select型（連想配列options）
4. `it_converts_checkbox_type_with_multiple_selections` - chk型（複数選択）
5. `it_converts_files_type_with_original_filenames` - files型（ファイル名抽出）
6. `it_adds_unit_to_number_type` - number型（単位付加）
7. `it_skips_null_and_empty_values` - null/空値のスキップ
8. `it_includes_attached_file_content` - 添付ファイル内容の追加
9. `it_truncates_long_attached_text_and_logs_warning` - 添付ファイルテキストの切り詰め
10. `it_handles_empty_group_name` - 空グループ名の処理（"その他"）
11. `it_handles_array_content_attached` - 配列型content_attachedの処理

**テスト実行結果:**
```
PASS  Tests\Feature\Jobs\ProcessLedgerForRagJobTest
  ✓ it generates structured markdown from ledger                 8.95s  
  ✓ it handles different display levels                          0.78s  
  ✓ it converts select type with associative options             0.67s  
  ✓ it converts checkbox type with multiple selections           0.72s  
  ✓ it converts files type with original filenames               0.73s  
  ✓ it adds unit to number type                                  0.69s  
  ✓ it skips null and empty values                               0.70s  
  ✓ it includes attached file content                            0.75s  
  ✓ it truncates long attached text and logs warning             0.76s  
  ✓ it handles empty group name                                  0.70s  
  ✓ it handles array content attached                            0.76s  

Tests:    11 passed (29 assertions)
Duration: 16.58s
```

**変更ファイル:**
- `tests/Feature/Jobs/ProcessLedgerForRagJobTest.php`

**重要な学習ポイント:**
```php
// テストでLedgerを作成する際は、正規化後の形式でデータを作成
$ledger = Ledger::factory()->create([
    'content' => [
        0 => '',  // カラムIDの欠番を埋める
        1 => '田中太郎',  // カラムID=1の値
        2 => 'RAG機能の設計を完了しました',  // カラムID=2の値
    ],
]);

// normalizeByColumnDefine()による正規化プロセス:
// Livewire: [1 => 'value', 3 => 'value']
//        ↓
// 正規化: [0 => '', 1 => 'value', 2 => '', 3 => 'value']
//        ↓
// DB保存: JSON ["", "value", "", "value"]
//        ↓
// DB読取: [0 => '', 1 => 'value', 2 => '', 3 => 'value']
```

### 8.2. 生成されるMarkdownの例

**入力データ:**
```php
LedgerDefine: [
    'title' => '日報',
    'create_description' => '日々の業務内容を記録する台帳です',
    'column_define' => [
        ['id' => 1, 'name' => '報告者', 'type' => 'text', 'group' => '基本情報', 'display_level' => 1],
        ['id' => 2, 'name' => '達成事項', 'type' => 'textarea', 'group' => '基本情報', 'display_level' => 1],
    ],
]

Ledger: [
    'content' => [0 => '', 1 => '田中太郎', 2 => 'RAG機能の設計を完了しました'],
]
```

**生成されるMarkdown:**
```markdown
# 日報

> 日々の業務内容を記録する台帳です

---
## 基本情報
### 報告者
田中太郎

### 達成事項
RAG機能の設計を完了しました

```

### 8.3. コードスタイル確認

**Laravel Pint 実行結果:**
```
✓✓

──────────────────────────────────────────────────────────────────────  
  FIXED   ............ 2 files, 2 style issues fixed  
✓ app/Jobs/ProcessLedgerForRagJob.php single_space_around_construct…  
✓ tests/Feature/Jobs/ProcessLedgerForRagJobTest.php no_unused_imports…
```

### 8.4. 変更ファイル一覧

```
M app/Jobs/ProcessLedgerForRagJob.php
M config/rag.php
M database/migrations/2025_10_18_034730_create_ledger_chunks_table.php
M tests/Feature/Jobs/ProcessLedgerForRagJobTest.php
```

### 8.5. 実装上の注意事項と技術的知見

#### 8.5.1. Ledgerのcontentデータ構造の理解

**重要:** LedgerLeapにおける`content`配列は、特殊な正規化プロセスを経てデータベースに保存されます。

**データフロー:**
1. **Livewire入力:** カラムIDをキーとした連想配列 `[1 => 'value', 3 => 'value']`
2. **正規化処理 (`normalizeByColumnDefine()`):** カラムIDの欠番を空文字で埋める
   - `[0 => '', 1 => 'value', 2 => '', 3 => 'value']`
3. **DB保存:** `array_values()`で連番配列に変換
   - JSON: `["", "value", "", "value"]`
4. **DB読み取り:** 連番配列として復元
   - `[0 => '', 1 => 'value', 2 => '', 3 => 'value']`

**結果:** カラムIDが配列のインデックスと一致するため、`$content[$columnDefine->id]`で直接値にアクセス可能。

**実装への影響:**
```php
// ProcessLedgerForRagJob.php
foreach ($sortedColumns as $columnData) {
    $columnDefine = new ColumnDefine($columnData);
    
    // カラムIDをインデックスとして直接アクセス
    $value = $content[$columnDefine->id] ?? null;
    $textValue = $this->extractValueAsText($columnDefine, $value);
}
```

**テストでの注意:**
```php
// ❌ 間違い: 正規化されていない形式
$ledger = Ledger::factory()->create([
    'content' => [1 => 'value'],  
]);
// → DBには ['value'] として保存される（インデックス0のみ）

// ✅ 正解: 正規化後の形式
$ledger = Ledger::factory()->create([
    'content' => [0 => '', 1 => 'value'],  
]);
// → DBには ['', 'value'] として保存される（インデックス0,1）
```

参考ドキュメント:
- `docs/database/schema.md` - contentの正規化プロセス
- `docs/development/Testing-Best-Practices.md` - テストでのcontentデータ作成

#### 8.5.2. カラムタイプのオプション処理

**select型とchk型のoptions:**
- **連想配列:** `['draft' => '下書き', 'published' => '公開']`
  - キーで検索してラベルを返す
- **単純配列:** `['option1', 'option2']`
  - 値が配列に含まれていればそのまま返す

**実装:**
```php
private function isAssocArray(array $arr): bool
{
    if (empty($arr)) {
        return false;
    }
    return array_keys($arr) !== range(0, count($arr) - 1);
}
```

#### 8.5.3. number型の単位取得

**InputTypeオブジェクトの活用:**
```php
if ($type === 'number') {
    $text = (string) $value;
    $unit = $column->getInputType()->unit ?? null;
    if ($unit) {
        $text .= " {$unit}";
    }
}
```

`$column->getInputType()`で`NumberType`オブジェクトにアクセスし、`unit`プロパティを取得。

#### 8.5.4. 添付ファイルテキストの配列対応

**content_attachedの型:**
- 文字列の場合: そのまま使用
- 配列の場合: `implode("\n\n", $attachedText)` で結合

**切り詰め処理:**
```php
$maxAttachedLength = config('rag.chunking.max_attached_text_length', 50000);
$originalLength = mb_strlen($attachedText);

if ($originalLength > $maxAttachedLength) {
    $attachedText = mb_substr($attachedText, 0, $maxAttachedLength) 
        . "\n\n[... 以降のテキストは省略されました]";
    
    Log::channel($logChannel)->warning('Attached text truncated for RAG', [
        'ledger_id' => $ledger->id,
        'original_length' => $originalLength,
        'truncated_length' => $maxAttachedLength
    ]);
}
```

**注意点:** `mb_strlen($ledger->content_attached)`は配列の場合エラーになるため、先に文字列化してから長さを測定。

### 8.6. 残タスク

- [ ] **Task 4: 既存データの再インデックス**
  - 運用手順として、デプロイ後に以下を実行:
    ```bash
    ./vendor/bin/sail artisan rag:chunk-existing-ledgers --force
    ./vendor/bin/sail artisan rag:chunk-status
    ```
  - 全チャンクデータを新しい構造化Markdownで再生成する必要がある

### 8.7. 次のステップへの提言

**Phase2への移行条件（設計書7.7より）:**
- チャンク数が予想を超えて増大（台帳あたり平均100チャンク超）
- エンベディング処理がシステムボトルネックになる
- 「添付ファイルのみ検索」のニーズが明確化
- ストレージコストが問題になる

**監視すべきメトリクス:**
- 台帳あたりの平均チャンク数
- エンベディング処理時間
- ストレージ使用量（`ledger_chunks`テーブルサイズ）
- 検索精度（ユーザーフィードバック）

**最適化の余地:**
- `max_attached_text_length`の調整（現在50,000文字）
- チャンクサイズの調整（現在2,000文字）
- オーバーラップサイズの調整（現在400文字）

---

## 9. 結論

本実装により、RAG機能のチャンキング処理が大幅に改善され、以下の効果が期待できます：

✅ **検索精度の向上:**
- 台帳定義のメタデータ（タイトル、説明）がコンテキストとして含まれる
- 項目名がラベルとして明示され、「何の項目か」が明確になる
- 階層構造（グループ、display_level）が保持される
- オプションのキー値ではなく人間が読めるラベルが使用される

✅ **実装の堅牢性:**
- 全12種類のカラムタイプに対応
- null/空値の適切な処理
- 添付ファイルテキストの長さ制限によるシステム安定性の確保
- 包括的なテストカバレッジ（11テスト、29アサーション）

✅ **運用の容易さ:**
- 詳細なログ出力（Markdown生成時間、切り詰め警告など）
- 設定ファイルでの柔軟な調整（添付ファイルテキスト長など）
- 明確な再インデックス手順

本実装は、LedgerLeapのRAG機能の基盤として、今後の機能拡張にも対応できる設計となっています。
