# Evidence Collection Order

Use this order unless the incident requires immediate containment.

## 1. Clarify the problem contract
- Expected behavior
- Actual behavior
- Reproduction steps
- Impact scope
- Environment (tenant, route, browser, local/CI, queue, branch)

## 2. Collect internal evidence first
1. Exception, stack trace, application log, browser log, failing test, CI log
2. Related code path, callers, recent changes, config, migrations
3. Existing tests and coverage around the behavior
4. Existing docs, runbooks, prompts, skills, previous bug notes
5. Similar repository implementations or prior fixes

## 3. LedgerLeap-specific quick checks
- Tenancy initialization missing in tests
- `#[Lazy]` component relying only on `tenant()?->id`
- Permission cache / tenant access cache not cleared
- Mroonga query violating single-column full-text constraint
- Livewire public state holding objects instead of arrays
- Test depending on Embedding / OCR / LDAP / other external services
- Tailwind utility added but frontend build not refreshed

## 4. Build hypotheses only after evidence exists
For each hypothesis, record:
- what evidence supports it
- what evidence weakens it
- what quick experiment would confirm it
- confidence level

## 5. Keep negative results
Always record failed experiments and disproven causes so future debugging does not repeat them.

