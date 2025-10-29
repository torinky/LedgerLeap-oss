# 2025年 VLM-OCR技術とインデックス戦略の再評価 (改訂版)

**作成日:** 2025年10月23日  
**最終更新:** 2025年10月25日  
**ドキュメント種別:** 公式ドキュメント（技術選定）  
**ステータス:** ✅ **実装計画確定**

> **🔄 改訂履歴:**
> - **2025-10-25**: VLM/RAG統合実装計画を確定
>   - `attached_files`テーブル活用方式を最終決定
>   - セクション3を改訂（統合戦略サマリー）
>   - 詳細実装計画書へのリンク追加
> - **2025-10-24**: 最新VLM/OCR調査結果を反映
> - **2025-10-23**: 初版作成

> **📖 関連ドキュメント:**
> - [RAG機能導入に関する技術検討](../work/rag-implementation/2025-10-16-rag-implementation-study.md) - RAG導入の全体戦略
> - [AIアシスタントと検索の哲学](../ai-and-search-guide.md) - LedgerLeapの検索思想
> - [添付ファイル機能](../function/Attachment.md) - 既存のOCR/Tikaアーキテクチャ
> - **[VLM/RAG統合実装計画書（最終版）](./vlm-rag-integration.md)** - ★詳細実装計画

---

## 1. はじめに

### 1.1. 本ドキュメントの目的

本ドキュメントは、2025年10月時点の最新のVLM（Visual Language Model）およびドキュメントAI技術動向を調査し、LedgerLeapの既存OCR機能（OcrMyPDF）の刷新、およびRAG機能と連携した次世代インデックス戦略を構想することを目的とする。

特に、プロジェクトの重要要件である「**オンプレミス・CPU環境での実行可能性**」を最優先事項とし、現実的な技術選定と段階的な導入アプローチを提案する。

### 1.2. 背景：現状の課題と機会

LedgerLeapは現在、OcrMyPDF（Tesseractベース）によるテキスト抽出と、Mroongaによるキーワードベースの全文検索を実装している。しかし、近年のVLM技術の急速な進化により、単なる「画像からの文字起こし」に留まらない、新たな可能性が生まれている。

| 項目 | 現状のOCR (OcrMyPDF) | 最新VLM-OCRがもたらす機会 |
| :--- | :--- | :--- |
| **抽出内容** | プレーンテキストのみ | **構造化データ (JSON)**, **Markdown**, **画像キャプション** |
| **精度** | 複雑なレイアウトや手書き文字に弱い | レイアウトを理解し、高精度な読取りが可能 |
| **検索** | キーワード検索のみ | **ハイブリッド検索**（キーワード＋セマンティック） |
| **CPU推論** | 実用的 | **量子化・最適化技術**により、CPUでの実行が現実的に |

本稿では、これらの機会をLedgerLeapにどう取り込み、「インデックスを強化」していくかの構想を具体化する。

---

## 2. オンプレミスCPU実行可能な日本語帳票対応VLM・OCRツール調査結果

オンプレミス環境でCPU実行可能、日本語ビジネス文書（帳票）に強く、マークダウンや構造化データを出力できるOSSモデル・ツールを、以下のカテゴリで整理しました。

### 1. 文書・帳票特化型VLM

#### **Donut（Document Understanding Transformer）**
- **特徴**: OCRフリーのエンドツーエンドTransformerモデル。請求書・フォームなど定型帳票からの情報抽出に特化。[1][2]
- **日本語対応**: 韓国語レシート解析で高性能を発揮。日本語データでのファインチューニングが必要。[1]
- **CPU実行**: 可能だが、推論速度は遅い。
- **構造化出力**: JSON形式で構造化データを出力可能。

#### **LayoutLMv3**
- **特徴**: レイアウト情報とテキストを統合的に理解するモデル。文書理解ベンチマークでSOTA性能。[3][4]
- **日本語対応**: 多言語対応だが、日本語特化ではない。ファインチューニング必要。
- **CPU実行**: 可能。
- **構造化出力**: 文書構造を理解し、構造化データとして抽出可能。
- **応用**: MarkerツールがLayoutLMv3を内部で使用してPDF→Markdown変換を実現。[4]

