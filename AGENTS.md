# LedgerLeap Agent Routing

## Primary Sources
- Repo-wide facts and invariants: `/.github/copilot-instructions.md`
- Path-specific codegen rules: `/.github/instructions/*.instructions.md`
- User-invocable workflows: `/.github/prompts/*.prompt.md`
- Reusable deep capabilities and decision trees: `/.github/skills/<name>/SKILL.md`
- Long examples and deep procedures: `/.github/skills/<name>/references/*.md`
- Reusable evaluation harnesses / fixtures: `/docs/harnesses/*`
- Human-readable operating procedures: `/docs/runbooks/*`
- Project overview, docs hub, and current LLM strategy: `/README.md`, `/docs/README.md`, `/docs/work/llm-integration/README.md`
- Client-facing AI capability manifests / bootstrap inputs: `/resources/ai/capabilities/*.yaml`
- Capability manifest policy and generation notes: `/resources/ai/capabilities/README.md`
- Implemented MCP / API public contracts: `/docs/development/MCP_Architecture_and_Flow.md`, `/docs/api/README.md`

## Routing Rules
- Use prompt files as the primary JetBrains-facing entrypoints.
- Use skills for recurring diagnosis, decision trees, and reusable capabilities.
- Keep `copilot-instructions.md` short, repo-wide, and stable.
- Put agent-wide discovery or maintenance rules here, not in feature skills.
- **Skill size limit**: `SKILL.md` must stay ≤ 120 lines. Extract long examples, deep procedures, and historical evidence into `references/*.md`. See `docs/runbooks/skill-maintenance-playbook.md` for the splitting procedure.
- Before any file or terminal work in a mixed WSL / Mac setup, verify the current directory and Git root with commands such as `pwd` and `git rev-parse --show-toplevel`; do not assume `/home`, `/Users`, or the workspace path.
- Keep one source of truth per rule; link instead of duplicating.
- Keep step-by-step human/AI operating sequences in `docs/runbooks/*`, not duplicated across prompts and skills.
- Keep copyable evaluation fixtures, sanitized templates, and clean-room harness layouts in `docs/harnesses/*`.
- For client-facing AI capability changes, update `resources/ai/capabilities/*.yaml` first; `ai:bootstrap-client-skills` output is derived.
- When slimming MCP / API tool descriptions, move client-facing workflow text to `resources/ai/capabilities/*.yaml` or discovery docs first; keep tool descriptions contract-centered and record rationale in `docs/work/*`.
- Reusable guidance must link to evidence that the next agent can inspect; keep repo proof in `docs/work/*` and official source summaries in `references/*.md`.
- If guidance depends on upstream docs or IDE support behavior, record when it was last confirmed and recheck it after the configured window or when the same area changes.
- For mirrored public docs, keep internal tracking metadata out of the public body. Store private issue mappings, worklog references, and packet workflow metadata in the companion packet / issue record, and link only sanitized public artifacts from `README.md` or `docs/**`.

## Proven Learning → Destination
- Global short invariant → `/.github/copilot-instructions.md`
- File/path-specific rule → `/.github/instructions/*.instructions.md`
- Slash workflow / playbook entrypoint → `/.github/prompts/*.prompt.md`
- Reusable workflow / diagnosis tree → `/.github/skills/<name>/SKILL.md`
- Detailed examples / evidence formats → `/.github/skills/<name>/references/*.md`
- Reusable evaluation harness / fixture → `/docs/harnesses/*`
- Client-facing AI capability manifest / bootstrap source → `resources/ai/capabilities/*.yaml`
- Missing structured user input → `/.github/ISSUE_TEMPLATE/*`
- Repeatable human/AI operating sequence → `docs/runbooks/*`
- Agent routing / discovery / maintenance policy → `AGENTS.md`

## Mandatory Maintenance Loop
When a bug fix, investigation, or sprint proves a reusable pattern:
1. Update the primary destination.
2. Sync neighbors: prompt ↔ skill ↔ instructions ↔ issue template ↔ runbook ↔ AGENTS.
3. Remove stale or conflicting guidance.
4. Run `/skill-maintenance` before considering the work complete.

When any work item finishes — issue, sprint, feature, investigation, documentation, prompt, or skill work — or the user explicitly asks for a retrospective, use the same loop to collect learnings first and then decide whether they stay in `docs/work/*` or graduate into `.github` assets.

## Domain Entry Points
- Bug investigation: `/bug-investigation`
- Bug implementation: `/bug-execution`
- Doc creation sprint: `/doc-creation-sprint`
- Doc publication packets: `/doc-publication-packet`
- HAR analysis: `/browser-har-analysis`
- Git commits: `/git-commit`
- GitHub issues / PRs: `/github-issue-workflow`
- Client-facing contract triage: `/client-facing-contract-triage`
- Client-facing contract maintenance agent: `.github/agents/client-facing-contract-maintenance.agent.md`
- Doc source inventory agent: `.github/agents/doc-source-inventory.agent.md`
- Doc packet executor agent: `.github/agents/doc-packet-executor.agent.md`
- CI failures: `/ci-failure-investigation`
- RAG / search: `/rag-vector-search`
- AI asset maintenance: `/skill-maintenance`

### Doc publication packet routing
- `/doc-publication-packet` is the router: choose lane, confirm inputs, and decide the handoff target.
- `doc-creation-sprint` discovers the top unwritten doc from the #226 backlog and creates it in one execution. Entrypoint for "make a doc without picking the target."
- `doc-source-inventory` refreshes #226-derived backlog / readiness deltas.
- `doc-publication-audit` executes one already-selected packet; it is not the lane selector.
- `docs/runbooks/doc-publication-packet-playbook.md` is the human/AI sequence, and `docs/harnesses/doc-publication-packet/continue-config.template.yaml` mirrors the same split for adapters.
- Public/private traceability handling for packet rewrites lives in `doc-publication-audit`, the packet template, and `docs/runbooks/doc-publication-packet-playbook.md`.

## LedgerLeap-Specific Traps
- Tenancy initialization in tests is mandatory.
- Permission changes require both permission cache and tenant access cache clearing.
- Mroonga full-text search is single-column only.
- Livewire public state must stay plain arrays.
- `#[Lazy]` tenant-aware components and Livewire URL helpers should follow `.github/skills/livewire-tenant-context/SKILL.md` for the shared tenant resolver and `tenant_id` fallback pattern.
- All UI composition and styling MUST strictly adhere to `/.github/instructions/design.instructions.md`. Prioritize Mary UI components but style them using daisyUI/Tailwind semantics to match the project's vibe.
