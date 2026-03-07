# テスト並列実行の導入計画 (Implementation Plan for Parallel Testing)

- 関連Issue: https://github.com/torinky/LedgerLeap/issues/81

## 概要
LedgerLeap のテスト実行時間を短縮するために、Laravel 12 の並列実行 (`php artisan test --parallel`) を段階導入する。
ただし、Mroonga 全文検索と `DatabaseMigrationsOnce` 系は直列実行を維持し、`Feature` テストでは `setUp()` 内で `tenancy()->initialize($tenant)` を必須とする。

## 目的とKPI
- 開発サイクル（Pint -> Test -> Commit）の待ち時間を削減する
- 並列化可能なテスト群の実行時間を 50% 以上短縮する
- CI のフレーク率を 1% 未満に維持する

## 前提制約（LedgerLeap 固有）
- Mroonga 全文検索テストは同時実行で干渉するため、直列グループに固定する
- `DatabaseMigrationsOnce` 利用テストは直列実行に固定する
- Feature テストの `setUp()` は毎回 `tenancy()->initialize($tenant)` を呼ぶ
- 並列導入中は `RefreshDatabaseWithTenant` の試験改修をいったんロールバックし、土台整備後に再実装する

## 現状の主要リスク
1. 中央DBの命名と並列トークン分離が不十分だと、プロセス間で同一DBに接続して競合する
2. `RefreshDatabaseWithTenant` の static 状態管理が複数系統だと、CI/並列時に tenant 初期化漏れが起きる
3. 直列対象の切り分けが曖昧だと、Mroonga 系が並列ジョブへ混入して不安定化する
4. CI を一気に切り替えると、失敗時の切り戻しが難しくなる

## 実施方針（順序固定）
1. 実行トポロジー確定（parallel と serial の境界を先に固定）
2. DB分離設計の確定（ワーカートークンごとのDB衝突回避）
3. GitHub Actions 短縮施策の導入（既存グループ互換を維持）
4. `RefreshDatabaseWithTenant` 再実装（状態機械を単純化）
5. CIカナリア導入と本番切替

## スプリント計画

### Sprint 1: 並列/直列の実行トポロジー確定（1-2日）
**目的**
- 「何を並列化し、何を直列に残すか」を先に固定し、手戻りを防ぐ

**作業項目**
- `tests/` を棚卸しし、次の3分類を定義する
  - `parallel-safe`
  - `serial-mroonga`
  - `serial-db-migrations`
- `database-migrations` グループ運用ルールを文書化する
- ローカル実行コマンドの標準形を決める

**Done条件**
- 対象テスト一覧と分類理由が計画書に記載されている
- 並列/直列の実行順序が明文化されている

**検証コマンド**
```bash
./vendor/bin/sail test --parallel --exclude-group=database-migrations,external
./vendor/bin/sail test --group=database-migrations
```

### Sprint 1 実施結果（2026-03-06）
- 棚卸し結果: `tests/**/*Test.php` は **220 ファイル**
- `serial-db-migrations`: **4 ファイル**
  - `tests/Feature/Api/SearchApiTest.php`
  - `tests/Feature/Search/LedgerFullTextSearchTest.php`
  - `tests/Feature/Search/SearchControllerAdditionalTest.php`
  - `tests/Unit/FolderTest.php`
- `serial-mroonga`: 追加の独立対象なし（Mroonga を使う統合テストは `serial-db-migrations` に統合）
- `parallel-safe`: 上記直列対象と `external` を除くテスト

**分類ルール（Sprint 1 確定）**
- `DatabaseMigrationsOnce` または `DatabaseMigrations` を使うテストは `#[Group('database-migrations')]` を必須とする
- Mroonga `MATCH() AGAINST()` の実動作検証を含むテストは `DatabaseMigrationsOnce + #[Group('database-migrations')]` を必須とする
- それ以外の標準 Feature/Unit は `parallel-safe` として扱う（`external` は別管理）

