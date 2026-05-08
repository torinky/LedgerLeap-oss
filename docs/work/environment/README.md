# 環境構築 (Environment)

このカテゴリには、開発環境の構築、自動化、および改善に関する作業記録やスクリプトの実装計画を格納しています。

---

## 📚 ドキュメント一覧

### Storage Permission Fix Retrospective（2026年5月8日）

- **[権限修正の振り返り](./2026-05-08_storage_permission_fix_retrospective.md)** - runtime storage subtree の exact-path 修正と検証メモ。
  - `storage/framework/testing/disks/public/tenants` の root 所有を `namei -l` で特定
  - exact-path の `chown` / `chmod` で最小修正
  - `touch` / `rm` の write probe で確認

### Docker Compose構成リファクタリング（2025年11月2日）

- **[リファクタリング計画](./2025-11-02_docker-compose-refactoring-plan.md)** - 設計・計画ドキュメント
  - 背景と目的、現状の課題分析
  - リファクタリング方針とアーキテクチャ設計
  - Phase 1-3の詳細な実装計画
  - テスト戦略とリスク対策

- **[リファクタリング実装記録](./2025-11-02_docker-compose-refactoring-implementation.md)** - 実装記録（本日作成）
  - Phase 1-3の実施内容と結果
  - 発生した問題と解決方法
  - ビルド・起動テスト結果
  - 成果物一覧と今後の課題

### 環境構築スクリプト（2025年8月24日）

- **[環境構築スクリプト実装記録](../../development/environment-setup.md)**: `bin/setup.sh` の実装にあたり、DB権限の自動化やDockerビルドエラーの解決など、開発環境のセットアップを自動化する過程の記録。