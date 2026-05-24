# Sprint 2-A2: publication packet 契約と Gemma4/OpenCode/Continue 実行プロファイル整備

## 概要
source-derived inventory をもとに、1 packet を **OpenCode または Continue.dev → LM Studio → Gemma4 26B** で安定実行するための shared schema / handoff / acceptance と、agent 別の execution profile を確定します。

## 背景 / 目的
- packet 化だけでは不十分で、local model 前提の input / output 制御がないと実行時に context overflow や再探索コストが発生する
- source inventory から packet backlog へ変換する共通ルールが必要
- docs 更新に合わせた comment sync 範囲も packet に持たせたい
- OpenCode は subagent / custom command / permission 分離を持つ一方、Continue.dev は `Plan / Agent` mode と `rules / prompts / config.yaml` を使うため、**同じ contract を agent ごとの adapter に落とす設計** が必要

## 現状
- `doc-publication-audit` は file-by-file rewrite を前提にしているが、Gemma4 26B 前提の packet schema と handoff template はまだない
- `docs/runbooks/local-llm-mcp-setup.md` と `docs/work/llm-integration/*` には local model の小分け思想があるが、public doc packet には未転用
- OpenCode 公式 docs では `Agents` / `Commands` / `Config` により project-local agent と command を source-control できる
- Continue 公式 docs では `config.yaml` の `rules` / `prompts` / `mcpServers` と `Plan / Agent` mode が正規の反復タスク導線であり、subagent 相当の仕組みは前提化されていない

## 目標 / 完了状態
- publication packet schema が確定している
- OpenCode / Continue.dev / LM Studio / Gemma4 26B 実行プロファイルが確定している
- OpenCode の subagent 利用境界と Continue の single-session 利用境界が明文化されている
- packet handoff template と acceptance template が確定している
- shared contract と agent-specific adapter の分割方針が確定している

## スコープ / 非スコープ
### 対象
- packet schema
- handoff summary format
- OpenCode profile
- Continue.dev profile
- local model budget rule
- comment anchor scope
- packet acceptance template

### 対象外
- source inventory の初回生成
- skill / subagent ファイルの実装
- packet pilot 実行
- OpenCode / Continue asset の本実装

## 確定したい出力
| 出力 | 内容 |
|---|---|
| shared packet schema v1 | `feature_slug`, `target_path`, `audience`, `doc_type`, `source_paths`, `code_anchors`, `test_anchors`, `comment_anchors`, `must_exclude`, `output_contract`, `done_when` |
| handoff template v1 | summary / paths / open questions / comment targets / unresolved risks を、local model に収まる短い形式で固定 |
| OpenCode run profile | `Plan/Build` と subagent、custom command、permission、single-writer の使い分け |
| Continue run profile | `Plan/Agent` mode、`.continue/rules`, `prompts`, `config.yaml`, LM Studio `apiBase` の使い分け |
| acceptance template | packet 完了時に残す evidence / checklist / comment-sync 判定 |

## 方針候補 / メモ
1. shared contract は agent 非依存にし、OpenCode / Continue 差分は execution profile に押し込む
2. OpenCode は read-heavy のみ最大 2 subagent 並列、write-heavy は単一 writer
3. Continue は 1 packet = 1 Plan/Agent session を原則とし、parallel subagent は前提にしない
4. packet の main conversation には raw source dump を返さず、summary / path / open questions のみ返す
5. packet には `source_paths`, `code_anchors`, `test_anchors`, `comment_anchors`, `must_exclude`, `done_when` を必須にする
6. local model 向けに、handoff の field budget / list→detail 原則 / count→detail の探索順を併記する

## スプリント分解
- [ ] OpenCode 公式 docs から agent / command / permission / config 上の制約を整理する
- [ ] Continue 公式 docs から mode / rules / prompts / config / LM Studio 接続上の制約を整理する
- [ ] shared publication packet schema を定義する
- [ ] packet handoff template と field budget を定義する
- [ ] OpenCode / Continue.dev / LM Studio / Gemma4 26B run profile matrix を定義する
- [ ] OpenCode の subagent 利用境界と Continue の single-session 利用境界を定義する
- [ ] packet acceptance template を定義する

## エビデンス / 参照先
- `docs/runbooks/local-llm-mcp-setup.md`
- `docs/work/llm-integration/2026-03-13_OnPrem_Local_Model_Onboarding_Design.md`
- `docs/work/llm-integration/2026-03-14_First_Access_Bootstrap_Discovery_Contract.md`
- OpenCode Agents — https://opencode.ai/docs/agents
- OpenCode Commands — https://opencode.ai/docs/commands
- OpenCode Config — https://opencode.ai/docs/config
- Continue config reference — https://docs.continue.dev/reference
- Continue Rules — https://docs.continue.dev/customize/deep-dives/rules
- Continue Agent mode quick start — https://docs.continue.dev/ide-extensions/agent/quick-start
- Continue OpenAI-compatible provider — https://docs.continue.dev/customize/model-providers/top-level/openai
- Continue awesome-rules — https://github.com/continuedev/awesome-rules
- OpenAI Codex Subagents — https://developers.openai.com/codex/concepts/subagents
- Anthropic multi-agent research — https://www.anthropic.com/engineering/multi-agent-research-system
- MCP Client Best Practices — https://modelcontextprotocol.io/docs/develop/clients/client-best-practices
- MCP Apps Patterns — https://apps.extensions.modelcontextprotocol.io/api/documents/Patterns.html

## 完了条件
- [ ] packet schema が後続 sprint で再利用できる形で定義されている
- [ ] OpenCode / Continue.dev の両方で Gemma4 26B 前提の実行ルールが明文化されている
- [ ] shared contract と adapter 境界が明文化されている
- [ ] packet handoff / acceptance が issue / docs/work で追跡できる形になっている
- [ ] 2-A4 の pilot で「どの agent から何を起動するか」が迷わない状態になっている
