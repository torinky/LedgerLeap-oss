# Public/private traceability guidance

- status: confirmed
- last_confirmed_at: 2026-05-26
- recheck_after: 90d
- recheck_trigger: packet contract/template changes, OSS sync guard changes, or upstream documentation-governance examples materially change

## Rule

Keep **public docs reader-facing** and move **private traceability** into the packet manifest, handoff, acceptance record, or private issue body.

## Keep out of the public doc body

- `docs/work/*`
- `issue-drafts/*`
- private GitHub issue numbers used only for planning
- `private-ref:` commit mapping markers
- `canonical body`, packet handoff, packet acceptance, or other workflow-only labels

## Keep in the companion record

- `packet_id`
- `target_path`
- `tracking_record_location`
- `private_reference_map`
- `public_reference_targets`
- freshness fields such as `last_confirmed_at` and `recheck_after`

## Allowed public traceability

- sanitized public GitHub issue
- public ADR / changelog / release note
- stable public contract page under `docs/**`

If the reference is not public-safe yet, keep it in the companion record only.

## Why

- Diataxis and Django both separate documentation forms by reader need rather than internal workflow history.
- Kubernetes page templates and style guidance keep page bodies focused on the selected content type and user-facing structure.
- Symfony, Laravel, Filament, and Spatie public docs prioritize installation, usage, contract, and realistic examples instead of private planning references.

## Evidence

- Repo proof: [`docs/work/2026-05-26_public-doc-traceability-governance.md`](../../../../docs/work/2026-05-26_public-doc-traceability-governance.md)
- Diataxis: https://diataxis.fr/
- Django writing docs: https://docs.djangoproject.com/en/stable/internals/contributing/writing-documentation/
- Kubernetes page templates: https://kubernetes.io/docs/contribute/style/page-templates/
- Kubernetes style guide: https://kubernetes.io/docs/contribute/style/style-guide/
- Symfony documentation standards: https://symfony.com/doc/current/contributing/documentation/standards.html
- Laravel docs: https://laravel.com/docs/12.x
- Filament docs: https://filamentphp.com/docs
- Spatie docs: https://spatie.be/docs/laravel-multitenancy/v4/introduction
