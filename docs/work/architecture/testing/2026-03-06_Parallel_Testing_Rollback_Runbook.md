# 並列テスト導入 ロールバックRunbook

- 関連Issue: https://github.com/torinky/LedgerLeap/issues/81
- 対象Sprint: Sprint 3 / Sprint 4 / Sprint 5

## ロールバックの発動条件

以下のいずれかを満たした場合、対応するロールバック手順を実施する。

| 条件 | 閾値 | 確認頻度 |
|------|------|----------|
| workflow 全体 P95 の悪化 | 3 連続実行で **10% 以上** 悪化 | 各 Push/PR |
| フレーク率 | **1% 超** （再実行で成功した失敗割合） | 週次 |
| `database-migrations` 安定性 | 失敗率が導入前より悪化 | 各 Push/PR |
| Mroonga 関連テストの干渉 | 1 件でも再現性のある失敗 | 各 Push/PR |

---

## レベル別ロールバック手順

### Level 1: カナリアジョブのみ失敗（main CI は正常）

**判定**: `parallel-canary.yml` の `continue-on-error: true` により main CI がブロックされていない場合

**対応**:
1. `parallel-canary.yml` を `workflow_dispatch` のみに絞って自動実行を停止する
   ```yaml
   # parallel-canary.yml の on: を変更
   on:
     workflow_dispatch:
   ```
2. 失敗ログを確認し、フレーク or 設計欠陥かを判定する
3. Issue #81 にログを追記して再設計を検討する

**コマンド**（ローカルでの再現確認）:
```bash
./vendor/bin/sail test --parallel --recreate-databases \
  --testsuite=Unit \
  --exclude-group=external \
  --exclude-group=database-migrations \
  --filter="<失敗したテスト名>"
```

---

### Level 2: `phpunit.yml` の既存ジョブが不安定化

**判定**: `unit` / `feature` / `db-migrations` ジョブのいずれかが P95 で 10% 以上悪化

**対応**:
1. 最後に変更した `.github/workflows/phpunit.yml` のコミットを特定する
   ```bash
   bash -c "cd /var/www/html && git log --oneline .github/workflows/phpunit.yml | head -5"
   ```
2. composite action 変更が原因の場合は `.github/actions/laravel-test-setup/action.yml` を前バージョンに戻す
   ```bash
   bash -c "cd /var/www/html && git revert <commit-hash> --no-edit"
   ```
3. revert が困難な場合は `phpunit.yml` をセットアップステップ展開形式に一時戻す:
   - Issue #81 の「关連コミット」から展開前の `phpunit.yml` を参照して手動で復元する

---

### Level 3: `RefreshDatabaseWithTenant` / `DatabaseMigrationsOnce` の改修によるテスト失敗

**判定**: Feature テスト群でテナント初期化漏れや DB 状態汚染が発生

**対応**:
1. 問題のテストを特定する
   ```bash
   ./vendor/bin/sail test tests/Feature/<失敗ディレクトリ> --display-errors
   ```
2. `tests/Traits/RefreshDatabaseWithTenant.php` の `setUpRefreshDatabaseWithTenant()` でプロセスキー取得が正しく動作しているか確認する
   ```php
   // Sail コンテナ内で確認
   php artisan tinker
   > \Illuminate\Support\Facades\ParallelTesting::token()
   ```
3. `TestDatabaseState::reset()` が全静的プロパティをリセットしているか確認する
4. 即時緩和: 問題のテストクラスの `setUp()` に明示的な tenant 初期化を追加する
   ```php
   tenancy()->initialize(static::getSharedTenantForCurrentProcess());
   ```
5. 根本修正が必要な場合はトレイトを `git checkout HEAD~1 -- tests/Traits/RefreshDatabaseWithTenant.php` で戻す

---

### Level 4: CI 全体の完全ロールバック

**判定**: 上記 Level 1-3 での対処が困難で CI が長期停止する場合

**対応**:
1. `parallel-canary.yml` を削除または無効化する
   ```bash
   bash -c "cd /var/www/html && git rm .github/workflows/parallel-canary.yml"
   ```
2. `phpunit.yml` を Sprint 2 完了時点（コミット `6df40d8f`）に戻す
   ```bash
   bash -c "cd /var/www/html && git checkout 6df40d8f -- .github/workflows/phpunit.yml"
   bash -c "cd /var/www/html && git rm -f .github/actions/laravel-test-setup/action.yml"
   ```
3. `tests/Traits/RefreshDatabaseWithTenant.php` を Sprint 4 実装前に戻す
   ```bash
   bash -c "cd /var/www/html && git log --oneline tests/Traits/RefreshDatabaseWithTenant.php | head -5"
   # → Sprint 4 実装前のコミットハッシュを確認して checkout
   ```
4. Issue #81 にロールバック実施を記録し、原因調査をコメントに追記する

---

## フレーク率の記録フォーマット

Issue #81 へ週次で以下フォーマットで記録する:

```
## フレーク率記録（YYYY-MM-DD）
- 計測期間: YYYY-MM-DD ～ YYYY-MM-DD
- 対象 workflow: phpunit.yml / parallel-canary.yml
- 実行回数: N 回
- 失敗回数（再実行で成功）: N 回
- フレーク率: X.X%
- P50: XX.XX 分 / P95: XX.XX 分
- 特記事項: なし / <テスト名>がフレーク
```

---

## ロールバック判定チェックリスト

変更を本番適用する前に確認する:

- [ ] `phpunit.yml` の unit / feature / db-migrations 3 ジョブがすべて GREEN
- [ ] `parallel-canary.yml` が 10 連続 GREEN（フレーク率 < 1%）
- [ ] `database-migrations` グループのテストがすべて直列実行で安定
- [ ] ロールバックコマンドが手元で実行可能なことを確認済み

