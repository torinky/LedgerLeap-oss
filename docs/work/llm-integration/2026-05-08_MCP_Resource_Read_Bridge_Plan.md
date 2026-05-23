# MCP Resource Read Bridge 計画

**作成日:** 2026年05月08日
**種別:** 作業計画（MCP resource bridge / 非対応クライアント対策）
**関連Issue:** #209, #169
**関連:** `app/Mcp/Resources/BootstrapClientResource.php`, `app/Mcp/Resources/LedgerAttachmentResource.php`, `app/Mcp/Resources/LedgerAttachmentBinaryResource.php`, `app/Mcp/Servers/LedgerLeapServer.php`, `docs/work/llm-integration/2026-04-17_Continue_MCP_Attachment_Resource_Exposure_Plan.md`, `docs/work/llm-integration/2026-04-26_MCP_Attachment_Binary_Delivery_Plan.md`

## 1. 背景

`resources/read` を直接実行できる MCP クライアントでは、LedgerLeap の resource template をそのまま follow-up 取得できる。
一方で、OpenClaw の追試で分かったように、resource URI を把握していても **`resources/read` 相当を持たないクライアント** が存在する。

今回の論点は添付ファイル binary だけではない。
LedgerLeap 側には `ledgerleap://bootstrap/{client}` のような bootstrap resource もあり、今後 resource が増えるほど、非対応クライアントにとっては「URI は見えるが中身へ進めない」状態が再発する。

したがって、**MCP server 側で resource URI を受けて中身を返す bridge tool** を用意し、`resources/read` 非対応クライアントを補助する導線を確保する。

## 2. 目的

- `resources/read` 非対応クライアントでも、LedgerLeap の resource を読めるようにする
- 添付ファイルだけでなく、既存の bootstrap resource など **server-registered resource 全般** を対象にできるようにする
- `resources/read` 対応クライアントは従来どおり標準経路を使い、bridge は補助導線に留める
- resource URI から実体へ進めない状態を、クライアント差分として吸収できるようにする
- 将来 resource が増えても、同じ契約で再利用できるようにする

## 3. 現状整理

### 3.1 既存の resource 実装

- `BootstrapClientResource` が `ledgerleap://bootstrap/{client}` を提供している
- `LedgerAttachmentResource` が attachment envelope を返している
- `LedgerAttachmentBinaryResource` が attachment binary を返している
- `LedgerLeapServer` は resource を登録して公開できる

### 3.2 主要クライアントの調査結果

#### Continue.dev
- `core/context/mcp/MCPConnection.ts` で `listResources()` と `readResource({ uri })` を持っている
- `core/context/providers/MCPContextProvider.ts` は resource URI を解決できるが、最終的には **text resource のみ** を受け入れている
- `Continue currently only supports text resources from MCP` という制約が実装側にあるため、binary はそのままでは扱えない

#### OpenCode
- `packages/opencode/src/mcp/index.ts` で `listResources()` と `readResource(clientName, resourceUri)` を持っている
- issue #15535 で `resources/read` 対応が明示的に要望されており、resource templates への要望も issue #7510 で出ている
- issue #14753 では custom scheme resource が download 層で失敗しており、`resources/read` 以外の経路では URI scheme 差分が課題になりやすい

#### OpenClaw
- `docs/cli/mcp.md` では generic MCP clients に standard tools を提供する一方、`attachments_fetch` は transcript content の metadata view と明記されている
- issue #60005 で MCP resources の追加が進んでいるが、resource と tool の互換吸収や text 正規化の話題も並行している
- issue #75714 / #77041 からも、client 側では resource / structured data をそのまま扱うより、primary content へ正規化する傾向が強い

#### 調査からの示唆
- Continue.dev は text-first bridge との相性がよい
- OpenCode は standard `resources/read` 対応寄りだが resource template / custom scheme の穴が残る
- OpenClaw は非対応・部分対応の境界がまだ揺れているため、server-side bridge の主対象として妥当
- したがって bridge は **text / JSON の正規化を主経路** とし、binary は補助的に扱う設計が安全

### 3.3 既存の利用前提

- `resources/read` 対応クライアントは、resource template を読んでそのまま follow-up できる
- `routes.download` / `routes.inspector` は人間向け導線として残っている
- `SearchLedgersTool` などは discovery / summary の責務に留める方針が既にある

### 3.4 今回の問題

- OpenClaw のツールセットには `resources/read` 相当がない
- resource URI は見えても、クライアントがその中身を取り出せない
- その結果、resource は discovery 用に見えても follow-up 用として使えない

## 4. 検討した選択肢

### 案A: server-side resource bridge tool を新設する

例:
- `ReadMcpResourceTool`
- `FetchMcpResourceTool`
- `LoadLedgerLeapResourceTool`

