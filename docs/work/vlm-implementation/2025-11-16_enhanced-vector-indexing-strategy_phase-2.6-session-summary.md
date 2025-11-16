# Phase 2.6 実装セッションまとめ
**日時:** 2025年11月16日  
**対象:** Vector Indexing Strategy - Phase 2.6 即時ベクトル化実装  
**ステータス:** 実装完了・効果測定準備中

---

## セッション概要

### 目的
Phase 2.6の実装として、OCR/VLM/Tika処理完了時に即座にベクトルインデックスを作成し、検索可能性を最大化する仕組みを構築。

### 主要な設計判断

#### 1. **ステータスベースのファイナライズ管理**
当初は専用カラムやクロール処理を検討したが、より洗練された方法に改善：

**採用した設計:**
```
AttachedFileStatus に追加:
- FINALIZED_BY_TIKA  // Tikaのみで完結（Office文書など）
- FINALIZED_BY_OCR   // OCR完了で確定（テキスト付きPDF、画像→PDF）
- FINALIZED_BY_VLM   // VLM統合完了で確定（最高品質）
```

**メリット:**
- 状態遷移が明確
- 既存のステータス管理に統合
- 余計なカラム追加不要
- イベント駆動で処理を分岐可能

#### 2. **イベント駆動アーキテクチャの活用**
クロール処理ではなく、各処理完了時にインテリジェントに判断：

**処理完了時の判断ロジック:**
```
Tikaジョブ完了時:
→ OCR/VLM不要なファイル → FINALIZED_BY_TIKA → 即座にベクトル化

OCRジョブ完了時:
→ VLM不要 or VLM済み → FINALIZED_BY_OCR → 即座にベクトル化
→ VLM待ち → ベクトル化スキップ（VLM完了を待つ）

VLMジョブ完了時:
→ OCR完了済み → 統合 → FINALIZED_BY_VLM → ベクトル化
→ OCR未完了 → ベクトル化スキップ（OCR完了を待つ）
```

#### 3. **ベクトルインデックスの上書き戦略**
「検索できないこと」の方が問題なので、暫定的なインデックスを先に作成：

- **Tikaのみ → OCR → VLM統合** の順で品質向上
- 後続処理で常に上書き可能
- 検索可能性を最優先

---

## 実装内容

### 新規作成ファイル

#### 1. **VectorizeAttachedFileJob**
`app/Jobs/VectorizeAttachedFileJob.php`

**責務:**
- 添付ファイルのベクトルインデックス作成
- キーワード強化テキスト生成（Phase 2.5連携）
- Ruriサーバーへのベクトル化リクエスト
- エラーハンドリングとリトライ

**特徴:**
- `ProcessAttachedFileTrait` を使用して共通ロジック活用
- `shouldGenerateVector()` で生成条件を判定
- ファイナライズステータスに基づく処理分岐

#### 2. **Phase26DemoSeeder**
`database/seeders/Phase26DemoSeeder.php`

**機能:**
- テストデータの一括作成
- 実ファイルを使ったリアルなデモ環境
- ジョブのディスパッチによる実際の処理フロー再現

**データ構成:**
- 3フォルダ × 5台帳 × 3ファイル = 45ファイル
- 多様なファイルタイプ（PDF、画像、Office文書）
- VLM/OCR/Tika処理の組み合わせ

#### 3. **測定ガイド**
`docs/work/vlm-implementation/2025-11-16_phase-2.6-measurement-guide.md`

効果測定の手順とメトリクスを定義。

---

## 既存ファイルの修正

### 1. **AttachedFileStatus Enum**
`app/Enums/AttachedFileStatus.php`

```php
// 新規追加
case FINALIZED_BY_TIKA = 'finalized_by_tika';
case FINALIZED_BY_OCR = 'finalized_by_ocr';
case FINALIZED_BY_VLM = 'finalized_by_vlm';
```

### 2. **ProcessVlmJob**
- VLM完了時に `VectorizeAttachedFileJob` をディスパッチ
- ファイナライズ判定ロジック追加

### 3. **ProcessOcrJob**
- OCR完了時にベクトル化判定
- VLM待ちの場合はスキップ

