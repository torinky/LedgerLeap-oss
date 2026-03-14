# MCP tool description audit and reduction plan

**作成日:** 2026年03月14日  
**ドキュメント種別:** 作業ファイル（調査・棚卸し・見直し計画）  
**関連Issue:** [#83](https://github.com/torinky/LedgerLeap/issues/83)

## 1. 目的

Issue #83 の検討を通じて、`app/Mcp/Tools/*.php` の `description` に
**ツール固有の契約説明** と **クライアント側 skill / guide が持つべきプロセス説明** が混在していることが明確になった。

本メモの目的は次の 3 点である。

1. `app/Mcp/Tools` の `description` を網羅的に棚卸しする
2. 何を tool description に残し、何を client-facing skill / guide へ移すべきかを整理する
3. 後続の実装 Issue に落とし込める粒度で、見直し順序と完了条件を決める

## 2. なぜ `docs/work/llm-integration` に置くか

今回の論点は、MCP ツール実装そのものよりも、
**client-facing / developer-facing / bootstrap discovery の責務分離** に属する。

そのため、一次保守先は以下のいずれでもなく、`docs/work/llm-integration` が適切と判断した。

- `.github/instructions/*` の path-specific 編集ルール
- `.github/skills/*` の再利用判断木
- `docs/function/*` の正式機能仕様

この文書は、**Issue #83 系の設計判断ログ** と **実装前の棚卸し資料** を兼ねる。

## 3. 調査対象

### 3.1 直接確認したコード

- `app/Mcp/Tools/*.php`（全 15 tool）
- `app/Mcp/Servers/LedgerLeapServer.php`
- `app/Mcp/Prompts/BootstrapClientSkillsPrompt.php`
- `app/Mcp/Resources/BootstrapClientResource.php`

### 3.2 対照に使った client-facing / developer-facing 正本

- `resources/ai/capabilities/README.md`
- `resources/ai/capabilities/*.yaml`
- `docs/work/llm-integration/2026-03-10_Client_Facing_Capability_Taxonomy.md`
- `docs/work/llm-integration/2026-03-12_Developer_Facing_Maintenance_Taxonomy.md`
- `docs/work/llm-integration/2026-03-14_First_Access_Bootstrap_Discovery_Contract.md`
- `docs/work/llm-integration/2026-03-13_MCP_Update_Tools_Implementation_Log.md`
- `docs/work/llm-integration/2025-10-05_MCP_SearchLedgersTool_Enhancement.md`

### 3.3 GitHub 文脈

- 親 Issue: `#83 [LLM Integration] MCP / API first での client-facing 再設計`
- Issue #83 の本文・コメントから、client-facing と developer-facing の分離方針、および bootstrap discovery の責務分離を再確認した

## 4. 調査方法

### 4.1 判定軸

各 description を次の 4 区分で見た。

| 区分 | 説明 | 主な置き場 |
|---|---|---|
| Tool contract | その tool 単体の役割、主要返却物、契約上の制約 | `app/Mcp/Tools/*.php` |
| Client flow | 「まず検索、次に詳細、その後更新」のような標準フロー | `resources/ai/capabilities/*.yaml` / client-side skill |
| Onboarding / discovery | 最初の質問例、開始導線、bundle 説明 | prompt / resource / bootstrap manifest |
| Developer internals | REST parity、共有 service、内部実装事情 | `docs/work` / `docs/function` / `.github` |

### 4.2 今回の基本判断

tool description に残すべきなのは、原則として次だけでよい。

1. この tool で何ができるか
2. 入力・出力で最低限知るべき契約上の特徴
3. 誤用を防ぐための短い制約

逆に、次は原則として tool description から外してよい。

- 他 tool を列挙した標準手順
- 「失敗したら次は別 tool」という探索戦略
- ペルソナ向けの質問例や業務シナリオ
- 詳細な performance tips
- 実装共有や REST parity などの内部都合

## 5. 棚卸し結果サマリー

### 5.1 全体件数

- 対象 tool: **15 件**
- `Standard workflow:` を含む tool: **3 件**
- `Workflow Example` を含む tool: **1 件**
- `Use this tool when ...` を含む tool: **1 件**
- server-level instructions にも具体的使用手順あり: **1 箇所**（`LedgerLeapServer::$instructions`）

### 5.2 description 密度の概観

| グループ | 該当 tool | 所見 |
|---|---|---|
| ほぼ問題なし（最小契約） | `GetLedgerDefinesTool`, `CreateLedgerTool`, `GetActivityLogTool`, `ExecuteApprovalTool`, `GetPendingApprovalsTool`, `GetWorkflowHistoryTool`, `ClaimWorkflowTaskTool` | プロセス誘導はほぼない。将来の語彙統一は別件でよい |
| 軽微（format 説明中心） | `GetLedgerStatsTool`, `GetUserActivityStatsTool`, `GetFolderStatsTool` | 冗長ではあるが、主に返却形式説明。優先度は低い |
| 中優先（標準手順を含む） | `GetLedgerDetailTool`, `UpdateLedgerTool`, `GetRelatedLedgersTool`, `GetClientBootstrapManifestTool` | tool 固有説明に加えて、他 tool との手順や契約背景が混じる |
| 最優先（プロセス説明が支配的） | `SearchLedgersTool` | description が 사실上 mini-skill 化しており、client-side skill / capability と重複している |

## 6. tool ごとの調査メモ

### 6.1 最優先見直し対象

| Tool | 現状 | 問題 | 残すもの | 移す先 |
|---|---|---|---|---|
| `SearchLedgersTool` | 103 行 / 約 8.9k chars。検索戦略、例、重要案件の扱い、パフォーマンスヒント、他 tool へのピボットを大量記述 | tool description というより client-side skill 本文。`ledger-search.yaml` の `recommended_flow`、guide、bootstrap skill と強く重複 | 検索対象、主要パラメータ群、`summary/raw`、`meta` の存在、`semantic_score` の意味、日本語対応の短い説明 | `resources/ai/capabilities/ledger-search.yaml`、`ledgerleap://guides/search-strategy`、client-side skill |

### 6.2 中優先見直し対象

| Tool | 現状 | 問題 | 残すもの | 移す先 |
|---|---|---|---|---|
| `GetLedgerDetailTool` | `Standard workflow` で検索→詳細→定義→dry_run→更新までを説明 | `ledger-update` の標準フローと重複 | 単一レコードの最新内容、workflow state、editability を返すこと | `resources/ai/capabilities/ledger-update.yaml` / update guide |
| `UpdateLedgerTool` | `Standard workflow` と初期契約の境界が混在 | フロー説明は capability 側、契約制約は tool 側に分けるべき | `content_patch` による部分更新、`dry_run`、tag 未対応、approved lock | `resources/ai/capabilities/ledger-update.yaml` / update guide |
| `GetRelatedLedgersTool` | 検索→詳細→関連探索→詳細確認の手順を記述 | `ledger-search` の related search flow と重複 | source ledger を起点に identifier / semantic で関連候補を返すこと | `resources/ai/capabilities/ledger-search.yaml` / search guide |
| `GetClientBootstrapManifestTool` | REST と同じ bundle、shared service 再利用、developer-facing internals 非露出を説明 | 実装事情や parity 背景が tool description に混入 | `client_type` / `role_profile` / `model_profile` / `language` に応じて client-facing bootstrap bundle を返すこと | `docs/work/llm-integration/*`, bootstrap discovery docs |

### 6.3 低優先の軽微見直し対象

| Tool | 現状 | 判断 |
|---|---|---|
| `GetLedgerStatsTool` | `summary/raw` の説明が 5 行 | 今すぐ問題化しないが、将来の style sweep で 2〜3 行に圧縮可能 |
| `GetUserActivityStatsTool` | 同上 | 同上 |
| `GetFolderStatsTool` | 同上 | 同上 |

### 6.4 現時点では大きな問題なし

| Tool | メモ |
|---|---|
| `GetLedgerDefinesTool` | 短い。将来 `folder_id` / `include_trashed` の扱いを少し補う余地はあるが、今回の主論点ではない |
| `CreateLedgerTool` | 短い。むしろ不足気味だが、process 過多ではない |
| `GetActivityLogTool` | ほぼ契約要約だけ。必要なら語彙を client-facing 化する程度 |
| `ExecuteApprovalTool` | ほぼ契約要約だけ |
| `GetPendingApprovalsTool` | ほぼ契約要約だけ |
| `GetWorkflowHistoryTool` | ほぼ契約要約だけ |
| `ClaimWorkflowTaskTool` | ほぼ契約要約だけ |

## 7. 追加で分かったこと

### 7.1 重複源は tool description だけではない

`app/Mcp/Servers/LedgerLeapServer.php` の `$instructions` にも、次のような具体的手順がある。

- 日付検索では `SearchLedgers` に date filter を使う
- 統計質問では `GetLedgerStats` / `GetUserActivityStats` / `GetFolderStats` を使い分ける
- 期間候補を列挙する

これは server-level instruction としては有用だが、
「tool description を slim にして client-side skill に寄せる」という方向と併せて、
**どこまで server 側に残すか** を別途見直す必要がある。

### 7.2 Search の長文化には歴史的理由がある

`2025-10-05_MCP_SearchLedgersTool_Enhancement.md` を読むと、
`SearchLedgersTool` description は当時の LLM 利用精度を上げるために意図的に厚くされた。

これは当時は合理的だったが、Issue #83 以後は次の前提が変わっている。

- capability manifest がある
- bootstrap discovery がある
- prompt / resource / tool の責務分担が整理された
- client-side skill へプロセスを持たせる発想が強くなった

したがって、`SearchLedgersTool` の長文化は「間違い」ではなく、
**責務分離の前段階で必要だった暫定最適化** と捉えるのが妥当である。

## 8. 見直し方針

### 8.1 目標状態

各 tool description は、基本的に次のどれかの長さに収める。

1. **短文 1〜3 行**: 単機能 tool（例: approval, history, claim）
2. **短い箇条書き 4〜8 行**: `format` や返却物が重要な tool（例: stats, bootstrap manifest）
3. **やや厚めだが契約中心**: `SearchLedgersTool`, `UpdateLedgerTool` のようにパラメータや制約が多い tool

ただし 3 でも、**他 tool の順序付き手順** は原則入れない。

### 8.2 残す内容のルール

tool description に残す候補:

- 1 文の目的
- 返却 payload の重要フィールド
- 誤用防止のための主要制約
- `summary/raw` の違いなど schema だけでは伝わりにくい契約

移す候補:

- recommended flow
- user scenario
- query strategy
- failure fallback
- best practice
- rich examples
- long performance note

### 8.3 一次反映先のルール

| 内容 | 一次反映先 |
|---|---|
| 標準業務フロー | `resources/ai/capabilities/*.yaml` |
| 初回質問例・開始導線 | prompt / bootstrap manifest / client-side skill |
| 実装判断や移送理由 | `docs/work/llm-integration/*.md` |
| 内部実装制約 | `.github/*`, `docs/function/*`, developer-facing docs |

### 8.4 initialization gate との関係

Issue #83 の追加検討として、**client-side skill の初期化が終わるまで通常 tool を解放しない gate** を入れる案が出ている。

もしこの gate を採用するなら、description slim 化はさらに進めやすくなる。

- pre-init では bootstrap 系だけを使わせる
- post-init では client が必要 skill を読める前提を置ける
- その結果、tool description に標準フローを書き戻す必要が減る

したがって、`SearchLedgersTool` などの process guidance を client-side skill へ移す作業は、
新規の initialization gate issue と相互参照して進めるのが望ましい。

## 9. 実施計画

### Phase 1: 高優先 tool の contract slimming

対象:

- `SearchLedgersTool`
- `GetLedgerDetailTool`
- `UpdateLedgerTool`
- `GetRelatedLedgersTool`
- `GetClientBootstrapManifestTool`

実施内容:

1. description から step-by-step guidance を削る
2. capability manifest / guide へ移す文言を整理する
3. 既存 schema と矛盾しない最小 contract 文へ書き換える
4. 既存テストで description 文字列に依存していないか確認する

### Phase 2: guide / skill 側の受け皿整備

実施内容:

1. `resources/ai/capabilities/*.yaml` の `recommended_flow` を必要なら補強する
2. bootstrap manifest / prompt / resource が「最初の導線」を十分担えているか確認する
3. client-side skill 生成物または論理 guide の不足を洗い出す

### Phase 3: 周辺重複の整理

対象:

- `LedgerLeapServer::$instructions`
- `docs/api/README.md`
- `docs/development/MCP_Architecture_and_Flow.md`
- bootstrap discovery 関連 docs

実施内容:

1. server-level guidance と client-side skill の責務境界を再確認する
2. Search / Update / Workflow / Analytics の「主導線」が 1 か所に寄るようにする
3. stale な重複表現を削る

## 10. 優先順位の結論

### 今すぐ着手すべきもの

1. `SearchLedgersTool`
2. `GetLedgerDetailTool`
3. `UpdateLedgerTool`
4. `GetRelatedLedgersTool`
5. `GetClientBootstrapManifestTool`

### 後続の style sweep で十分なもの

- `GetLedgerStatsTool`
- `GetUserActivityStatsTool`
- `GetFolderStatsTool`
- それ以外の短い tool descriptions

## 11. 受け入れ基準案

この見直しを完了とみなす基準案は次のとおり。

- `app/Mcp/Tools/*.php` の description から、他 tool を列挙した標準手順が原則除去されている
- `SearchLedgersTool` から rich examples / fallback strategy / performance note が client-facing skill 側へ移っている
- `GetLedgerDetailTool` / `UpdateLedgerTool` の update flow が `ledger-update` capability 側に集約されている
- `GetRelatedLedgersTool` の related-record flow が `ledger-search` capability 側に集約されている
- `GetClientBootstrapManifestTool` description から shared service / REST parity の内部事情が外れている
- 周辺 docs と capability manifest に、削ったプロセス説明の受け皿が存在する

## 12. 未解決論点

1. `LedgerLeapServer::$instructions` の具体例をどこまで残すか
2. client-side skill の実体をどこまで生成物として持つか
3. `summary/raw` のような返却形式説明を description と schema のどちらへ寄せるか
4. 低優先 tool もあわせて「短文化 style guide」を作るか

## 13. 推奨 follow-up issue

この調査からは、**「description の責務縮小」と「process の受け皿整備」を同時に追う follow-up issue** が自然である。

起票案は Issue **#100** として起票済み。

加えて、初期化前に通常 tool を閉じる前提を整える別系統の follow-up として、
Issue **#101** を関連 issue とする。

