# VLM実装作業ログディレクトリ

このディレクトリには、VLM(Vision Language Model)機能の実装に関する作業ログや計画書が格納されています。

> **📖 最新の公式ドキュメントはこちらを参照してください:**
> - **[VLM/OCR機能 概要・クイックスタート](../../development/vlm-ocr.md)**
> - **[VLMモデル切り替えガイド](../../development/vlm-model-switching-guide.md)**
> - **[VLM/RAG統合アーキテクチャ](../../architecture/vlm-rag-integration.md)**
> - **[VLM/OCR技術選定](../../architecture/vlm-ocr-technology-selection.md)**

---

## 📋 最新の計画書（Phase 2: ベクトルインデックス高度化）

### 現在進行中のPhase

**Phase 2.5 実装完了報告** - `2025-11-16_enhanced-vector-indexing-strategy_phase-2.5-implementation-completed.md` ✅ **完了**
- 固有名詞・記号の先頭埋め込み（基本機能）
- **品詞別ラベリング機能追加**（固有名詞 vs 一般名詞）
- **ストップワード機能追加**（自社名などを除外）
- 実装日: 2025-11-16
- テスト: 13ケース全てパス

**Phase 2.5-3.1 実装計画（改訂版）** - `2025-11-16_enhanced-vector-indexing-strategy-enhanced-vector-indexing-strategy.md`
- Phase 2.5: ✅ **完了** (2025-11-16)
- Phase 2.6: 🔜 次のステップ
- Phase 3.1: 📋 計画中
- **重要変更:** 単一ベクトル戦略（キーワード専用ベクトル削除）
- 実装期間を25.5日→13.5日に短縮（45%削減）

**Phase 2.6 実装計画** - `2025-11-16_phase-2.6-ocr-integration-implementation-plan.md` ⭐ **NEW**
- ソース別ステータス管理（FINALIZED_BY_TIKA/OCR/VLM）
- ファイルタイプ別最適化（オフィスファイルはTikaのみ）
- 即座にインデックス化→段階的品質向上
- 実装工数: 3日

### 基本戦略ドキュメント

**VLM-OCR技術とベクトルインデックス戦略レビュー** - `2025-11-15_vlm-ocr-and-indexing-strategy-review.md`
- 2025年11月時点のVLM技術動向調査
- 現状アーキテクチャの記録
- PaddleOCR-VL、MinerU等の技術評価

---

## 📊 実装ステータス

| Phase | 説明 | ステータス | 完了日 |
|-------|------|-----------|--------|
| Phase 0 | VLM導入PoC | ✅ 完了 | 2025-10-26 |
| Phase 1 | VLM統合・テスト | ✅ 完了 | 2025-11-07 |
| **Phase 2.5** | **キーワード埋め込み** | ✅ **完了** | **2025-11-16** |
| Phase 2.6 | OCR統合 | 🔜 設計完了 | - |
| Phase 3.1 | ハイブリッド検索 | 📋 計画中 | -

---

## 🗂️ 過去の作業記録（アーカイブ）

以下は、初期の計画書、調査ログ、実装記録です。開発の経緯を追跡するために保管されています。

### Phase 0-1: VLM導入・統合（完了）
- [Phase 0: VLM動作検証PoC計画書](./2025-10-25_phase0-vlm-poc-plan.md)
- [Phase 0: VLM動作検証PoC 実施記録](./2025-10-25_phase0-vlm-poc-execution-log.md)
- [PaddleOCRVL API実装完了記録](./2025-10-26_paddleocrvl-implementation-log.md)
- [VLMテスト更新計画](./2025-11-07_vlm-test-update-plan.md)
- [VLMテスト更新完了](./2025-11-07_vlm-test-update-completed.md)

### 技術調査・評価
- [VLM-OCR技術とインデックス戦略(v2)](./2025-10-23_vlm-ocr-and-indexing-strategy-review_updated_v2.md)
- [インデックス強化構想の技術評価](./2025-10-25_indexing-strategy-review-evaluation.md)
- [PaddleOCR バージョンアップ報告書](./2025-10-26_paddleocr-version-upgrade-report.md)
- [GitHub FAQ #16823 詳細分析](./2025-10-26_github-faq-16823-analysis.md)
- [高度なオプション調査](./2025-11-02_advanced-options-research.md)
- [出力品質比較](./2025-11-02_output-quality-comparison.md)

### PaddleOCR関連
- [PaddleOCR-VL 試行計画書](./2025-10-26_paddleocr-vl-trial-plan.md)
- [PaddleOCR-VL 公式情報再調査](./2025-10-26_paddleocr-vl-new-findings.md)
- [PaddleOCR-VL CPU実行検証](./2025-10-26_paddleocr-vl-test-results.md)
- [PaddleOCR最新版実装ガイド](./2025-10-26_paddleocr-latest-impl-guide.md)
- [構造化データ強化](./2025-11-02_paddleocr-structured-data-enhancement.md)

### その他
- [VLM保存戦略変更提案](./2025-10-25_vlm-storage-strategy-proposal.md)
- [VLM UI機能追加計画](./2025-10-25_vlm-ui-feature-addition.md)
- [統合VLM API実装](./2025-11-02_unified-vlm-api-implementation.md)
- [Marker/MinerU修正計画](./2025-11-02_marker-mineru-fix-plan.md)

---

## 🔗 関連ドキュメント

- [RAG機能導入に関する技術検討](../rag-implementation/2025-10-16-rag-implementation-study.md)
- [AIアシスタントと検索の哲学](../../ai-and-search-guide.md)
- [添付ファイル機能](../../function/Attachment.md)