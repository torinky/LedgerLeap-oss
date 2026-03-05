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
3. `RefreshDatabaseWithTenant` 再実装（状態機械を単純化）
4. CIカナリア導入
5. CI本番切替と運用固定

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

### Sprint 2: DB分離設計と設定整合（1-2日）
**目的**
- 並列ワーカー間の中央DB衝突を防ぐ

**作業項目**
- `config/database.php` / `phpunit.xml` / `.env.testing` の整合を確認する
- 並列時のDB命名規則（トークン単位）を設計し、直列ジョブ用DBと分離する
- 必要なDB権限と初期化順序を定義する

**Done条件**
- 並列ワーカー間でDB名が衝突しない設計が確定している
- 直列ジョブとのDB責務境界が文書化されている

**検証コマンド**
```bash
./vendor/bin/sail artisan test --parallel --recreate-databases --exclude-group=database-migrations,external
./vendor/bin/sail artisan migrate --env=testing
```

### Sprint 3: `RefreshDatabaseWithTenant` 再実装（2日）
**目的**
- ロールバック可能性を維持したまま、tenant初期化と状態管理を安定化する

**作業項目**
- static状態の管理を 1 系統に統一する（プロセスキー単位）
- `setUp()` で必ず `tenancy()->initialize($tenant)` が成立する流れにする
- `migrate:fresh` / tenant migrate の責務を明確化し、CI分岐でも null 初期化を防ぐ
- 失敗時は即座に旧実装へ戻せる差分粒度で改修する

**Done条件**
- `RefreshDatabaseWithTenant` の初期化手順が図示または手順化されている
- 並列実行時に tenant 初期化漏れが再現しない
- ロールバック手順が文書に明記されている

**検証コマンド**
```bash
./vendor/bin/sail test tests/Feature --parallel --exclude-group=database-migrations,external
./vendor/bin/sail test tests/Feature --filter=Tenant
```

### Sprint 4: CIカナリア導入（1-2日）
**目的**
- 本番切替前に失敗モードとフレークを観測する

**作業項目**
- CIにカナリア並列ジョブ（対象限定）を追加する
- 既存直列ジョブと一定期間並走する
- 成功判定（連続成功回数、実行時間、フレーク率）を固定する

**Done条件**
- カナリアジョブが連続成功基準を満たす
- 実行時間の改善値が記録される
- 切り戻し手順がCI定義とドキュメントに反映される

**検証コマンド**
```bash
./vendor/bin/sail test --parallel --exclude-group=database-migrations,external
./vendor/bin/sail test --group=database-migrations
```

### Sprint 5: 本番切替と運用固定（1日）
**目的**
- 並列運用をチーム標準化し、逸脱時の復旧を容易にする

**作業項目**
- CIの標準ジョブを parallel + serial の2系統に確定する
- 開発者向け実行手順をドキュメントへ統合する
- 例外テスト（外部依存など）のグルーピング運用を確定する

**Done条件**
- 開発/CIで同一の運用ルールを参照できる
- 失敗時の一次切り戻し手順が明文化されている

**検証コマンド**
```bash
./vendor/bin/sail pint
./vendor/bin/sail test
./vendor/bin/sail test --group=database-migrations
```

## 実行ルール（暫定）
- 日常開発は並列可能群を優先して回す
- Mroonga/`DatabaseMigrationsOnce` 群は必ず直列ジョブで回す
- `RefreshDatabaseWithTenant` の変更は Sprint 3 以前に先行しない
- すべての Feature テストで tenant 初期化を明示する

## 受け入れ基準（最終）
- 並列可能群で 50% 以上の時間短縮が確認できる
- 直列対象の失敗率が導入前後で悪化しない
- CIが parallel/serial 分離で安定運用できる
- トレイト改修がロールバック可能な形で管理されている

## 進捗管理ボード（Issue #81 同期）
運用方針: 進捗は `Not Started` / `In Progress` / `Done` / `Blocked` で管理し、更新時は本ドキュメントと Issue #81 を同時更新する。

### Sprint 1 管理タスク
| Task | Status |
|---|---|
| - [ ] `tests/` 棚卸し（対象一覧作成） | In Progress |
| - [ ] `parallel-safe` / `serial-mroonga` / `serial-db-migrations` 分類ルール確定 | Not Started |
| - [ ] `database-migrations` 付与基準を明文化 | Not Started |
| - [ ] 実行順序（parallel → serial）を手順化 | Not Started |
| - [ ] 分類結果サマリを Issue #81 に反映 | Not Started |

### Sprint 2 管理タスク
| Task | Status |
|---|---|
| - [ ] `config/database.php` / `phpunit.xml` / `.env.testing` の差分整理 | Not Started |
| - [ ] 並列ワーカーDB命名規則（token単位）確定 | Not Started |
| - [ ] 直列ジョブ用DBとの責務境界を定義 | Not Started |
| - [ ] DB権限要件と初期化順序を文書化 | Not Started |
| - [ ] 設定整合の結果を Issue #81 に反映 | Not Started |

### Sprint 3 管理タスク
| Task | Status |
|---|---|
| - [ ] `RefreshDatabaseWithTenant` の状態管理を1系統化する設計確定 | Not Started |
| - [ ] Feature `setUp()` の tenant 初期化必須ルールをテスト側へ反映 | Not Started |
| - [ ] `migrate:fresh` と tenant migrate の責務境界を再定義 | Not Started |
| - [ ] ロールバック手順を確定しドキュメント化 | Not Started |
| - [ ] 初期化シーケンスを Issue #81 に反映 | Not Started |

### Sprint 4 管理タスク
| Task | Status |
|---|---|
| - [ ] CIカナリアparallelジョブ（対象限定）を追加 | Not Started |
| - [ ] serialジョブ並走期間と判定条件を確定 | Not Started |
| - [ ] フレーク率/実行時間の記録フォーマットを固定 | Not Started |
| - [ ] 切り戻し条件を Runbook 化 | Not Started |
| - [ ] カナリア実績を Issue #81 に反映 | Not Started |

### Sprint 5 管理タスク
| Task | Status |
|---|---|
| - [ ] CI標準を `parallel + serial` 2系統に確定 | Not Started |
| - [ ] 開発者向け標準コマンドを最終版へ統合 | Not Started |
| - [ ] 例外テスト（external / database-migrations）の運用固定 | Not Started |
| - [ ] KPI最終結果（時間短縮率/フレーク率）を確定 | Not Started |
| - [ ] 最終報告を Issue #81 に反映 | Not Started |
