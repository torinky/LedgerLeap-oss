# Sprint 2-A3A: document format / source comment evidence

- status: researched
- last_confirmed_at: 2026-05-24
- recheck_after: 90d

## 要約

Sprint 2-A3 までで packet 実行の基本資産は整ったが、`docs/templates/doc-publication-packet-template.md` と harness から呼ぶ各 doc format には、**doc type ごとの section 形**と**source comment をどこまで必須化するか**がまだ弱い。#229 の pilot を始める前に、major OSS docs の形式知と PHPDoc 系の source comment 規約を使って補強した方がよい。

## doc format 側の外部エビデンス

| ソース | 確認点 | 2-A3A への含意 |
|---|---|---|
| Diataxis — https://diataxis.fr/ | docs は `tutorial`, `how-to`, `reference`, `explanation` の 4 需要で分ける | packet manifest に `doc_format_profile` を持たせ、doc type ごとの section shape を固定する |
| Django documentation writing guide — https://docs.djangoproject.com/en/stable/internals/contributing/writing-documentation/ | `Tutorials`, `Topic guides`, `Reference guides`, `How-to guides` の役割を分け、reference は説明を混ぜず、how-to は結果志向で書く | packet rewrite 時に「どの型の doc か」を最初に判定し、混線を防ぐ必要がある |
| Kubernetes page templates — https://kubernetes.io/docs/contribute/style/page-templates/ | `Concept`, `Task`, `Tutorial`, `Reference` ごとに `overview`, `prerequisites`, `steps`, `cleanup`, `whatsnext` などの section をテンプレート化している | harness/template に required sections を持たせ、AI が毎回 section を発明しないようにする |
| Kubernetes style guide — https://kubernetes.io/docs/contribute/style/style-guide/ | active voice, meaningful variable names, code style, placeholders, UI text formatting を明示している | evidence template に「style/wording guardrail」を入れ、曖昧な example や実運用に見えない sample を減らす |
| Symfony documentation standards — https://symfony.com/doc/current/contributing/documentation/standards.html | code example は real web app context を使い、`foo/bar` を避け、config format order や line length も固定する | packet ごとに sample realism と multi-format ordering を確認する checklist が必要 |

## PHP source comment 側の外部エビデンス

| ソース | 確認点 | 2-A3A への含意 |
|---|---|---|
| phpDocumentor DocBlocks guide — https://docs.phpdoc.org/guide/guides/docblocks.html | DocBlock は 1 structural element に対応し、`summary -> description -> tags` の順で構成される | class / method comment を packet の source-of-truth にするなら、最小構造を明文化する必要がある |
| phpDocumentor `@param` — https://docs.phpdoc.org/guide/references/phpdoc/tags/param.html | 型が signature で十分でも、複雑な引数や配列構造では description が推奨される | public doc の source になる method は、少なくとも複雑引数の intent を PHPDoc に残す |
| phpDocumentor `@return` — https://docs.phpdoc.org/guide/references/phpdoc/tags/return.html | `@return` は原則推奨で、複雑な戻り値は description を付けるべき | API/MCP/controller/service の戻り値説明を public docs とズラさないため、return description policy が必要 |
| phpDocumentor `@throws` — https://docs.phpdoc.org/guide/references/phpdoc/tags/throws.html | 例外の type と理由を書くことが推奨される | public behavior に失敗条件が含まれる packet は `@throws` を source comment 対象に含めるべき |
| phpDocumentor `@api` — https://docs.phpdoc.org/guide/references/phpdoc/tags/api.html | 安定 public API を明示できる | client-facing contract の安定 surface は `@api` 利用可否を判断基準に加える |
| Doctum — https://github.com/code-lts/doctum | config で source dir を渡して API docs を生成し、既定では public methods / properties を対象にする | PHPDoc/Doctum 互換を前提にすれば、将来 API doc を source から再生成する余地を残せる |

## 判定

### 1. 2-A4 に直接入れず 2-A3A を挟むべき

- #229 は pilot 実行 sprint であり、packet format と source comment rule まで同時に固めると scope が広がる
- `Sprint N-A` ルールに合致する follow-up で、#228 の spec-vs-implementation diff から出た未実装項目として扱える
- したがって **Sprint 2-A3A** を #228 と #229 の間に追加し、format / evidence / source-comment policy を先に固定するのが妥当

### 2. 2-A3A で固定したい最低項目

1. `doc_format_profile` ごとの required sections
2. packet handoff / acceptance に残す evidence 項目
3. source comment 対象の `class`, `method`, `public contract` の選び方
4. PHPDoc minimum tags (`@param`, `@return`, `@throws`) の適用基準
5. Doctum/phpDocumentor と両立する comment 形

## 2-A3A で更新対象にしたい repo asset

- `docs/templates/doc-publication-packet-template.md`
- `docs/runbooks/doc-publication-packet-playbook.md`
- `/.github/skills/doc-publication-audit/SKILL.md`
- `/.github/prompts/doc-publication-packet.prompt.md`
- `.continue/rules/01-doc-packet-core.md`
- `.opencode/commands/packet-rewrite.md`
- `docs/harnesses/doc-publication-packet/continue-config.template.yaml`

## 2-A4 へ渡す前提

- pilot packet は 2-A3A で決めた `doc_format_profile` を明示してから着手する
- packet evidence には外部根拠 URL と `last_confirmed_at` を残す
- source comment は「public docs の種になる method / class」に限定し、repo 全面一括追加はしない
