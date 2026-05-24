# Sprint 2-A3A: publication packet の文書フォーマット規約と PHPDoc ソースコメント連携の補強

## GitHub 追跡
- Umbrella: #225
- Upstream assets: #228
- Sprint 2-A3A: #230（本 Issue）
- Downstream pilot: #229
- Downstream implementation: #219

## 概要
Sprint 2-A3 で packet 実行用の reusable asset は整ったが、harness / template / prompt から呼ばれる各 doc format の section 形と、source から docs を裏打ちする PHPDoc comment 連携はまだ弱い。#229 の pilot に入る前に、major OSS docs の形式知と PHP ドキュメント生成系の標準に寄せて補強する。

## 背景 / 目的
- 2-A3 時点では `publication packet` の lane と adapter は整ったが、`tutorial`, `how-to`, `reference`, `explanation` ごとの required section がまだ固定されていない
- evidence の残し方が packet ごとに揺れると、なぜその構成にしたかを後続 sprint が再判断する必要がある
- public docs の説明元になる method / class に comment が残らないと、外部 doc と source の説明が再び乖離する
- phpDocumentor / Doctum 互換の PHPDoc 形に寄せておけば、将来の API doc 再生成や IDE 補助とも衝突しにくい

## 現状
- #226 で source-derived inventory / target doc list v2 / comment anchor candidate が確定した
- #227 で packet schema / handoff / acceptance / OpenCode-Continue run profile が確定した
- #228 で prompt / skill / agent / runbook / harness の最小資産が追加された
- ただし #228 では doc format profile と source comment policy は「最小ルール」に留まり、major project ベースの section 規約までは固定していない
- #229 は pilot sprint だが、その前に format と comment scope を 1 段固めた方が pilot のやり直しを減らせる

## 目標 / 完了状態
- packet 実行で使う `doc_format_profile` が major project docs を根拠に定義されている
- harness / template / runbook から required sections と evidence fields が呼べる
- source comment 対象 class / method の選定基準と PHPDoc minimum tags が決まっている
- #229 は format / evidence / source comment の前提が固定された状態で pilot に入れる

## スコープ / 非スコープ
### 対象
- major OSS docs の format / section / style evidence 収集と要約
- packet template / harness / runbook / skill に入れる doc format profile の設計
- source comment 対象と PHPDoc minimum rule の設計
- #225 / #229 への sequencing 反映

### 対象外
- pilot packet 自体の実行
- public docs 本文の大量作成
- repo 全体への一括 PHPDoc 追加
- Doctum / phpDocumentor の本番導入そのもの

## 方針候補 / メモ
1. doc type は Diataxis を土台にしつつ、Kubernetes page templates の section 形を参考に packet template に落とす
2. `tutorial`, `how-to`, `reference`, `explanation` の各 profile で required / optional section を分ける
3. Symfony docs standards を参考に、sample code は実運用に見える名前・文脈を使い、`foo` / `bar` を避ける
4. source comment は「public docs の根拠になる class / method」のみに限定し、1 packet で bounded に更新する
5. PHPDoc は phpDocumentor 互換を基本にし、少なくとも summary と必要な `@param`, `@return`, `@throws` を揃える
6. stable public contract に近い surface は `@api` の採否も判断対象に含める

## 想定アウトプット
- doc format profile matrix
- packet evidence field matrix
- PHPDoc minimum rule for packet comment sync
- updated packet template / runbook / prompt / rules / command references
- #229 pilot 前提の sequencing update

## スプリント分解
- [x] major project docs / PHPDoc 系の外部根拠を整理する
  - Evidence: `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md`
- [x] packet の doc format profile と required sections を定義する
  - Evidence: `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md`, `docs/templates/doc-publication-packet-template.md`, `docs/runbooks/doc-publication-packet-playbook.md`
- [x] source comment 対象と PHPDoc minimum rule を定義する
  - Evidence: `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md`, `.continue/rules/02-doc-packet-comment-sync.md`, `.github/skills/doc-publication-audit/SKILL.md`
- [x] #225 / #229 / packet asset へ反映し、pilot 前提を固定する
  - Evidence: `docs/work/issue-drafts/2026-05-24_issue-sprint-2a-doc-framework-body.md`, `docs/work/issue-drafts/2026-05-24_issue-sprint-2a4-pilot-body.md`, `.github/prompts/doc-publication-packet.prompt.md`, `.opencode/commands/packet-plan.md`

## エビデンス / 参照先
- `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md`
- Diataxis — https://diataxis.fr/
- Django writing documentation — https://docs.djangoproject.com/en/stable/internals/contributing/writing-documentation/
- Kubernetes page templates — https://kubernetes.io/docs/contribute/style/page-templates/
- Kubernetes style guide — https://kubernetes.io/docs/contribute/style/style-guide/
- Symfony documentation standards — https://symfony.com/doc/current/contributing/documentation/standards.html
- phpDocumentor DocBlocks guide — https://docs.phpdoc.org/guide/guides/docblocks.html
- phpDocumentor `@param` — https://docs.phpdoc.org/guide/references/phpdoc/tags/param.html
- phpDocumentor `@return` — https://docs.phpdoc.org/guide/references/phpdoc/tags/return.html
- phpDocumentor `@throws` — https://docs.phpdoc.org/guide/references/phpdoc/tags/throws.html
- phpDocumentor `@api` — https://docs.phpdoc.org/guide/references/phpdoc/tags/api.html
- Doctum — https://github.com/code-lts/doctum

## 完了条件
- [x] `doc_format_profile` ごとの required / optional sections が packet asset に反映されている
- [x] packet evidence に根拠 URL / `last_confirmed_at` / source anchor が入る
- [x] source comment 対象の class / method と PHPDoc minimum tags が明文化されている
- [x] #229 は upstream format/comment policy を再発明せず pilot 実行に専念できる

## 完了エビデンス（2026-05-24）

- Evidence SoT: `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md`
- Shared packet contract:
  - `docs/templates/doc-publication-packet-template.md`
  - `docs/runbooks/doc-publication-packet-playbook.md`
  - `.github/prompts/doc-publication-packet.prompt.md`
  - `.github/skills/doc-publication-audit/SKILL.md`
- Adapter sync:
  - `.continue/rules/01-doc-packet-core.md`
  - `.continue/rules/02-doc-packet-comment-sync.md`
  - `.opencode/commands/packet-plan.md`
  - `.opencode/commands/packet-rewrite.md`
  - `.opencode/commands/packet-comment-sync.md`
  - `docs/harnesses/doc-publication-packet/continue-config.template.yaml`
  - `/.github/agents/doc-packet-executor.agent.md`
  - `.opencode/agents/doc-packet-executor.md`
- Downstream handoff:
  - `docs/work/issue-drafts/2026-05-24_issue-sprint-2a-doc-framework-body.md`
  - `docs/work/issue-drafts/2026-05-24_issue-sprint-2a4-pilot-body.md`

## 関連リンク
- Umbrella: #225
- Upstream assets: #228
- Sprint 2-A3A: #230
- Pilot: #229
- Downstream implementation: #219