**長所**
- LedgerLeap 側で制御できる
- tenant / ACL / MIME / allowlist を server で閉じられる
- `resources/read` 非対応クライアントを直接救済できる
- `resources/read` 対応クライアントはそのまま標準経路を使える

**短所**
- `resources/read` と二重の経路になる
- tool 契約を適切に絞らないと、resource 読込以外の責務が混ざる

### 案B: resource 種別ごとに専用 bridge tool を作る

例:
- bootstrap 用 tool
- attachment 用 tool
- 将来の resource 用 tool

**長所**
- 契約が単純
- type ごとの安全性を作りやすい

**短所**
- tool 数が増えやすい
- resource が増えるたびに追加実装が必要

### 案C: client/runtime 側で bridge を実装する

**長所**
- MCP 仕様に最も素直
- 将来的にはきれい

**短所**
- LedgerLeap から制御しにくい
- OpenClaw 本体改修やランタイム依存が大きい
- 今回の対応策としては遅い

## 5. 推奨方針

### 5.1 第一推奨

**案A: server-side の汎用 resource bridge tool を新設する。**

この tool は、resource URI を入力すると server 側で resource を解決し、必要な形式で返す。
`resources/read` が使えるクライアントは標準経路を使い、使えないクライアントだけ bridge を使う。

### 5.2 対象範囲

最初の対象は次の順でよい。

1. `ledgerleap://bootstrap/{client}`
2. `ledgerleap://ledger/{tenant}/{ledger}` 系 resource
3. `ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}` 系 resource
4. `.../blob` を含む binary resource

### 5.3 返却方針

bridge tool は、resource 種別に応じて以下の正規化を返す。

- text / markdown: 文字列
- JSON envelope: 構造化 JSON
- binary: `mime_type` + `blob` もしくは安全な代替表現
- 付随情報: `resource_uri`, `resource_type`, `tenant`, `access_guide`, `available_formats`

補足:
- Sprint 1 の調査結果を踏まえると、**Continue.dev は text resource 限定**のため、bridge の主経路は text / JSON に寄せるべき
- OpenCode は `readResource` を持つが、custom scheme / resource template まわりに差があるため、`resources/read` への標準接続と bridge の両方を前提にするのがよい
- OpenClaw は standard tools / metadata view / content 正規化の方向が強いので、bridge 返却形の text-first 化が有効

### 5.4 安全制約

- allowlist にない URI は読ませない
- tenant mismatch は拒否する
- unauthorized は拒否する
- resource template の外側へは出さない
- 大きすぎる payload はサイズ制限を設ける

## 6. 責務分離

- **server**: resource 解決、認証、tenant 境界、allowlist、返却形状の統制
- **tool**: URI を受けて結果を返す薄いアダプタ
- **client**: `resources/read` があればそれを優先し、無ければ bridge を呼ぶ

この分離により、非対応クライアントの存在を前提にしつつ、標準 MCP 実装を壊さない。

## 7. スプリント分解

- [x] **Sprint 1: inventory / contract fixed**
  - [x] bridge 対象 resource を列挙する
  - [x] `resources/read` 非対応クライアントの制約を整理する
  - [x] 返却形式の候補を text / JSON / blob / metadata に分ける
- [x] **Sprint 1 実施結果**
  - 対象 resource は `ledgerleap://bootstrap/{client}`、`ledgerleap://ledger/{tenant}/{ledger}` 系、`ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}` 系、`.../blob` を含む binary resource に整理した
  - `resources/read` 非対応クライアントでは、URI を知っていても follow-up 取得に進めないことを確認した
  - Continue.dev は text-only resource 制約があり、OpenCode は `readResource` を持つ一方で resource template / custom scheme に課題があることを確認した
  - OpenClaw は standard tools / metadata view / content 正規化の実装が進んでいるが、resource 読込の境界はまだ揺れていることを確認した
  - bridge の返却形式は text / markdown、JSON envelope、binary、付随 metadata に分ける方針を固定した
  - 安全制約は allowlist、tenant mismatch 拒否、unauthorized 拒否、resource template 外の遮断、サイズ制限に整理した
- [x] **Sprint 2: tool contract design**
  - [x] 入力 schema を決める
  - [x] allowlist / auth / tenant 境界を決める
  - [x] エラー契約を決める
