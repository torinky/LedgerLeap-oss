---
name: ci-failure-investigation
description: Investigates GitHub Actions CI failures for LedgerLeap — fetches logs, classifies failure patterns, and guides to the correct fix skill. Use when CI fails, tests timeout, or pass locally but fail in CI.
compatibility: LedgerLeap (requires gh CLI; prefer gh api --jq over python helpers)
---

# ci-failure-investigation

## Step 0 — Preflight

```bash
gh auth status
```

- Prefer **plain-text `gh run list`** first. It is more reliable than starting with `--json`.
- Prefer **`gh api ... --jq`** over `gh run view --json ... | python3 -c ...`.
- On macOS, if a `python3` helper unexpectedly shows an **Xcode license** prompt, stop using Python for this task and switch to `gh api --jq`.
- Avoid nested shell quoting. Use one `gh` command per line and add filtering in a second step.

## Step 1 — Identify the failing run/job

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

If you already know the commit SHA:

```bash
gh run list --repo torinky/LedgerLeap --commit {SHA} --limit 10
```

## Step 2 — Get job IDs and conclusions

```bash
gh api repos/torinky/LedgerLeap/actions/runs/{RUN_ID}/jobs \
  --jq '.jobs[] | [.name, .databaseId, .conclusion] | @tsv'
```

To see failed steps only:

```bash
gh api repos/torinky/LedgerLeap/actions/runs/{RUN_ID}/jobs \
  --jq '.jobs[] | {name, failedSteps: [.steps[] | select(.conclusion != null and .conclusion != "success") | .name]}'
```

## Step 3 — Fetch log (prefer `gh api`)

```bash
gh api /repos/torinky/LedgerLeap/actions/jobs/{JOB_ID}/logs > /tmp/ci-job.log

grep -E "FAIL|Exception|timeout|SQLSTATE|Error" /tmp/ci-job.log | head -40
```

Why: `gh run view --log` can be flaky or incomplete; `gh api` is more stable.

## Step 4 — Stable command rules

- Start broad (`gh run list`) → narrow by workflow / branch / commit.
- For job details, use `gh api repos/.../actions/runs/{RUN_ID}/jobs --jq ...`.
- For logs, download first, then grep.
- If a `gh ... --json ...` command returns unexpectedly empty output, rerun with plain-text `gh run list` or switch to `gh api`.
- Keep shell quoting shallow. Avoid long `bash -lc '... "..." ...'` chains.

See [references/gh-actions-commands.md](references/gh-actions-commands.md) for copyable command recipes.

## Step 5 — Classify & route to fix skill

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

## Step 6 — Report to issue

Post investigation result using `github-issue-workflow` skill.

```markdown
## 🔍 CI 失敗ログ調査結果 (YYYY-MM-DD)
### 対象ラン: {RUN_ID} / {JOB_NAME}
### 失敗テスト: `ClassName` — 症状 (X件)
### 根本原因: （説明）
### 対応方針:
- [ ] 対応1
```
