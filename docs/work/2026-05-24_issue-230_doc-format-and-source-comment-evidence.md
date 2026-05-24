# Sprint 2-A3A: document format / source comment evidence

- status: confirmed
- last_confirmed_at: 2026-05-24
- recheck_after: 90d
- recheck_trigger: publication packet contract, packet template, prompt/skill adapter, or PHPDoc comment-sync policy changes

## 要約

Sprint 2-A3 までで packet 実行の基本資産は整ったが、`docs/templates/doc-publication-packet-template.md` と各 adapter は、**doc type ごとの section 形**と**source comment をどこまで必須化するか**をまだ固定しきれていなかった。#229 の pilot 前に、major OSS docs と phpDocumentor 系の標準を根拠に、`doc_format_profile`, evidence fields, PHPDoc minimum rule を shared packet contract に昇格させる。

## doc format 側の外部エビデンス

| ソース | 確認点 | 2-A3A への含意 |
|---|---|---|
| Diataxis — https://diataxis.fr/ | docs は `tutorial`, `how-to`, `reference`, `explanation` の 4 需要で分ける | packet manifest に `doc_format_profile` を持たせ、doc type ごとの section shape を固定する |
| Django documentation writing guide — https://docs.djangoproject.com/en/stable/internals/contributing/writing-documentation/ | `Tutorials`, `Topic guides`, `Reference guides`, `How-to guides` の役割を分け、reference は説明を混ぜず、how-to は結果志向で書く | packet rewrite 時に「どの型の doc か」を最初に判定し、混線を防ぐ必要がある |
| Kubernetes page templates — https://kubernetes.io/docs/contribute/style/page-templates/ | `Concept`, `Task`, `Tutorial`, `Reference` ごとに `overview`, `prerequisites`, `steps`, `cleanup`, `whatsnext` などの section をテンプレート化している | harness/template に required sections を持たせ、AI が毎回 section を発明しないようにする |
| Kubernetes style guide — https://kubernetes.io/docs/contribute/style/style-guide/ | active voice, meaningful variable names, code style, placeholders, UI text formatting を明示している | evidence template に `style_guardrails` を入れ、曖昧な example や実運用に見えない sample を減らす |
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

## 固定した `doc_format_profile` matrix

| `doc_format_profile` | 使う場面 | Required sections | Optional sections | `style_guardrails` | 主な根拠 |
|---|---|---|---|---|---|
| `tutorial` | 初学者に通しで体験させる学習導線 | `summary`, `goal`, `prerequisites`, `steps`, `verification`, `next_steps` | `cleanup`, `troubleshooting`, `related_links` | learner-first, step order 固定, reference 情報を混ぜすぎない | Diataxis tutorial, Django tutorials, Kubernetes tutorial |
| `how-to` | 既存利用者が結果だけ得たい手順 | `summary`, `goal`, `prerequisites`, `procedure`, `verification` | `troubleshooting`, `rollback`, `related_links` | result-first, shortest path, rationale は最小限 | Diataxis how-to, Django how-to, Kubernetes task |
| `reference` | 契約・項目・振る舞いを参照させる | `summary`, `contract_or_surface`, `parameters_or_fields`, `responses_or_effects`, `constraints`, `related_sources` | `examples`, `failure_modes`, `change_history` | contract-first, opinion や設計理由を混ぜない | Diataxis reference, Django reference, Kubernetes reference |
| `explanation` | 背景・設計判断・制約を理解させる | `summary`, `problem`, `context`, `decision`, `tradeoffs`, `related_links` | `alternatives`, `faq`, `next_steps` | why-first, step-by-step 手順にしない | Diataxis explanation, Django topic guide, Kubernetes concept |

## packet evidence field matrix

