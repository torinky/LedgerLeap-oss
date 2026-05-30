# Public Documentation Placement Guide

This guide helps decide where a page or note should live in the repository.

## Root files

- `README.md`: short project overview, quick start, and links into the docs hub.
- `CONTRIBUTING.md`: contributor workflow, local setup, testing, branching, and PR expectations.
- `CODE_OF_CONDUCT.md`: community behavior policy.
- `SECURITY.md`: vulnerability reporting and response policy.

## Public docs tree

- `docs/README.md`: public docs index.
- `docs/getting-started/`: install, first run, demo setup, and configuration.
- `docs/features/`: user-visible features and workflows.
- `docs/architecture/`: system design, tenancy, data model, and boundaries.
- `docs/contributing/`: contributor setup, standards, testing, and branch rules.
- `docs/api/`: API and MCP-facing overview pages.

## Private work tree

- `docs/work/`: planning, investigation, decision history, and sprint records.
- Do not mirror this directory into the public repository.

## Issue body placement guidance

- Sprint issues should include a creation-scope block so readers can see what the sprint creates and how that maps to the plan.
- Keep creation timing as metadata, but do not let it replace the deliverable list.
- Include scope, checklist progress, evidence, and completion criteria in the issue body itself.
- Keep completion reports aligned with the GitHub issue so the report can be used as the canonical local draft.

## Common file-to-home mapping

| Content type | Suggested home |
|--------------|----------------|
| Quick project entry | `README.md` |
| Setup / first run | `docs/getting-started/*` |
| Feature walkthrough | `docs/features/*` |
| Architecture / tenancy / data | `docs/architecture/*` |
| Contributor process | `CONTRIBUTING.md` or `docs/contributing/*` |
| Security reporting | `SECURITY.md` |
| Internal planning or retrospective | `docs/work/*` |