**実行順序（標準）**
```bash
./vendor/bin/sail test --parallel --exclude-group=database-migrations,external
./vendor/bin/sail test --group=database-migrations
./vendor/bin/sail test --group=external
```

### Sprint 2: DB分離設計 + CI短縮ベースライン計測（1-2日）
**目的**
- 並列ワーカー間の中央DB衝突を防ぎ、Actions短縮施策の評価基準を先に固定する

**作業項目**
- `config/database.php` / `phpunit.xml` / `.env.testing` の整合を確認する
- 並列時のDB命名規則（トークン単位）を設計し、直列ジョブ用DBと分離する
- 現行 Actions のジョブ時間（unit/feature/db-migrations）のベースラインを採取する
- 低リスク短縮施策（`concurrency` / `paths-ignore`）の導入可否を判定する

**Done条件**
- 並列ワーカー間でDB名が衝突しない設計が確定している
- 既存ジョブのベースライン計測値（P50/P95）が記録されている
- 互換性ガードを満たす短縮施策の適用可否が決定している

**検証コマンド**
```bash
./vendor/bin/sail artisan test --parallel --recreate-databases --exclude-group=database-migrations,external
./vendor/bin/sail artisan migrate --env=testing
```

### Sprint 2 DB命名規則（決定）
- `mysql_testing` は固定文字列を使わず `env('DB_DATABASE', 'ledgerleap_test')` を参照する
- ベースDB名は `ledgerleap_test` を維持し、並列時は Laravel のテストDB分離規則（token suffix）に委譲する
- 直列ジョブ（`db-migrations`）は従来どおりベースDBを使用し、グループ分離で干渉を回避する

### Sprint 2 直列ジョブ責務境界（確定）
- `db-migrations` ジョブの対象は `#[Group('database-migrations')]` を付与したテストのみ（Source of Truth）
- 現在の対象クラス: `Tests\Unit\FolderTest`, `Tests\Feature\Api\SearchApiTest`, `Tests\Feature\Search\LedgerFullTextSearchTest`, `Tests\Feature\Search\SearchControllerAdditionalTest`
- `unit` / `feature` は `--exclude-group=external --exclude-group=database-migrations` を維持し、責務重複を避ける
- 新規直列対象を追加する場合は、テスト側へ `#[Group('database-migrations')]` を付与して `db-migrations` に自動吸収する

### Sprint 2 検証ログ（2026-03-06）
- 実行コマンド: `./vendor/bin/sail artisan test --parallel --recreate-databases --testsuite=Unit --exclude-group=external,database-migrations --filter=SetPrimaryOrganizationTest`
- 結果: `2 passed`（parallel 8 processes）

### Sprint 2 Actionsベースライン（2026-03-06）
- 直近 20 run（workflow: `Laravel CI (PHPUnit / Pest)`）
  - workflow全体: P50 **26.79分**, P95 **34.98分**
- 代表成功run: `22740532870`
  - `Unit Tests`: **6.73分**
  - `Feature Tests`: **24.32分**
  - `DB Migrations Tests`: **4.20分**

### Sprint 3: Actionsワークフロー最適化（挙動不変） + トレイト再実装準備（1-2日）
**目的**
- テスト内容を変えずに Actions 実行時間を短縮し、トレイト改修前の土台を固める

**作業項目**
- `.github/workflows/phpunit.yml` の重複セットアップ削減方針を決める（reusable/composite 含む）
- `unit` / `feature` / `db-migrations` のジョブIDとグループ境界を維持したまま短縮する
- `RefreshDatabaseWithTenant` 改修の前提条件（初期化責務、CI分岐）を確定する
- 失敗時のロールバック手順を Runbook 化する

**Done条件**
- 既存グループ互換を維持したまま Actions の重複工程が削減されている
- 主要ジョブの実行コマンド互換（exclude/group）が維持されている
- `RefreshDatabaseWithTenant` 改修の前提と中止条件が文書化されている

**検証コマンド**
```bash
./vendor/bin/sail test --testsuite=Unit --exclude-group=external --exclude-group=database-migrations
./vendor/bin/sail test --testsuite=Feature --exclude-group=external --exclude-group=database-migrations
./vendor/bin/sail test --group=database-migrations
```

