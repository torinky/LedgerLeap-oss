# Evidence & Freshness Rules

Use this reference when a `skill-maintenance` update turns a one-off finding into durable guidance.

## Core Rule

Every durable claim must let the next agent answer all of these without redoing the whole investigation:

1. **What is the claim?**
2. **What evidence proves it?**
3. **When was it last confirmed?**
4. **When should it be checked again?**
5. **What event should force an earlier recheck?**

## Evidence Record Template

```yaml
claim: <rule or finding>
status: confirmed-official | confirmed-repo | provisional
last_confirmed_at: YYYY-MM-DD
recheck_after: 30d | 90d | 180d
recheck_trigger:
  - upstream docs changed
  - same feature area is edited
  - IDE / Copilot support behavior changes
sources:
  - type: official-doc
    url: <canonical URL>
  - type: repo-proof
    path: <repo markdown path>
notes: <what is proven vs inferred>
```

## Status Meanings

- `confirmed-official`: directly supported by the latest official documentation checked by the agent.
- `confirmed-repo`: proven by repository history, code, tests, or issue / work logs.
- `provisional`: useful working guidance, but either partially inferred or based on incomplete upstream evidence. Recheck sooner.

## Default Recheck Windows

| Situation | Default |
|---|---|
| Official GitHub / Copilot documentation claim | `90d` |
| Partial or inferred upstream support claim | `30d` |
| Repo-only operating pattern with stable local evidence | `180d` |

If the domain is moving quickly, choose the shorter window.

## Mandatory Recheck Triggers

Recheck immediately when any of these is true:

- `today > last_confirmed_at + recheck_after`
- You are editing the same feature area or file family
- The upstream product docs, IDE support matrix, or customization contract changed
- A new bug / contradiction suggests the stored guidance may be stale

## Placement Rules

- Put **repo implementation evidence** in `docs/work/*`, issue logs, or runbooks.
- Put **official product evidence summaries** in `references/*.md` with direct URLs.
- Keep SKILL / prompt bodies short; store the evidence table in a reference file and link to it.

## Current Example Anchors

- Issue #100 implementation evidence: `/docs/work/llm-integration/2026-03-14_MCP_Tool_Description_Audit_and_Reduction_Plan.md#14-issue-100-実装トラッカー2026-03-14`
- JetBrains / Copilot customization evidence: `./jetbrains-copilot-support.md`

