# LLM連携 (LLM Integration)

**カテゴリ:** 作業ファイル（計画・設計・作業ログ）

> **📖 公式ドキュメントへのリンク:**  
> このカテゴリの作業結果として実装された機能の公式ドキュメントは以下を参照してください：
> - [MCP アーキテクチャと動作フロー](../../development/MCP_Architecture_and_Flow.md) - 実装済みMCPの全体構造
> - [MCP プロンプトガイドライン](../../development/MCP_Prompt_Guidelines.md) - LLM対話のベストプラクティス
> - [スコアリングシステム 開発者ガイド](../../development/scoring-system.md) - レコードスコアリング機能
> - [API仕様](../../api/README.md) - REST API公式仕様

このカテゴリには、LedgerLeapと大規模言語モデル（LLM）を連携させるための機能に関する実装計画やAPI仕様、およびそのテストやデモに必要なデータセットの設計・実装に関する作業ログを格納しています。

---

### ✅ 現状サマリー (2026-03-14 / Sprint 6反映)

2025年までの計画で **API基盤 / MCPサーバー / 検索・登録・ワークフロー・統計** の基礎は整いました。
一方、2026年3月の再検討により、今後の主計画は **クライアント別ファイル生成** ではなく、**MCP / API を唯一の接点とする client-first な公開契約の整備** に置き直しています。

今後は次の3層を明確に分離して整理します。

- **client-facing**: MCP / API クライアントや LLM が見る業務能力、台帳構造、操作導線
- **developer-facing**: LedgerLeap 開発者向けの内部制約、同期、保守、生成補助
- **bootstrap discovery**: クライアント初回接続時に、役割・モデル・用途に応じた最小 skill / prompt / resource を返す導線（具体 contract は Sprint 6 文書で定義済み）

また、client-facing capability は `docs/function/PersonaUseCaseScenario.md` のペルソナ（実務担当者 / 管理者 / 現場リーダー）を基準に再定義します。

### まず読む順番

