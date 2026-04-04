# AI運用資産メンテナンス・プレイブック

不具合調査、実装、スプリント作業のあとに得た学びを、LedgerLeap の AI 運用資産へ反映するための標準手順です。

関連資料:
- [skill-maintenance prompt](/.github/prompts/skill-maintenance.prompt.md)
- [skill-maintenance skill](/.github/skills/skill-maintenance/SKILL.md)
- [Bug Response Playbook](/docs/runbooks/bug-response-playbook.md)
- [Routing Matrix](/.github/skills/skill-maintenance/references/routing.md)
- [Evidence & Freshness](/.github/skills/skill-maintenance/references/evidence-freshness.md)
- [Agent Routing](/AGENTS.md)

---

## 1. 目的

次を毎回同じ順で行う。

1. 今回の学びを列挙する
2. どのファイルへ反映すべきかを決める
3. 学びごとの evidence と鮮度情報を残す
4. 近接する `.github` 資産へ同期する
5. 古くなった指示を削除する
6. 発見性と再利用性を確認する

完了時の振り返りは、`docs/work/*` にまず evidence と反省点を残し、再利用可能性が確定したものだけを `.github` に昇格させる。

---

## 2. どこへ書くか

| 学びの種類 | 主な反映先 |
|---|---|
| repo 全体で効く短い制約 | `.github/copilot-instructions.md` |
| path 固有の自動適用ルール | `.github/instructions/*.instructions.md` |
| slash で起動するワークフロー | `.github/prompts/*.prompt.md` |
| 再利用可能な判断木 / 診断知識 | `.github/skills/<name>/SKILL.md` |
| 長い例 / 詳細手順 / 深い資料 | `.github/skills/<name>/references/*.md` |
| 再利用可能な評価ハーネス / fixture / sanitized template | `docs/harnesses/*` |
| 入力テンプレート不足 | `.github/ISSUE_TEMPLATE/*` |
| 人向けの運用手順 | `docs/runbooks/*` |
| agent 全体の routing / 発見規則 | `AGENTS.md` |

---

## 3. 標準フロー

### Completion / Retrospective Trigger

次のタイミングでは、この playbook を優先して使う。

- issue が完了したとき
- sprint が完了したとき
- ユーザーから明示的に「振り返りをしてください」と指示されたとき

この場合は、実装内容そのものよりも、次の観点を先に集める。

- 何がうまくいったか
- どこで認識の齟齬や手戻りが起きたか
- 次回のために再利用できるか
- その学びを `docs/work/*` に留めるか、`.github` へ昇格するか

## Step 1 — Collect

最低限、次を列挙する。

- 新しく確定した事実
- 再発しそうな失敗パターン
- 有効だった回避策
- 間違っていた既存ルール
- 足りなかったテンプレートや導線

## Step 2 — Classify

各学びについて primary destination を 1 つ決める。

ポイント:
- 最初から複数ファイルへ同じ文を貼らない
- まず 1 か所を source of truth にする

## Step 3 — Update Primary Destination

主反映先を更新する。

- `copilot-instructions.md` は短い全体制約だけ
- prompt は起動入口
- skill は再利用知識
- runbook は人向けの説明
- AGENTS は routing と discovery
- MCP / API tool description を slim 化する場合は、先に `resources/ai/capabilities/*.yaml` または discovery docs に client-facing flow の受け皿を作ってから削る

## Step 4 — Attach Evidence and Freshness

各 durable claim について次を残す。

- repo 実装証拠の置き場（通常は `docs/work/*`）
- official source の要約置き場（通常は `references/*.md`）
- `last_confirmed_at`
- `recheck_after`
- 期限前でも再確認すべき trigger

目安:
- official docs 依存の主張 → `90d`
- 推測を含む暫定主張 → `30d`
- repo 内で完結する運用パターン → `180d`

### Durable learning の具体例

今回の `Issue #114` では、次の分離が再利用可能な運用パターンとして確定した。

- backend の回帰検知: `performance-YYYY-MM-DD.log` / `performance_stats.json`
- frontend の体感測定: `browser.log` / DevTools Console

この種の分離ルールは、`docs/work/*` に実装根拠を残したうえで、`docs/operations/*` に日常運用手順として反映し、必要なら prompt / skill / runbook に同期する。

## Step 5 — Sync Neighbors

主反映先を更新したら、次の近接資産を確認する。

- prompt を変えた → 対応 skill / runbook / issue template も必要か
- skill を変えた → prompt / AGENTS / inventory も必要か
- instructions を変えた → `copilot-instructions.md` に要約すべきか
- issue template を変えた → prompt の参照先も変えるべきか
- routing を変えた → `AGENTS.md` と maintenance 資料を同期すべきか

## Step 6 — Consolidate

次を確認する。

- もう不要な古い記述が残っていないか
- 同じルールが複数箇所に重複していないか
- 用語が prompt と skill でズレていないか
- tool description を削った場合、同等の client-facing 情報が capability / discovery / generated skill 側で再取得できるか
- evidence link が切れていないか、または再確認期限が切れたまま放置されていないか

## Step 7 — Validate

- リンクが解決する
- slash entrypoint がある
- skill description が WHAT / WHEN を含む
- `copilot-instructions.md` が肥大化していない
- JetBrains で prompt 主導になっている
- evidence から元資料へ辿れる
- `today > last_confirmed_at + recheck_after` の主張が残っていない

---

## 4. 完了条件

- [ ] 学びごとに primary destination を決めた
- [ ] `.github` 以外を含む neighbor sync を確認した
- [ ] 古い / 重複した記述を整理した
- [ ] 新しい recurring workflow は prompt または skill に昇格した
- [ ] `AGENTS.md` の routing と矛盾しない
- [ ] tool description の slim 化では、削除前に受け皿資産を更新した
- [ ] reusable claim に evidence と再確認期限がある

---

## 5. 推奨運用

- バグ修正の完了前に `/skill-maintenance` を実行する
- 新しい skill を作ったら、JetBrains 用に対応 prompt も用意する
- `docs/runbooks/bug-response-playbook.md` の Learnings ステップと矛盾しないように運用する
- `.github` は人間向け説明よりも **AI が迷わない routing と発見性** を優先する
