# Sprint 2-A2: publication packet 契約と Gemma4/OpenCode/Continue 実行プロファイル整備

## GitHub 追跡
- Umbrella: #225
- Upstream inventory: #226
- Sprint 2-A2: #227（本 Issue）
- Downstream assets: #228
- Downstream pilot: #229

## 概要
source-derived inventory（#226）を正本として、1 packet を **OpenCode または Continue.dev → LM Studio → Gemma4 26B** で安定実行するための shared schema / handoff / acceptance と、agent 別の execution profile を確定しました。

## 背景 / 目的
- packet 化だけでは不十分で、local model 前提の input / output 制御がないと context overflow や再探索コストが残る
- #226 の inventory から packet backlog へ変換する共通ルールが必要
- docs 更新に合わせた comment sync 範囲を packet 契約へ持たせたい
- OpenCode は primary agent / subagent / custom command / permission の分離を持ち、Continue.dev は `config.yaml` / `rules` / `prompts` / `Plan / Agent` mode を持つため、**shared contract と agent adapter を明示的に分ける必要がある**

## 2-A1 から受け継ぐ authoritative inputs
- feature families: `Ledger lifecycle`, `Workflow`, `Search / lookup / taxonomy`, `Attachments`, `My Portal`, `Notifications / announcements`, `Folders`, `Identity / RBAC`, `REST API`, `MCP contract`
- target doc list v2: `docs/getting-started/*`, `docs/features/*`, `docs/admin/*`, `docs/api/*`, `docs/architecture/*`
- `REST API` と `MCP contract` は別 packet family として扱う
- `docs/contributing/*` は 2-A1 source set では裏取り不足のため、default backlog から外し **provisional** として別管理する

## 2-A2 で確定した判断
1. `target_slug` を packet の canonical identifier とし、`target_path` は `doc_area + target_slug` から導出する
2. `doc_area` は #226 の target doc list v2 に限定し、`docs/contributing/*` は `source_status: provisional` のときだけ例外的に保持できる
3. `comment_anchors` は「常に必須」ではなく、**`comment_sync_policy` が `required` / `optional` / `not_applicable` のいずれかで明示されていること**を必須にする
4. Gemma4 26B 向け handoff は summary-first を徹底し、raw source dump を main conversation に残さない
5. OpenCode は read-heavy のみ最大 2 subagent 並列、write-heavy は 1 writer に固定する
6. Continue.dev は subagent 相当を前提にせず、**1 packet = 1 Plan/Agent session** を基本単位にする

## shared publication packet schema v1

### 1. packet identity
- `packet_id`: `<doc_area>.<target_slug>`
- 例:
  - `docs/getting-started.portal-and-navigation`
  - `docs/api.search-api`
  - `docs/api.mcp-client-guide`

### 2. field set
| Field | 必須 | 入力種別 | ルール |
|---|---|---|---|
| `feature_family` | 必須 | derived | #226 の正規化 family をそのまま使う |
| `doc_area` | 必須 | derived | `docs/getting-started`, `docs/features`, `docs/admin`, `docs/api`, `docs/architecture` のみを default 許可 |
| `target_slug` | 必須 | authored | packet の canonical slug。`target_path` の唯一の命名起点 |
| `target_path` | 必須 | derived | `<doc_area>/<target_slug>.md` で導出し、手打ちで drift させない |
| `public_classification` | 必須 | derived | `public-user`, `admin-operator`, `api-client`, `mcp-client`, `architecture`, `provisional` のいずれか |
| `source_status` | 必須 | derived | `confirmed` / `provisional`。`docs/contributing/*` は `provisional` のみ |
| `audience` | 必須 | hybrid | 主読者を 1 つに絞る。必要なら secondary audience は handoff 側に回す |
| `doc_type` | 必須 | authored | `tutorial`, `how-to`, `reference`, `explanation` から選ぶ |
| `source_paths` | 必須 | derived | #226 inventory と既存 docs から 1〜5 件 |
| `code_anchors` | 必須 | derived | 2〜8 件。route / Livewire / Filament / MCP tool など観測面を優先 |
| `test_anchors` | 必須 | derived | 1〜5 件。observable behavior の根拠だけを残す |
| `comment_anchors` | 条件付き必須 | hybrid | 0〜5 件。空配列を許すのは `comment_sync_policy: not_applicable` のときだけ |
| `comment_sync_policy` | 必須 | authored | `required`, `optional`, `not_applicable` のいずれか |
| `must_exclude` | 必須 | authored | 1〜6 項目。内部実装事情 / secret / operator-only detail を列挙 |
| `output_contract` | 必須 | authored | handoff / acceptance / comment sync の返却形を指す |
| `done_when` | 必須 | authored | 3〜5 件の checkable acceptance |