### Sprint 3 初期実施ログ（2026-03-06）
- `.github/workflows/phpunit.yml` に `concurrency` を追加し、同一 ref の古い実行を自動キャンセル
- `push` / `pull_request` に `paths-ignore`（`docs/**`, `**/*.md`）を追加し、ドキュメント変更のみの実行を抑制
- 既存グループ境界（`unit` / `feature` / `db-migrations` と `external` / `database-migrations`）は未変更

### Sprint 3 追加実施ログ（2026-03-06）
- `.github/workflows/phpunit.yml` の `unit` / `feature` ジョブで `--exclude-group=external,database-migrations` を廃止
- `--exclude-group=external --exclude-group=database-migrations` へ変更し、PHPUnit 12 非推奨警告を回避

### Sprint 3 互換確認ログ（2026-03-06）
- 実行コマンド: `./vendor/bin/sail test --testsuite=Unit --exclude-group=external --exclude-group=database-migrations --filter=SetPrimaryOrganizationTest`
- 結果: `2 passed`（非推奨警告なし）

### Sprint 4: `RefreshDatabaseWithTenant` 再実装 + CIカナリア導入（2日）
**目的**
- トレイトを安全に再実装し、カナリアで安定性と短縮効果を同時検証する

**作業項目**
- static状態の管理を 1 系統に統一する（プロセスキー単位）
- `setUp()` で必ず `tenancy()->initialize($tenant)` が成立する流れにする
- CIにカナリア並列ジョブ（対象限定）を追加し、既存直列ジョブと一定期間並走する
- 実行時間改善とフレーク率を記録し、しきい値で判定する

**Done条件**
- 並列実行時に tenant 初期化漏れが再現しない
- カナリアジョブが連続成功基準を満たす
- 切り戻し条件に従って自動/手動の戻し判断が可能

**検証コマンド**
```bash
./vendor/bin/sail test tests/Feature --parallel --exclude-group=database-migrations,external
./vendor/bin/sail test --group=database-migrations
```

### Sprint 4 再設計メモ（初版）
- 状態管理キーを `ParallelTesting::token() ?: 'global'` へ統一し、`globalDatabaseMigrated` / `sharedTenant` の単一共有依存を解消する
- `setUpRefreshDatabaseWithTenant()` は「DB準備」「tenant初期化」「共有データ作成」を責務分離し、`tenancy()->initialize($tenant)` を毎回保証する
- `TestDatabaseState::reset()` と `RefreshDatabaseWithTenant` の静的プロパティ対応を1対1で明示し、reset漏れを防ぐ
- CI分岐（`env('CI')`）でも tenant null 経路を作らないよう、tenant取得失敗時のフォールバックを明確化する

### Sprint 4 実装進捗ログ（2026-03-06）
- `tests/Traits/RefreshDatabaseWithTenant.php` をプロセスキー（`ParallelTesting::token() ?: 'global'`）単位の状態管理へ更新
- `migratedByProcess` / `sharedTenantsByProcess` を導入し、`setUpRefreshDatabaseWithTenant()` の migrate / tenant 初期化判定をプロセス単位へ変更
- `tests/Support/TestDatabaseState.php` に新規静的状態のリセットを追加

### Sprint 4 実装検証ログ（2026-03-06）
- `./vendor/bin/sail test tests/Feature/TenantIsolationTest.php` -> `5 passed`
- `./vendor/bin/sail test tests/Feature/PermissionCacheConsistencyTest.php` -> `4 passed`

### Sprint 5: 本番切替と運用固定（1日）
**目的**
- 並列運用と Actions 短縮施策をチーム標準として固定する

**作業項目**
- CIの標準ジョブを parallel + serial の2系統で確定する
- 開発者向け実行手順と CI運用ルールをドキュメントへ統合する
- KPI最終結果（時間短縮率/フレーク率）を Issue #81 に最終報告する