#### **Florence-2**
- **特徴**: Microsoftの多機能VLM。OCR、キャプション、物体検出など多様なタスクに対応。[5]
- **日本語対応**: 日本語データでファインチューニングすることでOCR・キャプション生成が可能。[6]
- **CPU実行**: 可能（Apple Silicon MPSにも対応）。[6]
- **構造化出力**: タスク指定により多様な形式で出力可能。

### 2. 日本語特化型VLM

#### **Swallow-VLM（Llama 3 Swallow VLM）**
- **特徴**: 日本語に特化したLlama 3ベースのVLM。日本語理解と視覚情報の統合に優れる。[7][8]
- **日本語対応**: 日本語特化の事前学習済み。
- **CPU実行**: 7B/8Bモデルは量子化（GGUF）によりCPU実行可能。[7]
- **構造化出力**: プロンプト次第でMarkdownやJSON出力が可能。
- **応用**: 日本語VLMベンチマークで高性能を実証。

#### **Heron BLIP（日本語版）**
- **特徴**: Turing Motors開発の日本語VLM。BLIP + Japanese StableLMの組み合わせ。[9][10][11]
- **日本語対応**: 日本語VQAデータセットとLLaVA-620k日本語翻訳で学習。[10]
- **CPU実行**: 7Bモデルは量子化でCPU実行可能。
- **構造化出力**: プロンプトによりMarkdown等の出力可能。
- **実績**: ARM Macでの実行実績あり。[12]

#### **Heron-NVILA（最新版）**
- **特徴**: Qwen2.5-VLベースの日本語特化VLM。画像の高解像度処理とトークン圧縮が特徴。[13]
- **日本語対応**: 日本語最適化済み（15B/2B/1Bモデル）。
- **CPU実行**: 1B/2Bモデルは軽量でCPU実行可能性あり。
- **構造化出力**: Qwen2.5ベースのため、指示に応じた構造化出力に対応。

#### **Japanese Stable VLM**
- **特徴**: Stability AI Japan開発の日本語画像言語モデル。商用利用可能。[14][15][16]
- **日本語対応**: 日本語キャプション生成、VQAに特化。
- **CPU実行**: 7Bモデルで可能。
- **構造化出力**: タグ条件付きキャプション機能あり。

#### **rinna/nekomata-vision**
- **特徴**: rinna社のQwenベースVLM。日本語視覚言語理解に対応。[7]
- **日本語対応**: 日本語特化。
- **CPU実行**: 量子化（GGUF）でCPU実行可能。
- **構造化出力**: 指示に応じて対応。

### 3. 汎用VLM（日本語対応可能）

#### **LLaVA-NeXT**
- **特徴**: 高性能な汎用VLM。多言語対応。
- **日本語対応**: 英語ベースだが、日本語プロンプトで一定の対応可能。[10]
- **CPU実行**: 7B/8Bモデルは量子化でCPU実行可能。
- **構造化出力**: プロンプトにより対応。

#### **Qwen2-VL / Qwen2.5-VL**
- **特徴**: Alibaba開発の高性能VLM。画像・動画・OCRに強い。多言語対応（日本語含む）。[17][18][5]
- **日本語対応**: 日本語テキスト認識に対応。日本語VQAも可能。[19][20]
- **CPU実行**: 7Bモデルは量子化（GPTQ-Int4/AWQ）でCPU実行可能。[17]
- **構造化出力**: 高度な指示に対応し、Markdown等の出力可能。
- **実績**: 日本語PDF検索・OCRで実績あり。[20][21][22]

#### **PaliGemma / PaliGemma2**
- **特徴**: Google開発の3BパラメータVLM。軽量で多様なタスクに対応。[5]
- **日本語対応**: 多言語対応（日本語含む）。
- **CPU実行**: 3Bモデルのため、CPU実行が現実的。
- **構造化出力**: タスク指定により対応。

### 4. 新興・高性能モデル

