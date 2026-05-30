---
description: Maintain LedgerLeap AI operating assets after every bug fix, sprint, investigation, or user-requested retrospective. Sync prompts, skills, instructions, AGENTS, issue templates, and runbooks from newly proven learnings.
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
- [Evidence & Freshness](../skills/skill-maintenance/references/evidence-freshness.md)
- [JetBrains / Copilot Support Notes](../skills/skill-maintenance/references/jetbrains-copilot-support.md)
- [Skill Inventory](../skills/skill-maintenance/references/skill-inventory.md)

## When to Run

- すべての作業（不具合対応、機能検討、調査、実装、スプリント、ドキュメント更新）が完了したとき
- issue / sprint 完了後に、学びを整理して残したいとき
- ユーザーから明示的に「振り返りをしてください」と指示されたとき
- 既存 instruction / prompt / skill が誤っていたと分かったとき
- 新しい recurring workflow が 2 回以上出現したとき
- issue template / runbook / AGENTS の不足が判明したとき
- 同じ CI 調査コマンドや shell 手順で 2 回以上詰まったとき
- MCP / API tool description から process guidance を capability / docs 側へ移し、受け皿の確認が必要になったとき

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

1. **Collect**: 今回新しく確定した事実、失敗パターン、回避策、失敗した操作、採用しなかった案、ワークフローを列挙する。issue / sprint 完了後や retrospective 指示時は、実装結果とは別に「学び」だけを取り出す
   - 作業開始前に `pwd` と `git rev-parse --show-toplevel` を実行し、現在地と Git ルートを確定する。WSL / Mac でパスが違う前提なので、ここで食い違ったら作業を止める
2. **Two-layer review**: 学びは必ず 2 層で整理する
   - 進め方の改善: 対象レイヤーの固定、証拠順序、仮説比較、検証ゲート、手戻りの防止
   - 個別具体の手法改善: 使ったコマンド、設定、UI 変更、テンプレート、文言、実装パターン
3. **Classify**: 上の routing matrix で primary destination を決める
4. **Sync neighbors**: skill を変えたら prompt / instructions / `copilot-instructions.md` / `AGENTS.md` への反映要否も確認する
5. **Consolidate**: 重複するルールは 1 か所へ寄せ、他はリンクに置き換える
6. **Evidence**: durable claim ごとに、repo 証拠または official source への到達点を記録する
7. **Validate**: 行数制約、リンク、description、発見性、slash entrypoint、evidence reachability に加えて、`tool = contract` / `capability = flow` / `docs = rationale` の分離を確認する
8. **Operationalize**: 同じところで詰まった CI / shell / `gh` 手順は、安定コマンド集として skill reference / prompt / runbook に同期する
9. **Recheck**: doc-sensitive guidance には `last_confirmed_at` と `recheck_after` を付け、期限超過または同領域変更時に再確認する

## JetBrains / Copilot Rule

JetBrains では **prompt files と instructions が主導線**。skills は reusable deep knowledge として維持し、**prompt / AGENTS から発見できる状態**を保つ。

## Minimum Output

- 今回の学び一覧
- 変更すべき `.github` / `docs` ファイル一覧
- 追加 / 更新 / 削除の理由
- 学びごとの evidence link / evidence location
- doc-sensitive guidance の `last_confirmed_at` / `recheck_after` / `status`
- tool description を削る場合の移送先と、受け皿確認結果
- 同期漏れチェック結果
- follow-up 候補

## Final Checklist

- [ ] 学びごとに primary destination を決めた
- [ ] skill 以外の `.github` 資産も確認した
- [ ] prompt / skill / instructions / AGENTS の重複を減らした
- [ ] `copilot-instructions.md` は短く保った
- [ ] 新規 recurring workflow は skill 化または prompt 化した
- [ ] tool description を slim 化した場合、capability / discovery 側に同等の client-facing 情報が残っている
- [ ] durable claim に evidence があり、次の担当者が辿れる
- [ ] doc-sensitive guidance に `last_confirmed_at` / `recheck_after` がある
