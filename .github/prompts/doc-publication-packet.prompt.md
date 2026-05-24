---
description: Plan or execute one LedgerLeap publication packet using the shared packet contract, inventory-refresh workflow, and packet rewrite workflow.
---

# doc-publication-packet

## 目的

#226 / #227 / #228 の成果を前提に、1 packet を **inventory refresh / packet rewrite / comment sync** のどれとして扱うかを決め、必要な skill / agent / runbook へつなぐ。

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

## 必要入力

- `packet_id` または `target_slug`
- `target_path`
- `audience` / `doc_type`
- `source_paths`, `code_anchors`, `test_anchors`
- `comment_sync_policy`
- `must_exclude`

## 出力

- chosen lane: inventory refresh / packet rewrite / comment sync
- 読むべきファイル一覧
- 更新対象の asset / doc
- 残リスクと follow-up

## 参照

- [doc-source-inventory](../skills/doc-source-inventory/SKILL.md)
- [doc-publication-audit](../skills/doc-publication-audit/SKILL.md)
- [Doc Publication Packet Playbook](../../docs/runbooks/doc-publication-packet-playbook.md)
- [Doc Publication Packet Template](../../docs/templates/doc-publication-packet-template.md)

## 使い方

この prompt で packet lane を決めたあと、inventory 側は [doc-source-inventory](../skills/doc-source-inventory/SKILL.md)、rewrite 側は [doc-publication-audit](../skills/doc-publication-audit/SKILL.md) と packet playbook に引き継ぐ。