#### **DeepSeek-VL / DeepSeek-OCR**
- **特徴**: 中国DeepSeek-AI開発の高性能VLM。DeepSeek-OCRは文書解析に特化した3BモデルでSOTA性能。[23][24][25]
- **日本語対応**: 多言語対応。日本語文書の認識も可能。[23]
- **CPU実行**: 3Bモデルは軽量でCPU実行可能。
- **構造化出力**: LaTeX、Markdown形式での出力に対応。[24]
- **特徴**: コンテキスト光学圧縮技術により、少ないトークンで高精度認識を実現。[24][23]

#### **PaddleOCR / PaddleOCR-VL-0.9B**
- **特徴**: Baidu開発の軽量OCRツール。PaddleOCR-VL-0.9Bは2025年10月リリースの超軽量VLM。[26][27][28][29]
- **日本語対応**: 日本語を含む109言語に対応。[27][28][30]
- **CPU実行**: **CPU実行に最適化**。0.9Bパラメータで通常のCPUで実行可能。[26][27]
- **構造化出力**: テキスト、表（HTML）、数式（LaTeX）を抽出可能。[27][26]
- **性能**: OmniBenchDoc V1.5で世界1位。72Bモデルを上回る。[26]
- **実績**: 日本語ビジネス文書での実用実績あり。[31][32]

### 5. PDF→Markdown変換特化ツール

#### **MinerU**
- **特徴**: PDF→Markdown/JSON変換に特化。表・数式・画像を高精度抽出。[33][34][35]
- **日本語対応**: 日本語含む84言語のOCRに対応。[34]
- **CPU実行**: CPU環境に対応。[34]
- **構造化出力**: Markdown、JSON、HTMLで出力。レイアウト情報を保持。[34]
- **特徴**: ヘッダー・フッター除去、見出し・段落構造の保持が優秀。[34]

#### **Marker**
- **特徴**: PDF/EPUB/MOBIをMarkdownに変換。速度と精度で既存ツールを凌駕。[36][37][4]
- **日本語対応**: 非英語圏言語では最適化されていないが、使用可能。[4]
- **CPU実行**: CPU/GPU/MPSで動作。[4]
- **構造化出力**: Markdown + JSON形式。[37]
- **技術**: LayoutLMv3、Nougat、T5を組み合わせた6段階処理パイプライン。[4]

#### **olmOCR**
- **特徴**: VLMベースのPDF→Markdown変換ツール。GPUも活用可能。[38][39]
- **日本語対応**: 日本語ビジネス文書で高精度。[39][38]
- **CPU実行**: 可能だが、GPU推奨。
- **構造化出力**: Markdown形式（JSONLファイル）。[38]

#### **Docling**
- **特徴**: IBM開発のAI文書変換ツール。複雑な文書構造の解析に強い。[40][41][42][43]
- **日本語対応**: 日本語PDFでの検証実績あり。[40]
- **CPU実行**: 可能。
- **構造化出力**: Markdown、JSON形式。
- **OCRエンジン**: EasyOCR、Tesseract、RapidOCRから選択可能。[40]

### 6. 数式・学術文書特化

#### **GOT-OCR2.0**
- **特徴**: 数式・表・グラフ・楽譜まで認識可能な高機能OCR。[44][45][46]
- **日本語対応**: **現在日本語非対応**（中国語・英語のみ）。将来的にファインチューニングで対応可能。[44]
- **CPU実行**: 580Mパラメータで軽量。CPU実行可能だが、GPU推奨。
- **構造化出力**: LaTeX、Markdown、TikZ、SMILES形式で出力。[45][44]

#### **Nougat**
- **特徴**: Meta AI開発の学術文書特化OCR。数式をLaTeX形式で出力。[47][48][49]
- **日本語対応**: 英語論文に特化。日本語は非対応。
- **CPU実行**: 可能だが非常に遅く、GPU推奨。[47]
- **構造化出力**: Mathpix Markdown互換形式（.mmd）。[47]

### 7. 従来型OCRエンジン

#### **Tesseract OCR**
- **特徴**: 歴史あるOSS OCR。日本語学習データあり。[50][40]
- **日本語対応**: 日本語対応だが、精度は新型VLMに劣る。[51][50]
- **CPU実行**: CPU専用。
- **構造化出力**: テキスト出力のみ。構造化には後処理が必要。

