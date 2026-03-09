# テストドキュメント 目次

**最終更新:** 2026-03-08

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

## ローカル実行の推奨パターン（CI準拠）

PhpStorm やローカル terminal では、**全件 `--parallel` を直接実行せず**、CI と同じ単位で分けて実行する。

### まず全体を通したいときの入口

#### PhpStorm から実行する場合（通常はこちら）

- `Pest: Full (phpunit.xml)`
- 実測では `phpunit.xml` ベースの全体実行がローカルで完走しており、**普段の全体確認入口として利用可能**
- Pest のテストツリーや失敗箇所の追跡もしやすいため、日常運用ではこちらを優先する

#### terminal / Composer から実行する場合

```bash
./vendor/bin/sail composer test:full
```

- `test:full` は `test:prepare:local` → `test:ci:unit` → `test:ci:feature` → `test:ci:db-migrations` → `test:external` を順番に実行する
- ローカルで利用可能な外部コンテナがあれば、その範囲の `external` テストも含めて確認できる
- **カバレッジは取らない**が、CI に近い分割順で再現したいときの補助入口として使える

### 通常確認

```bash
./vendor/bin/sail composer test:ci:unit
./vendor/bin/sail composer test:ci:feature
./vendor/bin/sail composer test:ci:db-migrations
```

- `test:ci:unit`: Unit + `external` / `database-migrations` 除外
- `test:ci:feature`: Feature + `external` / `database-migrations` 除外
- `test:ci:db-migrations`: 全文検索・`DatabaseMigrationsOnce` 系を分離実行

### 並列 canary 相当

```bash
./vendor/bin/sail composer test:canary:unit
./vendor/bin/sail composer test:canary:feature
```

- `test:canary:unit`: CI の parallel unit 相当
- `test:canary:feature`: `FeatureParallelSubset` のみ並列実行

### 外部コンテナを使う確認（ローカル専用）

```bash
./vendor/bin/sail composer test:external
```

- 対象: `#[Group('external')]` が付いたテスト
- 例: 実 LDAP、実 VLM、RAG 性能確認
- 通常 CI / canary では除外される
- Embedding / VLM / LDAP などのコンテナがローカルで利用可能なときだけ実行する

### PhpStorm 設定の考え方

- PHP interpreter は `docker-compose.yml` の `laravel` service に合わせる
- 通常実行は `phpunit.xml`、並列 canary は `phpunit.parallel.xml` を使う
- **日常の全体確認は `Pest: Full (phpunit.xml)` を優先**する
- `Full: All Tests` は `composer test:full` 相当の補助入口で、`test:prepare:local` や `test:external` を含む分割実行を再現したいときに使う
- `Coverage: Full` は HTML レポート生成用の入口
- 実運用としては、**Composer 側の主用途は coverage と scripted な再現実行**と考えてよい
- Run Configuration は上記 scripts と同じ分割で作る
- `database-migrations` を通常 Feature 実行や全件 parallel に混ぜない
- 共有 Run Configuration は `.idea/runConfigurations/` にコミットしてあるため、Composer / PHP interpreter を設定するとそのまま使える
