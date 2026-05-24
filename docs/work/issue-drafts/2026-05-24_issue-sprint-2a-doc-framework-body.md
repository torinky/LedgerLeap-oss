# Sprint 2-A: 公開ドキュメント分割フレームワーク整備

## 概要
Issue #219 の本文執筆に入る前に、公開ドキュメント作業を **source-driven / packet-driven** に再構成するための umbrella sprint です。目的は、OpenCode 経由の LM Studio / Gemma4 26B のような小コンテキスト環境でも、コード探索・source 抽出・公開 doc 化・source comment 同期を bounded な単位で独立実行できるようにすることです。

## 背景 / 目的
- #219 は利用者・コントリビュータ・API/MCP 利用者向け doc を一括で新規作成する sprint だが、対象 audience と source が広く 1 セッションに収まりにくい
- 現在の公開 doc リストは「全 feature が同じ粒度で揃っている」仮説に依存しており、routes / Livewire / Filament / API / MCP / tests から見える実装面の feature coverage を反映していない
- docs 更新時に public surface の source comment も一緒に整える運用を先に決めないと、外部向け docs と source の意図説明が乖離しやすい
- 今回だけの手順ではなく、今後の docs 維持でも再利用できる skill / subagent / runbook を用意したい

## 現状
- #226 で source-derived inventory / current coverage gap / #219 用 target doc list v2 / comment anchor candidate list が確定した
- 公開 target は `docs/getting-started/*`, `docs/features/*`, `docs/admin/*`, `docs/api/*`, `docs/architecture/*` に再整理され、REST API と MCP は別 packet 系列として扱う前提になった
- `docs/contributing/*` は 2-A1 の source set では十分に裏取りできておらず、別 source scan を前提に後続 sprint へ持ち越す
- `docs/runbooks/local-llm-mcp-setup.md` と `docs/work/llm-integration/*` では local model 向けに `list -> detail`, 最小 bundle, text budget を前提にしている
- `/.github/skills/doc-publication-audit/SKILL.md` は file-by-file rewrite を前提にしているが、Issue #219 用の source inventory / packet 契約 / Gemma4 実行プロファイルはまだ未整備

## 目標 / 完了状態
- #219 を進める前に **source-derived な公開 doc target list** が確定している
- `publication packet` の schema, handoff, acceptance が Gemma4 26B 前提で確定している
- source inventory と packet rewrite を再利用できる skill / subagent / runbook の方針が確定している
- docs 更新と source comment 同期の最小ルールが確定している
- #219 に渡す packet backlog と優先順位が確定している

## スコープ / 非スコープ
### 対象
- source code からの doc inventory 生成
- `publication packet` 単位の定義
- OpenCode / LM Studio / Gemma4 26B 実行プロファイル
- packet 実行時の handoff / evidence / completion contract の定義
- packet 実行に使う skill / subagent / runbook の設計
- docs 対象 feature に対応する source comment 同期ルールの整理

### 対象外
- `docs/getting-started/*` や `docs/features/*` 本文の大量執筆完了
- OSS repo への実同期や public 切替
- AI 資産リポジトリ切り出しそのもの
- 既存 internal docs の全文 rewrite

## 方針候補 / メモ
1. 最小単位は「章」ではなく `1 target public file + source anchors + comment anchors + done criteria` の packet とする
2. packet は source-derived inventory を通過した feature だけを対象に作る
3. OpenCode / Gemma4 26B では read-heavy のみ最大 2 subagent 並列、write-heavy は単一 writer に制限する
4. docs 更新が public behavior を説明する場合、必要最小限の source comment / docblock を同 packet 内で整える
5. reusable 化は `doc-publication-audit` を rewrite 専用に残し、source inventory 用の skill / subagent を別立てする方向で検討する
6. packet contract / asset / pilot は #226 で確定した `feature family`, `doc area`, `public/internal`, `comment anchor group` を入力の正本として扱う
7. `docs/contributing/*` は 2-A1 の inventory からは外し、#219 本体へ直結させる前に別 source set で再棚卸しする

## スプリント分解
- [x] **#226 / Sprint 2-A1:** source-derived な公開 doc candidate / coverage gap / comment anchor candidate を生成する
  - Evidence: `docs/work/issue-drafts/2026-05-24_issue-sprint-2a1-source-inventory-body.md`, #226
- [x] **#227 / Sprint 2-A2:** `publication packet` schema と OpenCode / LM Studio / Gemma4 26B 実行プロファイルを確定する
  - Evidence: `docs/work/issue-drafts/2026-05-24_issue-sprint-2a2-packet-contract-body.md`, #227
- [x] **#228 / Sprint 2-A3:** source inventory / packet rewrite 用の skill / subagent / runbook の方針と最小成果物を確定する
  - Evidence: `docs/work/issue-drafts/2026-05-24_issue-sprint-2a3-assets-body.md`, #228
