# Issue #219 / #225 公開ドキュメント分割フレームワーク再計画

**作成日:** 2026-05-24  
**対象:** #219 `[Sprint 2] 利用者・コントリビュータ向け公開ドキュメント新規作成`  
**準備トラック:** #225 `[Sprint 2-A] 公開ドキュメント分割フレームワーク整備`  
**主対象ランタイム:** OpenCode / Continue.dev → LM Studio → Gemma4 26B  

---

## 1. 改訂後の結論

Sprint 2-A は「packet 化の準備」だけでは足りない。  
次の 4 つを **準備スプリント側で先に固定** する必要がある。

1. **ソースコード起点の doc inventory 生成**  
   現在の公開 doc リストは仮説であり、実装面の feature coverage を反映していない。
2. **Gemma4 26B / OpenCode / Continue.dev 実行前提の packet 契約**  
   1 packet を小コンテキストで安定実行できるよう、入力・出力・並列ルールと agent 別 adapter を固定する。
3. **skill / subagent / runbook の reusable 化**  
   今回の #219 だけでなく、その後の docs 維持でも同じ packet 実行系を使えるようにする。
4. **docs と source comment の同期ルール**  
   doc だけ更新して public surface の意図説明が古いまま残るのを防ぐ。

したがって、#225 はさらに **#226〜#229** に分解して進捗管理するのが妥当である。

---

## 2. 再計画が必要になった理由

## 2.1 現在の公開 doc 候補リストは source-derived ではない

従来の想定では、公開 doc 出力先を:

- `docs/getting-started/*`
- `docs/features/*`
- `docs/architecture/*`
- `docs/contributing/*`
- `docs/api/*`

の固定セットとして扱っていた。  
しかし実装側を見直すと、公開候補の機能面はこの粒度に綺麗には収まっていない。

### 実装から見える feature surface の例

| ソース | 例 | 含意 |
|---|---|---|
| `routes/tenant.php` | ledger import / export download / file download / OCR PDF / VLM download / my-portal / ledgerDefine 管理 | Getting Started / Features / Operations の境界が未整理 |
| `routes/api.php` | bootstrap manifest, search, ledger CRUD, ledger define list | `docs/api/*` は 2 ファイルでは粗すぎる可能性がある |
| `app/Livewire/Ledger/*` | related ledgers, rollback, workflow status, export, import | `ledger-management.md` 1 枚に収めると粒度差が大きい |
| `app/Filament/Resources/*` | admin announcement, permission, role, tag, tenant, user, synonym | contributor/admin surface の公開判断が未整理 |
| `tests/Feature/*` | bootstrap resource, notification banner, file inspector, rollback, VLM/RAG, synonym, MCP | 実際の feature coverage は既存 `docs/function/*.md` と 1:1 ではない |

### 「現行リストにあるが、source scan が必要」理由

現在の計画は「全機能が同じ粒度で docs に落ちる」前提になっている。  
しかし source 側では、少なくとも次のような差がある。

- **public guide として独立候補**  
  import/export, file inspector, workflow/rollback, notification/admin announcement, bootstrap/API discovery
- **architecture/reference 側へ寄せる候補**  
  VLM/OCR/Tika, queue/file processing, MCP bootstrap contract
- **internal-only の可能性が高い候補**  
  実装都合の service / job / cache / low-level config

よって、**先に source から doc candidate list を生成し、その後で packet 化する**順に変える必要がある。

---

## 3. repo から得られた具体的な示唆

### 3.1 現行 `docs/function` は source coverage の正本ではない

`docs/function/` には:

- `AccessAndActivity.md`
- `Attachment.md`
- `Ledger.md`
- `Search.md`
- `WorkFlow.md`
- `Notification.md`
- ほか

がある一方、source 側には:

- `AdminAnnouncementService`
- `BootstrapManifestController`
- `FileInspector`
- `RetryVlmProcessingJob`
- ledger rollback 一式
- export / import controller

など、public candidate になりうる surface がさらに存在する。

### 3.2 source inventory は複数の信号を束ねる必要がある

単一のディレクトリだけでは不十分。  
少なくとも次の信号を束ねる必要がある。

