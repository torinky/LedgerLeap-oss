---
name: ci-failure-investigation
description: >
  GitHub Actions CI の失敗ログ調査ワークフロー。
  CI が失敗したとき、またはテストのタイムアウト・即時失敗を調査するときに参照すること。
---

# CI 失敗ログ調査ワークフロー

## このスキルを使うタイミング

- GitHub Actions CI でテストが失敗したとき
- テストが「60秒タイムアウト」または「0秒即時失敗」しているとき
- ローカルでは通るのに CI だけで失敗するとき
- CI のランがキャンセルされたとき

---

## 1. 最新ランの特定

```bash
gh run list --repo torinky/LedgerLeap --limit 5 \
  --json databaseId,status,conclusion,displayTitle
```

`status: in_progress` のランは完了を待つか、完了済みランを対象にする。

---

## 2. ジョブ一覧と失敗箇所の特定

```bash
gh run view {RUN_ID} --repo torinky/LedgerLeap \
  --json jobs | python3 -c "
import sys, json
for job in json.load(sys.stdin)['jobs']:
    print(job['name'], job['databaseId'], job['status'], job['conclusion'])
    for step in job['steps']:
        if step['conclusion'] not in ('success', ''):
            print(f'  FAIL: {step[\"name\"]} -> {step[\"conclusion\"]}')
"
```

---

## 3. ログの取得（重要）

### ⚠️ 注意: `gh run view --log` はランが進行中は機能しない

完了済みジョブのログは **`gh api`** で取得すること：

```bash
# ジョブIDを使ってログ取得
gh api /repos/torinky/LedgerLeap/actions/jobs/{JOB_ID}/logs 2>&1 \
  | grep -E "FAIL|⨯|Exception|SQLSTATE|Error|timeout" \
  | head -40
```

全ログをファイルに保存して調査する場合：

```bash
gh api /repos/torinky/LedgerLeap/actions/jobs/{JOB_ID}/logs > /tmp/job.log
grep -n "FAIL\|⨯\|Exception\|RuntimeException" /tmp/job.log | head -30
```

---

## 4. 失敗パターンの分類

| パターン | 症状 | 根本原因の候補 |
|---|---|---|
| **60秒タイムアウト** | テストが64秒かかって失敗 | 外部サービス（Embedding/VLM）への接続待ち → `Queue::fake()` が必要 |
| **即時失敗（0秒）** | テストが0〜0.5秒で失敗 | 前のテストが `migrate:rollback` でDBを破壊 → `database-migrations` グループ分離が必要 |
| **TenantCouldNotBeIdentified** | `Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException` | ドメイン登録漏れ（`domains()->create(['domain' => 'localhost'])`） または `config(['tenancy.central_domains' => ['127.0.0.1']])` 未設定 |
| **RuntimeException: Embedding service** | `Embedding service did not become ready within 60 seconds` | `Queue::fake()` 未使用 + `QUEUE_CONNECTION=sync` + Embeddingコンテナ不在 |
| **SQLSTATE接続エラー** | `SQLSTATE[HY000] [1045] Access denied` | CI の DB_PASSWORD が `.env.example` と不一致 |
| **タイムアウト後キャンセル** | ラン全体が30分でキャンセル | テストが遅すぎる（`DatabaseMigrations` の乱用等） |

---

## 5. 根本原因調査の手順

### 5.1 ログからエラーメッセージを抽出

```bash
gh api /repos/torinky/LedgerLeap/actions/jobs/{JOB_ID}/logs 2>&1 \
  | grep -E "RuntimeException|SQLSTATE|Exception:|FAILED" \
  | head -20
```

### 5.2 ローカルで再現確認

```bash
# 失敗テストを単体で実行
./vendor/bin/sail pest --filter="失敗テストクラス名" --display-errors 2>&1 | tail -20

# 失敗グループをまとめて実行
./vendor/bin/sail pest --group=database-migrations --display-errors 2>&1 | tail -20
```

### 5.3 ローカルで通る場合の追加調査

ローカルで通るが CI で失敗する場合：

1. **環境変数の差異を確認**
   - CI は `DB_PASSWORD=`（空）、ローカルは `DB_PASSWORD=password` 等
   - CI の `.env` 設定は `.github/workflows/phpunit.yml` の `Laravel Setting` ステップを確認

2. **外部コンテナの有無を確認**
   - ローカル: Embeddingコンテナ (`http://embedding:8000`) が起動している
   - CI: Embeddingコンテナなし → `Queue::fake()` が必要

3. **テスト実行順序の確認**
   - CI は全テストを順次実行するため、先行テストが DB 状態を破壊している可能性
   - `DatabaseMigrations` / `migrate:rollback` が原因かを確認

---

## 6. 調査結果のイシューへの報告

調査完了後は GitHub イシューにコメントを追加すること（`github-issue-workflow` スキル参照）。

### コメントテンプレート

```markdown
## 🔍 CI 失敗ログ調査結果 (YYYY-MM-DD)

### 対象ラン
ラン `{RUN_ID}` / ジョブ `{JOB_NAME}`

### 失敗テスト一覧

| テストクラス | 症状 | 失敗数 |
|---|---|---|
| `ClassName` | 60秒タイムアウト | X |

### 根本原因

**原因**: （具体的な説明）

```
// 問題のコード or エラーメッセージ
```

### 対応方針

- [ ] 対応1
- [ ] 対応2
```

---

## 7. よくある修正パターンへの誘導

| 根本原因 | 参照スキル |
|---|---|
| `Embedding service` タイムアウト / `Queue::fake()` 未使用 | `test-external-dependency-isolation` |
| `DatabaseMigrations` が他テストのDBを破壊 | `database-migrations-test-optimization` |
| テスト時間が300秒超 | `database-migrations-test-optimization` |
| イシューへの報告・チェックリスト更新 | `github-issue-workflow` |

