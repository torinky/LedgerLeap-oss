---
description: Plan or execute one LedgerLeap publication packet using the shared packet contract, inventory-refresh workflow, and packet rewrite workflow.
---

# doc-publication-packet

## 目的

#226 / #227 / #228 の成果を前提に、1 packet を **inventory refresh / packet rewrite / comment sync** のどれとして扱うかを決め、必要な skill / agent / runbook へつなぐ。

## 位置付け

- この prompt は **router / triage entrypoint**。
- lane を選んで handoff を固めるところまでが責務。
- packet 本文の rewrite 主担当は [doc-publication-audit](../skills/doc-publication-audit/SKILL.md)。
- #226 baseline の差分確認が必要なら [doc-source-inventory](../skills/doc-source-inventory/SKILL.md) を使う。

## 使う場面

- `docs/getting-started/*`, `docs/features/*`, `docs/admin/*`, `docs/api/*`, `docs/architecture/*` の 1 ファイルを bounded task で扱いたい
- #226 の source-derived inventory が古いか、packet readiness だけを更新したい
- #227 の packet schema を使って 1 packet を実行したい
- OpenCode / Continue.dev / JetBrains のどこから始めても同じ packet contract を使いたい

## 判定順

1. #226 baseline を更新したいか確認する
   - family / doc area / anchor delta を見直すなら [doc-source-inventory](../skills/doc-source-inventory/SKILL.md)
2. packet がすでに定義済みか確認する
   - `packet_id`, `target_path`, `comment_sync_policy` が決まっているなら [doc-publication-audit](../skills/doc-publication-audit/SKILL.md)
3. docs 本文ではなく comment anchor だけが対象か確認する
   - `comment_sync_policy` に従って comment sync lane へ分ける
4. REST API と MCP contract は同じ `docs/api/*` でも別 packet に保つ
5. `docs/contributing/*` は provisional queue のまま扱い、通常 packet backlog へ混ぜない

## ルーティング境界

| 状態 | 使うもの |
|---|---|
| lane も packet handoff も未確定 | `/doc-publication-packet` |
| #226 baseline や packet readiness を見直したい | `doc-source-inventory` |
| packet handoff が確定済みで 1 target file を rewrite したい | `doc-publication-audit` |
| docs 本文は触らず comment anchor だけ更新したい | comment sync lane |

## 必要入力

- `packet_id` または `target_slug`
- `target_path`
- `audience` / `doc_type`
- `doc_format_profile`
- `source_paths`, `code_anchors`, `test_anchors`
- `comment_sync_policy`
- `must_exclude`

## 出力

- chosen lane: inventory refresh / packet rewrite / comment sync
- selected `doc_format_profile`
- required sections / optional sections
- evidence fields (`external_evidence_urls`, `last_confirmed_at`, `source_anchor`, `comment_sync_decision`)
- PHPDoc minimum rule / defer reason
- 読むべきファイル一覧
- 更新対象の asset / doc
- 残リスクと follow-up

## 参照

- [doc-source-inventory](../skills/doc-source-inventory/SKILL.md)
- [doc-publication-audit](../skills/doc-publication-audit/SKILL.md)
- [Doc Publication Packet Playbook](../../docs/runbooks/doc-publication-packet-playbook.md)
- [Doc Publication Packet Template](../../docs/templates/doc-publication-packet-template.md)

## 使い方

この prompt で packet lane を決めたあと、inventory 側は [doc-source-inventory](../skills/doc-source-inventory/SKILL.md)、rewrite 側は [doc-publication-audit](../skills/doc-publication-audit/SKILL.md) と packet playbook に引き継ぐ。rewrite に入る前に `doc_format_profile`, required sections, evidence fields, comment sync scope を確定する。**packet handoff がすでに確定している場合は、この prompt を経由せず `doc-publication-audit` に直接入ってよい。**
