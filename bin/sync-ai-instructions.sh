#!/bin/bash

# ==============================================================================
# AI Instructions Sync Script for LedgerLeap
# Syncs .github/instructions, skills, prompts to:
#   - .gemini/   (Gemini CLI — legacy, still active until sunset)
#   - .agents/   (Antigravity CLI "agy" — Google's Gemini CLI replacement)
#
# opencode reads .github/ directly via opencode.json — no copy needed.
# GitHub Copilot reads .github/ natively — no copy needed.
#
# Run automatically via the pre-commit hook (.git/hooks/pre-commit) when
# .github/instructions/, .github/skills/, .github/prompts/, or
# .github/copilot-instructions.md are staged for commit.
# ==============================================================================

set -euo pipefail

PROJECT_ROOT=$(cd "$(dirname "$0")/.." && pwd)
GITHUB_DIR="$PROJECT_ROOT/.github"
GEMINI_DIR="$PROJECT_ROOT/.gemini"
AGENTS_DIR="$PROJECT_ROOT/.agents"

DIRS=("instructions" "skills" "prompts")

echo "Starting AI instructions sync (.github -> .gemini, .agents)..."

sync_target() {
    local target="$1"
    for dir in "${DIRS[@]}"; do
        mkdir -p "$target/$dir"
        # rsync: overwrite + delete stale files that no longer exist in .github
        rsync -a --delete "$GITHUB_DIR/$dir/" "$target/$dir/"
    done
}

sync_target "$GEMINI_DIR"
echo "  ✓ .gemini synced"

sync_target "$AGENTS_DIR"
echo "  ✓ .agents synced"

# Report skill counts for verification
GITHUB_SKILLS=$(ls "$GITHUB_DIR/skills/" | wc -l | tr -d ' ')
GEMINI_SKILLS=$(ls "$GEMINI_DIR/skills/" | wc -l | tr -d ' ')
AGENTS_SKILLS=$(ls "$AGENTS_DIR/skills/" | wc -l | tr -d ' ')

echo ""
echo "Sync complete."
echo "  skills: .github=$GITHUB_SKILLS  .gemini=$GEMINI_SKILLS  .agents=$AGENTS_SKILLS"
if [ "$GITHUB_SKILLS" != "$GEMINI_SKILLS" ] || [ "$GITHUB_SKILLS" != "$AGENTS_SKILLS" ]; then
    echo "  WARNING: skill counts don't match — investigate."
    exit 1
fi
echo "Note: .github source files were NOT modified."
