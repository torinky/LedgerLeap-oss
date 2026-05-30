# テストドキュメント 目次

**最終更新:** 2026-04-05

LedgerLeap のテスト設計・実装に関するドキュメントをトピック別に分割しています。
新しいテストを書く前に、該当するドキュメントを参照してください。

> [!IMPORTANT]
> LedgerLeap のテスト実行は **Laravel Sail / Docker-based PhpStorm interpreter 前提** です。
> `php artisan test` や `./vendor/bin/pest` の host 実行は禁止です。必ず `./vendor/bin/sail test` または `./vendor/bin/sail pest` を使ってください。

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
| [08-vlm-cache-regression.md](./08-vlm-cache-regression.md) | VLM のキャッシュ判定・オフライン起動の回帰テスト | `docker/paddle/unified_api.py` を触る場合 |

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
→ **[03-external-dependency-isolation.md](./03-external-dependency-isolation.md)**

### VLM の起動前キャッシュ判定や offline mode を確認したいとき
→ **[08-vlm-cache-regression.md](./08-vlm-cache-regression.md)**
`docker/paddle/unified_api.py` の `_is_backend_cached()` / `_resolve_offline_mode()` を対象にする。

### カバレッジが 0% になるとき
→ **[07-coverage.md](./07-coverage.md)**
`#[CoversClass]` の付与と `instance()` 経由の呼び出しを確認すること。

---

## テスト分類の方針（要約）

- `parallel-safe`: Unit / Livewire / Services のうち、`external` や `database-migrations` に依存しないもの
- `serial-remainder`: `FeatureSerial` に残す、まだ並列化しない残余
- `database-migrations`: Mroonga / `DatabaseMigrationsOnce` 系
- `external`: LDAP / VLM / Embedding など外部コンテナ依存
- `vlm-cache-regression`: `docker/paddle/unified_api.py` の純粋ロジック回帰（Python `unittest`、外部コンテナ不要）

**入口の対応**
- `test:ci:unit` / `test:ci:feature` → `parallel-safe`
- `test:ci:feature:serial` → `serial-remainder`
- `test:ci:db-migrations` → `database-migrations`
- `test:external` → `external`
- `python3 -m unittest discover -s docker/paddle/tests -p "test_*.py"` → `vlm-cache-regression`

---

## 関連ガイド（定型ワークフロー）

より具体的な操作手順は、次の公開ドキュメントを参照：

| ガイド | 用途 |
|---|---|
| [03-external-dependency-isolation.md](./03-external-dependency-isolation.md) | 外部サービス依存テストのチェックリスト |
| [02-database-traits.md](./02-database-traits.md) | DB トレイトの選択と Mroonga 系の運用 |
| [07-coverage.md](./07-coverage.md) | カバレッジ低下時の確認手順 |

## ローカル実行の推奨パターン（CI準拠）

PhpStorm やローカル terminal では、**全件 `--parallel` を直接実行せず**、CI と同じ単位で分けて実行する。

### まず全体を通したいときの入口

#### PhpStorm から実行する場合（通常はこちら）

- `Pest: Main (phpunit.serial.xml)`
- 実測では `phpunit.serial.xml` ベースの全体実行がローカルで完走しており、**普段の全体確認入口として利用可能**
- Pest のテストツリーや失敗箇所の追跡もしやすいため、日常運用ではこちらを優先する

#### terminal / Composer から実行する場合

```bash
./vendor/bin/sail composer test:full
```

- host の PHP から `composer test:*` / `php artisan test` を直接実行しないこと

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

### 並列確認

```bash
./vendor/bin/sail composer test:ci:unit
./vendor/bin/sail composer test:ci:feature
```

- `test:ci:unit`: CI の parallel unit 相当
- `test:ci:feature`: `FeatureParallelSubset` のみ並列実行

### 外部コンテナを使う確認（ローカル専用）

```bash
./vendor/bin/sail composer test:external
```

- 対象: `#[Group('external')]` が付いたテスト
- 例: 実 LDAP、実 VLM、RAG 性能確認
- 通常 CI / canary では除外される
- Embedding / VLM / LDAP などのコンテナがローカルで利用可能なときだけ実行する

### VLM キャッシュ判定の回帰確認（ローカル / CI 共通）

```bash
python3 -m unittest discover -s docker/paddle/tests -p "test_*.py"
```

- `docker/paddle/unified_api.py` のキャッシュマーカー変更や `VLM_OFFLINE` の挙動変更時に実行する
- FastAPI / 実 OCR / 実コンテナを使わないため高速に回せる
- CI では `.github/workflows/vlm-cache-regression.yml` で自動実行する

### PhpStorm 設定の考え方

- PHP interpreter は `docker-compose.yml` の `laravel` service に合わせる
- ローカルの system PHP / Homebrew PHP を test runner に使わない
- 通常実行は `phpunit.xml`、並列 canary は `phpunit.parallel.xml` を使う
- **日常の全体確認は `Pest: Main (phpunit.serial.xml)` を優先**する
- `Full: All Tests` は `composer test:full` 相当の補助入口で、`test:prepare:local` や `test:external` を含む分割実行を再現したいときに使う
- `Coverage: Full` は HTML レポート生成用の入口
- 実運用としては、**Composer 側の主用途は coverage と scripted な再現実行**と考えてよい
- 直近の `test:coverage` 調査記録: [`docs/work/testing/2026-03-21_test-coverage-db-recovery-and-tenancy-guidelines.md`](../../../docs/work/testing/2026-03-21_test-coverage-db-recovery-and-tenancy-guidelines.md)
- Run Configuration は上記 scripts と同じ分割で作る
- `database-migrations` を通常 Feature 実行や全件 parallel に混ぜない
- 共有 Run Configuration は `.idea/runConfigurations/` にコミットしてあるため、Composer / PHP interpreter を設定するとそのまま使える
