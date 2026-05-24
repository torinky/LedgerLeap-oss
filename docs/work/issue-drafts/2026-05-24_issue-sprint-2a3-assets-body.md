# Sprint 2-A3: packet 実行用 skill / subagent / runbook 整備

## 概要
Sprint 2-A1 / 2-A2 の成果を、今回限りのメモで終わらせず reusable な AI asset として整備します。対象は **shared packet SoT**, source inventory 用 skill、packet rewrite 用 subagent、OpenCode / Continue.dev adapter、operator runbook です。

## 背景 / 目的
- #219 だけでなく、その後の docs 保守でも同じ packet 実行系を再利用したい
- 既存 `doc-publication-audit` は rewrite に強いが、source-derived inventory と Gemma4 packet orchestration はまだカバーしていない
- OpenCode / LM Studio / Gemma4 26B 前提の作業ルールを human-readable な runbook にも残す必要がある
- Continue.dev は OpenCode のような subagent 中心ではなく `rules / prompts / Plan / Agent` 中心なので、**共通ルール本体と agent 別 adapter を分ける設計** が必要
- 類似事例として、Continue の `awesome-rules` と repo 内 `.continue/checks` は repeated task を source-controlled asset に落とす運用を採っている

## 現状
- `/.github/skills/doc-publication-audit/SKILL.md` は file-by-file rewrite を前提にしている
- custom agent は現在 `client-facing-contract-maintenance`, `test-creation` だけで、doc packet 用 agent はない
- OpenCode operator 用の public-doc packet runbook は未整備
- `.opencode/*` / `.continue/*` に packet workflow 用 asset はまだない

## 目標 / 完了状態
- source inventory 用 skill 方針が確定している
- packet rewrite 用 subagent 方針が確定している
- OpenCode adapter と Continue adapter の役割分担が確定している
- operator runbook / prompt entrypoint の要否が確定している
- asset の primary destination と neighbor sync 方針が確定している

## スコープ / 非スコープ
### 対象
- shared packet rule / handoff SoT の置き場
- skill 追加または既存 skill 拡張方針
- custom agent 追加方針
- OpenCode `.opencode/agents` / `.opencode/commands` adapter 方針
- Continue `.continue/rules` / prompts / config snippet adapter 方針
- runbook の構成
- prompt entrypoint の要否整理

### 対象外
- public doc 本文の大量執筆
- packet pilot の実行
- OSS sync
- Continue Hub への公開配布

## 確定したい asset 構成
| 層 | 候補 | 役割 |
|---|---|---|
| shared SoT | packet rule / handoff spec / acceptance spec | agent 非依存の packet contract を 1 箇所に集約 |
| repo-native skill | `doc-source-inventory`, `doc-publication-audit` 拡張 | 判断基準と flow を LedgerLeap 側の reusable knowledge として保持 |
| repo-native agent | `doc-source-inventory.agent.md`, `doc-packet-executor.agent.md` | Copilot 系 subagent から bounded task を再利用 |
| OpenCode adapter | `.opencode/agents/*`, `.opencode/commands/*` | OpenCode の primary/subagent と custom command に contract を載せる |
| Continue adapter | `.continue/rules/*`, prompts, `config.yaml` snippet | Continue の Plan/Agent と `/prompt` に contract を載せる |
| human-readable ops | `docs/runbooks/*` | オペレータが agent ごとの差を理解して運用する |

## 方針候補 / メモ
1. `doc-publication-audit` は rewrite 専用として維持し、新たに `doc-source-inventory` を切る
2. custom agent は `doc-source-inventory` と `doc-packet-executor` を候補にする
3. OpenCode は `.opencode/commands/packet-plan.md`, `packet-rewrite.md`, `packet-comment-sync.md` のような command entry を候補にする
4. Continue は `.continue/rules/01-doc-packet-core.md`, `02-comment-sync.md` と `/packet-plan`, `/packet-rewrite`, `/packet-comment-sync` 相当 prompt を候補にする
5. operator 手順は `docs/runbooks/*` に置き、.github には reusable decision tree だけ残す
6. repeated task の本文は agent ごとに複製せず、shared SoT から adapter を派生させる

## スプリント分解
- [ ] shared packet rule / handoff SoT の primary destination を決める
- [ ] skill 方針を決める
- [ ] custom agent 方針を決める
- [ ] OpenCode adapter 方針を決める
- [ ] Continue adapter 方針を決める
- [ ] runbook / prompt entrypoint の要否を決める
- [ ] primary destination と neighbor sync 方針を確定する
- [ ] 2-A4 で必要な最小 asset set を確定する

## エビデンス / 参照先
- `/.github/skills/doc-publication-audit/SKILL.md`
- `/.github/skills/skill-maintenance/SKILL.md`
- `/.github/agents/client-facing-contract-maintenance.agent.md`
- `/.github/agents/test-creation.agent.md`
- `/.github/instructions/ai-assets.instructions.md`
- `docs/runbooks/local-llm-mcp-setup.md`
- OpenCode Agents — https://opencode.ai/docs/agents
- OpenCode Commands — https://opencode.ai/docs/commands
- OpenCode Config — https://opencode.ai/docs/config
- Continue config reference — https://docs.continue.dev/reference
- Continue Rules — https://docs.continue.dev/customize/deep-dives/rules
- Continue Agent mode quick start — https://docs.continue.dev/ide-extensions/agent/quick-start
- Continue OpenAI-compatible provider — https://docs.continue.dev/customize/model-providers/top-level/openai
- Continue awesome-rules — https://github.com/continuedev/awesome-rules
- Continue repo (`.continue/checks`) — https://github.com/continuedev/continue
- OpenAI Codex Subagents — https://developers.openai.com/codex/concepts/subagents

## 完了条件
- [ ] source inventory と packet rewrite の reusable asset 方針が定まっている
- [ ] shared SoT / runbook / skill / agent / OpenCode adapter / Continue adapter の役割分担が明文化されている
- [ ] OpenCode と Continue のどちらからでも 2-A4 を開始できる最小 asset set が定まっている
- [ ] asset の primary destination と neighbor sync 手順が明文化されている
