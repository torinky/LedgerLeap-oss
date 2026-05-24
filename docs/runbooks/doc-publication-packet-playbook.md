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
- [Issue #230 evidence](../work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md)
- [Local LLM MCP Setup Guide](./local-llm-mcp-setup.md)

## 1. 何を packet と呼ぶか

- packet は **1 public target file** を処理する bounded task
- baseline は #226 の feature families / target doc list v2 / comment anchor candidates
- contract は #227 の packet schema v1 / handoff / acceptance / run profile
- adapter asset は #228 で追加した prompt / skill / agent / OpenCode / Continue / runbook

## 1.5 役割マップ

| Layer | Primary asset | 役割 | ここから先へ渡す先 |
|---|---|---|---|
| Entry | `/doc-publication-packet` | lane 選定と handoff 整理 | `doc-source-inventory` / `doc-publication-audit` / comment sync lane |
| Inventory | `doc-source-inventory` | #226 baseline と packet readiness の差分確認 | packet handoff / downstream issue |
| Execution | `doc-publication-audit` | handoff 済み 1 packet の rewrite / comment sync | target doc / acceptance / issue handoff |
| Contract | `docs/templates/doc-publication-packet-template.md` | manifest / handoff / acceptance の SoT | すべての lane |
| Adapter | `docs/harnesses/doc-publication-packet/continue-config.template.yaml` | `packet-plan` / `packet-rewrite` / `packet-comment-sync` の責務反映 | Continue / local model 実行 |

- `/doc-publication-packet` と `doc-publication-audit` は同じことをしない
- 前者は **router**, 後者は **executor**
- packet handoff が確定済みなら `/doc-publication-packet` を毎回経由する必要はない

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

## 3. `doc_format_profile` を先に固定する

packet rewrite を始める前に、`doc_type` と同じ 4 種の `doc_format_profile` を 1 つ選ぶ。

| Profile | Required sections | Optional sections | Guardrails |
|---|---|---|---|
| `tutorial` | `summary`, `goal`, `prerequisites`, `steps`, `verification`, `next_steps` | `cleanup`, `troubleshooting`, `related_links` | learner-first、順序固定、reference 混在を避ける |
| `how-to` | `summary`, `goal`, `prerequisites`, `procedure`, `verification` | `troubleshooting`, `rollback`, `related_links` | result-first、最短手順、理由説明は最小限 |
| `reference` | `summary`, `contract_or_surface`, `parameters_or_fields`, `responses_or_effects`, `constraints`, `related_sources` | `examples`, `failure_modes`, `change_history` | contract-first、背景説明を混ぜない |
| `explanation` | `summary`, `problem`, `context`, `decision`, `tradeoffs`, `related_links` | `alternatives`, `faq`, `next_steps` | why-first、手順書にしない |

- section 名は packet contract の stable token として扱い、公開 doc 側の見出し文言は target audience に合わせて調整する
- required sections は packet handoff / acceptance に転記し、AI が毎回構造を発明しないようにする
- `style_guardrails` には active voice, realistic examples, format-order など profile ごとの書き方制約を短く残す

## 4. packet evidence の最低項目

どの lane でも、少なくとも次を packet handoff / acceptance に残す。

| Field | Rule |
|---|---|
| `doc_format_profile` | 4 profile から 1 つ選ぶ |
| `required_sections` / `optional_sections` | profile から転記する |
| `external_evidence_urls` | major OSS docs / official docs の根拠を 1 つ以上残す |
| `last_confirmed_at` | official-doc-sensitive claim の確認日を残す |
| `recheck_after` | 既定 90d。別ルールがあれば明記する |
| `source_anchor` | public docs を裏打ちする code/test/comment anchor を残す |
| `comment_sync_decision` | `required` / `optional` / `not_applicable` の結論を残す |

- test がある packet は `test_anchor` も残す
- comment sync を deferred にする場合は `defer_reason` を acceptance に残す
- #229 の pilot では上の最低項目が揃っていない packet を対象にしない
- comment sync 方針そのものを検証する pilot では、`comment_sync_policy = not_applicable` と `required` の packet を最低 1 件ずつ含める
  - Evidence: [Issue #229 retrospective](../work/2026-05-24_issue-229-retrospective.md)

## 5. source comment policy

- comment sync は **packet の `comment_anchors` と `source_anchor` に限定** し、repo-wide sweep にしない
- 優先対象は public docs の本文で直接説明する `controller action`, `Livewire public method`, `service method`, `MCP/API tool`, `stable DTO/value object`
- `private` helper, boilerplate accessor, migration-only code, docs 本文に出てこない内部詳細は対象外にする
- `comment_sync_policy = not_applicable` の packet では理由だけ残し、新しい comment work を発明しない

### PHPDoc minimum rule

| Target | Minimum |
|---|---|
| class / interface / trait | short summary。`@api` は stable public contract surface のみ候補 |
| public method used as source anchor | short summary、complex inputs には `@param`、non-void / structured outputs には `@return`、observable failure modes には `@throws` |

- DocBlock order は `summary -> description -> tags`
- signature で十分な trivial scalar は無理に冗長化しない
- packet 専用タグは導入せず、phpDocumentor / Doctum 互換を保つ

## 6. 共通ルール

1. `pwd` と `git rev-parse --show-toplevel` で現在地を確認する
2. REST API と MCP contract を同じ packet に混ぜない
3. `docs/contributing/*` は provisional queue のまま扱う
4. main conversation には raw source dump を残さず summary-first で進める
5. write phase は常に 1 writer に固定する
6. packet handoff / acceptance は template に合わせる

## 7. JetBrains / Copilot entrypoint

1. `/doc-publication-packet` で lane を決める
2. inventory refresh が必要なら `doc-source-inventory`
3. packet rewrite なら `doc-publication-audit`
4. rewrite 前に `doc_format_profile`, required sections, evidence fields, comment sync scope を固定する
5. 終了時は issue body / handoff / runbook への影響を確認する

### 最短判断

- **まだ何を使うか迷う** → `/doc-publication-packet`
- **backlog / anchor / readiness を見直す** → `doc-source-inventory`
- **1 packet の rewrite に入る** → `doc-publication-audit`
- **comment だけ更新する** → comment sync lane

## 8. OpenCode adapter

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

## 9. Continue.dev adapter

### Asset set

- `.continue/rules/01-doc-packet-core.md`
- `.continue/rules/02-doc-packet-comment-sync.md`
- `docs/harnesses/doc-publication-packet/continue-config.template.yaml`

### 使い分け

- `.continue/rules/*` は packet core rule と comment sync rule を分ける
- Continue prompt blocks は harness の `config.template.yaml` に置き、unsupported な repo-local prompt discovery を仮定しない
- `Plan` mode は inventory / anchor read、`Agent` mode は single writer の rewrite に使う

## 10. 2-A4 に渡す最小 asset set

| Layer | File |
|---|---|
| JetBrains entry | `.github/prompts/doc-publication-packet.prompt.md` |
| Inventory refresh | `.github/skills/doc-source-inventory/SKILL.md`, `.github/agents/doc-source-inventory.agent.md` |
| Packet rewrite | `.github/skills/doc-publication-audit/SKILL.md`, `.github/agents/doc-packet-executor.agent.md`, `docs/templates/doc-publication-packet-template.md` |
| OpenCode | `.opencode/agents/*`, `.opencode/commands/*` |
| Continue | `.continue/rules/*`, `docs/harnesses/doc-publication-packet/continue-config.template.yaml` |
| Human ops | `docs/runbooks/doc-publication-packet-playbook.md` |

## 11. 完了条件

- inventory refresh と packet rewrite の責務分離が明確
- OpenCode と Continue の両方に最小 adapter asset がある
- handoff / acceptance template が 1 箇所で参照できる
- `doc_format_profile`, evidence fields, PHPDoc minimum rule が shared packet contract に反映されている
- #229 が packet backlog 実行から始められる
