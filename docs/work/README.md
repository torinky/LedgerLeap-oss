# LedgerLeap 実装計画・作業ログ

**ディレクトリ種別:** 作業ファイル（計画・設計・実装記録）

> **📖 対応する公式ドキュメント:**  
> このディレクトリの作業結果として実装された機能の公式仕様は、[`/docs/README.md`](../README.md) から各カテゴリへ辿ってください。
>
> **特にLLM連携機能については:**
> - 作業ファイル: [`/docs/work/llm-integration/README.md`](./llm-integration/README.md)
> - 現在の主計画: [`/docs/work/llm-integration/2026-03-09_Client_Skill_Bootstrap_Strategy.md`](./llm-integration/2026-03-09_Client_Skill_Bootstrap_Strategy.md)
> - 公式ドキュメント: [`/docs/development/MCP_Architecture_and_Flow.md`](../development/MCP_Architecture_and_Flow.md)
>
> 現行方針は **MCP / API first** です。client-facing の公開契約と developer-facing の保守資産を分離し、クライアント別ファイル生成は補助的な位置づけとして扱います。

このディレクトリには、LedgerLeapの機能開発における実装計画と作業記録を格納しています。
各ドキュメントは、機能や関心事に応じて以下のカテゴリに分類されています。

---

## 📋 ドキュメント管理方針

### 作業ファイル vs 公式ドキュメント

**このディレクトリ (`/docs/work/`):**
- 開発計画、設計検討、実装記録、意思決定プロセス
- 実装前の要件定義や技術選定の議論
- 実装中の作業ログや課題・解決策の記録

**公式ドキュメント (`/docs/` 直下):**
- 実装済み機能の確定した技術仕様
- システムの現在の状態を正確に反映
- 開発者・運用者が参照する正式な仕様書

### 相互リンクの活用

各ドキュメントの冒頭には「関連ドキュメント」セクションがあり、以下のリンクが記載されています：
- 計画文書（作業ファイル）→ 実装結果（公式ドキュメント）
- 公式ドキュメント → 元となった計画（作業ファイル）

これにより、**なぜその実装になったのか**（計画・意思決定）と**何が実装されたか**（仕様）の両方を追跡できます。

---

### ✅ 現状サマリー (2025-10-11)

このディレクトリ内のドキュメントは、カテゴリごとに整理され、それぞれの `README.md` に現状のステータスが記載されています。
各ドキュメントと現在のコードベースとの間に大きな齟齬がないことを確認済みです。

### 運用改善メモ

- **[完了後の振り返りハンドオフ方針](./2026-04-04_retrospective-handoff-policy.md)**: issue / sprint / feature / investigation / documentation など、すべての作業完了後に学びを抽出し、進め方の改善と個別手法の改善を 2 層で整理する運用メモ。
- **[File Inspector 検索条件の再オープン維持メモ](./ui-ux/2026-04-27_file-inspector-search-reopen-retrospective.md)**: 詳細画面 full mode の `attachment-card` で検索語の引き回しを見落とした事例。Livewire / server / frontend JS の値の受け渡しは一般論として扱い、類似改修前に実装例の調査を先に行う。

---

## 📂 カテゴリ一覧

- 🚀 **[コア機能 (core-features)](./core-features/README.md)**: ワークフロー、権限管理、添付ファイル、自動リンクなど。
- 🎨 **[UI/UX改善 (ui-ux)](./ui-ux/README.md)**: カラム表示やナビゲーションなど、ユーザー体験の向上に関する改修。
- 🏗️ **[アーキテクチャ (architecture)](./architecture/README.md)**: マルチテナント、データベース、テスト戦略など、システム全体の設計。
- 🤖 **[LLM連携 (llm-integration)](./llm-integration/README.md)**: 大規模言語モデルとの連携機能。
- 👁️ **[VLM実装 (vlm-implementation)](./vlm-implementation/README.md)**: VLM/OCR機能の実装に関する過去の作業記録。
- 🔬 **[VLM/RAG統合 (vlm-rag-integration)](./vlm-rag-integration/README.md)**: VLMとRAG機能を統合し、高度な検索機能を実現するための作業記録。
- 🌍 **[環境構築・運用 (environment)](./environment/README.md)**: 開発環境、実行環境、運用に関する作業記録。
