# Issue #228 close / #230 handoff retrospective

**作成日:** 2026-05-24
**対象:** #225, #228, #229, #230
**範囲:** publication packet workflow assets の確定、#228 完了、#230 follow-up sprint の起票と handoff 準備

## 1. 何を完了したか

- #228 の reusable asset 実装を完了し、owner review 後に close できる状態まで揃えた
- packet workflow 用の prompt / skill / agent / runbook / harness / adapter 一式を repo に追加した
- #228 review で出た追加要求を受け、doc format / evidence / PHPDoc source comment を扱う follow-up sprint #230 を起票した
- #225 umbrella と #229 pilot を #230 前提の順序へ同期した
- #230 を新セッションで進めるための evidence と handoff 材料を整理した

## 2. 良かったこと

### 技術要素
- `docs/work/issue-drafts/*` を canonical body にし、`gh issue edit --body-file ...` で full-body sync したので、issue とローカル正本のズレを抑えられた
- OpenCode と Continue.dev の違いを shared contract と adapter に分離したことで、#228 の asset 設計が明確になった
- doc-format 強化の根拠を Diataxis / Django / Kubernetes / Symfony / phpDocumentor / Doctum に分けて保持したため、#230 の議論を「好み」ではなく evidence ベースで始められる

### 作業の進め方
- #228 を閉じる前に owner review を待ち、review 後の差分だけを #230 として切り出したので、完了済み sprint と follow-up sprint を混ぜずに済んだ
- umbrella #225 と downstream #229 を同じ pass で更新したため、#230 を挟む新しい順序が一箇所だけでなく追跡全体に反映された
- issue body sync のたびに GitHub 上の本文を再取得して比較したため、コメントだけで進捗を伝えて本文が古いまま残る状態を避けられた

## 3. 悪かったこと

### 技術要素
- #228 の asset 実装時点では `doc_format_profile` と required sections まで固定しておらず、pilot の直前で追加 sprint が必要になった
- source comment 同期ルールを「必要最小限」に留めたため、class / method / PHPDoc tag の選定基準が #229 にはまだ不足していた

### 作業の進め方
- #228 review を受ける前に format/evidence/comment の深掘りを済ませていなかったので、review 後に sequencing を組み直す手間が発生した
- 新スプリントの必要性は早めに見えたが、最初のスコープ整理段階では #229 拡張と #230 新設のどちらで進めるかを明示し切れていなかった

## 4. 上書き指示されたこと

### 技術要素
- packet workflow は成立しているが、**各 document type の format を major project の best practice で補強する**方針に上書きされた
- source comment は任意補助ではなく、**PHP 既存ドキュメントシステムと整合する形で method / class に確実に残す運用**へ強化することになった

### 作業の進め方
- #229 にそのまま入るのではなく、review で見つかった gap は **Sprint 2-A3A (#230)** として先に固定してから pilot に進む順序へ上書きされた
- 追加要求はコメントで補足するだけでなく、canonical draft / umbrella / downstream issue を同じ pass で同期する進め方が必要になった

## 5. 直接修正したこと

- `/.github/prompts/doc-publication-packet.prompt.md`
- `/.github/skills/doc-source-inventory/SKILL.md`
- `/.github/skills/doc-publication-audit/SKILL.md`
- `/.github/skills/doc-publication-audit/references/packet-execution-assets.md`
- `/.github/agents/doc-source-inventory.agent.md`
- `/.github/agents/doc-packet-executor.agent.md`
- `.opencode/agents/*`
- `.opencode/commands/*`
- `.continue/rules/*`
- `docs/runbooks/doc-publication-packet-playbook.md`
- `docs/templates/doc-publication-packet-template.md`
- `docs/harnesses/doc-publication-packet/*`
- `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md`
- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a-doc-framework-body.md`
- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a4-pilot-body.md`
- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a3a-format-and-source-comment-body.md`

## 6. 失敗した案と、その案が違うと分かった証拠

### 6.1 失敗した案

- #228 の asset 一式があれば、そのまま #229 pilot に入れる
- source comment policy は packet ごとの判断に任せれば十分で、追加 sprint は不要
- Continue.dev の prompt entry は repo-local prompt discovery に寄せても問題ない

### 6.2 違うと分かった証拠

- user review で、doc type ごとの format、evidence、source comment の根拠不足が明示的に指摘された
- Kubernetes page templates と Django / Symfony docs standards を見ると、doc type ごとに section shape や書き分けがかなり明確で、現状 template では不足があった
- phpDocumentor と Doctum を見ると、source comment を PHPDoc 互換で残すほど後続の生成・IDE 利用と両立しやすく、曖昧な inline comment 運用より優位だった
- Continue 公式情報の再確認により、repo-local prompt discovery を前提にせず harness config と `.continue/rules/*` を主導線にした方が安全と判断できた

## 7. 学びの分類

| 学び | 技術 / 進め方 | 判定 | 次の置き場 | evidence |
|---|---|---|---|---|
| review で見つかった gap は Sprint N-A を切って upstream/downstream を同時更新する | 進め方 | reusable 既存ルールの再確認 | 既存の `github-issue-workflow` / `skill-maintenance` ルールで十分。今回は `docs/work/*` に evidence を残す | `docs/work/issue-drafts/2026-05-24_issue-sprint-2a-doc-framework-body.md`, #230 |
| doc-format-sensitive guidance は official-source evidence と freshness を持ってから packet asset に入れる | 技術 + 進め方 | reusable 候補 | まず `docs/work/*` に保持し、#230 実装後に `.github` へ昇格を判断 | `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md` |
| Continue packet prompts は harness config 経由に寄せる | 技術 | reusable（今回反映済み） | #228 asset として repo に反映済み | `docs/harnesses/doc-publication-packet/continue-config.template.yaml`, `/.continue/rules/*` |

## 8. 今回は `.github` へ追加昇格しないもの

- `doc_format_profile` の詳細 rule は #230 で実際に template / runbook / skill へ反映するまで、まだ local evidence 扱いに留める
- PHPDoc minimum rule も #230 で対象 class / method を通した実装前のため、現時点では retrospective と evidence メモに留める

## 9. 公式資料の鮮度メモ

| claim | status | last_confirmed_at | recheck_after | recheck_trigger |
|---|---|---|---|---|
| Diataxis / Django / Kubernetes / Symfony を根拠に packet doc format profile を設計できる | researched | 2026-05-24 | 90d | #230 着手時に upstream docs の section template や standards が大きく変わっていたとき |
| phpDocumentor / Doctum を根拠に PHPDoc minimum rule を設計できる | researched | 2026-05-24 | 90d | #230 で source comment policy を確定する前に PHPDoc tag guidance が更新されていたとき |

## 10. 次回の開始条件

- #230 では `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md` を起点に、packet template / runbook / skill / prompt / adapter へ必要最小限だけ反映する
- #229 の pilot 実行や packet backlog freeze へは脱線せず、format / evidence / source comment policy の固定に集中する
- GitHub 側では #225 と #229 が #230 前提に同期済みなので、新セッションでは本文の再整理から始めなくてよい