1. **route / controller** — 公開 URL, API surface
2. **Livewire / Filament / Blade** — WebUI surface
3. **MCP / API contract** — client-facing machine interface
4. **tests/Feature** — 実際に検証されている observable behavior
5. **lang keys** — UI 機能の語彙クラスタ
6. **既存 docs/function, docs/api, docs/architecture** — 種本候補

---

## 4. 外部エビデンス

## 4.1 docs architecture

| ソース | 要点 | この計画への含意 |
|---|---|---|
| Diataxis — https://diataxis.fr/ | docs は `tutorial / how-to / reference / explanation` の需要で分けるべき | まず feature inventory を作り、その後で doc type を割り当てるべき |
| Write the Docs, Docs as Code — https://www.writethedocs.org/guide/docs-as-code/ | docs は issue, review, automation と同じ flow で管理すべき | Sprint / issue / acceptance を packet ごとに持つ運用が妥当 |

## 4.2 source-driven reference generation

| ソース | 要点 | この計画への含意 |
|---|---|---|
| Kubernetes API reference generation — https://kubernetes.io/docs/contribute/generate-ref-docs/kubernetes-api/ | Kubernetes は OpenAPI spec から reference docs を生成し、doc bug は upstream source を直す | doc target list も source artifact から起こすべき |
| Sphinx autodoc tutorial — https://www.sphinx-doc.org/en/master/tutorial/automatic-doc-generation.html | 手書き docs は signature とズレやすい。docstring / source から生成した方が同期しやすい | public doc の根拠になる comment / docblock は source に近い位置で持つべき |
| Sphinx autodoc — https://www.sphinx-doc.org/en/master/usage/extensions/autodoc.html | doc comments / docstrings を source から引ける。public/private の印も source 近傍で管理できる | source comment を packet 対応時に整える価値がある |

## 4.3 context-constrained packet execution

| ソース | 要点 | この計画への含意 |
|---|---|---|
| OpenAI Codex Subagents — https://developers.openai.com/codex/concepts/subagents | context pollution / rot を避けるには bounded piece に分け、subagent は summary を返すべき | packet 実行は raw dump ではなく summary handoff 前提にする |
| Anthropic multi-agent research — https://www.anthropic.com/engineering/multi-agent-research-system | orchestrator は objective / output format / boundaries を明示して subagent を使うべき | packet contract に input / output / boundary を固定する必要がある |
| MCP Client Best Practices — https://modelcontextprotocol.io/docs/develop/clients/client-best-practices | progressive discovery で必要な tool 定義だけ遅延投入し、大きな intermediate result を抑える | docs 作業でも source map から必要 source だけを読むべき |
| MCP Apps Patterns — https://apps.extensions.modelcontextprotocol.io/api/documents/Patterns.html | large data は chunked tool calls で扱うべき | source inventory も chunk / batch 化して扱うべき |

## 4.4 OpenCode / Continue.dev の公式仕様

| ソース | 要点 | この計画への含意 |
|---|---|---|
| OpenCode Agents — https://opencode.ai/docs/agents | `Build/Plan` primary と `General/Explore/Scout` subagent を持ち、permission を agent ごとに分けられる | OpenCode 経路では read-heavy を subagent に分離し、write-heavy は 1 writer に固定できる |
| OpenCode Commands — https://opencode.ai/docs/commands | project-local `.opencode/commands/` に custom command を置け、`agent` / `subtask` / `model` を固定できる | 繰り返し packet 作業は ad-hoc prompt ではなく project-committed command として再利用すべき |
| OpenCode Config — https://opencode.ai/docs/config | global / project / `.opencode` が merge される | packet 実行の project ルールは repo 内 config / command / agent として配布しやすい |
| Continue config reference — https://docs.continue.dev/reference | `config.yaml` に `models` / `rules` / `prompts` / `mcpServers` を source-control できる | Continue 経路では shared contract を `rules` / `prompts` / `config snippet` に落とすのが自然 |
| Continue Rules — https://docs.continue.dev/customize/deep-dives/rules | `.continue/rules` は workspace ローカルに置け、glob / description / order を持てる | repeated task guardrail は hidden prompt ではなく repo 管理の local rules に分離すべき |
| Continue Agent mode quick start — https://docs.continue.dev/ide-extensions/agent/quick-start | `Chat / Plan / Agent` の 3 mode で、Plan は read-only、Agent は tool 実行 | Continue は subagent 前提ではなく、1 packet = 1 Plan/Agent session の単位で扱う方が安定する |
| Continue OpenAI-compatible provider — https://docs.continue.dev/customize/model-providers/top-level/openai | `provider: openai` + `apiBase` で OpenAI 互換 endpoint を使える | LM Studio を Continue 側から使うときの接続契約を issue #227 で固定できる |
| Continue awesome-rules — https://github.com/continuedev/awesome-rules | modular rule pack を source-control し、assistant 別に render する運用を推奨 | packet 実行ルールも「共有ルール本体 + agent 別 adapter」の形にすると保守しやすい |
| Continue repo (`.continue/checks`) — https://github.com/continuedev/continue | repeated AI check を repo 内 markdown で管理し、PR ごとに再利用する | packet quality gate も source-controlled asset として持つ方が drift を防げる |

