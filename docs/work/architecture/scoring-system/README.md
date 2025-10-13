# スコアリングシステム 作業ドキュメント

このディレクトリには、ハイブリッド型情報価値評価システム（スコアリングシステム）の実装に関する作業ドキュメントが含まれています。

## 📁 ドキュメント構成

### メイン計画書
- **2025-10-08_search-result-scoring-and-sorting-plan.md** - 全体実装計画（Phase 1〜5）
  - Phase 1: 基本スコアリング機能 ✅ 完了
  - Phase 1.5: スケジューリング最適化 ✅ 完了
  - Phase 2〜5: 今後の拡張計画

### Phase 1 実装ドキュメント

#### Step 1.7 UI統合（完了）
- **2025-10-12_step1-7-ui-integration-plan.md** - UI統合の詳細計画
- **2025-10-12_step1-7-implementation-complete.md** - 実装完了レポート
- **2025-10-12_step1-7-header-score-display.md** - 台帳定義ヘッダースコア表示
- **2025-10-12_step1-7-ledger-define-sort.md** - 検索時の台帳定義ソート機能
- **2025-10-12_step1-7-troubleshooting.md** - トラブルシューティングガイド

### Phase 1.5 実装ドキュメント

#### Step 1.8 スケジューリング最適化（完了）
- **2025-10-12_phase1-5-step1-8-implementation-complete.md** - 実装完了レポート

### 技術検討資料
- **2025-10-12_hybrid-scoring-performance-study.md** - パフォーマンス検証

## 🔗 関連する公式ドキュメント

実装が完了した機能の公式ドキュメントは以下に配置されています：

- **[スコアリングシステム（機能ドキュメント）](../../../features/scoring-system.md)** - エンドユーザー・システム管理者向け
  - スコア計算式の詳細
  - 使用方法（画面操作）
  - スコア更新頻度の設定
  - トラブルシューティング
  
- **[スコアリングシステム（開発者ガイド）](../../../development/scoring-system.md)** - 開発者・保守担当者向け
  - アーキテクチャ概要
  - コアサービスの実装詳細
  - テスト戦略
  - パフォーマンス考慮事項
  - 拡張方法
  - **MCP統合情報** 🆕
  
- **[MCP アーキテクチャと動作フロー](../../../development/MCP_Architecture_and_Flow.md)** - MCP機能の全体構造 🆕
  - スコアリング機能のMCP統合
  - SearchLedgersToolのソート機能
  
- **[データベーススキーマ](../../../database/schema.md)** - スコア関連テーブルの定義
  - `ledgers.activity_score`、`ledgers.composite_score` カラム
  - インデックス定義
  
- **[メインREADME](../../../README.md)** - プロジェクト全体のエントリーポイント
  - スコアリング機能へのリンク

## 🔗 関連するLLM統合ドキュメント 🆕

スコアリング機能のLLM連携に関するドキュメント：

- **[MCPスコアリング統合計画](../../llm-integration/2025-10-13_MCP_Scoring_Integration_Plan.md)** - MCP統合の設計と方針
- **[MCPスコアリング統合実装完了](../../llm-integration/2025-10-13_MCP_Sorting_Implementation_Complete.md)** - 実装完了報告
- **[MCPプロンプトと応答内容の設計案](../../llm-integration/2025-09-27_MCP_Prompt_and_Response_Design.md)** - LLMユースケース

## 📊 実装状況サマリー

### Phase 1: 基本機能 ✅ 完了（2025-10-12）
- Step 1.1〜1.7: 全て完了
- 実装工数: 3.2日（計画5日→36%削減）
- テスト: 14件全てパス（37 assertions）

### Phase 1.5: スケジューリング最適化 ✅ 完了（2025-10-12）
- Step 1.8: 完了
- 実装工数: 0.3日（計画0.5日→40%削減）
- テスト: 6件全てパス（18 assertions）

### 次のフェーズ
- **Phase 2:** フィードバック収集とスコアロジック改善（予定）
- **Phase 3:** 検索統合と関連性スコア（予定）
- **Phase 4:** パフォーマンス最適化（予定）
- **Phase 5:** 高度な機能（任意）

## 📝 ドキュメント管理ポリシー

### 作業ドキュメント（このディレクトリ）
- 実装計画、進捗レポート、技術検討資料
- 日付付きファイル名（YYYY-MM-DD_description.md）
- 開発プロセスの記録と意思決定の経緯

### 公式ドキュメント（/docs/）
- ユーザー向け機能説明、開発者向けガイド
- 実装完了後に作業ドキュメントから情報を抽出・整理
- バージョン管理と保守性を重視

---

**最終更新:** 2025年10月13日  
**管理者:** LedgerLeap開発チーム