**Done条件**
- 開発/CIで同一の運用ルールを参照できる
- 失敗時の一次切り戻し手順が明文化されている
- Actions短縮効果がベースライン比較で確認できる

**検証コマンド**
```bash
./vendor/bin/sail pint
./vendor/bin/sail test
./vendor/bin/sail test --group=database-migrations
```

## 計測指標とロールバック条件
**計測指標（Issue #81 に週次記録）**
- workflow全体の所要時間: P50 / P95
- job別所要時間: `unit` / `feature` / `db-migrations`
- フレーク率: 再実行で成功した失敗割合

**ロールバック条件（いずれかで発動）**
- P95 が 3 連続実行で 10% 以上悪化
- フレーク率が 1% を超過
- `database-migrations` または Mroonga 関連の安定性が悪化

## 進捗管理ボード（Issue #81 同期）
運用方針: 進捗は `Not Started` / `In Progress` / `Done` / `Blocked` で管理し、更新時は本ドキュメントと Issue #81 を同時更新する。

### Sprint 1 管理タスク
| Task | Status |
|---|---|
| - [ ] `tests/` 棚卸し（対象一覧作成） | Done |
| - [ ] `parallel-safe` / `serial-mroonga` / `serial-db-migrations` 分類ルール確定 | Done |
| - [ ] `database-migrations` 付与基準を明文化 | Done |
| - [ ] 実行順序（parallel → serial）を手順化 | Done |
| - [ ] 分類結果サマリを Issue #81 に反映 | Done |

### Sprint 2 管理タスク
| Task | Status |
|---|---|
| - [ ] `config/database.php` / `phpunit.xml` / `.env.testing` の差分整理 | Done |
| - [ ] 並列ワーカーDB命名規則（token単位）確定 | Done |
| - [ ] 直列ジョブ用DBとの責務境界を定義 | Done |
| - [ ] Actions ベースライン（job別時間）を採取 | Done |
| - [ ] `concurrency` / `paths-ignore` 適用可否を判定 | Done |

### Sprint 3 管理タスク
| Task | Status |
|---|---|
| - [ ] Actions 重複セットアップ削減方針を確定 | Done |
| - [ ] `unit` / `feature` / `db-migrations` 互換を維持して短縮化 | Done |
| - [ ] `RefreshDatabaseWithTenant` 改修前提を確定 | Done |
| - [ ] ロールバックRunbookを整備 | Done |
| - [ ] 変更結果を Issue #81 に反映 | In Progress |

### Sprint 3 追加実施ログ（2026-03-06 第2回）
- `.github/actions/laravel-test-setup/action.yml` を composite action として新規作成
  - PHP/Node/Composer/npm + Laravel 初期設定 + DB準備 + テナント作成を集約
- `.github/workflows/phpunit.yml` の 3 ジョブのセットアップ重複を composite action 呼び出し 1 行に削減
  - 変更前: 約 130 行 × 3 ジョブの重複 / 変更後: セットアップを 1 箇所で管理
- `docs/work/architecture/testing/2026-03-06_Parallel_Testing_Rollback_Runbook.md` を新規作成
  - Level 1〜4 のロールバック手順とフレーク率記録フォーマットを文書化
- `.github/workflows/external-tests.yml` を新規作成（手動実行のみ、夜間スケジュール準備済み）

### Sprint 4 管理タスク
| Task | Status |
|---|---|
| - [ ] `RefreshDatabaseWithTenant` の状態管理を1系統化する設計確定 | Done |
| - [ ] Feature `setUp()` の tenant 初期化必須ルールをテスト側へ反映 | Done |
| - [ ] `DatabaseMigrationsOnce` の CI 環境対応（migrate:fresh スキップ） | Done |
| - [ ] CIカナリアparallelジョブ（対象限定）を追加 | Done |
| - [ ] フレーク率/実行時間の記録フォーマットで判定 | Done |
| - [ ] カナリア実績を Issue #81 に反映 | In Progress |