---

## 5. 2-A の作業モデル

## 5.1 最小単位: publication packet

従来どおり、実行最小単位は **1 public target file** とする。  
ただし packet は **source-derived inventory を通過した後** にだけ生成する。

### packet schema（案）

| 項目 | 内容 |
|---|---|
| `feature_slug` | source inventory 由来の feature family |
| `target_path` | 公開先 1 ファイル |
| `audience` | user / contributor / api-reader |
| `doc_type` | tutorial / how-to / reference / explanation |
| `source_paths` | 種本 docs |
| `code_anchors` | routes / controllers / Livewire / services / MCP tools |
| `test_anchors` | observable behavior を証明するテスト |
| `comment_anchors` | 同期対象にする docblock / inline comment 候補 |
| `must_exclude` | internal rationale / secrets / prod-only / implementation detail |
| `output_contract` | subagent が返す summary 形式 |
| `done_when` | packet 単体 acceptance |

## 5.2 source inventory から packet へ変換する段階

1. source scan
2. feature family normalization
3. public/internal classification
4. audience + doc_type assignment
5. packet generation
6. backlog prioritization

---

## 6. OpenCode / Continue.dev / LM Studio / Gemma4 26B 実行プロファイル

## 6.1 前提

1 packet は **OpenCode または Continue.dev から LM Studio 上の Gemma4 26B を使って処理する**前提で設計する。  
したがって、Cloud LLM 向けの一括読込ではなく、local model の制約を最初から織り込む。

## 6.2 実行ルール

| 項目 | 共通ルール | OpenCode 側 adapter | Continue.dev 側 adapter |
|---|---|---|---|
| model class | `general-local` 相当（Gemma4 26B） | project config / command 側で model 固定 | `config.yaml` の local model 定義で固定 |
| packet input | packet manifest + source summary + code/test/comment anchor summary | custom command から packet manifest を渡す | prompt `/packet-*` と `.continue/rules` で packet manifest を前提化 |
| raw dump | main conversation へ source 全文を返さない | subagent summary を親へ返す | Plan/Agent session に summary だけを残す |
| write phase | 1 packet につき 1 writer | `Build` 系 1 writer | `Agent` mode 1 writer |
| repeated task entry | repo に commit された asset から起動 | `.opencode/commands/` / `.opencode/agents/` | `config.yaml` / `.continue/rules/` / prompts |
| permission gate | read と write を分離 | Plan/Explore で read、Build で write | Plan mode で read、Agent mode で write |

## 6.3 OpenCode 固有の使い分け

| 項目 | ルール |
|---|---|
| orchestrator | OpenCode main thread |
| read-heavy | `Plan` + `Explore/Scout/General` を使用し、最大 2 subagent 並列 |
| write-heavy | `Build` または write 許可した packet writer agent 1 本に限定 |
| repeated tasks | `.opencode/commands/packet-plan.md`, `packet-rewrite.md` のような custom command 化を前提にする |
| context hygiene | `subtask: true` で親コンテキスト汚染を避け、返却は summary / path / open questions のみ |

## 6.4 Continue.dev 固有の使い分け

