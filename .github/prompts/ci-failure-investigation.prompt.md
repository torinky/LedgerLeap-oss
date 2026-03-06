---
description: Investigate GitHub Actions CI failures for LedgerLeap. Fetches logs, classifies failure patterns, and routes to the correct fix.
---

# ci-failure-investigation

## Step 1 — Identify failing run

```bash
gh run list --repo torinky/LedgerLeap --limit 5 \
  --json databaseId,status,conclusion,displayTitle
```

## Step 2 — Get job IDs

```bash
gh run view {RUN_ID} --repo torinky/LedgerLeap \
  --json jobs | python3 -c "
import sys, json
for j in json.load(sys.stdin)['jobs']:
    print(j['name'], j['databaseId'], j['conclusion'])
    for s in j['steps']:
        if s['conclusion'] not in ('success',''):
            print('  FAIL:', s['name'])
"
```

## Step 3 — Fetch log

```bash
gh api /repos/torinky/LedgerLeap/actions/jobs/{JOB_ID}/logs 2>&1 \
  | grep -E "FAIL|Exception|timeout|SQLSTATE|Error" | head -40
```

## Step 4 — Classify & route

| Symptom | Root cause | Fix |
|---|---|---|
| Test takes 60s then fails | Missing `Queue::fake()` — Embedding container unreachable | `test-external-dependency-isolation` skill |
| Test fails in 0s | Previous test's `migrate:rollback` destroyed DB | `database-migrations-test-optimization` skill |
| `TenantCouldNotBeIdentifiedOnDomain` | Domain not registered | Add `domains()->create()` |
| `DomainOccupiedByOtherTenantException` | Tests share domain name | Use unique domain per class or `firstOrCreate()` |
| `RoleAlreadyExists` | `Role::create()` without `migrate:fresh` | Change to `Role::firstOrCreate()` |
| `Database file wnjpn.db does not exist` | Split zip not merged in CI | Add merge+unzip step to `phpunit.yml` |
| Run cancelled after 30min | `DatabaseMigrations` overuse (~13s per test) | `database-migrations-test-optimization` skill |
| `SQLSTATE Access denied` | DB_PASSWORD mismatch | Check `phpunit.yml` Laravel Setting step |

## Step 5 — Report

Post result using `github-issue-workflow` prompt:
```markdown
## 🔍 CI 失敗ログ調査結果 (YYYY-MM-DD)
### 対象ラン: {RUN_ID} / {JOB_NAME}
### 失敗テスト: `ClassName` — 症状 (X件)
### 根本原因: （説明）
### 対応方針:
- [ ] 対応1
```

See `.github/skills/ci-failure-investigation/SKILL.md` for full patterns.

