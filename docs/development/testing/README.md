# テストドキュメント 目次

**最終更新:** 2026-02-28

LedgerLeap のテスト設計・実装に関するドキュメントをトピック別に分割しています。
新しいテストを書く前に、該当するドキュメントを参照してください。

---

## ドキュメント一覧

| ファイル | 内容 | 主な対象読者 |
|---|---|---|
| [01-fundamentals.md](./01-fundamentals.md) | 基本原則・テスト環境設定・アンチパターン | 全員 |
| [02-database-traits.md](./02-database-traits.md) | DBトレイトの使い分け・テナント・マイグレーション管理 | 全員 |
| [03-external-dependency-isolation.md](./03-external-dependency-isolation.md) | 外部サービス依存テストの分離（Queue::fake()・RAGジョブ構造） | Ledger/AttachedFileを扱う場合 |
| [04-ledger-content-structure.md](./04-ledger-content-structure.md) | Ledgerの`content`データ構造とテスト時の注意点 | Ledger操作のテストを書く場合 |
| [05-livewire.md](./05-livewire.md) | Livewireコンポーネントのテストパターン | LivewireコンポーネントのUI実装 |
| [06-mcp-tools.md](./06-mcp-tools.md) | MCPツールのテストパターン | MCPツール実装 |
| [07-coverage.md](./07-coverage.md) | カバレッジ測定・目標値・ツール | カバレッジ改善時 |

---

## クイックリファレンス：何をテストするときにどこを読むか

### Ledger や AttachedFile のファクトリを使うとき
→ **[03-external-dependency-isolation.md](./03-external-dependency-isolation.md)**
`Queue::fake()` を忘れると CI が60秒タイムアウトする。

### 全文検索（Mroonga）のテストを書くとき
→ **[02-database-traits.md](./02-database-traits.md)**
`DatabaseMigrationsOnce` トレイトを使うこと。`RefreshDatabase` では動作しない。

### Livewire 親子コンポーネントのテストを書くとき
→ **[05-livewire.md](./05-livewire.md)**
`#[Reactive]` や `wire:loading.remove.delay` の挙動に注意。

### テストが CI でだけ失敗するとき
→ **[03-external-dependency-isolation.md](./03-external-dependency-isolation.md)** および
→ **[.github/skills/ci-failure-investigation/SKILL.md](../../../.github/skills/ci-failure-investigation/SKILL.md)**

### カバレッジが 0% になるとき
→ **[07-coverage.md](./07-coverage.md)**
`#[CoversClass]` の付与と `instance()` 経由の呼び出しを確認すること。

---

## スキル（定型ワークフロー）

より具体的な操作手順は `.github/skills/` のスキルを参照：

| スキル | 用途 |
|---|---|
| [test-external-dependency-isolation](../../../.github/skills/test-external-dependency-isolation/SKILL.md) | 外部サービス依存テストのチェックリスト |
| [database-migrations-test-optimization](../../../.github/skills/database-migrations-test-optimization/SKILL.md) | DBMigrationsトレイルの選択と高速化 |
| [ci-failure-investigation](../../../.github/skills/ci-failure-investigation/SKILL.md) | CI失敗ログの調査手順 |
| [github-issue-workflow](../../../.github/skills/github-issue-workflow/SKILL.md) | イシューの更新・報告 |