### 4. **ProcessTikaJob**
- Tika完了時にベクトル化判定
- Office文書など単独で完結するファイルに対応

### 5. **既存テストの修正**
- `VlmIntegrationTest` のステータス検証を更新
- 新しいファイナライズステータスに対応

---

## Phase 2.5 の強化

### ストップワード機能の追加
**背景:** 自社名など頻出する固有名詞がノイズになる問題

**実装:**
```php
KeywordEnhancedTextGenerator::setStopwords(['株式会社ABC', '弊社']);
```

### 品詞別ラベル付け
**目的:** 固有名詞と一般名詞を区別して特徴抽出の精度向上

**形式:**
```
[PROPER]山田太郎 [COMMON]契約書 [PROPER]東京都
```

**メリット:**
- ベクトル空間での分離性向上
- 検索時のマッチング精度向上

---

## 技術的な課題と解決

### 1. **複数の処理完了タイミング**
**課題:** OCR/VLM/Tikaが非同期で完了するため、統合タイミングが不定

**解決策:**
- ステータスベースの状態管理
- 各ジョブ完了時に「今ベクトル化すべきか」を判断
- 後続処理による上書き許可

### 2. **品質とスピードのトレードオフ**
**課題:** 最高品質（VLM統合）を待つと検索可能になるまで時間がかかる

**解決策:**
- 暫定インデックスの即時作成
- 後続処理での上書き更新
- 「検索できない」状態を最小化

### 3. **ファイルタイプごとの最適経路**
**課題:** Office文書のTikaテキストは高品質だが、画像のOCR/VLMは統合が必要

**解決策:**
```
Office文書: Tika完了 → FINALIZED_BY_TIKA → ベクトル化
画像PDF: OCR完了 → FINALIZED_BY_OCR → ベクトル化 → VLM完了 → 上書き
画像ファイル: OCR+VLM統合 → FINALIZED_BY_VLM → ベクトル化
```

---

## 次のステップ

### 1. **効果測定（保留中）**
**測定項目:**
- ベクトルインデックス作成までの時間
- 検索精度の改善度
- キーワード強化の効果

**課題:**
- `tests/fixtures/files/` のファイルを使った限定的評価を検討中
- デモシーダーの実行に問題あり（ファイルパス、ジョブディスパッチ）

### 2. **Phase 2.7 以降の検討**
- ベクトルインデックスの品質評価
- キーワード辞書の拡充
- ストップワードの自動学習

---

## 未解決の問題

### 1. **デモデータの検証不足**
- `Phase26DemoSeeder` が正しく動作するか未確認
- 実際のファイル処理フローでのベクトル化が機能するか未検証

### 2. **測定方法の具体化**
- fixture ファイルを使った評価方法が不明確
- 「誤った判断をしている」との指摘あり（詳細不明）

### 3. **統合テストの不足**
- 各ジョブ単体のテストは存在
- エンドツーエンドの統合フローのテストが必要

---

## コミット履歴

```
61b26c8 feat(ocr-integration): Implement source-based finalization status and immediate vectorization
1491362 feat(keyword-enhancement): Add stopwords functionality and separate proper/common nouns
6fb4e77 feat(ocr-integration): Implement strategy for integrating multiple OCR results
f0dbe76 feat(embedding): Add KeywordEnhancedTextGenerator for improved keyword extraction
b18ed7a feat(vector-indexing): Introduce enhanced vector indexing strategy
```

---

## 設計ドキュメント

1. `docs/work/vlm-implementation/phase2.6-vectorization-strategy.md` - 全体戦略
2. `docs/work/vlm-implementation/2025-11-16_phase2.5-keyword-enhancement.md` - キーワード強化
3. `docs/work/vlm-implementation/2025-11-16_phase-2.6-measurement-guide.md` - 測定ガイド

---

## 備考

### 設計上の重要な教訓

1. **シンプルな状態管理:** 専用カラムではなく既存のステータスEnumを拡張
2. **イベント駆動:** クロール処理ではなく完了時の判断
3. **検索可能性優先:** 最高品質を待つのではなく暫定インデックスを先行作成

### 残課題

効果測定の具体的な方法と、デモデータでの実証が必要。現状では理論的な実装が完了したが、実際の動作確認と性能評価が未完了。
