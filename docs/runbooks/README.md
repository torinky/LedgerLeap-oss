# Runbooks Index

LedgerLeap の標準運用手順への入口です。

> [!IMPORTANT]
> LedgerLeap のローカルテスト実行は **Laravel Sail / Docker-based interpreter 前提** です。
> host の `php artisan test` / `./vendor/bin/pest` は使用せず、`./vendor/bin/sail test` / `./vendor/bin/sail pest` を使用してください。

## Available Runbooks
- [Bug Response Playbook](./bug-response-playbook.md): 不具合の intake → 調査 → 実装 → 検証 → 学び反映
- [GitHub Issue Body Sync Playbook](./github-issue-body-sync-playbook.md): issue 本文を canonical file から全文同期し、反映後に再取得で検証する
- [AI Asset Maintenance Playbook](./ai-asset-maintenance-playbook.md): 学びを `.github` / `AGENTS.md` / runbooks へ同期する手順
- [Browser HAR Analysis Playbook](./browser-har-analysis-playbook.md): HAR 比較 → repeated request 切り分け → 証跡化
- [UI Migration Playbook](./ui-migration-playbook.md): プロジェクト全画面を統一ルール（Mary UI / daisyUI）に準拠させるためのUI置き換え手順
- [Local LLM MCP Setup Guide](./local-llm-mcp-setup.md): LM Studio / Ollama での LedgerLeap MCP 利用設定手順（コンテキスト最適化・クラッシュ対策）

## Recommended Flow
1. 不具合対応は [Bug Response Playbook](./bug-response-playbook.md) を起点に進める
2. GitHub issue 本文の全文更新と反映検証は [GitHub Issue Body Sync Playbook](./github-issue-body-sync-playbook.md) を使う
3. 再利用可能な学びが出たら [AI Asset Maintenance Playbook](./ai-asset-maintenance-playbook.md) に移る
4. HAR 比較が必要なら [Browser HAR Analysis Playbook](./browser-har-analysis-playbook.md) を使う
5. slash entrypoints は `/.github/prompts/bug-investigation.prompt.md`, `/.github/prompts/bug-execution.prompt.md`, `/.github/prompts/browser-har-analysis.prompt.md`, `/.github/prompts/skill-maintenance.prompt.md`