#### **EasyOCR**
- **特徴**: 80言語以上対応の軽量OCR。Pythonで簡単導入。[52]
- **日本語対応**: 日本語対応。[52]
- **CPU実行**: 可能。
- **構造化出力**: テキスト出力。構造化には後処理が必要。

### 総合推奨

| 優先順位 | モデル/ツール | 理由 |
|:---:|:---|:---|
| **1位** | **PaddleOCR-VL-0.9B** | CPU実行最適化、日本語対応109言語、表・数式抽出可能、SOTA性能[26][27] |
| **2位** | **MinerU** | PDF→Markdown特化、日本語84言語対応、CPU対応、構造保持に優秀[35][34] |
| **3位** | **Qwen2-VL-7B（量子化）** | 日本語OCR実績豊富、量子化でCPU実行可能、高性能VLM[20][17][18] |
| **4位** | **Heron BLIP / Swallow-VLM** | 日本語特化VLM、CPU実行可能、国内開発で実績あり[9][10][11] |
| **5位** | **DeepSeek-OCR** | 軽量3B、文書解析特化、Markdown出力対応、CPU実行可能[23][24][25] |

#### 用途別推奨
- **帳票・構造化データ抽出重視**: PaddleOCR-VL-0.9B[27][26]
- **PDF→Markdown変換**: MinerU、Marker[35][37][4][34]
- **日本語VQA・理解タスク**: Heron BLIP、Swallow-VLM[8][9][10][7]
- **汎用性重視**: Qwen2-VL（量子化版）[18][17]
- **軽量・高速処理**: PaddleOCR-VL-0.9B、DeepSeek-OCR[23][24][26]

---

---

## 3. LedgerLeapにおけるVLM/RAG統合戦略（最終決定版）

**最終更新:** 2025年10月25日  
**設計方針:** `attached_files`テーブル活用によるVLM/RAG統合  

> **📘 完全な実装計画は以下を参照:**
> 
> - **[VLM/RAG統合実装計画書（最終版）](./vlm-rag-integration.md)** - 処理フロー詳細、ジョブ実装、エラーハンドリング、実装チェックリスト
> - **[VLM保存戦略変更提案](../work/vlm-implementation/2025-10-25_vlm-storage-strategy-proposal.md)** - 設計変更の詳細評価
> - **[VLM UI機能追加計画](../work/vlm-implementation/2025-10-25_vlm-ui-feature-addition.md)** - UI設計、ダウンロード機能
> - **[技術評価レポート](../work/vlm-implementation/2025-10-25_indexing-strategy-review-evaluation.md)** - 既存実装との整合性検証

### 3.1. 設計決定：attached_filesテーブル活用

**採用理由:**
1. ✅ **データ正規化**: `content_attached` JSONの肥大化を回避
2. ✅ **RAG統合の容易性**: 既存の`ledger_chunks`テーブルとの自然な連携
3. ✅ **パフォーマンス**: Mroonga全文検索への影響なし
4. ✅ **拡張性**: 将来のベクトル検索への対応が容易

**データ保存先の明確化:**

| データ種別 | 保存先 | 用途 | 形式 |
|-----------|--------|------|------|
| **Tika/OCR結果** | `ledgers.content_attached` | Mroongaキーワード検索 | プレーンテキスト |
| **VLM結果** | `attached_files.vlm_markdown` | RAGチャンキング | Markdown |
| **VLM構造化データ** | `attached_files.vlm_structured_data` | エンティティ抽出 | JSON |
| **RAGチャンク** | `ledger_chunks.chunk_text` | セマンティック検索 | テキスト |
| **Embedding** | `ledger_chunks.embedding` | ベクトル検索 | Float配列 |

### 3.2. 処理フロー全体像

```
ファイルアップロード
  ↓
[Phase 1] Apache Tika → content_attached (既存・Mroonga検索用)
  ↓
[Phase 2] VLM処理 → attached_files.vlm_markdown (新規・RAG用)
  ↓
[Phase 3] Chunking → ledger_chunks (VLM Markdown優先使用)
  ↓
[Phase 4] Embedding → ledger_chunks.embedding
  ↓
セマンティック検索可能
```