### Sprint 4 追加実施ログ（2026-03-06 第2回）
- `tests/Traits/DatabaseMigrationsOnce.php` に CI 環境分岐を追加（抜け漏れ修正）
  - CI=true: migrate:fresh / tenants:migrate をスキップし ci-test-tenant を再利用
  - ローカル: 従来通りクラス初回に migrate:fresh を実行
- `.github/workflows/parallel-canary.yml` を新規作成
  - `canary-unit-parallel`: Unit を `--parallel --recreate-databases` で実行（`continue-on-error: true`）
  - `canary-feature-parallel`: Feature/Livewire + Feature/Services サブセットを並列実行
  - カナリア失敗が main CI（phpunit.yml）をブロックしない設計

### Sprint 4 抜け漏れ対応（2026-03-06）
**発見された抜け漏れ（全3件）:**
1. `DatabaseMigrationsOnce` が CI 環境でも `migrate:fresh` を実行していた → **対応済み**
2. `external` グループの定期実行 workflow が計画に記載されていたが未実装 → **対応済み**
3. composite action による重複削減が「できない」とコメントされていたが実装可能だった → **対応済み**

### Sprint 5 管理タスク
| Task | Status |
|---|---|
| - [ ] CI標準を `parallel + serial` 2系統に確定 | In Progress |
| - [ ] 開発者向け標準コマンドを最終版へ統合 | Done |
| - [ ] 例外テスト（external / database-migrations）の運用固定 | Done |
| - [ ] KPI最終結果（時間短縮率/フレーク率）を確定 | In Progress（カナリア実績待ち） |
| - [ ] 最終報告を Issue #81 に反映 | In Progress |

### Sprint 6 管理タスク
| Task | Status |
|---|---|
| - [ ] `--parallel --processes=2 --filter=LedgerDiffViewerTest` で残存エラー詳細を確認 | Done |
| - [ ] `migrate:fresh` がワーカーDBに正しく接続できているか検証 | Done |
| - [ ] CI ワークフローにワーカーDB事前作成ステップを追加 | Not Started |
| - [ ] `switchToTestingDatabase()` / `getBaseDatabaseName()` 等の不要メソッドを削除 | In Progress |
| - [ ] ローカル並列テストの標準コマンドをドキュメント化 | Done |

### Sprint 5 追加修正ログ（2026-03-06）— ローカル並列実行バグ修正

**発生した問題（`FeatureParallelSubset` 並列実行で大量 QueryException）**

`LedgerDiffViewerTest` ほか `RefreshDatabaseWithTenant` を使う全テストが並列実行時に失敗。

エラー分類:
1. `1044 Access denied … drop database ledgerleap_test_test_1` — `LedgerDiffViewerTest` が `RefreshDatabase`（標準）を使っていたため、paratestがワーカーDB drop を試みるが権限なし
2. `1146 Table 'ledgerleap_test.migrations' doesn't exist` + `1050 Table already exists` — 複数ワーカーがベースDB `ledgerleap_test` に同時 `migrate:fresh` を競合実行
3. `1452 Cannot add or update a child row: folders … REFERENCES tenants` — ワーカーDBに切り替わらずベースDBへの挿入になり外部キー制約違反

**根本原因**

- Laravel の `TestDatabases::bootTestDatabase()` フックは標準トレイト（`RefreshDatabase` 等）のみを検知して `switchToDatabase()` を実行する。`RefreshDatabaseWithTenant` はカスタムトレイトのためDB自動切り替えが発動しない
- ワーカープロセス間で `$migratedByProcess` はプロセス内メモリのみで共有されないため、全ワーカーが `hasMigratedForCurrentProcess()=false` でスタートし同一ベースDBに競合
- `artisan('migrate')` 実行時に phpunit.xml の `$_SERVER['DB_DATABASE']` が config を上書きしてワーカーDB設定が無効化される

**修正内容**

`tests/Traits/RefreshDatabaseWithTenant.php`:

