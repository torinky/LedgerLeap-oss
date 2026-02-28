# LedgerLeap テストベストプラクティス

> **⚠️ このファイルは分割されました（2026-02-28）**
>
> ドキュメントが大きくなりすぎたため、トピックごとに `testing/` サブディレクトリへ分割しました。
> 以後は下記の各ファイルを参照してください。

---

## 📁 新しい場所: `docs/development/testing/`

| ファイル | 内容 |
|---|---|
| **[testing/README.md](./testing/README.md)** | **目次・クイックリファレンス（まずここを見る）** |
| [testing/01-fundamentals.md](./testing/01-fundamentals.md) | 基本原則・テスト環境設定・アンチパターン |
| [testing/02-database-traits.md](./testing/02-database-traits.md) | DB トレイルの使い分け・テナント・マイグレーション管理 |
| [testing/03-external-dependency-isolation.md](./testing/03-external-dependency-isolation.md) | 外部サービス依存テストの分離（Queue::fake()・RAGジョブ構造）|
| [testing/04-ledger-content-structure.md](./testing/04-ledger-content-structure.md) | Ledger の `content` データ構造とテスト時の注意点 |
| [testing/05-livewire.md](./testing/05-livewire.md) | Livewire コンポーネントのテストパターン |
| [testing/06-mcp-tools.md](./testing/06-mcp-tools.md) | MCP ツールのテストパターン |
| [testing/07-coverage.md](./testing/07-coverage.md) | カバレッジ測定・目標値・ツール |

---

## 更新履歴（旧ファイル分）

- **2026-02-28: `testing/` サブディレクトリへ分割**（Issue #74 対応）
  - `testing/03-external-dependency-isolation.md` を新規追加（Queue::fake() デフォルト化・4層テスト担保マップ）
- 2026-02-22: Livewire `#[Computed]` プロパティのカバレッジ取得手法、`CoversClass` の重要性、`latest_diff_id` パターンを追加（Issue #69対応）
- 2026-02-11: マイグレーション管理とトラブルシューティングセクションを追加
- 2026-02-09: Livewire 3 親子コンポーネントのテストベストプラクティスを追加（Issue #60対応）
- 2026-02-08: Sail環境におけるモデルイベントの発火挙動（touch() vs update()）を追加
- 2026-01-31: Phase 7 リアクティブ統合の知見を追加
- 2025-12-13: Phase 2（複製機能テスト）実装に基づく知見を追加
- 2025-11-11: Phase6実装に基づく知見を追加
- 2025-10-01: 初版作成