- [x] **Sprint 2 実施結果**
  - 入力 schema は `resource_uri` 必須、`preferred_format` 任意、`include_metadata` 任意、`max_bytes` / `max_chars` 任意、`include_blob` は既定 `false` に整理した
  - allowlist / 境界は、認証済みユーザーのみ、現在 tenant と resource URI の tenant 一致、登録済み resource template のみ、template 外参照禁止で固定した
  - 返却形式は共通 envelope で `resource_uri`, `resource_type`, `mime_type`, `delivery_mode`, `available_formats`, `payloads`, `access_guide` を揃える方針にした
  - binary は既定で inline 返却しない。必要時のみ専用 blob resource / download route に分離する
  - 失敗時の標準エラーは `INVALID_ARGUMENT`, `NOT_ALLOWED`, `TENANT_MISMATCH`, `UNAUTHORIZED`, `NOT_FOUND`, `UNREADABLE`, `TOO_LARGE`, `INTERNAL_ERROR` を採用する
  - 対象 resource の粒度は server-registered resource の個別 URI 単位とし、初期対象は `bootstrap`, `ledger`, `attachment`, `blob`
- [x] **Sprint 3: implementation / tests**
  - [x] bridge tool を実装する
  - [x] bootstrap resource と attachment resource を最低限カバーする
  - [x] 非対応クライアント前提の回帰テストを追加する
- [x] **Sprint 3 実施結果**
  - `ReadMcpResourceTool` を追加し、`ledgerleap://bootstrap/{client}` と attachment envelope / blob resource を bridge 経由で解決できるようにした
  - bridge の返却は normalized envelope に寄せ、`resource_uri` / `resource_type` / `mime_type` / `delivery_mode` / `available_formats` / `payloads` / `access_guide` を統一した
  - `tests/Unit/Mcp/Tools/ReadMcpResourceToolTest.php` と `tests/Unit/Mcp/Servers/LedgerLeapServerTest.php` を追加し、bootstrap / attachment / unsupported / unauthenticated / server registration を確認した
  - 既存の bootstrap / attachment envelope 系の回帰テストも通し、標準 `resources/read` 経路を壊していないことを確認した
- [x] **Sprint 4: docs / rollout**
  - [x] issue #169 に方針検討経緯を追記する
  - [x] issue #209 を作業管理の本体にする
  - [x] `docs/work/llm-integration/README.md` の索引を更新する
  - [x] 既存 attachment plan と continue plan に bridge への導線を追加する
- [x] **Sprint 4 実施結果**
  - issue #209 の本文・コメントを Sprint 3 完了状態へ更新し、tracking issue を closed にした
  - `docs/work/llm-integration/2026-05-08_MCP_Resource_Read_Bridge_Plan.md` を Sprint 3 完了 / Sprint 4 完了状態へ同期した
  - `docs/work/llm-integration/README.md` と関連作業ログの索引を最新状態に揃えた
  - `docs/work/environment/2026-05-08_storage_permission_fix_retrospective.md` を追加し、runtime storage subtree の exact-path 権限修正パターンを記録した

## 8. 完了条件

- [x] `resources/read` 非対応クライアントでも、server-registered resource の中身に到達できる
- [x] bridge の対象 / 非対象 / allowlist が明文化されている
- [x] `resources/read` 標準経路を壊していない
- [x] tenant / ACL を越えない
- [x] issue / docs / 実装の追跡先が一意に分かる

## 9. Sprint 2 完了メモ

- 入力 schema は `resource_uri` 必須 + `preferred_format` / `include_metadata` / `max_bytes` / `max_chars` 任意で固定した
- `include_blob` は原則無効として、binary は専用 blob resource / download route に分離する方針にした
- bridge の主経路は Continue.dev / OpenClaw の実態に合わせて text / JSON 正規化とした
- OpenCode は標準 `resources/read` 対応寄りとして扱いつつ、custom scheme / resource template 差分に bridge が有効であることを確認した
- エラー契約と対象 resource 粒度を固定したので、Sprint 3 では実装と回帰テストに進める

## 10. 参照

- [Issue #209](https://github.com/torinky/LedgerLeap/issues/209)
- [Issue #169](https://github.com/torinky/LedgerLeap/issues/169)
- [Continue.dev 向け MCP 添付 Resource Exposure 計画](./2026-04-17_Continue_MCP_Attachment_Resource_Exposure_Plan.md)
- [MCP 添付ファイル本体バイナリ配信計画](./2026-04-26_MCP_Attachment_Binary_Delivery_Plan.md)
- [Attachment Delivery Strategy (ADS) 設計仕様書](./2026-04-08_attachment_delivery_strategy_spec.md)

## 11. 補足

この計画は、添付ファイルだけを対象にした回避策ではなく、**今後増える MCP resource 全般に対する共通の退避経路** を作ることを目的にしている。

そのため、個別 resource の仕様は既存の resource / ADS / Continue 計画で管理し、bridge は「resource URI を実体へつなぐ共通ツール」として扱う。

2026-05-08 の外部調査を踏まえると、bridge は「標準 MCP を置き換える」のではなく、**Continue.dev の text-only 制約、OpenCode の custom scheme / resource template 差、OpenClaw の metadata 正規化傾向** を吸収する補助層として位置付けるのが妥当である。
