# WBS 2.2.1 設計・実装指示書: RAG `ruri`モデル プリフィックス対応

**作成日:** 2025年10月21日  
**親タスク:** WBS 2.2 ベクトル検索とスコア集計ロジック実装
**タスク:** `ruri`埋め込みモデル利用時の検索精度向上のため、クエリおよび文書へのプリフィックス付与機能を実装する。  
**ステータス:** ✅ 完了

---

## 1. 背景と目的

埋め込みモデル `ruri` (例: `cl-nagoya/ruri-v3-310m`) は、テキストの種類（検索クエリか、検索対象の文書か）を区別するためのプリフィックスを付与することで、その性能を最大限に発揮できるよう設計されている。

WBS 2.2で実装された基本検索ロジックを改善し、`ruri`モデルがアクティブな場合に、ベクトル化を行う直前のテキストに適切なプリフィックスを付与する仕組みを導入し、セマンティック検索の精度を向上させることが本タスクの目的である。

参考資料: [テキスト埋め込みモデル Ruri を使ってみる - Qiita](https://qiita.com/7shi/items/90c745833c7839e38c03)

---

## 2. 設計方針

将来的なモデルの追加・変更に柔軟に対応できるよう、設定ファイル駆動の設計を採用する。プリフィックスの付与処理は、ベクトル化の責務を持つ `EmbeddingService` に集約する。

### 2.1. `config/rag.php` の拡張

`model.available_models` 配下の各モデル設定に、`prefix` キーを追加する。これにより、モデルごとに異なるプリフィックスを容易に管理できる。

**実装例:**
```php
// config/rag.php

'available_models' => [
    'ruri-v3-310m' => [
        'name' => 'cl-nagoya/ruri-v3-310m',
        'dimension' => 768,
        'description' => 'Fast and lightweight Japanese model with excellent performance (recommended for ARM64 dev).',
        'prefix' => [
            'query' => '検索クエリ: ',
            'passage' => '検索文書: ',
        ],
    ],
    // ... 他のモデル
],
```

### 2.2. `EmbeddingService` の責務変更

`EmbeddingService`がプリフィックスの付与処理を担当する。

**`embed` メソッドのシグネチャ変更:**
- **変更前:** `embed(string|array $texts): array`
- **変更後:** `embed(string|array $texts, string $type = 'query'): array`

`$type` パラメータ（`'query'` または `'passage'`）を受け取り、`config/rag.php` から現在アクティブなモデルに対応するプリフィックスを取得して付与する。

### 2.3. 既存コードからの呼び出し変更

1.  **検索クエリのベクトル化:**
    - **対象ファイル:** `app/Services/RagSearchService.php`
    - **変更内容:** `$this->embeddingService->embed($query, 'query');`

2.  **文書チャンクのベクトル化:**
    - **対象ファイル:** `app/Jobs/ProcessLedgerForRagJob.php`
    - **変更内容:** `$embeddingService->embed($chunkTexts, 'passage');`

---

## 3. 実装タスク一覧

- [x] **Task 1: `config/rag.php` の更新**
- [x] **Task 2: `EmbeddingService` の改修**
- [x] **Task 3: `RagSearchService` の更新**
- [x] **Task 4: `ProcessLedgerForRagJob` の更新**
- [ ] **Task 5: 既存データの再インデックス** (運用手順として後述)

---

## 4. テスト計画

- [x] **`EmbeddingServiceTest` (新規作成):** プリフィックス付与ロジックをユニットテストで検証。
- [x] **`RagSearchServiceTest` (修正):** `embed` メソッドが正しい引数 (`'query'`) で呼び出されることを検証。
- [x] **`ProcessLedgerForRagJobTest` (新規作成):** `embed` メソッドが正しい引数 (`'passage'`) で呼び出されることを検証。

---

## 5. 結論

本設計に基づき実装を進めることで、`ruri`モデルの性能を最大限に引き出し、セマンティック検索の品質向上が期待できる。また、設定ファイル駆動のアプローチにより、将来のメンテナンス性も確保される。

---

## 6. 実装結果サマリー

**ステータス:** ✅ 実装完了・テスト完了

設計方針に基づき、以下のファイルに対して変更およびテストの追加を実施した。

### 6.1. 変更ファイル一覧

- **`config/rag.php`**
  - `available_models` 配下の各モデル設定に `prefix` キーを追加。`ruri`モデルには日本語プリフィックスを、その他モデルには空のプリフィックスを設定。

- **`app/Services/EmbeddingService.php`**
  - `embed` メソッドを改修し、第2引数 `$type` を受け取れるように変更。設定ファイルに応じて動的にプリフィックスを付与するロジックを実装。

- **`app/Services/RagSearchService.php`**
  - `embed` メソッドの呼び出し箇所で、テキスト種別として `'query'` を明示的に指定するよう修正。

- **`app/Jobs/ProcessLedgerForRagJob.php`**
  - `embed` メソッドの呼び出し箇所で、テキスト種別として `'passage'` を明示的に指定するよう修正。

### 6.2. テスト結果

追加・修正したすべてのテストが正常にパスすることを確認した。

- **`tests/Unit/Services/EmbeddingServiceTest.php` (新規)**
  - `EmbeddingService` のプリフィックス付与ロジックが、モデル設定に応じて正しく動作することを検証。

- **`tests/Feature/Jobs/ProcessLedgerForRagJobTest.php` (新規)**
  - `ProcessLedgerForRagJob` が `EmbeddingService::embed` を `'passage'` タイプで呼び出すことを検証。

- **`tests/Feature/RagSearchServiceTest.php` (修正)**
  - `RagSearchService` が `EmbeddingService::embed` を `'query'` タイプで呼び出すことを検証。
  - 既存のテストが、`'passage'` タイプの呼び出しも考慮したモック設定で正しく動作するように修正。

---

## 7. 運用手順: チャンクデータの再インデックス

**重要:** 本改修をデプロイした後、既存のチャンクデータは古いプリフィックスなしのベクトル情報のままです。検索精度を正しく向上させるため、**必ず以下の手順で全チャンクデータの再インデックス（再作成）を実施してください。**

### 7.1. なぜ再インデックスが必要か？

検索クエリには `検索クエリ: ` が付与される一方、データベース内の文書ベクトルが `検索文書: ` なしで生成されていると、両者のベクトル空間が異なってしまい、類似度計算が正しく機能しません。そのため、データベース内の全文書チャンクを、新しいロジックで再生成する必要があります。

### 7.2. 実行コマンド

`rag:chunk-existing-ledgers` コマンドに `--force` オプションを付けて実行します。

```bash
# Sail環境の場合
./vendor/bin/sail artisan rag:chunk-existing-ledgers --force
```

### 7.3. コマンドの動作

- `--force` オプションにより、すでにチャンク化済みの台帳も含め、**すべての台帳**が処理対象となります。
- 各台帳について、`ProcessLedgerForRagJob` がディスパッチされます。
- ジョブは、まずその台帳に紐づく既存のチャンクをすべて削除し、その後、新しいプリフィックス (`検索文書: `) 付きのテキストでベクトルを再生成し、新しいチャンクとしてデータベースに保存します。

### 7.4. 注意事項

- **キューワーカーの起動:** コマンド実行前に、キューワーカーが稼働していることを確認してください (`./vendor/bin/sail artisan queue:work`)。
- **実行時間:** 対象となる台帳の総数によっては、処理に時間がかかる場合があります。本番環境で実行する際は、システムへの負荷が少ない時間帯に実施することを推奨します。
- **進捗確認:** 以下のコマンドで、チャンク化の進捗状況を確認できます。
  ```bash
  ./vendor/bin/sail artisan rag:chunk-status
  ```