### 3. Gemma4 26B 向け field budget
| 項目 | budget |
|---|---|
| summary | 600 文字以内、または 8 bullet 以内 |
| `source_paths` | 最大 5 |
| `code_anchors` | 最大 8 |
| `test_anchors` | 最大 5 |
| `comment_anchors` | 最大 5 |
| open questions | 最大 3 |
| unresolved risks | 最大 3 |
| `must_exclude` | 最大 6 |
| `done_when` | 最大 5 |

### 4. output contract rule
- main conversation には raw source 全文を返さない
- 返却は `summary -> anchors -> open questions -> risks -> next write target` の順で固定する
- list→detail を守り、必要な source 本文は packet writer が個別取得する

## #226 inventory から packet backlog へ変換するルール
1. #226 の `feature family` 行と target doc list v2 を起点にする
2. **1 public target file = 1 packet** に分割する
3. `target_slug` を先に決め、`target_path` はそこから導出する
4. `public_classification` を次の lane に写像する
   - `docs/getting-started/*` / `docs/features/*` → `public-user`
   - `docs/admin/*` → `admin-operator`
   - `docs/api/*` + REST API family → `api-client`
   - `docs/api/*` + MCP family → `mcp-client`
   - `docs/architecture/*` → `architecture`
   - `docs/contributing/*` → `provisional`
5. `source_status=confirmed` かつ code/test/comment sync 判定が揃った packet だけを **ready backlog** に入れる
6. `source_status=provisional` は **default backlog へ入れず**、別キューに残す
7. `REST API` と `MCP contract` は同じ `docs/api/*` 配下でも別 packet family として分離する
8. comment anchor candidate は #226 の family 別 candidate list から対応 packet へ引き継ぐ

### backlog conversion examples
| #226 input | packet |
|---|---|
| `My Portal` + `docs/getting-started/*` | `docs/getting-started.portal-and-navigation` |
| `Search / lookup / taxonomy` + `docs/features/*` | `docs/features.search-and-lookup` |
| `REST API` + `docs/api/*` | `docs/api.search-api`, `docs/api.ledger-api`, `docs/api.bootstrap-manifest-api` |
| `MCP contract` + `docs/api/*` | `docs/api.mcp-client-guide` |
| `docs/contributing/*` candidate | `source_status: provisional` で separate queue |

## packet handoff template v1
```markdown
## Packet handoff
- Packet: `<packet_id>`
- Goal: `<120 chars以内>`
- Publish target: `docs/...`
- Reader + doc_type: `<audience> / <doc_type>`
- Source summary:
  - `...`
  - `...`
- Required anchors:
  - code: `path:line`
  - test: `path:line`
  - comment: `path:line` or `not_applicable: <reason>`
- Must exclude:
  - `...`
- Open questions:
  - `...`
- Unresolved risks:
  - `...`
- Done when:
  - [ ] `...`
```

### handoff operating rules
- summary は 600 文字以内 / 8 bullet 以内
- open questions / risks は各 3 件まで
- 参照 path は packet writer が追加取得できる粒度に留める
- source 全文貼り付けは禁止

## packet acceptance template v1
```markdown
## Packet acceptance
| 観点 | 判定 | エビデンス |
|---|---|---|
| public target updated | ✅ / ❌ | `docs/...` |
| source-derived scope respected | ✅ / ❌ | `feature_family`, `must_exclude` |
| code/test anchors reflected | ✅ / ❌ | `path:line` |
| comment sync handled | ✅ / ❌ | `required / optional / not_applicable` と対象 path |
| unresolved risks recorded | ✅ / ❌ | 箇条書き |

- Done when:
  - [ ] packet target が更新済み
  - [ ] acceptance table が埋まっている
  - [ ] comment sync 判定が残っている
  - [ ] 次 sprint が迷わない handoff が残っている
```

