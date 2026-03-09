# JetBrains / Copilot Support Notes

These notes summarize how to optimize LedgerLeap's `.github` layout for JetBrains and GitHub Copilot.

## Official Support Summary

### JetBrains IDEs
- `/.github/copilot-instructions.md` is supported as repository-wide always-on instructions.
- `/.github/instructions/**/*.instructions.md` is supported as path-specific instructions.
- `/.github/prompts/*.prompt.md` is supported as reusable prompt files and slash entrypoints.
- `AGENTS.md` is documented for Copilot coding agent support.

### Agent Skills
- `.github/skills/<name>/SKILL.md` is an official Copilot skill location.
- GitHub docs describe skills for Copilot coding agent and Copilot CLI.
- VS Code docs document slash invocation and automatic loading for skills.
- JetBrains documentation is clearer for prompts/instructions than for direct skill discovery in chat.

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

