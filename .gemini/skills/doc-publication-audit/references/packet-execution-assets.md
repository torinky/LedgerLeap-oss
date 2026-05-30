# Issue #228 packet execution asset summary

- `status`: confirmed-official
- `last_confirmed_at`: 2026-05-24
- `recheck_after`: 90d
- `recheck_trigger`: OpenCode changes agent / command semantics, Continue changes `config.yaml` top-level keys or rule loading, or LM Studio OpenAI-compatible setup changes again

## OpenCode

- OpenCode provides primary agents (`Build`, `Plan`) and subagents (`General`, `Explore`, `Scout`).
  - Source: https://opencode.ai/docs/agents
- Custom agents can be stored in `.opencode/agents/`.
  - Source: https://opencode.ai/docs/agents
- Custom commands can be stored in `.opencode/commands/`.
  - Source: https://opencode.ai/docs/commands
- `subtask: true` forces a subagent-style isolated command execution.
  - Source: https://opencode.ai/docs/commands
- Agent permissions can gate `edit`, `bash`, and `task`, so read-only planning and single-writer execution can be separated.
  - Source: https://opencode.ai/docs/agents

## Continue.dev

- Continue `config.yaml` uses top-level `models`, `rules`, `prompts`, and `mcpServers`.
  - Source: https://docs.continue.dev/reference
- `Plan` mode is read-only exploration and `Agent` mode can edit files and run tools.
  - Source: https://docs.continue.dev/ide-extensions/agent/quick-start
- Local workspace rules live in `.continue/rules/` and can be loaded conditionally with frontmatter.
  - Source: https://docs.continue.dev/customize/deep-dives/rules
- OpenAI-compatible providers are supported with `provider: openai` plus `apiBase`.
  - Source: https://docs.continue.dev/customize/model-providers/top-level/openai

## Repo decisions from #227 → #228

1. Keep the shared packet contract in repo-native docs / templates, not inside adapter-specific prompts.
2. Put OpenCode adapters in `.opencode/agents/` and `.opencode/commands/`.
3. Put Continue guardrails in `.continue/rules/` and keep the `config.yaml` example as a sanitized harness template.
4. Use `docs/templates/doc-publication-packet-template.md` as the shared handoff / acceptance shape for both adapters.
