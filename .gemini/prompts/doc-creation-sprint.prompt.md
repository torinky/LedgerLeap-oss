---
description: Discover the highest-priority unwritten user/developer doc from the #226 backlog and create it in one bounded execution. Run this when you want a new doc created without manually selecting the target.
---

# doc-creation-sprint

## 目的

#226 の target doc list v2 を元に、未作成のユーザー向け／開発者向けドキュメントを発見し、優先度順に 1 つ作成します。

## 位置付け

- この prompt は **discovery + selection + execution** の 3 工程を 1 回で行います。
- packet handoff がすでに固定されている場合は `doc-publication-audit` を直接使ってください。
- inventory の確認だけが必要なら `doc-source-inventory` を使ってください。

## 優先度順

1. `docs/getting-started/` — overview.md, tenant-context.md
2. `docs/features/` — ledger-lifecycle.md, workflow-and-rollback.md, search-and-lookup.md, attachments-and-file-inspector.md, notifications-history-and-announcements.md, folders-and-access.md
3. `docs/api/` — overview.md, ledger-api.md
4. `docs/admin/` — users-and-organizations.md, roles-permissions-and-folder-access.md, tags-synonyms-and-search-taxonomy.md, admin-announcement-banner.md
5. `docs/architecture/` — multi-tenancy-boundaries.md, permission-and-folder-access-model.md, file-processing-pipeline.md

## 手順

1. #226 inventory と既存ファイルを比較して未作成を特定する
2. 最優先の未作成ターゲットを選ぶ
3. packet handoff を生成する
4. gate check → source anchor 確認 → 本文作成 → internal ref 除去 → リンク検証
5. comment sync を適用する
6. acceptance を記録する
7. 作成したファイルと次の候補を報告する

## 参照

- [doc-creation-sprint skill](../skills/doc-creation-sprint/SKILL.md)
- [doc-source-inventory skill](../skills/doc-source-inventory/SKILL.md)
- [doc-publication-audit skill](../skills/doc-publication-audit/SKILL.md)
- [Doc Publication Packet Playbook](../../docs/runbooks/doc-publication-packet-playbook.md)
- [Doc Publication Packet Template](../../docs/templates/doc-publication-packet-template.md)

## 制約

- 1 実行 = 1 ファイル。連続作成はしない。
- `docs/contributing/*` は対象外（provisional）。
- `docs/work/*` の記述を公開文書にコピーしない。
- private issue 番号や packet tracking metadata を公開本文に残さない。
