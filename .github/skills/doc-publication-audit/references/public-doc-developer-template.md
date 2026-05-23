# Developer-Facing Public Doc Template

Use this template for architecture notes, configuration references, testing guides, API/MCP pages, and contributor-oriented deep dives.

## Recommended placement

- `docs/architecture/*`: system design, data model, tenancy, and boundaries.
- `docs/contributing/*`: setup, workflow, standards, testing, and branch rules.
- `docs/api/*`: API and MCP overview pages.
- Top-level `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`: community and operational entry points.

## Page structure

1. Title block
2. Purpose
3. Scope and non-scope
4. System or code behavior
5. Config, commands, or data model details
6. Testing or validation notes
7. Edge cases, constraints, and caveats
8. Evidence links

## Starter outline

```md
# Topic Name

## Purpose
Explain why the topic matters to maintainers or contributors.

## Scope
Describe what is covered and what is intentionally out of scope.

## Behavior or design
Summarize the implemented behavior or architecture.

## Configuration or commands
List the relevant settings, environment variables, or commands.

## Validation
State how the implementation is verified.

## Edge cases
Call out failure modes, unsupported combinations, and upgrade caveats.

## Constraints
Describe the cases that are not supported or need care.

## Evidence
- Link to the implementation file
- Link to the test file
- Link to the related work note
```

## Typical OSS pattern to mirror

- Keep the root README short and use it as a high-level portal only.
- Move extended technical detail into `docs/` by audience.
- Keep contribution, conduct, and security in dedicated top-level files so GitHub users can find them without reading the rest of the docs.
- Make validation and edge cases explicit, especially for setup, migration, and internal configuration pages.
