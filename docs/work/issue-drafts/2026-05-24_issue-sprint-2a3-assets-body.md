# Sprint 2-A3: packet 実行用 skill / subagent / runbook 整備

## GitHub 追跡
- Umbrella: #225
- Upstream inventory: #226
- Upstream contract: #227
- Sprint 2-A3: #228（本 Issue）
- Downstream pilot: #229

## 概要
Sprint 2-A1 / 2-A2 の成果を reusable な AI asset として固定しました。shared packet contract を再掲するのではなく、**JetBrains entry / inventory skill / packet executor agent / OpenCode adapter / Continue adapter / operator runbook / shared template** に分割し、#229 が packet backlog 実行から始められる状態にしました。

## 背景 / 目的
- #219 だけでなく、その後の docs 保守でも同じ packet 実行系を再利用したい
- `doc-publication-audit` は rewrite に強いが、packet handoff / comment sync / single-writer rule を直接は持っていなかった
- Continue.dev は OpenCode と違って repo-local prompt discovery を前提にできないため、prompt / rule / config の受け皿を分けて置く必要があった
- human-readable な runbook と sanitized harness がないと、operator が adapter 差分を再調査し続ける

## 2-A3 で確定した判断
1. **shared packet SoT は `docs/work` の issue canonical body + `docs/templates/doc-publication-packet-template.md` + `docs/runbooks/doc-publication-packet-playbook.md` の組み合わせで持つ**
2. `doc-source-inventory` を新規 skill として追加し、役割を **inventory refresh / delta extraction** に限定する
3. `doc-publication-audit` は rewrite 専用のまま維持し、packet handoff / comment sync / stale-baseline handback を追加する
4. repo-native custom agent は `.github/agents/doc-source-inventory.agent.md` と `.github/agents/doc-packet-executor.agent.md` の 2 本に分ける
5. OpenCode adapter は `.opencode/agents/*` と `.opencode/commands/*` に置き、`packet-plan` だけ `subtask: true` にする
6. Continue adapter は `.continue/rules/*` と harness の `continue-config.template.yaml` に置き、**repo-local prompt discovery を仮定しない**
7. operator flow は `docs/runbooks/doc-publication-packet-playbook.md` に集約し、JetBrains entry は `/.github/prompts/doc-publication-packet.prompt.md` に置く

## 役割分担
| Layer | File | Role |
|---|---|---|
| JetBrains entry | `/.github/prompts/doc-publication-packet.prompt.md` | inventory refresh / packet rewrite / comment sync の lane 選択 |
| repo-native skill | `/.github/skills/doc-source-inventory/SKILL.md` | #226 baseline からの delta refresh |
| repo-native skill | `/.github/skills/doc-publication-audit/SKILL.md` | 1 packet rewrite + comment sync |
| repo-native agent | `/.github/agents/doc-source-inventory.agent.md` | Copilot subagent 用 inventory refresh |
| repo-native agent | `/.github/agents/doc-packet-executor.agent.md` | Copilot subagent 用 single-packet writer |
| OpenCode adapter | `.opencode/agents/*`, `.opencode/commands/*` | OpenCode の subagent / command entry |
| Continue adapter | `.continue/rules/*`, `docs/harnesses/doc-publication-packet/continue-config.template.yaml` | Continue の Plan/Agent rule + sanitized config |
| human ops | `docs/runbooks/doc-publication-packet-playbook.md` | operator 向け全体フロー |
| shared template | `docs/templates/doc-publication-packet-template.md` | packet manifest / handoff / acceptance |

## Continue adapter で明示した制約
- Continue `config.yaml` の top-level は `models`, `rules`, `prompts`, `mcpServers`
- local rules は `.continue/rules/*.md` を使う
- Continue docs で repo-local prompts の自動 discovery を前提にできないため、packet prompt blocks は harness の `continue-config.template.yaml` に置く
- したがって Continue 側の reusable asset は **repo rule + harness snippet** を正本とし、JetBrains prompt をそのまま複製しない

## 2-A4 に渡す最小 asset set
- JetBrains: `/.github/prompts/doc-publication-packet.prompt.md`
- Inventory refresh: `/.github/skills/doc-source-inventory/SKILL.md`, `/.github/agents/doc-source-inventory.agent.md`
- Packet rewrite: `/.github/skills/doc-publication-audit/SKILL.md`, `/.github/agents/doc-packet-executor.agent.md`
- Shared contract surface: `docs/templates/doc-publication-packet-template.md`, `docs/runbooks/doc-publication-packet-playbook.md`
- OpenCode: `.opencode/agents/doc-source-inventory.md`, `.opencode/agents/doc-packet-executor.md`, `.opencode/commands/packet-plan.md`, `.opencode/commands/packet-rewrite.md`, `.opencode/commands/packet-comment-sync.md`
- Continue: `.continue/rules/01-doc-packet-core.md`, `.continue/rules/02-doc-packet-comment-sync.md`, `docs/harnesses/doc-publication-packet/continue-config.template.yaml`

## スプリント分解
- [x] shared packet rule / handoff SoT の primary destination を決める
- [x] skill 方針を決める
- [x] inventory refresh と packet execution の責務分離を決める
- [x] custom agent 方針を決める
- [x] OpenCode adapter 方針を決める
- [x] Continue adapter 方針を決める
- [x] runbook / prompt entrypoint の要否を決める
- [x] primary destination と neighbor sync 方針を確定する
- [x] 2-A4 で必要な最小 asset set を確定する

## エビデンス / 参照先
- `/.github/prompts/doc-publication-packet.prompt.md`
- `/.github/skills/doc-source-inventory/SKILL.md`
- `/.github/skills/doc-publication-audit/SKILL.md`
- `/.github/skills/doc-publication-audit/references/packet-execution-assets.md`
- `/.github/agents/doc-source-inventory.agent.md`
- `/.github/agents/doc-packet-executor.agent.md`
- `.opencode/agents/doc-source-inventory.md`
- `.opencode/agents/doc-packet-executor.md`
- `.opencode/commands/packet-plan.md`
- `.opencode/commands/packet-rewrite.md`
- `.opencode/commands/packet-comment-sync.md`
- `.continue/rules/01-doc-packet-core.md`
- `.continue/rules/02-doc-packet-comment-sync.md`
- `docs/runbooks/doc-publication-packet-playbook.md`
- `docs/templates/doc-publication-packet-template.md`
- `docs/harnesses/doc-publication-packet/continue-config.template.yaml`
- `AGENTS.md`
- `docs/runbooks/README.md`
- `docs/harnesses/README.md`
- OpenCode Agents — https://opencode.ai/docs/agents
- OpenCode Commands — https://opencode.ai/docs/commands
- OpenCode Config — https://opencode.ai/docs/config
- Continue config reference — https://docs.continue.dev/reference
- Continue Rules — https://docs.continue.dev/customize/deep-dives/rules
- Continue Agent mode quick start — https://docs.continue.dev/ide-extensions/agent/quick-start
- Continue OpenAI-compatible provider — https://docs.continue.dev/customize/model-providers/top-level/openai

## 完了条件
- [x] source inventory と packet rewrite の reusable asset 方針が定まっている
- [x] shared SoT / runbook / skill / agent / OpenCode adapter / Continue adapter の役割分担が明文化されている
- [x] OpenCode と Continue のどちらからでも 2-A4 を開始できる最小 asset set が定まっている
- [x] asset の primary destination と neighbor sync 手順が明文化されている
