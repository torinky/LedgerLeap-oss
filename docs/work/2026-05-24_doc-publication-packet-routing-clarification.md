# Doc publication packet routing clarification

- status: confirmed-repo
- last_confirmed_at: 2026-05-24
- recheck_after: 180d
- recheck_trigger: packet prompt / skill / runbook / harness responsibilities drift, or a new adapter adds its own packet entrypoint

## 問題

`/doc-publication-packet` と `doc-publication-audit` の両方が packet 実行に触れているため、**entrypoint なのか executor なのか**が読み手によって曖昧になりやすい。

## 決定

| Asset | 役割 | やること | やらないこと |
|---|---|---|---|
| `/doc-publication-packet` | router / triage entrypoint | lane 選定、必要入力の確認、handoff 先の決定 | 本文 rewrite の主担当にならない |
| `doc-source-inventory` | inventory refresh skill | #226 baseline との差分確認、packet readiness の更新 | packet rewrite をしない |
| `doc-publication-audit` | single-packet executor skill | handoff 済み packet 1 件の rewrite / comment sync | lane 選定や inventory refresh をしない |
| `docs/templates/doc-publication-packet-template.md` | contract SoT | manifest / handoff / acceptance の記録形を固定 | routing の説明を主担当にしない |
| `docs/runbooks/doc-publication-packet-playbook.md` | human / AI ops flow | 上の役割を順番に実行する手順を説明 | skill の詳細ルールを重複保持しない |
| `docs/harnesses/doc-publication-packet/continue-config.template.yaml` | adapter mirror | `packet-plan` / `packet-rewrite` / `packet-comment-sync` の責務を adapter に写す | repo 固有の長い運用説明を持ち込まない |

## 推奨フロー

1. まず `/doc-publication-packet` で **lane を決める**
2. backlog / anchor / readiness を見直すなら `doc-source-inventory`
3. packet handoff が固まっているなら `doc-publication-audit`
4. docs 本文は触らず comment だけなら comment-sync lane
5. 実行時の handoff / acceptance は packet template を使う
6. 人間と adapter の運用順は runbook / harness を参照する

## 判断基準

- **packet handoff が未確定** → router 側 (`/doc-publication-packet` または `doc-source-inventory`)
- **packet handoff が確定済み** → executor 側 (`doc-publication-audit`)
- **comment だけ** → comment-sync lane

## 反映先

- `/.github/prompts/doc-publication-packet.prompt.md`
- `/.github/skills/doc-publication-audit/SKILL.md`
- `docs/runbooks/doc-publication-packet-playbook.md`
- `docs/harnesses/doc-publication-packet/continue-config.template.yaml`
- `AGENTS.md`