**処理タイミング（新規ファイルアップロード時）:**
```
0:00 - アップロード
0:01 - Tika処理 (5秒)
0:06 - ユーザー画面復帰可能 ✅
0:16 - VLM処理開始 (10秒遅延・非同期)
0:28 - Chunking処理 (非同期)
0:46 - 全処理完了
```

### 3.3. データスキーマ

**attached_files テーブル拡張（新規カラム）:**

```sql
vlm_markdown LONGTEXT NULL
  COMMENT 'VLM抽出Markdown結果（RAG用）'
  
vlm_structured_data JSON NULL
  COMMENT 'VLM構造化データ（エンティティ、テーブル等）'
  
vlm_model VARCHAR(100) NULL INDEX
vlm_confidence DECIMAL(4,3) NULL
vlm_processing_time_ms INT UNSIGNED NULL
vlm_processed_at TIMESTAMP NULL INDEX
```

**サイズ見積もり:** 約7KB/ファイル（1000ファイルで7MB）

### 3.4. 実装ロードマップ

- **Week 1-2:** フェーズ1（基盤整備）- スキーマ拡張、設定ファイル
- **Week 2-3:** フェーズ2（VLM処理）- VLMジョブ実装、テスト
- **Week 3-4:** フェーズ3（RAG統合）- Chunking更新、自動化
- **Week 4-6:** フェーズ4（UI機能）- ダウンロード、プレビュー

### 3.5. 技術的保証

**後方互換性:**
- ✅ 既存のTika/OCR処理は一切変更なし
- ✅ VLM失敗時もTika結果で検索可能
- ✅ 段階的有効化可能（`VLM_ENABLED=false`で無効化）

**エラーハンドリング:**
- VLM処理失敗 → `status=VLM_FAILED`（台帳検索は可能）
- 自動リトライ: 2回（5分間隔）
- 詳細ログ出力による追跡可能性

**パフォーマンス:**
- ユーザー待機時間: < 10秒（Tika完了後、画面復帰可能）
- 全処理は非同期（ユーザー体験を損なわない）
- 低優先度キュー使用（通常業務に影響なし）


---

## 4. PoC（概念実証）計画

> **📘 PoC実施の詳細:**
> [VLM/RAG統合実装計画書](./vlm-rag-integration.md)

### 4.1. PoC目標

技術的実現可能性を検証：
1. ✅ VLM処理の実行可能性（オンプレミスCPU環境）
2. ✅ `attached_files`スキーマ拡張の妥当性
3. ✅ `ledger_chunks`との統合効果

### 4.2. PoCスコープ

**対象:** 10件の実データ（請求書5件、領収書3件、議事録2件）  
**評価項目:** VLM処理成功率、Markdown品質、処理時間、システム負荷

### 4.3. PoC成功基準

| 項目 | 目標値 |
|------|--------|
| VLM処理成功率 | > 90% |
| Markdown品質 | > 4.0/5.0 |
| 平均処理時間 | < 20秒/ファイル |
| 信頼度スコア | > 0.85 |


## 5. 結論と推奨アプローチ（最終版）

VLM-OCR技術とRAG機能の統合により、LedgerLeapの検索体験を根本から変えるポテンシャルを実現します。本文書で確定した設計方針に基づき、段階的に導入します。

### 5.1. 最終設計決定

✅ **採用:** `attached_files`テーブルにVLM結果を保存  
❌ **不採用:** `content_attached` JSON方式（肥大化リスク）

> **📘 詳細な実装計画:**
> - **[VLM/RAG統合実装計画書（最終版）](./vlm-rag-integration.md)**
>   - 処理フロー詳細（Mermaid図付き）
>   - ジョブ実装（完全なソースコード）
>   - エラーハンドリングとリトライ戦略
>   - 実装チェックリスト（Week 1-6）
>   - 運用マニュアル

### 5.2. 実装ロードマップ