- [x] **#230 / Sprint 2-A3A:** publication packet の文書フォーマット規約 / evidence field / PHPDoc source comment policy を補強する
  - Evidence: `docs/work/issue-drafts/2026-05-24_issue-sprint-2a3a-format-and-source-comment-body.md`, `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md`, #230
- [ ] **#229 / Sprint 2-A4:** #230 で固定した format/comment policy を前提に pilot packet と comment sync を実証し、#219 向け packet backlog を凍結する

## 確定アウトプット
| Sprint | 主アウトプット |
|---|---|
| **#226 / 2-A1** | source feature inventory / doc coverage gap / target doc list v2 / comment anchor candidate list ✅ |
| **#227 / 2-A2** | publication packet schema / handoff template / OpenCode-Gemma4 run profile / packet acceptance template ✅ |
| **#228 / 2-A3** | source inventory 用 skill 方針 / packet rewrite 用 subagent 方針 / operator runbook 方針 ✅ |
| **#230 / 2-A3A** | doc format profile matrix / evidence field matrix / PHPDoc minimum rule ✅ |
| **#229 / 2-A4** | pilot packet 実行記録 / docs+comment sync 評価 / #219 packet backlog |

## Sprint 2-A1 handover (2026-05-24)

### Branch
- 完了時 branch: `main`
- 次 sprint 推奨起点: `main` から対象 issue 番号の branch を新規作成

### Changed files
| ファイル | 変更内容 | 次 sprint での扱い |
|---|---|---|
| `docs/work/issue-drafts/2026-05-24_issue-sprint-2a1-source-inventory-body.md` | source-derived inventory / coverage gap / target doc list v2 / comment anchors を正本化 | #227〜#230 / #229 の packet contract / asset / pilot 候補の正本として再利用 |
| `docs/work/2026-05-24_issue-219_chunked-doc-framework-plan.md` | #226〜#230 の分割方針と sprint 詳細を保持 | 後続 sprint の目的・範囲確認に再利用 |

### TODO comment locations
- なし（TODO マーカーは追加していない）

### Open questions
- #228 の reusable asset で、`doc-source-inventory` を初回 discovery 用ではなく inventory refresh / diff 用に切り替えるか
- #229 の pilot を `portal-and-navigation` と `search-api` / `bootstrap-manifest-api` のどれで始めるか
- #219 の `docs/contributing/*` を別 source scan issue に切り出すか、2-A4 backlog 凍結時に provisional 扱いで残すか

## Sprint 2-A2 handover (2026-05-24)

### Branch
- 完了時 branch: `main`
- 次 sprint 推奨起点: `main` から対象 issue 番号の branch を新規作成

### Changed files
| ファイル | 変更内容 | 次 sprint での扱い |
|---|---|---|
| `docs/work/issue-drafts/2026-05-24_issue-sprint-2a2-packet-contract-body.md` | packet schema v1 / backlog conversion rule / handoff template / acceptance template / OpenCode-Continue run profile matrix を正本化 | #228 の shared SoT と adapter asset 設計の正本として再利用 |
| `docs/work/issue-drafts/2026-05-24_issue-sprint-2a-doc-framework-body.md` | umbrella の sprint 進捗と 2-A2 handover を同期 | #228 以降の sprint handover 追記先として継続利用 |

### TODO comment locations
- なし（TODO マーカーは追加していない）

### Open questions
- #229 の pilot packet を `portal-and-navigation`, `search-api`, `mcp-client-guide` のどれで始めるか
- `docs/contributing/*` の provisional queue を #219 本体へどう引き渡すか

## Sprint 2-A3 handover (2026-05-24)

### Branch
- 完了時 branch: `main`
- 次 sprint 推奨起点: `main` から対象 issue 番号の branch を新規作成

### Changed files
| ファイル | 変更内容 | 次 sprint での扱い |
|---|---|---|
| `/.github/prompts/doc-publication-packet.prompt.md` | JetBrains / Copilot 用の packet lane entrypoint を追加 | #229 の packet kickoff entry として再利用 |
| `/.github/skills/doc-source-inventory/SKILL.md` | inventory refresh 専用 skill を追加 | #229 で stale packet / anchor delta が出たときの起点にする |
| `/.github/skills/doc-publication-audit/SKILL.md` | packet handoff / comment sync / stale-baseline handback を追加 | #229 の rewrite lane に再利用 |
| `/.github/agents/doc-source-inventory.agent.md`, `/.github/agents/doc-packet-executor.agent.md` | Copilot 系 subagent の inventory / rewrite 役割を追加 | #229 で bounded task を切るときの候補として再利用 |
| `.opencode/agents/*`, `.opencode/commands/*` | OpenCode adapter を追加 | #229 で OpenCode 実行時にそのまま使用 |
| `.continue/rules/*`, `docs/harnesses/doc-publication-packet/continue-config.template.yaml` | Continue adapter と sanitized config harness を追加 | #229 で Continue 実行時の最小セットとして再利用 |
| `docs/runbooks/doc-publication-packet-playbook.md` | packet operator flow を追加 | #229 の人間向け手順として再利用 |
| `docs/templates/doc-publication-packet-template.md` | shared packet manifest / handoff / acceptance template を追加 | #229 の packet evidence 形として再利用 |

