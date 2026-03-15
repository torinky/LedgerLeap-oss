# JetBrains / Copilot Support Notes

These notes summarize how to optimize LedgerLeap's `.github` layout for JetBrains and GitHub Copilot.

> Last review: `2026-03-14`
> Recheck rule: official-doc claims default to `90d`; provisional interpretation claims default to `30d` or earlier when JetBrains / Copilot customization docs change.

## Official Support Summary

### JetBrains IDEs
- `/.github/copilot-instructions.md` is supported as repository-wide always-on instructions.
- `/.github/instructions/**/*.instructions.md` is supported as path-specific instructions.
- `/.github/prompts/*.prompt.md` is supported as reusable prompt files and slash entrypoints.
- `AGENTS.md` is documented for Copilot coding agent support.

### Agent Skills
- `.github/skills/<name>/SKILL.md` is an official Copilot skill location.
- GitHub docs describe skills for Copilot coding agent and Copilot CLI.
- Skill directories must be lowercase and use hyphens.
- `SKILL.md` files require YAML frontmatter with at least `name` and `description`.

## Evidence Register

| Claim | Status | Last confirmed | Recheck after | Evidence |
|---|---|---|---|---|
| `/.github/copilot-instructions.md`, `/.github/instructions/*.instructions.md`, `.github/prompts/*.prompt.md`, and `AGENTS.md` are supported customization locations | `confirmed-official` | `2026-03-14` | `90d` | GitHub Docs: `add-repository-instructions.md` lines describing `NAME.instructions.md`, `AGENTS.md`, and `.prompt.md`; customization cheat sheet row for custom instructions / prompt files |
| Prompt files must end with `.prompt.md` and are reusable slash entrypoints | `confirmed-official` | `2026-03-14` | `90d` | GitHub Docs: `add-repository-instructions.md` prompt creation section; `your-first-prompt-file.md` / `create-readme.md` examples |
| Example repository instruction guidance includes `Instructions must be no longer than 2 pages.` | `confirmed-official` | `2026-03-14` | `90d` | GitHub Docs: `add-repository-instructions.md` lines 76-78 |
| Prompt-file example guidance includes `Keep content under 500 KiB (GitHub truncates beyond this)` | `confirmed-official` | `2026-03-14` | `90d` | GitHub Docs: `create-readme.md` lines 48-50 |
| Project skills live at `.github/skills/<skill-name>/SKILL.md`; each skill has its own lowercase-hyphenated directory; `SKILL.md` requires YAML frontmatter with `name` and `description` | `confirmed-official` | `2026-03-14` | `90d` | GitHub Docs reusable: `creating-adding-skills.md` |
| JetBrains discovery remains prompt-first in LedgerLeap because official JetBrains-facing docs explicitly document prompts/instructions, while direct JetBrains chat discovery for skills is less explicit in the sources last checked | `provisional` | `2026-03-14` | `30d` | Official docs above + LedgerLeap routing choice in `/AGENTS.md` and `/docs/runbooks/ai-asset-maintenance-playbook.md` |

### Source URLs checked on 2026-03-14

- <https://raw.githubusercontent.com/github/docs/main/content/copilot/how-tos/configure-custom-instructions/add-repository-instructions.md>
- <https://raw.githubusercontent.com/github/docs/main/content/copilot/tutorials/customization-library/prompt-files/create-readme.md>
- <https://raw.githubusercontent.com/github/docs/main/content/copilot/tutorials/customization-library/prompt-files/your-first-prompt-file.md>
- <https://raw.githubusercontent.com/github/docs/main/data/reusables/copilot/creating-adding-skills.md>
- <https://raw.githubusercontent.com/github/docs/main/content/copilot/reference/customization-cheat-sheet.md>

## LedgerLeap Recommendation

Because JetBrains prompt files are a clear primary entrypoint, use this layering:

1. **Prompt files first** for slash-invocable workflows that users explicitly run.
2. **Instructions files** for path-specific code generation rules.
3. **Skills** for reusable decision trees, diagnostics, and deep knowledge.
4. **AGENTS.md** for repository-wide routing and maintenance policy for agentic tools.
5. **copilot-instructions.md** only for compact repo-wide invariants.

## What This Means in Practice

- Do not expect JetBrains users to discover a new skill unless a prompt, AGENTS rule, or existing workflow points to it.
- If a skill is important, create or update the matching prompt.
- If a routing convention changes, update `AGENTS.md` as well as the prompt/skill pair.
- Keep `copilot-instructions.md` small; move operational detail into prompts, skills, or runbooks.
- Recheck this note set when the 30d / 90d windows expire or when GitHub Docs changes the customization surface.

## Recommended Directory Roles

| Path | Role |
|---|---|
| `.github/copilot-instructions.md` | compact repo-wide invariants |
| `.github/instructions/` | path-specific auto-applied coding rules |
| `.github/prompts/` | primary slash workflows |
| `.github/skills/` | reusable capabilities and decision trees |
| `.github/ISSUE_TEMPLATE/` | structured intake |
| `AGENTS.md` | agent routing and discovery policy |
| `docs/runbooks/` | human-readable operating sequences |

