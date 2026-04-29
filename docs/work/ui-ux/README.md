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

- **[台帳一覧検索ヘッダーのレスポンシブ/スクロール境界メモ](./ledger-list-redesign/2026-04-11_ledger-search-header-responsive-scroll-note.md)** ✅ 完了・skill 化: 検索入力の主役化、`sort_by` / `per_page` の中間幅横並び、sticky ヘッダーのスクロール遮蔽量を減らす余白調整を記録し、`search-header-responsive-layout` skill の evidence として昇格。

- **[デザインワークフロー再編メモ](./2026-04-18_design-workflow-reorganization-note.md)** ✅ 完了・skill 化: daisyUI を前提に共通基盤を縮約し、`title-block` / `form-layout` を独立スキルとして追加。`ledger-detail-header`・`search-header-responsive-layout`・`livewire-loading-ui` と合わせて再利用前提で束ねる。

- **[文字・アイコンサイズのレスポンシブ基準メモ](./2026-04-18_text-icon-size-responsiveness-note.md)** ✅ 完了・skill 化: PC で見にくい固定の小文字・小アイコンを避け、読める既定値やレスポンシブなサイズ段階へ寄せる方針を追加。

- **[台帳詳細 基本情報タブ リファイン計画](./2026-04-18_ledger-detail-basic-info-tab-refinement-plan.md)** ✅ Sprint 2 完了: `show.blade.php` の重複構造を整理し、`ledger-diff-viewer` と `workflow-status-card` の可読性を改善。`workflow-action-buttons` の current version 表示も維持し、関連 Feature テストを通過確認済み。

- **[Sticky Action Bar Footer Pattern](./2026-04-22_sticky-action-bar-footer-pattern.md)** ✅ 完了・skill 化: 共有フッターは `x-ledger.sticky-action-bar` を使い、`left` / `right` / `footer` の役割を固定する基準を `sticky-action-bar-footer-pattern` skill として昇格。

- **[Issue #161 / 文言・パンくず・補助コンポーネント整理](./2026-04-19_issue-161-breadcrumbs-supporting-components-plan.md)** ✅ 完了: `Top` の翻訳キー化、`show.blade.php` のメタ情報ラベル整理、`expandable-content` を共有ヘルパーとして維持する判断を反映済み。`ShowTest` も通過確認済み。

- **[台帳更新履歴スプリットペイン振り返り](./2026-04-20_ledger-history-split-pane-retrospective.md)** ✅ 完了: Mary UI カード化、翻訳キー化、loading の段階表示の修正、design instructions への反映、そして途中で誤った点の記録をまとめた retrospective。

- **[台帳リスト検索結果のタグ表示 / 列数切り替え レトロスペクティブ](./2026-04-26_ledger-search-tag-display-retrospective.md)** ✅ 完了: `#タグ` を本文検索に混ぜずにタグ表示へ分離し、検索結果パネルの列数をキーワード/タグの組み合わせで切り替えた記録。`SearchContextTest` / `RecordsTableQueryTest` / `IndexManagerIntegrationTest` で回帰を固定。

- **[File Inspector 検索条件の再オープン維持メモ](./2026-04-27_file-inspector-search-reopen-retrospective.md)** ✅ 完了: 詳細画面 full mode の `attachment-card` が検索語を payload に載せ忘れていた点を修正し、配線確認と再オープン確認を分けて回帰固定した記録。

- **[管理者お知らせバナーのバリデーション / 公開状態整合 振り返り](./admin-announcement-banner/2026-04-29_admin_announcement_banner_validation_status_retrospective.md)** ✅ 完了: 作成 / 編集フォームに必須入力と開始・終了日の整合を追加し、一覧と編集フォームの公開状態表示を `displayStatusKey()` に揃えた記録。後続の通知スタック修正と Livewire テストのログ確認も含めて、`skill-maintenance` と `ai-asset-maintenance-playbook` の定型フローを整備。

- **[システム管理者からの通知の編集権限 要件検討メモ](./admin-announcement-banner/2026-04-29_admin_announcement_edit_permission_requirements.md)** 📝 検討中: 閲覧は共通、変更系のみ権限化する前提で、なぜそうしたかの判断理由に加えて中規模見積りと 2 スプリント案まで整理した要件メモ。

- **[システム管理者からの通知の編集権限 振り返り](./admin-announcement-banner/2026-04-29_admin_announcement_edit_permission_retrospective.md)** ✅ 完了: 閲覧共通・変更系分離の権限設計、`AdminAnnouncementResource` の入口制御、通知センター回帰確認、そして issue 完了時の「技術要素 / 仕事の進め方 / 上書き指示」分解を記録した振り返り。

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
  - **[台帳一覧検索ヘッダーのレスポンシブ/スクロール境界メモ](./ledger-list-redesign/2026-04-11_ledger-search-header-responsive-scroll-note.md)** ✅ 完了・skill 化: sticky ヘッダーの余白圧縮、`sort_by` / `per_page` の横並び維持、下の結果一覧が見切れにくい境界を記録し、`search-header-responsive-layout` skill の evidence に昇格。

### ナビゲーション (navigation)
  テナント切り替えメニューなど、システムの主要なナビゲーション機能の改善に関するドキュメント。

- ### Filament テーマ / ダッシュボード
  - **[Issue #126: Filament テーマ UI スモークチェック記録](./2026-04-02_filament-theme-ui-smoke-report.md)** ✅ 完了: `theme.css` を Filament 5 / Tailwind v4 構成へ整理し、`DashboardLinksWidget` の色クラスを修正。`./vendor/bin/sail npm run build` 成功と簡易 UI チェックで dashboard / tree の見た目不具合が解消したことを確認。

- ### テーブルUI
  - **[台帳テーブルUI モダナイゼーション計画](./2025-10-12_table-ui-modernization-plan.md)** 📝 計画段階: アクション列・スコア列の最適化、統合ツールバーによるソート・フィルタUIの改善計画。

### 状態表示 / バッジ設計
  - **[Status / Count Display Badge Guidance](./2026-04-11_status-badge-pattern-guidance.md)** 📝 判断基準メモ: フッターやサマリーの短い状態・件数・メタ情報を badge + icon + tooltip へ寄せる基準と、text / chip / tag との使い分けを整理。

### 共有フッター / Sticky Action Bar
  - **[Sticky Action Bar Footer Pattern](./2026-04-22_sticky-action-bar-footer-pattern.md)** ✅ 完了・skill 化: 共有フッターは `x-ledger.sticky-action-bar` に寄せ、`left` / `right` / `footer` の slot 責務と badge-first summary を独立 skill に昇格。

### 文言設計 / コピーライティング
  - **[Text Writing Guidance for Buttons, Labels, and Descriptions](./2026-04-11_text-writing-guidance.md)** 📝 判断基準メモ: ボタンは action、ラベルは noun、説明は guidance、エラーは problem + next step で揃える。

### 文字・アイコンサイズ設計
  - **[Text and Icon Size Responsiveness Note](./2026-04-18_text-icon-size-responsiveness-note.md)** 📝 判断基準メモ: 文字とアイコンを固定小サイズに閉じ込めず、device/context に応じて読みやすく変化させる。

- ### ブラウザ固有の問題
  - **[Safariフリーズ問題](./2025-07-20_safari-freeze-debug-log.md)** ✅ 解決済み: 特定の画面でSafariブラウザがフリーズする問題に関する詳細なデバッグログと調査記録。
