---
name: client-facing-contract-promotion
description: Promotes an existing WebUI workflow to a client-facing MCP / API contract with minimal scope, shared service extraction, and staged contract exposure. Use when a WebUI feature exists but MCP or REST does not yet expose it.
compatibility: LedgerLeap (resources/ai/capabilities, app/Services, app/Mcp, docs/development/MCP_Architecture_and_Flow.md, issue-driven iteration)
---

# client-facing-contract-promotion

## Decision Tree

```text
A useful workflow already exists in WebUI?
├─ NO  → design the user goal first; do not start from tool names
├─ YES → confirm the workflow is visible in WebUI terms only
├─ Can the workflow fit an existing capability?
│  ├─ YES → extend the existing capability first
│  └─ NO  → consider a new capability only after one implementation slice proves it
├─ Is the logic trapped inside Livewire / controller code?
│  └─ YES → extract a shared service before adding MCP / REST
├─ Do users need the workflow from AI right now?
│  ├─ YES → add the smallest MCP contract first
│  └─ NO  → keep it as WebUI-only until demand is clearer
└─ Is REST required immediately?
   ├─ YES → add a narrow supporting contract after MCP shape is proven
   └─ NO  → defer REST and keep the first slice MCP-first
```

## Working Rules

- Start from the **user goal and observed WebUI flow**, not from internal classes.
- Prefer **existing capability extension** before inventing a new capability.
- Extract a **shared service** when the current logic is locked inside Livewire / controller code.
- Ship the **smallest MCP contract** that makes the workflow usable.
- Treat REST as **follow-up support** unless another client already depends on it.
- Record the implementation evidence in the execution issue, then return only reusable patterns to the iteration issue.

## Minimum Deliverable

- Existing WebUI flow and gap summary
- Capability decision: extension vs new capability
- Shared service extraction when needed
- MCP tool or contract with minimal client-facing output
- Targeted validation (`sail test`, `sail pint`)
- Reusable learning returned to the parent iteration issue

## Reusable Pattern Proven by Issue #98

1. If a workflow already exists in WebUI but not in MCP / REST, it is a good first candidate for incremental contract promotion.
2. First ask whether **`resources/ai/capabilities/*.yaml` can absorb it as an extension**.
3. If WebUI code owns the core logic, extract a **shared service** before exposing the workflow to MCP.
4. Prefer **MCP-first, REST-later** for the first implementation slice.
5. Keep `#execution` issues for concrete evidence and use the parent iteration issue for distilled rules only.

## Checklist

- [ ] WebUI flow confirmed and described in client-facing vocabulary
- [ ] Existing capability extension considered before new capability creation
- [ ] Shared service extracted if logic was UI-bound
- [ ] MCP contract kept minimal and reasoned from user flow
- [ ] REST necessity explicitly confirmed or deferred
- [ ] Tests and pint executed in Sail
- [ ] Parent iteration issue updated with reusable takeaways

