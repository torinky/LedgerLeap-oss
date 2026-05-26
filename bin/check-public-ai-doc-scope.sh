#!/bin/bash

set -euo pipefail

PROJECT_ROOT=$(cd "$(dirname "$0")/.." && pwd)
cd "$PROJECT_ROOT"

SEARCH_PATHS=(README.md)
OPTIONAL_SEARCH_PATHS=(
    'docs/README.md'
    'docs/api'
    'docs/architecture'
    'docs/contributing'
    'docs/features'
    'docs/getting-started'
)
EXCLUDE_GLOBS=(--glob '!docs/work/**' --glob '!docs/runbooks/**' --glob '!docs/harnesses/**')
AI_EXCLUDED_PATHS=(
    'docs/work/'
    'docs/work/llm-integration/'
    'issue-drafts/'
    'resources/ai/'
    'AGENTS.md'
    '.github/skills/'
    '.github/prompts/'
    '.github/instructions/'
    '.github/agents/'
)

violations=()

for path in "${OPTIONAL_SEARCH_PATHS[@]}"; do
    if [[ -e "$path" ]]; then
        SEARCH_PATHS+=("$path")
    fi
done

for path in "${AI_EXCLUDED_PATHS[@]}"; do
    if matches=$(rg -nF "$path" "${SEARCH_PATHS[@]}" "${EXCLUDE_GLOBS[@]}"); then
        violations+=("Public docs link to sync-excluded AI asset path: $path"$'\n'"$matches")
    fi
done

if matches=$(rg -n '/Users/[^ )]+' "${SEARCH_PATHS[@]}" --glob '*.md'); then
    violations+=("Public AI / API docs contain a local absolute path"$'\n'"$matches")
fi

if matches=$(rg -nF 'demo@example.com' "${SEARCH_PATHS[@]}" --glob '*.md'); then
    violations+=("Public AI / API docs contain a demo account identifier"$'\n'"$matches")
fi

if matches=$(rg -n 'private-ref:|canonical body|packet handoff|packet acceptance' "${SEARCH_PATHS[@]}" --glob '*.md' "${EXCLUDE_GLOBS[@]}"); then
    violations+=("Public docs contain internal tracking metadata"$'\n'"$matches")
fi

if (( ${#violations[@]} > 0 )); then
    echo "Public doc scope check failed."
    printf '\n%s\n' "${violations[@]}"
    exit 1
fi

echo "Public doc scope check passed."
