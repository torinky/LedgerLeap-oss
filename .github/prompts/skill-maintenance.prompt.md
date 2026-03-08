---
description: Maintain LedgerLeap AI operating assets after every bug fix, sprint, or investigation. Sync prompts, skills, instructions, AGENTS, issue templates, and runbooks from newly proven learnings.
---

# skill-maintenance

## Goal

新しく確定した学びを、**どの `.github` 資産へ反映すべきかを分類し、必要なファイルをまとめて同期**する。

参照:
- [Agent Routing](../../AGENTS.md)
- [Repository Instructions](../copilot-instructions.md)
- [AI Asset Maintenance Playbook](../../docs/runbooks/ai-asset-maintenance-playbook.md)
- [Routing Matrix](../skills/skill-maintenance/references/routing.md)
- [Workflow & Quality Gate](../skills/skill-maintenance/references/workflow.md)
- [JetBrains / Copilot Support Notes](../skills/skill-maintenance/references/jetbrains-copilot-support.md)
- [Skill Inventory](../skills/skill-maintenance/references/skill-inventory.md)

## When to Run

- バグ修正で再利用可能な原因・回避策・実装パターンが確定したとき
- 既存 instruction / prompt / skill が誤っていたと分かったとき
- 新しい recurring workflow が 2 回以上出現したとき
- issue template / runbook / AGENTS の不足が判明したとき
- 同じ CI 調査コマンドや shell 手順で 2 回以上詰まったとき

## Routing Matrix

| Finding | Primary destination | Notes |
|---|---|---|
| 全体で効く短い事実 / 制約 | `.github/copilot-instructions.md` | 常設・短文のみ |
| path 固有のコード生成ルール | `.github/instructions/*.instructions.md` | 自動適用対象 |
| 人や AI が明示起動する手順 | `.github/prompts/*.prompt.md` | `/name` の入口 |
| 繰り返し使う判断木 / 診断知識 | `.github/skills/<name>/SKILL.md` | WHAT + WHEN を description に |
| 長い例 / 詳細手順 / deep refs | `.github/skills/<name>/references/*.md` | 1 topic / file |
| 定型入力の不足 | `.github/ISSUE_TEMPLATE/*` | 再現条件や証拠の標準化 |
| 運用フロー / 人間向け手順 | `docs/runbooks/*` | prompt から参照 |
| agent-wide routing / discovery rules | `AGENTS.md` | AI 最適化優先 |

## Maintenance Loop

1. **Collect**: 今回新しく確定した事実、失敗パターン、回避策、ワークフローを列挙する
2. **Classify**: 上の routing matrix で primary destination を決める
3. **Sync neighbors**: skill を変えたら prompt / instructions / `copilot-instructions.md` / `AGENTS.md` への反映要否も確認する
4. **Consolidate**: 重複するルールは 1 か所へ寄せ、他はリンクに置き換える
5. **Validate**: 行数制約、リンク、description、発見性、slash entrypoint を確認する
6. **Operationalize**: 同じところで詰まった CI / shell / `gh` 手順は、安定コマンド集として skill reference / prompt / runbook に同期する

## JetBrains / Copilot Rule

JetBrains では **prompt files と instructions が主導線**。skills は reusable deep knowledge として維持し、**prompt / AGENTS から発見できる状態**を保つ。

## Minimum Output

- 今回の学び一覧
- 変更すべき `.github` / `docs` ファイル一覧
- 追加 / 更新 / 削除の理由
- 同期漏れチェック結果
- follow-up 候補

## Final Checklist

- [ ] 学びごとに primary destination を決めた
- [ ] skill 以外の `.github` 資産も確認した
- [ ] prompt / skill / instructions / AGENTS の重複を減らした
- [ ] `copilot-instructions.md` は短く保った
- [ ] 新規 recurring workflow は skill 化または prompt 化した
