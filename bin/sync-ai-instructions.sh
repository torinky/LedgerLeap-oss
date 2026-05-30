#!/bin/bash

# ==============================================================================
# AI Instructions Sync Script for LedgerLeap
#
# ARCHITECTURE: Two-layer sync
#
# Layer 1 — Symlinks (same format, bidirectional):
#   .gemini/instructions  -> ../.github/instructions
#   .gemini/skills        -> ../.github/skills
#   .gemini/prompts       -> ../.github/prompts
#   .agents/instructions  -> ../.github/instructions
#   .agents/skills        -> ../.github/skills
#   .agents/prompts       -> ../.github/prompts
#
# Layer 2 — Generated files (format dialects, canonical → derived):
#   .gemini/settings.json mcpServers → opencode.json mcp section
#     Gemini format: { command: "cmd", args: [...], env: {...} }
#     opencode format: { type: "local", command: ["cmd", ...], environment: {...} }
#
# Canonical sources:
#   - skills/instructions/prompts: .github/ (bidirectional via symlinks)
#   - MCP servers: .gemini/settings.json mcpServers section
#
# opencode reads .github/ directly via opencode.json — no copy needed.
# GitHub Copilot reads .github/ natively — no copy needed.
#
# Auto-triggered by .git/hooks/pre-commit on:
#   - .github/instructions|skills|prompts changes
#   - .gemini/settings.json changes
# ==============================================================================

set -euo pipefail

PROJECT_ROOT=$(cd "$(dirname "$0")/.." && pwd)
GITHUB_DIR="$PROJECT_ROOT/.github"
DIRS=("instructions" "skills" "prompts")
TARGETS=(".gemini" ".agents")

# --- Layer 1: Symlinks ---
echo "Layer 1: Verifying symlinks (.gemini, .agents -> .github)..."

for target in "${TARGETS[@]}"; do
    TARGET_DIR="$PROJECT_ROOT/$target"
    mkdir -p "$TARGET_DIR"
    for dir in "${DIRS[@]}"; do
        link="$TARGET_DIR/$dir"
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

# --- Layer 2: MCP server sync (.gemini/settings.json → opencode.json) ---
echo ""
echo "Layer 2: Syncing MCP servers (.gemini/settings.json → opencode.json)..."

python3 - "$PROJECT_ROOT" <<'PYEOF'
import json, sys, os, re

project = sys.argv[1]
gemini_settings_path = os.path.join(project, ".gemini", "settings.json")
opencode_path = os.path.join(project, "opencode.json")

with open(gemini_settings_path) as f:
    gemini = json.load(f)

mcp_servers = gemini.get("mcpServers", {})
if not mcp_servers:
    print("  No mcpServers found — skipping opencode.json update")
    sys.exit(0)

# Convert Gemini format → opencode format
# Gemini: { command: "cmd", args: [...], env: {...} }
# opencode: { type: "local", command: ["cmd", ...], environment: {...} }
opencode_mcp = {}
for name, cfg in mcp_servers.items():
    entry = {"type": "local"}
    cmd = cfg.get("command", "")
    args = cfg.get("args", [])
    entry["command"] = [cmd] + args if cmd else list(args)
    if "env" in cfg:
        entry["environment"] = cfg["env"]
    opencode_mcp[name] = entry

# Read opencode.json — strip trailing commas for tolerance
with open(opencode_path) as f:
    raw = f.read()
raw_clean = re.sub(r',(\s*[}\]])', r'\1', raw)
oc = json.loads(raw_clean)

oc["mcp"] = opencode_mcp

with open(opencode_path, "w") as f:
    json.dump(oc, f, indent=2, ensure_ascii=False)
    f.write("\n")

print(f"  ✓ opencode.json updated — {len(opencode_mcp)} MCP server(s) synced")
PYEOF

# Summary
GITHUB_SKILLS=$(ls "$GITHUB_DIR/skills/" | wc -l | tr -d ' ')
echo ""
echo "Sync complete."
echo "  Layer 1 (symlinks): {instructions,skills,prompts} shared across .github/.gemini/.agents"
echo "  Layer 2 (generated): $( python3 -c "import json; d=json.load(open('$PROJECT_ROOT/.gemini/settings.json')); print(len(d.get('mcpServers',{})))" ) MCP servers .gemini/settings.json → opencode.json"
echo "  Skills: $GITHUB_SKILLS in .github (same for .gemini/.agents via symlink)"
