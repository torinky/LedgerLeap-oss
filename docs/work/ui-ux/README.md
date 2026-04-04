# UI/UX改善 (UI/UX Improvements)

このカテゴリには、ユーザー体験の向上を目的としたUI/UXの改修に関する実装計画や作業ログを格納しています。

---

### 📊 現状サマリー (2025-10-12)

#### 進行中のプロジェクト

- **[台帳テーブルUI モダナイゼーション](./2025-10-12_table-ui-modernization-plan.md)** 📝 計画段階
  - Phase 1でスコアリング機能実装後の新たなUI課題に対応
  - 統合ツールバー方式による ソート・フィルタUIの改善
  - ホバーオーバーレイによるアクション表示で業務データ列を最大化
  - 見積工数: 5.4日（Phase 1-3）

- **[相関コンポーネントのリアクティブ統合 (Phase 7)](./2026-01-31_index-reactive-integration-plan.md)** 🚀 実装・是正中
  - `IndexManager` による親子リアクティブ化とローディングUXの完全化
  - **[Phase 7 是正レポート](./2026-01-31_phase7-remediation-report.md)**: フォルダ遷移スケルトンの不一致解消、Feature Testのリグレッション修正記録

- **[台帳リスト画面のフロントエンドパフォーマンス改善 (Issue #59)](./2026-02-08_issue-59-phase2-investigation-report.md)** 🔍 調査完了・対応方針提案済み
  - Phase 2: Alpine.js 初期化コストの深掘り調査
  - 添付ファイル表示の「もっと見る」UIによる遅延問題の特定
  - 遅延初期化とCSS制御による最適化方針の提案

- **[Issue #133 / 台帳一覧→詳細の highlight query 継承 振り返り](./ledger-list-redesign/2026-04-04_issue-133_highlight-query-handoff-retrospective.md)** ✅ 完了: 一覧→詳細リンクと自動リンク `/l/{query}` の両方で `highlight` を復元し、canonical URL 方針との整合を確認。`RecordsTableQueryTest` と `CrossReferenceTest` に href 検証を追加し、過去実装との差分を記録。

- **[常時モニタ指標と回帰検知の整理 (Issue #114)](./2026-03-21_issue-114_performance_monitoring_and_regression_detection_report.md)** ✅ 実装・運用整理完了
  - 常時モニタと調査用メトリクスの分離
  - 閾値アラートと `performance` ログチャネルの整備
  - 日常運用向けの確認手順を `docs/operations/ledger-records-performance-monitoring.md` に分離

- **[Issue #128: Filament UI polish retrospective](./2026-04-04_issue-128_filament-ui-retrospective.md)** ✅ 完了: 移行後 UI 差分のうち、翻訳・権限メニュー・topbar 余白・hover 背景・編集画面幅の戻し方を記録。`PanelsRenderHook::GLOBAL_SEARCH_AFTER` での差し込み、`lang/ja/user.php` の補完、`maxContentWidth(Width::Full)` の採用判断を future maintainer 向けに整理。

#### 完了済み (2025-10-11)

以前のドキュメントは、すべて実装・解決済みです。
各ドキュメントに記載されている計画や課題解決策は、現在のコードベースに正しく反映されており、実装との間に大きな齟齬がないことを確認済みです。

---

## 📚 サブカテゴリ

### カラム管理 (column-management)
  台帳定義のカラム編集UIや、台帳の表示・入力フォームにおけるカラムの表示方法（グループ化、表示レベル制御など）の改善に関するドキュメント。

### 台帳リストデザイン再設計 (ledger-list-redesign)
  - **[フォルダツリー固定表示・深い階層対応 改善提案 (2026-02-23)](./ledger-list-redesign/2026-02-23_folder-tree-sticky-improvement-plan.md)** 📝 提案段階: 広い画面でのスクロール時ツリー消失問題の解消と、深い階層・多ノード時のUX向上に向けた4つの改善提案。
    - **[台帳一覧URL正規化 計画書 (2026-03-29)](./ledger-list-redesign/2026-03-29_ledger-list-url-normalization-plan.md)** 🚧 追加スプリント継続中: 共有URLの canonical 化、`l` / `f` / `cf` の短縮クエリ整理、スプリント分解とテスト観点の整理を完了し、FileInspector 連携の追加スプリントを追記。

### ナビゲーション (navigation)
  テナント切り替えメニューなど、システムの主要なナビゲーション機能の改善に関するドキュメント。

- ### Filament テーマ / ダッシュボード
  - **[Issue #126: Filament テーマ UI スモークチェック記録](./2026-04-02_filament-theme-ui-smoke-report.md)** ✅ 完了: `theme.css` を Filament 5 / Tailwind v4 構成へ整理し、`DashboardLinksWidget` の色クラスを修正。`./vendor/bin/sail npm run build` 成功と簡易 UI チェックで dashboard / tree の見た目不具合が解消したことを確認。

- ### テーブルUI
  - **[台帳テーブルUI モダナイゼーション計画](./2025-10-12_table-ui-modernization-plan.md)** 📝 計画段階: アクション列・スコア列の最適化、統合ツールバーによるソート・フィルタUIの改善計画。

- ### ブラウザ固有の問題
  - **[Safariフリーズ問題](./2025-07-20_safari-freeze-debug-log.md)** ✅ 解決済み: 特定の画面でSafariブラウザがフリーズする問題に関する詳細なデバッグログと調査記録。