### TODO comment locations
- なし（TODO マーカーは追加していない）

### Open questions
- #229 の pilot packet を `portal-and-navigation`, `search-api`, `mcp-client-guide` のどれで始めるか
- `docs/contributing/*` の provisional queue を #219 本体へどう引き渡すか

## Sprint 2-A3A handover (2026-05-24)

### Branch
- 完了時 branch: `main`
- 次 sprint 推奨起点: `main` から対象 issue 番号の branch を新規作成

### Changed files
| ファイル | 変更内容 | 次 sprint での扱い |
|---|---|---|
| `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md` | `doc_format_profile` matrix / evidence field matrix / source comment target selection / PHPDoc minimum rule を正本化 | #229 の pilot handoff と acceptance の根拠として再利用 |
| `docs/templates/doc-publication-packet-template.md` | packet manifest / handoff / acceptance に format profile と evidence fields を追加 | #229 の packet handoff にそのまま使用 |
| `docs/runbooks/doc-publication-packet-playbook.md` | format profile / evidence / source comment policy を operator flow に追加 | #229 の pilot 実行手順として再利用 |
| `/.github/prompts/doc-publication-packet.prompt.md`, `/.github/skills/doc-publication-audit/SKILL.md` | JetBrains / Copilot 側の rewrite entrypoint に format / evidence / PHPDoc policy を同期 | #229 の packet kickoff 時にそのまま使用 |
| `.continue/rules/*`, `.opencode/commands/*`, `docs/harnesses/doc-publication-packet/continue-config.template.yaml`, `/.github/agents/doc-packet-executor.agent.md`, `.opencode/agents/doc-packet-executor.md` | Continue / OpenCode / Copilot adapter に policy を同期 | pilot adapter として再利用 |

### TODO comment locations
- なし（TODO マーカーは追加していない）

### Open questions
- #229 の 1 件目 pilot packet を `portal-and-navigation`, `search-api`, `mcp-client-guide` のどれで始めるか
- `docs/contributing/*` の provisional queue を #219 本体へどう引き渡すか

## エビデンス / 参照先
- `docs/work/2026-05-24_issue-219_chunked-doc-framework-plan.md`
- `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md`
- `docs/work/2026-05-23_oss-publication-plan.md`
- `docs/runbooks/local-llm-mcp-setup.md`
- `routes/tenant.php`
- `routes/api.php`
- `app/Livewire/*`
- `app/Filament/*`
- `tests/Feature/*`
- `/.github/skills/doc-publication-audit/SKILL.md`
- `/.github/skills/skill-maintenance/SKILL.md`
- Diataxis — https://diataxis.fr/
- Write the Docs, Docs as Code — https://www.writethedocs.org/guide/docs-as-code/
- Kubernetes API reference generation — https://kubernetes.io/docs/contribute/generate-ref-docs/kubernetes-api/
- Sphinx autodoc tutorial — https://www.sphinx-doc.org/en/master/tutorial/automatic-doc-generation.html
- Sphinx autodoc — https://www.sphinx-doc.org/en/master/usage/extensions/autodoc.html
- OpenAI Codex Subagents — https://developers.openai.com/codex/concepts/subagents
- Anthropic multi-agent research — https://www.anthropic.com/engineering/multi-agent-research-system
- MCP Client Best Practices — https://modelcontextprotocol.io/docs/develop/clients/client-best-practices
- MCP Apps Patterns — https://apps.extensions.modelcontextprotocol.io/api/documents/Patterns.html

## 完了条件
- [ ] source-derived な doc inventory と current coverage gap が整理されている
- [ ] #219 の target doc list が source inventory ベースで更新されている
- [ ] 1 packet を Gemma4 26B で処理する input / output / subagent / acceptance 契約が定義されている
- [ ] skill / subagent / runbook の最小構成が決まり、再利用可能な形で整理されている
- [ ] pilot packet を動かす前提が整い、#219 は packet backlog 実行だけで開始できる

## 関連リンク
- Epic: #216
- Sprint 2: #219
- 関連計画: `docs/work/2026-05-24_issue-219_chunked-doc-framework-plan.md`

## GitHub 追跡
- Epic: #216 / Sprint 2: #219 / Sprint 2-A: #225 / Sprint 2-A1: #226 / Sprint 2-A2: #227 / Sprint 2-A3: #228 / Sprint 2-A3A: #230 / Sprint 2-A4: #229

## Owner manual steps
1. GitHub Web UI で #225 を開く
2. 右サイドバーまたは sub-issue UI から #226, #227, #228, #230, #229 を sub-issue として追加する
3. #216 と #219 から見て追跡しやすいよう、必要なら handover/comment に #225 系列を追記する

## 確認事項
- この issue はバグ報告ではないことを確認した
- 背景 / 現状 / 目標 / スコープを分けて書いた
- スプリント分解と完了条件を記入した
- 参照先やエビデンスを可能な範囲で添付した
