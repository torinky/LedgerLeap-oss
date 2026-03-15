# Gemini clean-room evaluation evidence template

## Metadata

- Date:
- Evaluator:
- Issue:
- OS: macOS / Windows
- Gemini CLI version:
- Client mode: interactive / headless

## Environment

- Neutral parent path:
- Workspace path:
- `GEMINI_CLI_HOME` path:
- Independent `.git` boundary used?: yes / no
- Trusted Folders enabled?: yes / no / unknown

## Harness composition

- Copied from:
- Staged artifacts:
- Omitted artifacts:
- `workspace/.gemini/settings.json` sanitized?: yes / no
- `httpUrl` value:
- `Authorization` source: generated token / existing token / unknown
- `workspace/.gemini/GEMINI.md` present?: yes / no
- `workspace/.gemini/skills/` present?: yes / no

## Pre-run checks

- `/memory show` summary:
- `/skills list` summary:
- HTTP MCP endpoint reachable?: yes / no / unknown
- HTTP status for unauthenticated request:
- HTTP status for authenticated request:
- Tenant-resolving host used?: yes / no
- `mcp:*` ability confirmed?: yes / no / unknown
- Any unexpected `GEMINI.md` origins?:
- Any unexpected user skills / extension skills?:
- Any parent `.env` or context detected?:

## Contaminated run snapshot

- Workspace used:
- User-level state used:
- Loaded context summary:
- Observed first bootstrap behavior:
- Suspected contamination points:

## Clean-room run snapshot

- Workspace used:
- User-level state used:
- Loaded context summary:
- MCP endpoint used:
- Auth method used: Bearer token / other
- Observed first bootstrap behavior:
- Staged generated artifacts, if any:

## Diff

### bootstrap discovery difference
- 

### HTTP transport / auth difference
- 

### first response / first guidance difference
- 

### placement / delivery implications for `#105`
- 

### persona implications for `#108`
- 

## Result

- Clean-room valid?: yes / no / needs rerun
- If invalid, why:
- Follow-up action:

