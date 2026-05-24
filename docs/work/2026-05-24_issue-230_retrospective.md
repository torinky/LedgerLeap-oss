# Issue #230 retrospective

- status: local
- last_confirmed_at: 2026-05-24
- recheck_after: 90d

## 良かったこと

### 技術要素
- `doc_format_profile`, evidence fields, PHPDoc minimum rule を先に `docs/work/*` の SoT に固定してから template / runbook / prompt / adapter へ流したことで、同じ規約を複数ファイルで書き直さずに済んだ。
- packet template と playbook に同じ section matrix を置いたことで、JetBrains / OpenCode / Continue の adapter から同じ contract を参照できる形に揃った。
- `/doc-publication-packet` を router、`doc-publication-audit` を single-packet executor と明文化したことで、entrypoint と rewrite 本体の責務が分離された。

### 作業の進め方
- handoff の「primary destination first」を守って evidence doc を先に更新したため、後続の issue body / adapter sync が迷いにくかった。
- #225 と #229 の下書きを同じ pass で更新し、#230 完了後の sequencing drift を残さずに済んだ。
- routing の曖昧さを指摘された時点で prompt / skill / runbook / harness / AGENTS を同じ pass で見直したことで、局所修正で終わらず全体の導線を揃えられた。

## 悪かったこと

### 技術要素
- #228 の時点では `doc_format_profile` と evidence fields を最小ルールのまま残していたため、pilot 直前に追加 sprint が必要になった。
- `/doc-publication-packet` と `doc-publication-audit` の境界を early に明文化していなかったため、後から router / executor の役割整理が必要になった。

### 作業の進め方
- shared asset だけ更新して issue 下書きを後回しにすると、完了判定と next sprint handoff がずれる危険があった。

## 上書き指示されたこと

### 技術要素
- なし。

### 作業の進め方
- なし。

## 修正・エビデンス

- Evidence SoT: `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md`
- Routing clarification: `docs/work/2026-05-24_doc-publication-packet-routing-clarification.md`
- Shared packet assets:
  - `docs/templates/doc-publication-packet-template.md`
  - `docs/runbooks/doc-publication-packet-playbook.md`
  - `/.github/prompts/doc-publication-packet.prompt.md`
  - `/.github/skills/doc-publication-audit/SKILL.md`
- Adapter sync:
  - `.continue/rules/01-doc-packet-core.md`
  - `.continue/rules/02-doc-packet-comment-sync.md`
  - `.opencode/commands/packet-plan.md`
  - `.opencode/commands/packet-rewrite.md`
  - `.opencode/commands/packet-comment-sync.md`
  - `docs/harnesses/doc-publication-packet/continue-config.template.yaml`
  - `/.github/agents/doc-packet-executor.agent.md`
  - `.opencode/agents/doc-packet-executor.md`

## 学び（ reusable / local / retire ）

- `reusable` / 技術要素: publication packet の format/evidence/comment policy は `docs/work/*` の evidence SoT → template/runbook → prompt/skill/adapter の順で同期すると衝突が少ない。
- `reusable` / 技術要素: publication packet 系 asset は `prompt = router`, `skill = executor`, `runbook = sequence`, `harness = adapter mirror` を先に固定しておくと重複や責務混線を避けやすい。
- `reusable` / 作業の進め方: sprint 完了と downstream sprint handoff は同じ pass で更新し、umbrella issue に handover section を追加しておく。
- `local` / 技術要素: #229 の pilot packet 候補の最終選定はまだ未確定なので、候補比較そのものは #230 では固定しない。