1. **[クライアント接続モデル再計画（MCP / API First）](./2026-03-09_Client_Skill_Bootstrap_Strategy.md)** — 2026年以降の主計画
2. **[client-facing capability taxonomy](./2026-03-10_Client_Facing_Capability_Taxonomy.md)** — Sprint 2 で整理した client-facing capability の一覧とペルソナ別初期 skill セット
3. **[developer-facing maintenance taxonomy](./2026-03-12_Developer_Facing_Maintenance_Taxonomy.md)** — Sprint 3 で整理した AI 資産の保守先・SoT / 派生物の境界・重複整理方針
4. **[on-prem / local model onboarding design](./2026-03-13_OnPrem_Local_Model_Onboarding_Design.md)** — Sprint 4 で整理した on-prem / local model 前提の onboarding 役割分担、text budget、Sprint 5 / 6 への引き継ぎ境界
5. **[update path public contract](./2026-03-13_Update_Path_Public_Contract.md)** — Sprint 5 で整理した `ledger-update` の client-facing workflow、PATCH 主契約、read path 前提、API / MCP 差分
6. **[first-access bootstrap discovery contract](./2026-03-14_First_Access_Bootstrap_Discovery_Contract.md)** — Sprint 6 で固定した初回 discovery contract、carrier 比較、local model budget、client/developer 境界
7. **[Issue #83 UI evaluation plan](./2026-03-14_Issue-83_UI_Evaluation_Plan.md)** — VSCode + Continue + ローカルLLM 主体の UI 評価計画、ダミーデータ、期待応答、低能力SaaS比較
8. **[MCP アーキテクチャと動作フロー](../../development/MCP_Architecture_and_Flow.md)** — 実装済みの MCP 公開契約
9. **[API仕様](../../api/README.md)** — 実装済みの REST 公開契約
10. 2025年の各計画書 — 歴史的経緯・実装判断の参照用

---

## 📚 ドキュメント一覧

### MCP（Model Context Protocol）実装

#### 基盤・アーキテクチャ
- **[LLM連携機能 開発ロードマップ](./2025-09-23_LLM_Integration_Roadmap.md)**: LLM連携機能の全体像と開発ロードマップ。
- **[フェーズ1 API技術仕様書](./2025-09-24_LLM_Phase1_API_Specification.md)**: 外部連携用APIの技術仕様。
- **[MCP包括的実装計画](./2025-09-29_Comprehensive_MCP_Implementation_Plan.md)**: MCPサーバー機能の全体実装計画。
- **[MCPサーバー実装計画](./2025-09-27_MCP_Server_Implementation_Plan.md)**: `laravel/mcp` パッケージを利用したMCPサーバー機能の実装計画。

#### プロンプト設計・最適化
- **[MCPプロンプトと応答内容の設計案](./2025-09-27_MCP_Prompt_and_Response_Design.md)**: ペルソナに基づいた、`gemini` CLIの具体的な質問応答例と設計方針。
- **[MCP応答最適化計画](./2025-09-28_MCP_Response_Optimization_Plan.md)**: MCPレスポンスをLLMが解釈しやすいように最適化する計画。
- **[SearchLedgersTool レスポンス仕様変更計画](./2025-10-03_MCP_SearchLedgersTool_Response_Refactoring_Plan.md)**: `search_ledgers_tool` の応答仕様の改訂計画。
- **[SearchLedgersTool ドキュメント改善実装](./2025-10-05_MCP_SearchLedgersTool_Enhancement.md)**: `SearchLedgersTool` の説明文（description）を改善した際の実装記録。

#### スコアリング統合 🆕
- **[MCPスコアリング統合計画](./2025-10-13_MCP_Scoring_Integration_Plan.md)**: レコードスコアリングシステムとMCPの統合設計。
- **[MCPスコアリング統合実装完了](./2025-10-13_MCP_Sorting_Implementation_Complete.md)**: SearchLedgersToolへのソートパラメータ追加の実装完了報告。

#### 機能拡張
- **[AI 指示書の同期と共有計画](./20260308_ai_instructions_sync_plan.md)**: `.github` を正本とした AI 指示資産の同期・共有方針。
- **[クライアント接続モデル再計画（MCP / API First）](./2026-03-09_Client_Skill_Bootstrap_Strategy.md)**: client-facing / developer-facing を分離し、オンプレ・ローカルモデル前提で、ペルソナ対応と初回 bootstrap discovery を含めて LLM 連携を再計画した親計画。
- **[client-facing capability taxonomy](./2026-03-10_Client_Facing_Capability_Taxonomy.md)**: `ledger-search` / `ledger-create` / `ledger-update` / `workflow-review` を業務能力として整理し、ペルソナ別の初期 skill セットを定義した Sprint 2 の成果物。`docs/function/Ledger.md` / `Search.md` / `WorkFlow.md` は developer-facing の正式仕様として維持し、この文書群とは役割を分離する。
- **[developer-facing maintenance taxonomy](./2026-03-12_Developer_Facing_Maintenance_Taxonomy.md)**: Sprint 3 の成果物。`.github` / `AGENTS.md` / `docs/runbooks` / `docs/work` / `resources/ai/capabilities` / generator prototype の責務を整理し、内部制約の保守先と SoT / 派生物の境界を明文化する。
- **[on-prem / local model onboarding design](./2026-03-13_OnPrem_Local_Model_Onboarding_Design.md)**: Sprint 4 の成果物。on-prem / local model 前提で、offline docs / MCP / REST API の役割分担、prompt / resource / tool の責務分担、local model 向け text budget、Sprint 5 / 6 への引き継ぎ境界を整理する。
- **[first-access bootstrap discovery contract](./2026-03-14_First_Access_Bootstrap_Discovery_Contract.md)**: Sprint 6 の成果物。REST bootstrap manifest を初期 contract として固定し、MCP `resource / prompt / tool` の比較、local model 向け text/schema budget、client-facing / developer-facing 境界、後続 Issue 分解を整理する。
  - 後続実装 Issue: [#92](https://github.com/torinky/LedgerLeap/issues/92), [#93](https://github.com/torinky/LedgerLeap/issues/93), [#94](https://github.com/torinky/LedgerLeap/issues/94), [#95](https://github.com/torinky/LedgerLeap/issues/95)
- **[Issue #83 UI evaluation plan](./2026-03-14_Issue-83_UI_Evaluation_Plan.md)**: VSCode + Continue + ローカルLLM を主対象に、bootstrap discovery / capability / onboarding を UI から評価する計画。ダミーデータ、シナリオ、期待応答、低能力SaaS比較の観点を整理する。
  - Tracking Issue: [#96](https://github.com/torinky/LedgerLeap/issues/96)
- **[update path public contract](./2026-03-13_Update_Path_Public_Contract.md)**: Sprint 5 の成果物。`ledger-update` を client-facing 契約として定義し、単一レコード read path の必要性、PATCH 主契約、pending 状態編集時の `DRAFT` 戻し、API 実装 / MCP 実装への分解単位を整理する。
- **[update API implementation log](./2026-03-13_Update_API_Implementation_Log.md)**: Issue #90 の実装ログ。`GET/PATCH /api/v1/ledgers/{ledger}` の判断、既存 workflow サービス再利用方針、tag update を見送った理由、公式ドキュメント化の手掛かりを記録する。
- **[MCP update tools implementation log](./2026-03-13_MCP_Update_Tools_Implementation_Log.md)**: Issue #91 の実装ログ。`GetLedgerDetailTool` / `UpdateLedgerTool` の役割分担、`dry_run` の最小差分設計、テストの責務分離、別スプリントへ切り出した論点を記録する。
- **[改訂版MCP実装計画 (ビュー調査版)](./2025-10-01_Revised_MCP_Implementation_Plan.md)**: 既存のビューや翻訳リソースの活用を反映した改訂計画。
- **[添付ファイル活用計画](./2025-10-04_MCP_AttachedFiles_Integration_Plan.md)**: MCP経由で添付ファイル情報を活用するための実装計画。
- **[添付ファイル活用タスク分析](./2025-10-04_MCP_Task5.2_AttachedFile_Analysis.md)**: 添付ファイル関連の未実装タスクに関する要件分析。

#### トラブルシューティング
- **[日本語検索対応実装レポート](./JAPANESE_SEARCH_IMPLEMENTATION_2025-10-05.md)**: MCPツールでの日本語キーワード検索への対応記録。
- **[MCP検索API調査レポート](./MCP_SEARCH_DEBUG_REPORT_2025-10-05.md)**: MCP API経由での検索タイムアウト問題に関する調査レポート。
- **[日付デフォルト値初期化の調査](./demo/2025-10-05_DateDefaultInitialization_Investigation.md)**: 日付カラムのデフォルト値初期化に関する不具合の調査記録。

#### 統計・レポート機能
- **[フェーズ3完了レポート](./PHASE3_COMPLETION_REPORT.md)**: MCP統計・レポート機能の実装完了報告。

### デモデータ (Demo Data)

- **[デモデータ マスタープラン](../../development/test-data-design.md)**: MCPツールやUIの機能を包括的に検証・デモするためのデータセット全体の設計思想と計画。
- **[デモデータ実装ログ](./demo/2025-10-04_demo_implementation_log.md)**: デモデータ作成の過程で発生した課題やパフォーマンス改善の記録。
- **[デモフェーズ1完了レポート](./demo/2025-10-11_demo_phase1_complete.md)**: マスタープランのフェーズ1で定義されたデモデータの実装完了レポート。
- **[Seeder利用ガイド](../../development/database-seeding-guide.md)**: デモデータを含むデータベースSeederの利用方法に関するガイド。
- **[デモ環境ログイン情報](../../development/demo-credentials.md)**: デモ環境で使用するユーザーアカウント情報。
- **[デモStep1最小構成](./demo/2025-10-04_demo_step1_minimal.md)**: LLMとの対話デモができる最小限のデータセット構築計画。
- **[デモフェーズ1拡張実装進捗](./demo/2025-10-11_demo_phase1_extension_progress.md)**: マスタープランPhase1の拡張データセット実装に関する進捗レポート。

---

# LedgerLeap 実装計画・作業ログ

このディレクトリには、LedgerLeapの機能開発における実装計画と作業記録を格納しています。

## 📋 アクティブな実装計画

### 🚀 **メイン実装計画**
- **[クライアント接続モデル再計画（MCP / API First）](./2026-03-09_Client_Skill_Bootstrap_Strategy.md)** ⭐ **最優先**
  - **目標**: MCP / API を唯一の client 接点として、公開契約・client-facing skill・developer-facing SoT・初回 bootstrap discovery を再整理する
  - **期間**: 6スプリント（情報設計 → client-facing taxonomy → developer-facing taxonomy → on-prem onboarding → update path → bootstrap discovery）
  - **範囲**: ペルソナ対応、オンプレ・ローカルモデル前提、更新系公開契約、初回アクセス時 skill bootstrap discovery
  - **関連Issue**: [#83](https://github.com/torinky/LedgerLeap/issues/83) （親計画・進捗管理先）
  - **状況**: Sprint 1-6 完了（情報設計のリセット / client-facing capability taxonomy / developer-facing maintenance taxonomy / on-prem onboarding design / update path public contract / first-access bootstrap discovery contract） / MCP parity は `GetClientBootstrapManifestTool`（Issue #94）として実装済み。static bootstrap resource（Issue #92）も実装済みで、bootstrap prompt 分離のみ後続 Issue として継続
- **[MCP包括的実装計画](./2025-09-29_Comprehensive_MCP_Implementation_Plan.md)** ⭐ **継続参照**
  - **目標**: AI統合業務管理プラットフォームへの完全発展
  - **期間**: 4-6週間
  - **範囲**: ワークフロー統合、監査機能、統計・レポート、高度検索
  - **状況**: 2025年時点の実装計画として参照。2026年以降の主計画は上記再計画へ集約

## 📚 完了済み計画

### ✅ **基盤実装完了**
- **[添付ファイル活用計画](./2025-10-04_MCP_AttachedFiles_Integration_Plan.md)** - **提案中** 🟡
  - **目標**: 添付ファイル（PDF/画像）の内容を活用したインテリジェント検索
  - **主要機能**:
    - SearchLedgersTool の添付ファイル対応（`include_attachments`パラメータ）
    - 新規 GetAttachedFilesTool（抽出テキスト確認）
    - 新規 SearchByAttachedFileContentTool（ファイル内容特化検索）
    - OCR/Tika抽出テキストの活用
  - **ユースケース**: 請求書検索、契約書変更確認、文書管理、ストレージ最適化
  - **工数**: Phase 1: 6時間, Phase 2: 8時間, Phase 3: 10時間
  - **関連**: content_attached フィールドとAttachedFileモデルの完全活用

- **[SearchLedgersTool レスポンス仕様変更](./2025-10-03_MCP_SearchLedgersTool_Response_Refactoring_Plan.md)** - **改訂版承認済み** ✅
  - **目標**: 柔軟な情報量制御とLLM最適化
  - **主要機能**: 
    - 4つのレスポンスモード（raw, summary, summary+preview, detailed）
    - `include_content` パラメータでカスタムフィールド制御
    - 英語固定キー + 翻訳済み値の設計
    - 段階的情報取得ワークフローのサポート
  - **工数**: 8.5時間（1-2日）
  - **関連**: [MCPプロンプトと応答内容の設計案](./2025-09-27_MCP_Prompt_and_Response_Design.md) も更新済み

- **[MCP応答最適化計画](./2025-09-28_MCP_Response_Optimization_Plan.md)** - **完了済み** ✅
  - **成果**: 基本的なMCPツール実装、プロンプトチューニング完了
  - **実装内容**: SearchLedgersTool, CreateLedgerTool, GetLedgerDefinesTool
  - **品質**: format=summary, __display_fields__ 対応

### 📖 **設計・調査完了**
- **[MCPサーバー実装計画](./2025-09-27_MCP_Server_Implementation_Plan.md)** - **完了済み** ✅
  - **成果**: laravel/mcp パッケージ導入、基本アーキテクチャ確立
- **[MCPプロンプトと応答内容の設計案](./2025-09-27_MCP_Prompt_and_Response_Design.md)** - **完了済み** ✅
  - **成果**: ペルソナベースのユースケース定義
- **[LLM連携フェーズ1 API技術仕様書](./2025-09-24_LLM_Phase1_API_Specification.md)** - **完了済み** ✅
  - **成果**: API基盤仕様確立

## 🎯 実装進捗トラッキング

### Phase 0: 準備段階（技術基盤強化）
- [x] **Step 0.1**: spatie/laravel-query-builder 完全活用 ✅ **完了 (2025-09-29)**
  - 🎯 **成果**: 100行→20行のコード効率化、16.38ms高速クエリ、完全後方互換性
- [ ] **Step 0.2**: MCPツール認証統一化  
- [ ] **Step 0.3**: テストカバレッジ完全化

### Phase 1: ワークフロー統合
- [ ] **Step 1.1**: ワークフローAPI開発
- [ ] **Step 1.2**: ワークフローMCPツール実装
- [ ] **Step 1.3**: ワークフロー統合テスト

### Phase 2: アクティビティ監査機能
- [ ] **Step 2.1**: アクティビティログAPI開発
- [ ] **Step 2.2**: アクティビティMCPツール実装
- [ ] **Step 2.3**: セキュリティ監査統合テスト

### Phase 3: 統計・レポート機能
- [ ] **Step 3.1**: 統計分析API開発
- [ ] **Step 3.2**: 統計MCPツール実装

### Phase 4: 検索機能拡張・統合
- [ ] **Step 4.1**: 高度検索フィルタ追加

### Phase 5: 統合・最適化段階
- [ ] **Step 5.1**: MCPプロンプト最適化
- [ ] **Step 5.2**: パフォーマンス最適化
- [ ] **Step 5.3**: 最終統合テスト

## 📊 目標達成指標

### 機能目標
- **MCPツール数**: 現在 3個 → 目標 15個以上
- **対応ユースケース**: 現在 基本検索のみ → 目標 全15シナリオ
- **API機能**: ✅ **改善済み** spatie/laravel-query-builder活用 → 目標 完全業務フロー統合

### 品質目標
- **テストカバレッジ**: 現在 基本テストのみ → 目標 95%以上
- **応答品質**: 現在 JSONレスポンス → 目標 自然言語対話

### UX目標
```
Before: "台帳の検索しかできない"
After: "業務全体を自然言語で操作可能"
```

## 🔄 作業フロー

### 1. 実装開始前
- [ ] 該当Phaseの詳細要件確認
- [ ] 依存関係・前提条件の確認
- [ ] テスト環境の準備

### 2. 実装中
- [ ] ステップごとの完了基準チェック
- [ ] 継続的テスト実行
- [ ] コードレビュー実施

### 3. 実装完了後
- [ ] 統合テスト実行
- [ ] ドキュメント更新
- [ ] 次Phaseへの引き継ぎ

## 📝 作業ログ記録

実装作業の詳細な記録は、各実装計画ドキュメント内の「実装記録」セクションに記載してください。

## 🔗 関連ドキュメント

### 技術ドキュメント
- [MCP アーキテクチャと動作フロー](../../development/MCP_Architecture_and_Flow.md)
- [MCP プロンプトガイドライン](../../development/MCP_Prompt_Guidelines.md)

### 要件・設計ドキュメント  
- [ペルソナ、ユースケース、シナリオ](../../function/PersonaUseCaseScenario.md)
- [API仕様概要](../../api/README.md)

---

**更新日**: 2026年3月14日
**現行方針**: MCP / API first の client-facing 契約整備
**責任者**: LedgerLeap開発チーム