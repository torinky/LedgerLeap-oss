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

## Prompt / Skill Bias

- JetBrains users discover workflows through prompt files first.
- Create or update a prompt when a new skill is important for day-to-day use.
- Keep SKILL bodies compact; move long examples to `references/`.
- Keep `copilot-instructions.md` compact and always-on safe.

## Runbook / Template Rules

- Runbooks explain the operational sequence for humans and AI.
- Templates standardize evidence capture and output structure.
- If a workflow ends with a reusable learning, link to `/skill-maintenance` or the AI maintenance playbook.

## Validation

- Prefer relative Markdown links that resolve from the current file.
- Ensure names, prompt shortcuts, and skill inventory stay consistent.
- After substantive `.github` changes, check affected files for IDE errors.
