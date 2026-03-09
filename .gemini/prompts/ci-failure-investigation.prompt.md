---
description: Investigate GitHub Actions CI failures for LedgerLeap. Fetch logs, classify failures, and route to the correct fix using stable gh command patterns.
---

# ci-failure-investigation

## Step 0 — Preflight

```bash
gh auth status
```

- Start with plain-text `gh run list`.
- Prefer `gh api ... --jq` over `python3 -c` JSON parsing.
- On macOS, if Python unexpectedly asks for the Xcode license, stop using Python and switch to `gh api --jq`.
- Keep shell quoting shallow.

## Step 1 — Identify failing run

```bash
gh run list --repo torinky/LedgerLeap --limit 10

gh run list --repo torinky/LedgerLeap \
  --workflow "Laravel CI (PHPUnit / Pest)" \
  --branch develop \
  --limit 5

gh run list --repo torinky/LedgerLeap \
  --workflow "Parallel Tests Canary (Sprint 4)" \
  --branch develop \
  --limit 5
```

## Step 2 — Get job IDs

```bash
gh api repos/torinky/LedgerLeap/actions/runs/{RUN_ID}/jobs \
  --jq '.jobs[] | [.name, .databaseId, .conclusion] | @tsv'
```

Failed steps only:

```bash
gh api repos/torinky/LedgerLeap/actions/runs/{RUN_ID}/jobs \
  --jq '.jobs[] | {name, failedSteps: [.steps[] | select(.conclusion != null and .conclusion != "success") | .name]}'
```

## Step 3 — Fetch log

```bash
gh api /repos/torinky/LedgerLeap/actions/jobs/{JOB_ID}/logs > /tmp/ci-job.log

grep -E "FAIL|Exception|timeout|SQLSTATE|Error" /tmp/ci-job.log | head -40
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

See `.github/skills/ci-failure-investigation/SKILL.md` for full patterns and references.