| 項目 | ルール |
|---|---|
| orchestrator | Continue chat session |
| read-heavy | `Plan` mode で source を確認し、1 packet = 1 session を原則にする |
| write-heavy | `Agent` mode に切り替えて 1 writer で更新する |
| repeated tasks | `.continue/rules/` に常設 guardrail、`prompts` で `/packet-plan`, `/packet-rewrite`, `/packet-comment-sync` を提供する |
| local model 接続 | `provider: openai` + `apiBase` で LM Studio を使う profile を前提化する |

## 6.5 packet ごとの分業

### read-heavy

- source inventory scan
- source summarization
- existing-doc / test anchor extraction

### write-heavy

- public doc draft
- code comment normalization
- issue / packet handoff update

### 推奨パターン

1. **main**: packet manifest を選ぶ
2. **subagent A**: source / code / test anchor を要約
3. **subagent B**: existing docs / wording / gap を要約
4. **main writer**: 1 target file と comment anchor だけを更新

---

## 7. source comment 同期方針

## 7.1 目的

public doc に依存する observable behavior や business rule が source 側で読みにくい場合、  
同じ packet の中で **コメント / docblock も一緒に整える**。

## 7.2 対象

- route/controller の public behavior を説明する docblock
- Livewire / Filament page の非自明な intent
- service / helper の business-rule comment
- MCP / API public contract 説明

## 7.3 対象外

- trivial な行コメント
- 内部実装の長文解説
- public docs をそのまま source comments に複写すること

## 7.4 packet との関係

各 packet は `comment_anchors` を持ち、次を同時に判定する。

1. doc で説明する public behavior が source 側でも追えるか
2. 既存コメントが古いか
3. comment を更新すべきか、削除すべきか、何もしないか

---

## 8. Sprint 2-A の再分解

## 8.1 umbrella

**#225 = umbrella / gating sprint**

下位は次の 4 スプリントに分ける。

| Sprint | 目的 | 主成果物 |
|---|---|---|
| **#226 / 2-A1** | source-derived inventory | feature inventory / coverage gap / target list / comment anchor candidate |
| **#227 / 2-A2** | packet contract + agent profiles | packet schema / handoff template / OpenCode・Continue run profile matrix |
| **#228 / 2-A3** | reusable assets | shared SoT / skill / subagent / OpenCode・Continue adapter / runbook |
| **#229 / 2-A4** | proof + backlog freeze | pilot packet / comment sync proof / #219 packet backlog |

## 8.2 各 sprint の詳細

### #226 / Sprint 2-A1: ソースコード起点の公開ドキュメント候補棚卸し

**目的:** 既存 doc リストを正本にせず、source 側から public doc candidate を生成する。

**入力:** routes, Livewire, Filament, MCP/API, tests, lang keys, existing docs  
**出力:**
- source feature inventory
- current doc coverage gap
- revised public doc target list
- comment anchor candidate list

**完了条件:**
- #219 で書くべき doc 候補が source-derived で列挙されている
- 現行想定リストとの差分が説明できる

### #227 / Sprint 2-A2: publication packet 契約と Gemma4/OpenCode/Continue 実行プロファイル整備

**目的:** 1 packet を Gemma4 26B で安定実行する契約を、OpenCode / Continue.dev の両経路で固定する。

**出力:**
- publication packet schema
- packet handoff template
- OpenCode / Continue.dev / LM Studio / Gemma4 26B run profile matrix
- OpenCode subagent parallelism rule と Continue single-session rule
- packet acceptance template
- local model 向け text / field budget の最小基準

**完了条件:**
- 1 packet の input / output / summary / comment scope が固定されている
- OpenCode 用と Continue 用で「何を shared contract にし、何を agent adapter に逃がすか」が分かれている
- 2-A4 の pilot でそのまま使える

### #228 / Sprint 2-A3: packet 実行用 skill / subagent / runbook 整備

**目的:** 今回限りの手順ではなく reusable asset に落とし、OpenCode / Continue.dev の両経路から再利用できるようにする。

