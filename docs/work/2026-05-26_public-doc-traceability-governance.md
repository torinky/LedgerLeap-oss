# Public doc traceability governance

- status: confirmed
- last_confirmed_at: 2026-05-26
- recheck_after: 90d
- recheck_trigger: public doc publication workflow, packet contract/template, OSS sync guard, or external doc-governance references change

## Summary

公開予定の `README.md` や `docs/**` に `docs/work/*`、private issue 番号、canonical body、packet handoff のような内部追跡情報を残すと、公開読者には不要な内部事情が露出し、mirror 先との境界も曖昧になる。一方で単純削除すると、private 側で「どの公開 doc がどの内部検討や issue に対応していたか」の追跡が失われる。対応方針は **公開本文と private companion record を分離**し、追跡性は packet manifest / issue / handoff に移し、公開 doc は必要な場合だけ sanitized された public artifact を参照すること。

## Repo evidence

| Source | Confirmed point | Implication |
|---|---|---|
| `docs/work/2026-05-23_oss-publication-plan.md` | `docs/work/` と既存の内部実装記録は公開せず、公開 doc は新規作成する方針 | public docs は内部 worklog を本文参照先にしない |
| `.github/skills/doc-publication-audit/SKILL.md` | public doc body は tracking metadata を持たず、packet manifest / handoff / acceptance は companion issue 側に置く | traceability は private companion record に寄せる |
| `.github/instructions/ai-assets.instructions.md` | OSS mirror 対象 doc から sync-excluded asset へリンクしない | internal path を public docs に残さない guard が必要 |
| `docs/runbooks/oss-sync-runbook.md` | public docs から sync-excluded asset を検出するチェックを sync 前に実行する | path leak を lint で止める運用が必要 |
| `docs/work/2026-05-24_issue-219-planning-retrospective.md` | public docs に private AI/worklog 直リンクが残ると再発防止が必要になる | path leak だけでなく tracking metadata leak も guard 対象にすべき |

## External evidence

| Source | Confirmed point | Governance implication |
|---|---|---|
| Diataxis — https://diataxis.fr/ | docs は reader need ごとの form で整理する | public docs は reader-facing outcome を優先し、 internal process history を混ぜない |
| Django writing documentation — https://docs.djangoproject.com/en/stable/internals/contributing/writing-documentation/ | tutorial / topic guide / reference / how-to の役割を分離し、reference に一般説明を混ぜない | public docs では internal rationale と public usage/reference を分ける |
| Kubernetes page templates — https://kubernetes.io/docs/contribute/style/page-templates/ | page type ごとに section shape を固定する | traceability 情報は本文ではなく companion record に置き、 page body は template responsibility に集中させる |
| Kubernetes style guide — https://kubernetes.io/docs/contribute/style/style-guide/ | placeholders, formatting, reader-facing consistency を重視する | public docs は内部識別子より reader-facing wording を優先する |
| Symfony documentation standards — https://symfony.com/doc/current/contributing/documentation/standards.html | examples は現実的で、 abstract internal notes ではなく user-relevant context を使う | public docs で internal ticket / worklog をそのまま出さない |
| Laravel docs — https://laravel.com/docs/12.x | installation, configuration, next steps など public consumption 中心の導線 | public docs の root/navigation は operational guidance を優先する |
| Filament docs — https://filamentphp.com/docs | quick links / getting started / resources の public IA を採用 | public docs は reader action を先頭に置くべき |
| Spatie docs — https://spatie.be/docs/laravel-multitenancy/v4/introduction | package docs は intro / contract / usage を中心にする | public docs で internal backlog trace は companion record 側に退避させる |

## Decision

1. 公開 doc 本文には `docs/work/*`, `issue-drafts/*`, private issue 番号, `private-ref:` などの internal tracking metadata を残さない。
2. 追跡性は private 側の packet manifest / handoff / acceptance / issue body に残す。
3. packet 単位の stable identifier は issue 番号より `packet_id` と `target_path` を優先する。
4. 公開 doc から参照が必要な場合は、sanitized された public issue / ADR / changelog など **public artifact に昇格したものだけ** を使う。
5. sync 前チェックは sync-excluded path だけでなく、internal tracking metadata の混入も検出対象に含める。

## Companion record minimum

- `packet_id`
- `target_path`
- `tracking_record_location`
- `private_reference_map`
- `public_reference_targets`
- `last_confirmed_at`
- `recheck_after`

## Non-goals

- public docs 本文に private 参照を「注意書き付きで残す」こと
- issue 番号を public 側の stable identifier にすること
- `docs/work/*` を partial public 化して traceability を確保すること
