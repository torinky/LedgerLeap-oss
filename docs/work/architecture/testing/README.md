# テスト (Testing)

このカテゴリには、LedgerLeapプロジェクトのテスト戦略、テストのパフォーマンス最適化、およびテスト基盤に関するドキュメントを格納しています。

---

### ✅ 現状サマリー (2026-04-05)

このカテゴリのドキュメントは、主に MCP（Managed Code Platform）連携機能のテストスイートを最適化し、実行時間を大幅に短縮した際の記録です。

主要な成果として、**`RefreshDatabaseWithTenant`** トレイトが開発・導入されました。これは、テストクラスごとにデータベースのマイグレーションを一度だけ実行し、各テストはトランケートによって高速にクリーンアップする仕組みです。これにより、**テスト実行時間が最大で70-88%削減**されるなど、開発効率の大幅な向上が達成されています。

この最適化アプローチは、MCP関連のテストに適用され、その有効性が確認されています。現在は `parallel + serial` の標準 CI を前提に、`phpunit.xml` / `phpunit.parallel.xml` / Composer scripts / PhpStorm Run Configuration の整合を保つ運用へ移行しています。

---

## 📚 ドキュメント一覧

- **[テスト実行ガイド（並列導入後の標準手順）](./2026-03-06_Parallel_Testing_Developer_Guide.md)**
  - `composer test:ci` を中心に、serial / parallel / external の標準コマンドをまとめた運用ガイド。

- **[Parallel Canary Tracking Template](./2026-03-08_Parallel_Canary_Tracking_Template.md)**
  - `parallel-canary.yml` の実績を記録し、Sprint 7 の完了判定に使うテンプレート。

- **[RefreshDatabaseWithTenant トレイト成功報告書](./2025-10-04_RefreshDatabaseWithTenant_Success_Report.md)**
  - テスト実行時間を最大88%削減した `RefreshDatabaseWithTenant` トレイトの開発と成果に関する詳細なレポート。

- **[MCP テスト最適化 拡大計画](./2025-10-04_MCP_Test_Optimization_Expansion_Plan.md)**
  - `RefreshDatabaseWithTenant` トレイトの成功を受け、最適化をプロジェクト全体のテストに拡大するための計画書。

- **[MCP テスト最適化計画（完了報告）](./2025-10-04_MCP_Test_Optimization_Plan.md)**
  - MCP関連テストの初期最適化計画とその完了報告。

- **[MCPテスト最適化 Phase 1 完了報告](./2025-10-04_MCP_Test_Phase1_Completion_Report.md)**
  - 最適化の第1フェーズの完了報告。

- **[MCPテスト最適化 Phase 2 完了報告](./2025-10-04_MCP_Test_Phase2_Completion_Report.md)**
  - 最適化の第2フェーズの完了報告。

- **[APIテスト最適化・修正作業結果](./2025-09-30_API_Test_Optimization_Results.md)**
  - APIテストで発生した複数の問題を修正し、パフォーマンスを改善した作業の記録。