**候補成果物:**
- shared packet rule / handoff SoT
- `doc-source-inventory` skill（新規）
- `doc-publication-audit` の packet 連携強化
- `doc-packet-executor.agent.md`（新規）
- OpenCode `.opencode/agents/` / `.opencode/commands/` adapter
- Continue `.continue/rules/` / prompts / config snippet adapter
- operator runbook
- prompt entrypoint の要否整理

**完了条件:**
- source inventory と packet execution が、shared SoT + agent 別 adapter の形で再利用できる
- 2-A4 用の最小 asset set が OpenCode / Continue それぞれで確定している

### #229 / Sprint 2-A4: pilot packet 実行と comment sync 検証

**目的:** 2-A1〜2-A3 の設計が本当に回るかを小さく実証する。

**対象候補:**
- `docs/getting-started/configuration.md`
- `docs/api/mcp.md`
- `docs/features/search.md`

**出力:**
- pilot packet 実行ログ
- docs + comment 同期の実証
- #219 に引き渡す packet backlog
- packet ごとの優先順位 / 難易度 / required sources

**完了条件:**
- 1〜2 packet が end-to-end で回る
- #219 を packet backlog 実行だけで開始できる

---

## 9. skill / subagent / adapter 整備の推奨方針

## 9.1 shared source of truth

既存 `doc-publication-audit` は file-by-file rewrite 用として維持しつつ、  
source-derived inventory 用に **新しい skill を分ける** のがよい。  
ただし repeated task の中身は agent ごとの hidden prompt に分散させず、  
**shared packet rule / handoff SoT** を先に 1 つ持つべきである。

## 9.2 repo-native skill / agent

1. **`doc-source-inventory`**（新規）
   - routes / Livewire / Filament / API / MCP / tests / lang / docs から
     public doc candidate を起こす
2. **`doc-publication-audit`**（既存）
   - 1 packet の public rewrite に集中
3. **`doc-source-inventory.agent.md`**（新規）
   - read-only
   - feature family / coverage gap / comment anchor 抽出
4. **`doc-packet-executor.agent.md`**（新規）
   - 1 packet の bounded rewrite
   - source summary → doc draft → comment anchor sync

## 9.3 OpenCode adapter

1. `.opencode/agents/` に packet explore / packet writer 系 agent を置く
2. `.opencode/commands/` に `/packet-plan`, `/packet-rewrite`, `/packet-comment-sync` 相当を置く
3. `subtask: true` を使う command と単一 writer command を明確に分ける

## 9.4 Continue.dev adapter

1. `.continue/rules/` に packet core / comment sync / local model budget rule を置く
2. `prompts` に `/packet-plan`, `/packet-rewrite`, `/packet-comment-sync` を置く
3. `config.yaml` には LM Studio 用 model, MCP server, prompt/rule 参照だけを置き、長い説明本文は rules/prompts に分離する

---

## 10. GitHub 運用提案

## 10.1 追跡単位

- **#225** を umbrella
- 2-A1〜2-A4 は個別 issue に分ける
- #219 は implementation sprint のまま保持

## 10.2 #225 に残すべきもの

- 2-A 全体の目的
- sub-sprint tracking block
- final handoff criteria

## 10.3 #219 に渡すもの

- packet backlog
- target doc list v2
- execution run profile
- reusable asset list

---

## 11. 次の実行判断

優先度は次の順がよい。

1. **2-A1** を先に完了  
   source-derived inventory がないと後続ทั้งหมดが仮説の上に立つ
2. **2-A2** で Gemma4/OpenCode/Continue 実行契約を固定
3. **2-A3** で reusable asset を作る
4. **2-A4** で pilot 実証し、#219 backlog を凍結

---

## 参照

- `docs/work/2026-05-23_oss-publication-plan.md`
- `docs/work/issue-drafts/2026-05-23_issue-219_issue-body.md`
- `docs/runbooks/local-llm-mcp-setup.md`
- `docs/work/llm-integration/2026-03-13_OnPrem_Local_Model_Onboarding_Design.md`
- `docs/work/llm-integration/2026-03-14_First_Access_Bootstrap_Discovery_Contract.md`
- `/.github/skills/doc-publication-audit/SKILL.md`
- `/.github/skills/skill-maintenance/SKILL.md`