1. `refreshDatabase()` に並列実行時の分岐を追加
   - `ParallelTesting::token()` が非null（ローカル並列）: worker DB（`ledgerleap_test_test_{token}`）を作成・選択して `migrate:fresh` を実行
   - CI（`env('CI')`）: 従来通りスキップ
   - ローカル直列（token=null）: 従来通り `migrate:fresh`
2. `ensureCurrentTestingDatabaseConnection()` を追加
   - `setUpRefreshDatabaseWithTenant()` の冒頭で毎テスト `mysql_testing` を worker DB へ再選択
   - `migrate:fresh` 実行後も worker DB 接続を再保証
3. `switchToTestingDatabase()` は `$_SERVER['DB_DATABASE']` を書き換えず、接続設定と purge のみを担当
4. `CreateColumnInputTypeValidationTest` を `RefreshDatabaseWithTenant` に移行し、shared tenant 取得後に別 `Tenant::create()` で上書きしていた処理を削除

**検証結果**
```
# 残存エラー最小再現（修正後）
./vendor/bin/sail pest --parallel --processes=2 --filter="LedgerDiffViewerTest" \
  --testsuite=FeatureParallelSubset --display-errors
→ Tests: 10 passed (44 assertions)  Duration: 18.30s  Parallel: 2 processes

# CreateColumn 系の再現確認（修正後）
./vendor/bin/sail pest --parallel tests/Feature/Livewire/Ledger/CreateColumnInputTypeValidationTest.php --display-errors
→ Tests: 3 passed (17 assertions)  Duration: 14.60s  Parallel: 8 processes

# 現在のローカル並列カナリア標準コマンド
./vendor/bin/sail pest --parallel --testsuite=FeatureParallelSubset \
  --exclude-group=external --exclude-group=database-migrations
→ Tests: 2 skipped, 461 passed (1105 assertions)  Duration: 498.76s  Parallel: 8 processes
```

### Sprint 6 進捗ログ（2026-03-07）
- `LedgerDiffViewerTest` の外部キー違反を worker DB 再選択で解消
- `CreateColumnInputTypeValidationTest` の shared tenant 上書きパターンを解消
- tenant 初期化安定化の関連テスト差分を 31 ファイルで整理し、commit `b7f7d4c9` `test(parallel): stabilize tenant-aware feature tests` として記録
- ローカル並列の標準コマンドを `FeatureParallelSubset` ベースへ更新（`--recreate-databases` なし）
- 次の着手点は CI の worker DB 事前作成と不要メソッド削除

### Sprint 6: external 定期実行の安定運用（新規追加）
**目的**
外部コンテナ（VLM/LDAP/RAG等）が必要なテストを定期実行し、外部依存テストの継続的検証を確立する。

**前提**: self-hosted runner または GitHub Actions GPU ランナーの整備が完了していること。

**管理タスク**
| Task | Status |
|---|---|
| - [ ] self-hosted runner / GPU ランナーの調達方針確定 | Not Started |
| - [ ] `external-tests.yml` の `schedule` を有効化 | Not Started |
| - [ ] external テストのフレーク率記録を開始 | Not Started |



## GitHub Actions 既存グループ互換性ガード
既存 CI ジョブ（`unit` / `feature` / `db-migrations`）への非互換を避けるため、以下を Sprint 2 以降の変更前提とする。

- `database-migrations` と `external` のグループ名は変更しない（rename / 廃止をしない）
- `unit` / `feature` ジョブは `--exclude-group=external --exclude-group=database-migrations` を維持する
- `db-migrations` ジョブは `--group=database-migrations` を維持する
- 新規に直列対象を追加する場合は `#[Group('database-migrations')]` を付与して既存ジョブへ吸収する
- 新規に外部依存テストを追加する場合は `#[Group('external')]` を付与して既存除外ルールへ吸収する

**影響確認チェック（変更ごとに実施）**
```bash
./vendor/bin/sail test --testsuite=Unit --exclude-group=external --exclude-group=database-migrations
./vendor/bin/sail test --testsuite=Feature --exclude-group=external --exclude-group=database-migrations
./vendor/bin/sail test --group=database-migrations
```
