# doc-creation-sprint skill 追加

## 日付
2026-05-27

## 背景
- ユーザー向けドキュメントを作成したいが、ターゲットを自分で選ぶ手間をかけたくない
- `doc-publication-audit` は packet handoff が固定済みであることを前提としており、ゼロから doc を作るには inventory 確認 → target 選択 → handoff 生成 → rewrite の 4 工程を手動で行う必要があった
- `doc-source-inventory` は writing を想定しておらず、read-only で delta 確認のみ

## 変更内容
### 新規作成
| ファイル | 内容 |
|---|---|
| `.github/skills/doc-creation-sprint/SKILL.md` | discovery → selection → execution を 1 回で行う skill |
| `.github/agents/doc-creation-sprint.agent.md` | Copilot subagent 定義 |
| `.github/prompts/doc-creation-sprint.prompt.md` | JetBrains / Copilot 用 entrypoint |
| `.opencode/commands/doc-creation-sprint.md` | OpenCode 用 command |
| `.continue/rules/03-doc-creation-sprint.md` | Continue 用 rule |

### 修正
| ファイル | 内容 |
|---|---|
| `.github/skills/doc-publication-audit/SKILL.md` | description を "Create or rewrite one stable public-facing doc..." に変更 |
| `AGENTS.md` | Domain Entry Points + Doc publication packet routing に doc-creation-sprint を追加 |
| `docs/runbooks/doc-publication-packet-playbook.md` | 役割マップ/関連資料/lane/最短判断/asset set/entrypoint に doc-creation-sprint を追加 |
| `docs/runbooks/README.md` | slash entrypoints に doc-creation-sprint を追加 |

## 設計判断
1. `doc-creation-sprint` は `doc-publication-audit` を内部で呼ぶ上位レイヤーとして設計。rewrite の詳細は audit に委譲し、sprint は discovery + handoff 生成 + gate check のみを独自に持つ
2. 優先度順は #226 の target doc list v2 の doc_area 順に従う: getting-started > features > api > admin > architecture
3. `docs/contributing/*` は #226 で provisional とされているため対象外
4. 1 実行 = 1 ファイルに固定。連続作成は意図的に禁止
5. 全エージェント（JetBrains / OpenCode / Continue）で同じフローを実行できるよう adapter asset を揃えた

## 次の候補バックログ (未作成 doc)
- getting-started: overview.md, tenant-context.md
- features: ledger-lifecycle.md, workflow-and-rollback.md, search-and-lookup.md, attachments-and-file-inspector.md, notifications-history-and-announcements.md, folders-and-access.md
- api: overview.md, ledger-api.md
- admin: (directory ごと未作成) 4 files
- architecture: multi-tenancy-boundaries.md, permission-and-folder-access-model.md, file-processing-pipeline.md

## Freshness
- last_confirmed_at: 2026-05-27
- recheck_after: 180d
