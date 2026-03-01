---
name: ci-failure-investigation
description: Investigates GitHub Actions CI failures for LedgerLeap — fetches logs, classifies failure patterns, and guides to the correct fix skill. Use when CI fails, tests timeout, or pass locally but fail in CI.
compatibility: LedgerLeap (requires gh CLI)
---

# ci-failure-investigation

## Step 1 — Identify the failing run/job

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

## Step 3 — Fetch log (use gh api — gh run view --log fails mid-run)

```bash
gh api /repos/torinky/LedgerLeap/actions/jobs/{JOB_ID}/logs 2>&1 \
  | grep -E "FAIL|Exception|timeout|SQLSTATE|Error" | head -40
```

## Step 4 — Classify & route to fix skill

| Symptom | Root cause | Fix skill / action |
|---|---|---|
| Test takes 60s then fails | Missing `Queue::fake()` — Embedding container unreachable | `test-external-dependency-isolation` |
| Test fails in 0s | Previous test's `migrate:rollback` destroyed DB | `database-migrations-test-optimization` |
| `TenantCouldNotBeIdentifiedOnDomain` | Domain not registered or central_domains not set | Add `domains()->create()` + `config(['tenancy.central_domains' => [...]])` |
| `DomainOccupiedByOtherTenantException` | Multiple test classes share same domain name | `firstOrCreate()` or unique domain per class — see [references/fix-patterns.md](references/fix-patterns.md) §1 |
| `RoleAlreadyExists` | `Role::create()` in `RefreshDatabase` without `migrate:fresh` | Change to `Role::firstOrCreate()` or add `#[Group('database-migrations')]` |
| `Database file wnjpn.db does not exist` | Split zip not merged in CI | Add merge+unzip step to `phpunit.yml` — see [references/fix-patterns.md](references/fix-patterns.md) §2 |
| `ViewException: component not found` | Uppercase component name (`YMD`) not mapped | Map to lowercase (`y-m-d`) in `create-column.blade.php` |
| Run cancelled after 30min | Tests too slow (`DatabaseMigrations` overuse) | `database-migrations-test-optimization` |
| `SQLSTATE Access denied` | DB_PASSWORD mismatch between CI env and app | Check `phpunit.yml` Laravel Setting step |

## Step 5 — Report to issue

Post investigation result using `github-issue-workflow` skill.

```markdown
## 🔍 CI 失敗ログ調査結果 (YYYY-MM-DD)
### 対象ラン: {RUN_ID} / {JOB_NAME}
### 失敗テスト: `ClassName` — 症状 (X件)
### 根本原因: （説明）
### 対応方針:
- [ ] 対応1
```