| フェーズ | 期間 | 成果物 | リスク |
|---------|------|--------|--------|
| **Phase 1** | Week 1-2 | スキーマ拡張、設定ファイル | 低 |
| **Phase 2** | Week 2-3 | VLM処理ジョブ実装 | 中 |
| **Phase 3** | Week 3-4 | RAG統合、Chunking更新 | 中 |
| **Phase 4** | Week 4-6 | UI機能追加 | 低 |

### 5.3. 技術的保証

✅ **後方互換性:** 既存のTika/OCR処理は一切変更なし  
✅ **エラー耐性:** VLM失敗時もTika結果で検索可能  
✅ **パフォーマンス:** ユーザー待機時間 < 10秒（非同期処理）

### 5.4. 推奨される次のアクション

**Week 1（即座に実施）:**
- [ ] ステークホルダーレビュー
- [ ] 予算・リソース確保
- [ ] [VLM/RAG統合実装計画書](./2025-10-25_vlm-rag-integration-plan-final.md) 精読
- [ ] 開発環境準備

**推奨:** 本計画に基づき、Week 1から実装を開始することを強く推奨します。

---

## 6. 出典・参考文献
[1] https://sangdooyun.github.io/data/kim2021donut.pdf
[2] https://github.com/clovaai/donut
[3] https://huggingface.co/docs/transformers/model_doc/layoutlmv2
[4] https://note.com/panda_lab/n/ncedca96086b9
[5] https://github.com/gokayfem/awesome-vlm-architectures
[6] https://qiita.com/yosim/items/8622997580e10b206260
[7] https://github.com/llm-jp/awesome-japanese-llm
[8] https://swallow-llm.github.io/evaluation/about.en.html?index=%22__ALL__%22&task=%5B%22Llama+3+Swallow+8B+Instruct%22%2C%22Swallow-7b-instruct-v0.1%22%5D&scatter=%22__ALL__%22
[9] https://huggingface.co/turing-motors/heron-chat-blip-ja-stablelm-base-7b-v1
[10] https://zenn.dev/turing_motors/articles/00df893a5e17b6
[11] https://github.com/turingmotors/heron
[12] https://zenn.dev/singularity/articles/heron-blip-v1
[13] https://zenn.dev/turing_motors/articles/7ac8ebe8756a3e
[14] https://weel.co.jp/media/tech/japanese-stable-vlm/
[15] https://huggingface.co/stabilityai/japanese-stable-vlm
[16] https://huggingface.co/stabilityai/japanese-stable-vlm/blob/main/README.md
[17] https://github.com/xwjim/Qwen2-VL
[18] https://huggingface.co/Qwen/Qwen2-VL-7B-Instruct
[19] https://note.com/holyday_mylife/n/n27e772d01465
[20] https://tech-blog.abeja.asia/entry/vlm-ocr-202507
[21] https://zenn.dev/yumefuku/articles/pdf-search-colqwen2
[22] https://note.com/oshizo/n/n473a0124585b
[23] https://note.com/trans_n_ai/n/n73c538a209ff
[24] https://apidog.com/jp/blog/deepseek-ocr/
[25] https://www.iweaver.ai/blog/deepseek-ocr-vision-language-model/
[26] https://zenn.dev/czmilo/articles/dbdd4b06889510
[27] https://dev.to/czmilo/2025-complete-guide-paddleocr-vl-09b-baidus-ultra-lightweight-document-parsing-powerhouse-1e8l
[28] https://sonusahani.com/blogs/paddleocr-vl
[29] https://github.com/PaddlePaddle/PaddleOCR
[30] https://arxiv.org/html/2507.05595v1
[31] https://qiita.com/sakamoto1209/items/59288cd88133852d2e9e
[32] https://www.aska-ltd.jp/jp/blog/284
[33] https://glama.ai/mcp/servers/@FutureUnreal/mcp-pdf2md?locale=ja-JP
[34] https://zenn.dev/kun432/scraps/c87a2570953747
[35] https://github.com/opendatalab/MinerU
[36] https://qiita.com/yuji-arakawa/items/6d0299c505315bc3cdb0
[37] https://github.com/datalab-to/marker
[38] https://note.com/kakeyang/n/n1ba8a489b0c6
[39] https://blog.scuti.jp/olmocr-pdf-text-extraction-1-32-cost/
[40] https://zenn.dev/data_and_ai/articles/e06e47eb702fd5
[41] https://recruit.gmo.jp/engineer/jisedai/blog/docling-pdf-table-image-extraction/
[42] https://dev.classmethod.jp/articles/converting-document-files-using-oss-tool-docling/
[43] https://www.ibm.com/jp-ja/new/announcements/granite-docling-end-to-end-document-conversion
[44] https://note.com/panda_lab/n/n0a6e77f9cd3f
[45] https://blog.dolphinvoice.ai/archives/356
[46] https://docsaid.org/ja/papers/text-spotting/got/
[47] https://zenn.dev/hk_ilohas/articles/meta-ai-nougat-ocr
[48] https://note.com/daichi_mu/n/nd302ab7d8ffd
[49] https://facebookresearch.github.io/nougat/
[50] https://stackoverflow.com/questions/2557743/most-accurate-open-source-ocr-for-japanese
[51] https://www.reddit.com/r/MachineLearning/comments/170j47f/d_tesseractocr_vs_paddleocr_vs_easyocr_for/
[52] https://github.com/JaidedAI/EasyOCR
[53] https://www.reddit.com/r/LearnJapanese/comments/wm7qou/japanese_ocr_mobile_options_comparison/
[54] https://arxiv.org/html/2403.13187v1
[55] https://note.com/en2enzo/n/n121f72756e58
[56] https://www.scribd.com/document/859647459/kim2021donut
[57] https://docs.unsloth.ai/models/qwen3-vl-run-and-fine-tune
[58] https://openlibrary.telkomuniversity.ac.id/pustaka/files/219195/abstraksi/document-analysis-and-recognition-icdar-2023-17th-international-conference-san-jos-ca-usa-august-21-26-2023-proceedings-part-ii.pdf
[59] https://note.com/npaka/n/n1d99253ae2cf
[60] https://qiita.com/yosim/items/c65b28bf4be05a14f390
[61] https://aman.ai/papers/
[62] https://arxiv.org/html/2404.07824v1
[63] https://github.com/hiyouga/LLaMA-Factory/releases
[64] https://www.youtube.com/watch?v=sMgx05wthKw
[65] https://loner49th.hatenablog.com/entry/2024/04/21/220643
[66] https://stability.ai/news/stability-ai-new-jplm-japanese-language-model-stablelm
[67] https://goldpenguin.org/blog/stability-launches-japanese-ai-text-generator/
[68] https://www.reddit.com/r/LocalLLaMA/comments/1ocrocy/deepseekocr_lives_up_to_the_hype/
[69] https://huggingface.co/blog/ocr-open-models
[70] https://qiita.com/vko/items/04fb0756abd89dff8573
[71] https://note.com/masa_wunder/n/n7f361aa17128
[72] https://zenn.dev/nyagato_00/articles/719b8c4749365f
[73] https://qiita.com/keisuke-okb/items/ae1dbb4f3e3034713245
[74] https://www.linkedin.com/pulse/deepseek-introduction-coding-vl-vl2-prover-r1-qwen-dabass-ph-d-0bfvf
[75] https://speakerdeck.com/kuehara/da-gui-mo-ri-ben-yu-vlm-asagi-vlmniokeruhe-cheng-detasetutonogou-zhu-tomoderushi-zhuang
[76] https://qwenlm.github.io/blog/qwen2-vl/
[77] https://tadaoyamaoka.hatenablog.com/entry/2024/09/01/180813
[78] https://ironsoftware.com/csharp/ocr/blog/ocr-tools/best-ocr-for-japanese-list/
[79] https://unstract.com/blog/best-pdf-ocr-software/
[80] https://arxiv.org/html/2505.14381v1
[81] https://intuitionlabs.ai/articles/ai-ocr-models-pdf-structured-text-comparison
[82] https://github.com/kotaro-kinoshita/yomitoku
[83] https://www.reddit.com/r/MachineLearning/comments/i98wr6/p_choosing_an_ocr/
[84] https://note.com/kotaro_kinoshita/n/n70df91659afc
[85] https://huggingface.co/docs/transformers/model_doc/trocr