#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
git-commit helper — writes a UTF-8 commit message to /tmp/commit_msg.txt

Usage:
    python3 .github/skills/git-commit/scripts/make_commit_msg.py \
        --type fix \
        --scope test \
        --subject "SearchApiTest を DatabaseMigrationsOnce に移行" \
        --body "RefreshDatabaseWithTenant は Mroonga インデックスをロールバックできない。\nDatabaseMigrationsOnce + TRUNCATE で確実にクリア。" \
        --footer "Closes #74"

    python3 .github/skills/git-commit/scripts/make_commit_msg.py \
        --raw "feat(auth): ログイン機能を追加\n\n詳細説明。\n\nCloses #42"

Output:
    Writes to /tmp/commit_msg.txt and prints a preview.
    Use: git commit -F /tmp/commit_msg.txt
"""

import argparse
import sys


def build_message(type_: str, scope: str, subject: str, body: str, footer: str) -> str:
    header = f"{type_}({scope}): {subject}" if scope else f"{type_}: {subject}"
    parts = [header]
    if body:
        parts.append("")
        parts.append(body)
    if footer:
        parts.append("")
        parts.append(footer)
    return "\n".join(parts) + "\n"


def main() -> None:
    parser = argparse.ArgumentParser(description="Write a git commit message to /tmp/commit_msg.txt")
    parser.add_argument("--type", dest="type_", help="Conventional Commits type (feat, fix, docs, ...)")
    parser.add_argument("--scope", default="", help="Optional scope (test, ci, ...)")
    parser.add_argument("--subject", help="Subject line (≤50 chars)")
    parser.add_argument("--body", default="", help="Body text (use \\n for newlines)")
    parser.add_argument("--footer", default="", help="Footer (e.g. Closes #74)")
    parser.add_argument("--raw", help="Full message as a raw string with \\n (bypasses structured args)")
    parser.add_argument("--output", default="/tmp/commit_msg.txt", help="Output file path")
    args = parser.parse_args()

    if args.raw:
        msg = args.raw.replace("\\n", "\n")
        if not msg.endswith("\n"):
            msg += "\n"
    elif args.type_ and args.subject:
        body = args.body.replace("\\n", "\n")
        msg = build_message(args.type_, args.scope, args.subject, body, args.footer)
    else:
        parser.print_help()
        sys.exit(1)

    with open(args.output, "w", encoding="utf-8") as f:
        f.write(msg)

    preview = msg[:120] + ("..." if len(msg) > 120 else "")
    print(f"OK — written to {args.output}")
    print(f"--- preview ---\n{preview}")


if __name__ == "__main__":
    main()

