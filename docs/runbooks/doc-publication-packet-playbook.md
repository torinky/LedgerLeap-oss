# Doc Publication Packet Playbook

LedgerLeap の公開ドキュメントを **1 packet = 1 target file** で進めるための標準手順です。

## 関連資料

- [Doc Publication Packet Prompt](../../.github/prompts/doc-publication-packet.prompt.md)
- [doc-source-inventory skill](../../.github/skills/doc-source-inventory/SKILL.md)
- [doc-publication-audit skill](../../.github/skills/doc-publication-audit/SKILL.md)
- [Doc Publication Packet Template](../templates/doc-publication-packet-template.md)
- [Issue #226 canonical body](../work/issue-drafts/2026-05-24_issue-sprint-2a1-source-inventory-body.md)
- [Issue #227 canonical body](../work/issue-drafts/2026-05-24_issue-sprint-2a2-packet-contract-body.md)
- [Issue #228 canonical body](../work/issue-drafts/2026-05-24_issue-sprint-2a3-assets-body.md)
- [Local LLM MCP Setup Guide](./local-llm-mcp-setup.md)

## 1. 何を packet と呼ぶか

- packet は **1 public target file** を処理する bounded task
- baseline は #226 の feature families / target doc list v2 / comment anchor candidates
- contract は #227 の packet schema v1 / handoff / acceptance / run profile
- adapter asset は #228 で追加した prompt / skill / agent / OpenCode / Continue / runbook

## 2. lane の選び方

### Inventory refresh

使うもの:
- [doc-publication-packet prompt](../../.github/prompts/doc-publication-packet.prompt.md)
- [doc-source-inventory skill](../../.github/skills/doc-source-inventory/SKILL.md)
- `.github/agents/doc-source-inventory.agent.md`

向いている作業:
- #226 baseline との差分確認
- family / anchor / packet readiness の更新
- provisional queue の切り分け

### Packet rewrite

使うもの:
- [doc-publication-audit skill](../../.github/skills/doc-publication-audit/SKILL.md)
- `.github/agents/doc-packet-executor.agent.md`
- [Doc Publication Packet Template](../templates/doc-publication-packet-template.md)

向いている作業:
- 1 packet の本文 rewrite
- handoff / acceptance 記録
- comment sync 付きの bounded rewrite

### Comment sync only

使うもの:
- `.opencode/commands/packet-comment-sync.md`
- `.continue/rules/02-doc-packet-comment-sync.md`
- packet template の acceptance section

向いている作業:
- docs 本文は変えず、comment anchor だけを整えるケース

## 3. 共通ルール

1. `pwd` と `git rev-parse --show-toplevel` で現在地を確認する
2. REST API と MCP contract を同じ packet に混ぜない
3. `docs/contributing/*` は provisional queue のまま扱う
4. main conversation には raw source dump を残さず summary-first で進める
5. write phase は常に 1 writer に固定する
6. packet handoff / acceptance は template に合わせる

## 4. JetBrains / Copilot entrypoint

1. `/doc-publication-packet` で lane を決める
2. inventory refresh が必要なら `doc-source-inventory`
3. packet rewrite なら `doc-publication-audit`
4. 終了時は issue body / handoff / runbook への影響を確認する

## 5. OpenCode adapter

### Asset set

- `.opencode/agents/doc-source-inventory.md`
- `.opencode/agents/doc-packet-executor.md`
- `.opencode/commands/packet-plan.md`
- `.opencode/commands/packet-rewrite.md`
- `.opencode/commands/packet-comment-sync.md`

### 使い分け

- `/packet-plan` は read-only の packet inventory / handoff preparation
- `/packet-rewrite` は単一 writer で 1 packet を実装
- `/packet-comment-sync` は comment anchor だけを更新
- packet-plan は `subtask: true` で子タスク化し、packet-rewrite / comment-sync は main writer を維持する

## 6. Continue.dev adapter

### Asset set

- `.continue/rules/01-doc-packet-core.md`
- `.continue/rules/02-doc-packet-comment-sync.md`
- `docs/harnesses/doc-publication-packet/continue-config.template.yaml`

### 使い分け

- `.continue/rules/*` は packet core rule と comment sync rule を分ける
- Continue prompt blocks は harness の `config.template.yaml` に置き、unsupported な repo-local prompt discovery を仮定しない
- `Plan` mode は inventory / anchor read、`Agent` mode は single writer の rewrite に使う

## 7. 2-A4 に渡す最小 asset set

| Layer | File |
|---|---|
| JetBrains entry | `.github/prompts/doc-publication-packet.prompt.md` |
| Inventory refresh | `.github/skills/doc-source-inventory/SKILL.md`, `.github/agents/doc-source-inventory.agent.md` |
| Packet rewrite | `.github/skills/doc-publication-audit/SKILL.md`, `.github/agents/doc-packet-executor.agent.md`, `docs/templates/doc-publication-packet-template.md` |
| OpenCode | `.opencode/agents/*`, `.opencode/commands/*` |
| Continue | `.continue/rules/*`, `docs/harnesses/doc-publication-packet/continue-config.template.yaml` |
| Human ops | `docs/runbooks/doc-publication-packet-playbook.md` |

## 8. 完了条件

- inventory refresh と packet rewrite の責務分離が明確
- OpenCode と Continue の両方に最小 adapter asset がある
- handoff / acceptance template が 1 箇所で参照できる
- #229 が packet backlog 実行から始められる
