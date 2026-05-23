# Sprint 3: AI 資産リポジトリ切り出しと参照整理

## 概要
AI 動作資産を `LedgerLeap-ai-assets` に分離し、公開リポジトリ側からは除外します。あわせて、相互参照ルールと維持方針を整理します。

## 作成タイミング
- 作成日時: 2026-05-23T08:08:28Z
- 位置づけ: Epic #216 の Sprint 3 として、公開ドキュメント整備の次に AI 資産の公開境界を切り分けるために起票
- 参照タイミング: 共有資産と公開資産の境界が見えた段階で、AI 資産の独立化を進める時点で作成

## このスプリントで作るもの
- `LedgerLeap-ai-assets` 側へ移す AI 資産の構成
- `.github/instructions/`, `.github/skills/`, `.github/prompts/`, `.github/agents/` の移行単位
- `AGENTS.md` と `opencode.json` の別管理ルール
- `resources/ai/capabilities/` の公開側除外と参照方法

このスプリントで作るのは、公開リポジトリに残さない AI 資産の分離設計。公開 docs 本文は対象外。

## 背景 / 目的
- `.github/instructions/` や `skills/` は公開リポジトリの読者に必要なものではない
- AI 資産は将来公開する可能性があるが、現時点では別管理が安全
- 公開側では AI 実行環境の前提を持ち込まない方がよい

## 現状
- `AGENTS.md`、`opencode.json`、`.github/copilot-instructions.md` などが現リポジトリにある
- `docs/runbooks/ai-*` や `docs/harnesses/` は AI 寄りの内容を含む
- `docs/work/llm-integration/` は LLM/MCP 設計記録としてまとまっている

## 目標 / 完了状態
- `LedgerLeap-ai-assets` に AI 資産がまとまる
- 公開リポジトリから AI 資産が外れる
- 公開側から AI 資産へのリンクが原則なくなる
- 将来公開する場合の運用ルールが分かる

## スコープ / 非スコープ
### 対象
- `.github/copilot-instructions.md`
- `.github/instructions/`, `.github/skills/`, `.github/prompts/`, `.github/agents/`
- `AGENTS.md`, `opencode.json`
- `resources/ai/capabilities/`
- `docs/runbooks/ai-*`, `docs/runbooks/local-llm-mcp-setup.md`
- `docs/harnesses/`
- `docs/work/llm-integration/`

### 対象外
- 公開ドキュメントの本執筆
- 公開リポジトリの bootstrap
- コミュニティファイルの最終整備

## 方針候補 / メモ
1. AI 資産はファイルコピーで移行し、履歴は持たない
2. 公開側の README / CONTRIBUTING だけ参照導線を残す
3. AI 資産内の本体参照は絶対 URL を基本にする

## Sprint 内で検討すべき詳細事項
- どのファイルを AI 資産へ移すかの最終一覧
- 公開側に残すべき runbook の有無
- `docs/work/llm-integration/` の移動先
- AI 資産リポジトリ公開時の案内文
- 参照リンクの絶対 URL 方針

## スプリント分解
- [ ] AI 資産の対象一覧を確定
- [ ] 移行先リポジトリの構成を確定
- [ ] 参照ルールを確定
- [ ] 公開側からの除外パスを確定

## エビデンス / 参照先
- `docs/work/2026-05-23_oss-publication-plan.md`
- `AGENTS.md`
- `.github/copilot-instructions.md`
- `.github/instructions/`
- `docs/runbooks/ai-asset-maintenance-playbook.md`
- `docs/harnesses/gemini-clean-room/`

## 完了条件
- [ ] AI 資産が別リポジトリへ切り出されている
- [ ] 公開リポジトリから AI 資産が除外されている
- [ ] 参照ルールが整理されている
- [ ] 将来の公開方針が残っている