## OpenCode / Continue.dev / LM Studio / Gemma4 26B run profile matrix
| 項目 | shared rule | OpenCode adapter | Continue.dev adapter | LM Studio / Gemma4 26B 前提 |
|---|---|---|---|---|
| session unit | 1 packet = 1 bounded task | primary agent 1 本を親に置く | chat session 1 本を親に置く | `general-local` 相当で扱う |
| read phase | summary-first / list→detail | `Plan` primary + `Explore` / `Scout` / `General` subagent | `Plan` mode | raw dump を main conversation に残さない |
| write phase | 1 packet = 1 writer | `Build` primary 1 本のみ | `Agent` mode 1 本のみ | 変更前に必要 source だけ再取得 |
| parallelism | write は常に単一 | read-heavy のみ最大 2 subagent 並列 | subagent 前提なし | context 汚染を避ける |
| repeated-task asset | shared contract を adapter から参照 | `.opencode/commands/*`, `.opencode/agents/*`, `opencode.json` | `config.yaml`, `.continue/rules/*`, `prompts` | adapter 層に閉じ込める |
| permission gate | read と write を分離 | `Plan` は edit/bash 制限、`Build` は write 許可、agent permission で細分化 | Plan/Agent mode と tool permission を使い分ける | destructive write を同時並行にしない |
| MCP connection | remote MCP 前提 | `mcp-remote` を OpenCode 設定へ | `mcpServers` で `command/args/env` を定義 | OpenAI-compatible endpoint は `apiBase` で接続 |
| local model profile | short summary / capped anchors | command template で handoff 形を固定 | rules + prompt で handoff 形を固定 | 32k+ context, stop-at-limit 運用を前提 |

## OpenCode subagent boundary
- built-in primary agent は `Build` / `Plan`
- built-in subagent は `General` / `Explore` / `Scout`
- read-heavy packet では `Plan` を親にし、必要に応じて最大 2 subagent まで
- write-heavy packet では `Build` 1 本だけを writer にする
- custom command は `.opencode/commands/*.md`、custom agent は `.opencode/agents/*.md` に置き、packet task を source-control する
- `permission` で `edit` / `bash` / `task` を制御し、packet writer 以外の write を防ぐ

## Continue.dev single-session boundary
- `config.yaml` の top-level は `models`, `rules`, `prompts`, `mcpServers` を使う
- `Plan` mode で read-only exploration、`Agent` mode で write を行う
- rules は `.continue/rules/*.md` を local source of truth とし、system message に結合される
- prompts は `/command` 形式で起動し、packet task starter を提供する
- LM Studio は `provider: openai` + `apiBase` で接続し、tool 使用が不安定なら `capabilities: [tool_use]` を明示する
- subagent 前提を置かず、1 packet を 1 session で完結させる

## shared contract と adapter の境界
### shared contract に置くもの
- packet schema v1
- backlog conversion rule
- handoff template
- acceptance template
- Gemma4 26B 向け field budget

### adapter に逃がすもの
- OpenCode の agent / command file path、permission 詳細
- Continue の `config.yaml` / `.continue/rules` / prompt 配置
- LM Studio 接続値（`apiBase`, `apiKey`, context length など）の client-specific setting

## 公式仕様の確認メモ（2026-05-24）
- OpenCode docs で `Build` / `Plan` primary、`General` / `Explore` / `Scout` subagent、`.opencode/agents` / `.opencode/commands`、permission 制御を確認
- Continue docs で `config.yaml` top-level の `models`, `rules`, `prompts`, `mcpServers` を確認
- Continue docs で `Plan` / `Agent` mode、rules の local `.continue/rules` 自動読み込み、prompts の `/command` 起動を確認
- Continue docs で OpenAI-compatible provider の `apiBase` を確認

## スプリント分解
- [x] OpenCode 公式 docs から agent / command / permission / config 上の制約を整理する
- [x] Continue 公式 docs から mode / rules / prompts / config / LM Studio 接続上の制約を整理する
- [x] shared publication packet schema を定義する
- [x] #226 の feature family / target doc list v2 から packet backlog へ変換するルールを定義する
- [x] packet handoff template と field budget を定義する
- [x] OpenCode / Continue.dev / LM Studio / Gemma4 26B run profile matrix を定義する
- [x] OpenCode の subagent 利用境界と Continue の single-session 利用境界を定義する
- [x] packet acceptance template を定義する

## エビデンス / 参照先
- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a1-source-inventory-body.md`
- `docs/work/2026-05-24_issue-219_chunked-doc-framework-plan.md`
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

## 完了条件
- [x] packet schema が後続 sprint で再利用できる形で定義されている
- [x] OpenCode / Continue.dev の両方で Gemma4 26B 前提の実行ルールが明文化されている
- [x] shared contract と adapter 境界が明文化されている
- [x] packet handoff / acceptance が issue / docs/work で追跡できる形になっている
- [x] 2-A4 の pilot で「どの agent から何を起動するか」が迷わない状態になっている
