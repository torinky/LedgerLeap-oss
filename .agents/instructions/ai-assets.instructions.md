---
applyTo: ".github/**/*.md,AGENTS.md,docs/runbooks/**/*.md,docs/templates/**/*.md"
---

# LedgerLeap AI Asset Rules

## Source-of-Truth Routing

- Repository-wide short invariants belong in `/.github/copilot-instructions.md`.
- Path-specific code generation rules belong in `/.github/instructions/*.instructions.md`.
- Slash-invocable workflows belong in `/.github/prompts/*.prompt.md`.
- Reusable capabilities and decision trees belong in `/.github/skills/<name>/SKILL.md`.
- Long examples and deep procedures belong in `/.github/skills/<name>/references/*.md`.
- Agent-wide routing and discovery rules belong in `/AGENTS.md`.
- Reusable evaluation harnesses, fixtures, and sanitized environment templates belong in `docs/harnesses/*`.
- Human-readable operating procedures belong in `docs/runbooks/*`.

## Editing Rules

- Update the primary destination first, then sync adjacent files.
- Do not copy the same rule into prompt, skill, runbook, and instructions without a clear reason.
- If a prompt and skill cover the same workflow, they must cross-link.
- If a new reusable pattern is added, update inventory or routing references when needed.
- Remove stale guidance instead of layering new text on top of conflicting old text.
- For LLM-facing docs, keep client-facing and developer-facing guidance separate.
- Client-facing wording must stay on WebUI-observable concepts and business workflows; implementation details move to developer-facing docs.
- When targeting local models, prefer short capability cards, small required-field lists, and list→detail flows.
- For client onboarding assets, use **prompt = short task starters**, **resource = stable reference cards**, **tool = dynamic or user-specific resolution**; avoid putting the final discovery contract into prompt text alone.
- When slimming MCP / API tool descriptions, keep the tool text focused on **contract, response shape, and misuse-prevention constraints**.
- Move step-by-step workflow guidance, fallback strategy, and rich examples to `resources/ai/capabilities/*.yaml` or bootstrap/discovery docs before removing them from tools.
- Never delete client-facing guidance from a tool description unless the destination asset already preserves equivalent information for generated skills or discovery flows.
- Durable guidance must cite traceable evidence: use `docs/work/*` for repo proof and `references/*.md` for official source summaries.
- For official-doc-sensitive guidance, record `last_confirmed_at` and `recheck_after`, and recheck when the same domain changes or the window expires.
- If a file will be mirrored to the OSS repo (for example `README.md` or `docs/**` outside excluded paths), do not link it to sync-excluded AI assets such as `docs/work/llm-integration/*`, `resources/ai/*`, `AGENTS.md`, or `.github/*`.
- If a file will be mirrored to the OSS repo, do not leave private issue numbers, canonical-body references, or packet-tracking metadata in the public doc body; keep them in the private packet / issue record and link only sanitized public artifacts.
- OSS-facing AI / API examples must use project-relative commands and placeholders; do not embed local absolute paths, private workspace paths, or demo-account identifiers in public docs.

## Prompt / Skill Bias

- JetBrains users discover workflows through prompt files first.
- Create or update a prompt when a new skill is important for day-to-day use.
- Keep SKILL bodies compact; move long examples to `references/`.
- Keep `copilot-instructions.md` compact and always-on safe.

## Runbook / Template Rules

- Runbooks explain the operational sequence for humans and AI.
- Harnesses capture copyable directory layouts, sanitized templates, and evaluation fixtures.
- Templates standardize evidence capture and output structure.
- If a workflow ends with a reusable learning, link to `/skill-maintenance` or the AI maintenance playbook.

## Validation

- Prefer relative Markdown links that resolve from the current file.
- Ensure names, prompt shortcuts, and skill inventory stay consistent.
- After substantive `.github` changes, check affected files for IDE errors.
