#!/bin/bash

# ==============================================================================
# AI Instructions Sync Script for LedgerLeap
# Syncs .github instructions/skills/prompts to .gemini for Gemini CLI (Serena).
# ==============================================================================

PROJECT_ROOT=$(cd "$(dirname "$0")/.." && pwd)
GITHUB_DIR="$PROJECT_ROOT/.github"
GEMINI_DIR="$PROJECT_ROOT/.gemini"

# Define directories to sync
DIRS=("instructions" "skills" "prompts")

echo "Starting AI instructions sync (Copilot -> Gemini CLI)..."

# Ensure .gemini target directories exist
for dir in "${DIRS[@]}"; do
    mkdir -p "$GEMINI_DIR/$dir"
done

# Sync directories
for dir in "${DIRS[@]}"; do
    echo "  Syncing $dir..."
    # Copy from .github to .gemini (overwrite, but don't delete extra files in .gemini)
    # Using cp -R instead of rsync for better portability if rsync is not available
    cp -R "$GITHUB_DIR/$dir/"* "$GEMINI_DIR/$dir/"
done

# Special processing for skills: ensure SKILL.md has name/description (if needed)
# Since they already have it, we just verify or add a comment
find "$GEMINI_DIR/skills" -name "SKILL.md" -exec sed -i '' '1s/^/<!-- Generated from .github - DO NOT EDIT MANUALLY -->\n/' {} + 2>/dev/null || true

echo "Sync completed successfully."
echo "Note: .github files were NOT modified."