| Field | 必須度 | 用途 | 2-A3A rule |
|---|---|---|---|
| `doc_format_profile` | required | section shape の正本 | 上の matrix から 1 つ選ぶ |
| `required_sections` / `optional_sections` | required | handoff / acceptance で section 発明を防ぐ | profile からそのまま転記する |
| `external_evidence_urls` | required | major OSS docs / official docs の根拠 | 1 つ以上、profile や style guardrail を裏打ちする URL を残す |
| `last_confirmed_at` | required | 根拠 freshness | official-doc-sensitive rule と同じ日付を残す |
| `recheck_after` | required | 再確認期限 | 既定 90d、別の根拠があれば明記 |
| `source_anchor` | required | repo 内の一次根拠 | code/test/comment のうち public docs を裏打ちする anchor を残す |
| `test_anchor` | conditional | 振る舞いの検証元 | test がある packet では残す |
| `comment_anchor` | conditional | comment sync 対象 | `comment_sync_policy` が `required` / `optional` のとき残す |
| `style_guardrails` | required | wording / example guardrail | active voice, realistic examples, format-order などを短く残す |
| `comment_sync_decision` | required | comment sync 実施/延期理由 | `required` / `optional` / `not_applicable` を acceptance に反映する |
| `defer_reason` | conditional | comment sync を遅らせる理由 | `optional` / `not_applicable` のとき必須 |

最低セットは **`doc_format_profile` + `required_sections` + `external_evidence_urls` + `last_confirmed_at` + `source_anchor`**。#229 ではこの最小セットが揃っていない packet を pilot 対象にしない。

## source comment target selection rule

1. comment sync は **1 packet の target file を裏打ちする public-source anchor** に限定する。
2. 優先対象は、public docs の本文で直接説明する `controller action`, `Livewire public method`, `service method`, `MCP/API tool`, `stable DTO/value object`。
3. `private` helper, boilerplate accessors, migration-only code, public docs に出てこない内部詳細は対象外にする。
4. `comment_sync_policy` が `not_applicable` の packet では comment work を発明せず、acceptance に理由だけ残す。
5. packet で扱う comment は **repo-wide sweep ではなく bounded update** とし、同じ packet の anchor 以外には広げない。

## PHPDoc minimum rule for packet comment sync

| Element | Always | Conditional | Notes |
|---|---|---|---|
| class / interface / trait | short summary | `@api` only for stable public contract surfaces | DocBlock order は `summary -> description -> tags` |
| public method used as source anchor | short summary | `@param` for complex inputs / array shapes / semantic arguments, `@return` for non-void or structured outputs, `@throws` for observable failure modes, `@api` only when the method itself is a stable contract | signature で足りる trivial scalar は無理に冗長化しない |

### 運用メモ

- `@param`, `@return`, `@throws` は **「タグを足すこと」ではなく public docs と source の意味を揃えること**が目的。
- `@api` は blanket で付けない。client-facing contract / stable extension point に近い surface だけを候補にする。
- Doctum/phpDocumentor と衝突しないよう、独自タグや packet 専用書式は導入しない。

## 2-A3A で更新した shared asset

- `docs/templates/doc-publication-packet-template.md`
- `docs/runbooks/doc-publication-packet-playbook.md`
- `/.github/skills/doc-publication-audit/SKILL.md`
- `/.github/prompts/doc-publication-packet.prompt.md`
- `.continue/rules/01-doc-packet-core.md`
- `.continue/rules/02-doc-packet-comment-sync.md`
- `.opencode/commands/packet-plan.md`
- `.opencode/commands/packet-rewrite.md`
- `.opencode/commands/packet-comment-sync.md`
- `docs/harnesses/doc-publication-packet/continue-config.template.yaml`
- `/.github/agents/doc-packet-executor.agent.md`
- `.opencode/agents/doc-packet-executor.md`

## 2-A4 へ渡す前提

- pilot packet は 2-A3A で決めた `doc_format_profile` と `required_sections` を handoff に明示してから着手する
- packet evidence には最低でも `external_evidence_urls`, `last_confirmed_at`, `source_anchor`, `comment_sync_decision` を残す
- source comment は「public docs の種になる method / class」に限定し、repo 全面一括追加はしない
