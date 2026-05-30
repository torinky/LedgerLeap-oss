#!/bin/bash

# ==============================================================================
# AI Instructions Sync Script for LedgerLeap
#
# ARCHITECTURE: Bidirectional sync via symlinks
#   .gemini/instructions  -> ../.github/instructions
#   .gemini/skills        -> ../.github/skills
#   .gemini/prompts       -> ../.github/prompts
#   .agents/instructions  -> ../.github/instructions
#   .agents/skills        -> ../.github/skills
#   .agents/prompts       -> ../.github/prompts
#
# Because all targets are symlinks to .github/, editing from any tool
# (Gemini CLI, Antigravity CLI "agy", Copilot, opencode) modifies the
# same source files — no copy needed, true bidirectional sync.
#
# opencode reads .github/ directly via opencode.json — no copy needed.
# GitHub Copilot reads .github/ natively — no copy needed.
#
# This script ensures the symlinks exist and are correct.
# Run automatically via the pre-commit hook when any .github/ AI asset is staged.
# ==============================================================================

set -euo pipefail

PROJECT_ROOT=$(cd "$(dirname "$0")/.." && pwd)
GITHUB_DIR="$PROJECT_ROOT/.github"
DIRS=("instructions" "skills" "prompts")
TARGETS=(".gemini" ".agents")

echo "Verifying AI instructions symlinks (.gemini, .agents -> .github)..."

for target in "${TARGETS[@]}"; do
    TARGET_DIR="$PROJECT_ROOT/$target"
    mkdir -p "$TARGET_DIR"
    for dir in "${DIRS[@]}"; do
        link="$TARGET_DIR/$dir"
        expected="../../.github/$dir"
        # Use relative path from inside $target/
        rel_target="../.github/$dir"
        if [ -L "$link" ]; then
            current=$(readlink "$link")
            if [ "$current" != "$rel_target" ]; then
                echo "  Fixing stale symlink: $target/$dir ($current -> $rel_target)"
                rm "$link"
                ln -s "$rel_target" "$link"
            fi
        elif [ -d "$link" ]; then
            echo "  Replacing directory with symlink: $target/$dir"
            rm -rf "$link"
            ln -s "$rel_target" "$link"
        else
            echo "  Creating symlink: $target/$dir"
            ln -s "$rel_target" "$link"
        fi
    done
    echo "  ✓ $target verified"
done

# Verify skill counts match
GITHUB_SKILLS=$(ls "$GITHUB_DIR/skills/" | wc -l | tr -d ' ')
echo ""
echo "Sync complete (symlink-based — all tools share the same source)."
echo "  skills in .github: $GITHUB_SKILLS  (same for .gemini and .agents via symlink)"
