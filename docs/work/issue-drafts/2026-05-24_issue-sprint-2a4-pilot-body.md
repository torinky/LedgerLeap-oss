# Sprint 2-A4: pilot packet 実行とコメント同期方針の検証

## GitHub 追跡
- Umbrella: #225
- Upstream inventory: #226
- Upstream format / comment policy: #230
- Sprint 2-A4: #229（本 Issue）
- Downstream implementation: #219

## 概要
Sprint 2-A1〜2-A3 と 2-A3A で定めた source inventory, packet contract, reusable asset, doc format profile, source comment policy を使い、1〜2 packet を pilot 実行して、docs + source comment 同期と #219 向け backlog の妥当性を実証します。

## 背景 / 目的
- 机上で packet schema を作るだけでは、Gemma4 26B での bounded execution が本当に回るか分からない
- source comment 同期は運用負荷があるため、pilot で scope を検証する必要がある
- #219 を開始する前に、packet backlog の優先順位と難易度を固定したい

## 現状
- #226 で source-derived inventory / target doc list v2 / comment anchor candidate が確定した
- #227 で packet schema / handoff / acceptance が確定した
- #228 で prompt / skill / agent / runbook / harness の最小資産が追加された
- #230 で doc format profile / evidence field / PHPDoc source comment policy を shared asset に反映し、pilot 前提を固定した
- pilot packet は `docs/templates/doc-publication-packet-template.md` の `doc_format_profile`, evidence fields, comment sync decision を埋めてから着手する
- #219 は packet backlog がないため implementation sprint として即着手しづらい
- #226 の結果として、pilot 候補は `docs/getting-started/*`, `docs/features/*`, `docs/api/*` の bounded packet を優先し、`docs/contributing/*` や広い admin/RBAC packet は後段へ回すのが妥当になった

## 目標 / 完了状態
- 1〜2 packet が end-to-end で実行されている
- docs と source comment の同時更新ルールが実証されている
- #219 向け packet backlog と優先順位が確定している

## スコープ / 非スコープ
### 対象
- pilot packet の選定
- packet 実行
- comment sync 実証
- packet backlog 凍結

### 対象外
- #219 全 packet の消化
- 大量の public doc 完成
- OSS sync

## 方針候補 / メモ
1. pilot は #226 で確定した target doc list v2 から、`portal-and-navigation` のような低コンテキストな end-user packet を 1 件選ぶ
2. 2 件目を選ぶ場合は `search-api` または `bootstrap-manifest-api` / `mcp-client-guide` のような bounded API/MCP packet を優先し、comment sync の要否を比較できるようにする
3. docs と comment の双方に drift が出やすい packet を 1 件含める
4. `docs/contributing/*` と広い admin / RBAC packet は source set と範囲が大きいため、pilot 候補からは原則外す
5. pilot の結果をもとに #219 backlog を優先度順に並べる

## 優先 pilot 候補
| 優先度 | packet 候補 | 理由 |
|---|---|---|
| 1 | `docs/getting-started/portal-and-navigation.md` | source 面が `MyPortal` と navigation に集約され、bounded で end-user 向け説明に寄せやすい |
| 2 | `docs/api/search-api.md` | `routes/api.php`, Search API tests, MCP search tool と対応し、REST API と MCP の分離検証に向く |
| 3 | `docs/api/bootstrap-manifest-api.md` or `docs/api/mcp-client-guide.md` | bootstrap manifest / MCP contract の comment sync と client-facing contract の切り分けを検証しやすい |

## スプリント分解
- [ ] #230 の format / evidence / source comment policy を取り込んだ上で pilot packet 候補を選定する
- [ ] 1〜2 packet を end-to-end 実行する
- [ ] comment sync scope を評価する
- [ ] #219 packet backlog と優先順位を凍結する

## エビデンス / 参照先
- Sprint 2-A1〜2-A3 の成果物
- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a3a-format-and-source-comment-body.md`
- `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md`
- `docs/templates/doc-publication-packet-template.md`
- `docs/runbooks/doc-publication-packet-playbook.md`
- `docs/work/2026-05-24_issue-219_chunked-doc-framework-plan.md`
- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a1-source-inventory-body.md`
- `/.github/skills/doc-publication-audit/SKILL.md`

## 完了条件
- [ ] pilot packet の handoff と成果物が残っている
- [ ] pilot packet に `doc_format_profile` と source comment policy が明示されている
- [ ] comment sync 方針の可否と scope が明文化されている
- [ ] #219 は packet backlog 実行の implementation sprint として開始できる状態になっている
