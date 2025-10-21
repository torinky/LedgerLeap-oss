# WBS 1.4.1 設計・実装指示書: 構造化チャンキングによるベクトル化精度向上

**作成日:** 2025年10月21日  
**更新日:** 2025年10月21日（検討結果反映）  
**親タスク:** WBS 1.4 `ProcessLedgerForRagJob` の実装
**タスク:** `LedgerDefine`のメタデータと`Ledger`の項目データを組み合わせ、Markdown形式で構造化されたテキストを生成するようチャンキング処理を改善し、ベクトル化の精度を向上させる。  
**ステータス:** ✅ **設計完了・妥当性確認済み**

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

- [ ] **Task 1: `config/rag.php` の設定追加**
    - [ ] `chunking.max_attached_text_length` 設定項目を追加する（デフォルト: 50000文字）
- [ ] **Task 2: `ProcessLedgerForRagJob` の改修**
    - [ ] `extractValueAsText` ヘルパーメソッドを実装する。
    - [ ] `buildMarkdownFromLedger` メソッドを実装する (`group`, `display_level` を考慮)。
    - [ ] 添付ファイルテキストの長さ制限処理を実装する。
    - [ ] 制限超過時の警告ログ出力を実装する。
    - [ ] `handle` メソッドの処理フローを新しいロジックに更新する。
- [ ] **Task 3: `ledger_chunks` マイグレーションの修正**
    - [ ] `2025-10-17-phase1-hybrid-search-plan.md` に記載のスキーマ定義を上記2.2の通り修正する（`chunk_source`カラム削除）。
- [ ] **Task 4: 既存データの再インデックス** (運用手順として後述)

---

## 4. テスト計画

- [ ] **`ProcessLedgerForRagJobTest` (修正・拡充)**
    - [ ] **値変換テスト (`extractValueAsText`):**
        - [ ] 基本型（text, textarea, number, url, auto_number）で期待通りのテキストが返ることを検証
        - [ ] number型で単位が正しく付加されることを検証
        - [ ] select型で連想配列options、単純配列optionsの両方で正しいラベルが返ることを検証
        - [ ] chk型で複数選択されたオプションがカンマ区切りで返ることを検証
        - [ ] chk型で何も選択されていない場合にnullが返ることを検証
        - [ ] files型で複数ファイル名がカンマ区切りで返ることを検証
        - [ ] files型で空配列の場合にnullが返ることを検証
        - [ ] 空文字列、null、空配列がすべてnullとして処理されることを検証
    - [ ] **Markdown生成テスト (`buildMarkdownFromLedger`):**
        - [ ] LedgerDefineのtitleとdescriptionが正しくH1見出しと引用として出力されることを検証
        - [ ] groupプロパティに基づいてH2見出しが正しく生成されることを検証
        - [ ] グループ名が空文字列またはnullの場合に「その他」グループとして出力されることを検証
        - [ ] グループがorderプロパティでソートされることを検証
        - [ ] display_levelに応じてH3/H4/H5見出しが正しく使い分けられることを検証
        - [ ] 値がnullの項目がスキップされることを検証
        - [ ] content_attachedが文字列の場合に正しく出力されることを検証
        - [ ] content_attachedが配列の場合に改行で結合されて出力されることを検証
        - [ ] content_attachedが空の場合に「添付ファイル内容」セクションが出力されないことを検証
        - [ ] content_attachedが最大長を超える場合に切り詰められ、警告ログが出力されることを検証
        - [ ] 切り詰め時に「[... 以降のテキストは省略されました]」が末尾に追加されることを検証
    - [ ] **複雑なカラムタイプの組み合わせテスト:**
        - [ ] select、chk、filesが混在する台帳で正しいMarkdownが生成されることを検証
        - [ ] 複数グループ、複数display_levelが混在する台帳での階層構造の正確性を検証
    - [ ] **チャンク化テスト:**
        - [ ] 生成されたMarkdownが正しくチャンクに分割されることを検証
        - [ ] チャンクがDBに保存される際に`chunk_source`カラムが存在しないことを確認（スキーマ変更後）
    - [ ] **エッジケースのテスト:**
        - [ ] `content`が空の台帳でエラーなく完了することを確認
        - [ ] `content_attached`が空の台帳でエラーなく完了することを確認
        - [ ] `content`と`content_attached`が両方空の台帳でエラーなく完了することを確認
        - [ ] 全項目の値がnullの台帳で空のMarkdownが生成されないことを確認（または適切に処理されること）

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
