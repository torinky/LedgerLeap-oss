# Issue #109: remote MCP requirement and transport options

**作成日:** 2026年03月15日  
**ドキュメント種別:** 作業ファイル（Issue #109 判断ログ）  
**関連Issue:** [#83](https://github.com/torinky/LedgerLeap/issues/83), [#105](https://github.com/torinky/LedgerLeap/issues/105), [#106](https://github.com/torinky/LedgerLeap/issues/106), [#108](https://github.com/torinky/LedgerLeap/issues/108), [#109](https://github.com/torinky/LedgerLeap/issues/109)

## Freshness

- `status`: draft-confirmed
- `last_confirmed_at`: 2026-03-15
- `recheck_after`: 90d
- `recheck_trigger`:
  - Gemini CLI の `mcpServers` / transport docs が更新されたとき
  - `laravel/mcp` の `web` / auth docs や package 実装が更新されたとき
  - `routes/ai.php` / `AuthenticatedMcpTool` / clean-room harness template を変更するとき

## 1. 目的

Issue `#109` について、**remote MCP を必達要件として扱うべきか** と、
その場合にどの transport / auth の組み合わせを優先すべきかを整理する。

この文書では次を固定する。

1. なぜ remote MCP が必要か
2. なぜ `command` ベース local MCP では足りないか
3. Gemini CLI / Laravel MCP / LedgerLeap 現行実装の差分
4. 今採るべき処置方針

## 2. remote MCP が必須である根拠

## 2.1 presentation 上の根拠

`docs/work/presentation/2026-02-24_introduction_slides/architecture.mmd` では、
システム構成を次の 3 層に分けている。

- `ユーザー環境`（PC / タブレット / スマホ）
- `LedgerLeap プラットフォーム`
- `外部連携`（生成 AI / API・MCP 他システム連携）

これは、**ユーザークライアントと LedgerLeap が同一端末に常駐する前提ではなく、ネットワーク越しの接続を前提**としていることを示している。

さらに `docs/work/presentation/2026-02-24_introduction_slides/slides.html` では次が訴求されている。

- 「外部 AI ツールから台帳データを安全に参照・活用可能」
- 「QR スキャンから履歴確認・点検報告までを現場で完結」
- 「マルチデバイス対応（スマホ・PC）」
- 「標準APIおよびMCPを通じ、生成AI（Claude等）や他システムと連携」

これらは、**利用者端末・外部 AI クライアント・LedgerLeap サーバーが分離されている運用**を前提とした訴求である。

## 2.2 ユーザーシナリオ上の根拠

`docs/function/PersonaUseCaseScenario.md` では、少なくとも次のシナリオが定義されている。

### 実務担当者
- 現場で QR スキャンから報告画面を即時起動する
- 添付資料込みで過去記録を検索する
- マイポータルから承認待ちタスクを処理する

### 管理者
- 活動状況を監査する
- 件数・滞留・偏りを把握して介入判断する

### 現場リーダー
- 現場の急な変更に対して代理更新する
- 添付資料を見ながら確認・共有する

これらはいずれも、**LedgerLeap サーバーが置かれている端末そのものではなく、各利用者のクライアントから接続して使う前提**で成り立つ。

## 2.3 LLM integration 設計上の根拠

`docs/work/llm-integration/2026-03-09_Client_Skill_Bootstrap_Strategy.md` では、
次を前提として固定している。

- クライアントは **MCP または REST API** を通じてのみ LedgerLeap に接続する
- CLI でファイルを生成して配る方式は **補助的な検証手段** に留める
- 主軸は **公開契約（MCP / API）** である

したがって、**同一端末でのみ成立する local command MCP は、本番的な client-facing 接続モデルの主契約になれない。**

## 3. 技術的現状

## 3.1 Gemini CLI 側でできること

Gemini CLI の公式 docs（`docs/reference/configuration.md`）では、`mcpServers` に対して少なくとも次が使える。

- `command`
- `url`
- `httpUrl`
- `headers`

つまり Gemini CLI 側は、**HTTP-accessible MCP server への接続を前提にできる**。

## 3.2 Laravel MCP 側でできること

`laravel/mcp` 公式 docs と package 実装（`vendor/laravel/mcp/src/Server/Registrar.php`）では、
MCP server の登録方法として次がある。

- `Mcp::local(...)` — stdio / Artisan command 前提
- `Mcp::web(...)` — HTTP POST 前提

また web server には middleware を付与でき、公式 docs では以下が示されている。

- `auth:sanctum`
- `Passport` による OAuth (`Mcp::oauthRoutes()`)

したがって、**Laravel MCP 自体は remote-accessible MCP をサポートしている**。

## 3.3 LedgerLeap 現行実装

### transport
- `routes/ai.php` は `Mcp::local('ledgerleap:mcp', LedgerLeapServer::class)` のみ
- `Mcp::web(...)` は未登録

### auth
- `app/Mcp/Traits/AuthenticatedMcpTool.php` は `MCP_AUTH_TOKEN` 環境変数から Sanctum token を解決する
- request header の `Authorization: Bearer ...` を直接使う構成になっていない

### 補完経路
- `routes/api.php` には bootstrap manifest REST API がある
- ただしこれは MCP transport の代替ではなく、bootstrap discovery 用 REST contract

## 3.4 最新の公式 docs から Sprint 2 に効く事実

### Gemini CLI

Gemini CLI 公式 docs では、`mcpServers` に対して `command` だけでなく次を持てる。

- `httpUrl`
- `url`
- `headers`

よって Gemini CLI 側には、**HTTP 接続そのものの制約はない**。

### Laravel MCP

Laravel MCP 公式 docs では、web server について次が明記されている。

- `Mcp::web(...)` は **remote AI clients / web-based integrations 向けの主な形態**
- `auth:sanctum` を middleware としてそのまま付与できる
- web server で auth middleware を付与した場合、MCP client は `Authorization: Bearer <token>` を送る
- `Request` から `$request->user()` を参照して authorization に使える

これは `vendor/laravel/mcp/src/Server/Registrar.php` の `web()` 実装とも整合しており、
HTTP transport は package の標準機能として扱ってよい。

## 3.5 類似実装の観測

GitHub 上の類似実装では、少なくとも次が確認できる。

### `karlomikus/bar-assistant`

`routes/ai.php` で:

```php
Mcp::web('/mcp/cocktails', CocktailServer::class)
    ->middleware(['auth:sanctum', McpIsEnabled::class, EnsureRequestHasBarQuery::class]);
```

### `promptlyagentai/promptlyagent`

`routes/ai.php` で:

```php
Mcp::oauthRoutes();

Mcp::web('knowledge', KnowledgeServer::class)
    ->middleware(['auth:sanctum', 'throttle:100,1']);
```

この2例から、少なくとも次が読み取れる。

1. `Mcp::web(...)` + `auth:sanctum` は Laravel MCP の一般的な実装パターン
2. route middleware で app 固有の access control を追加する設計は自然
3. OAuth route を有効化しても、当面の auth を Sanctum に置く構成はありうる

## 3.6 外部根拠URL（2026-03-15 確認）

後続の技術者が再検証できるよう、Sprint 2 の判断に使った外部資料の URL を残す。

### 公式 docs

- Gemini CLI configuration（`mcpServers` の `command` / `url` / `httpUrl` / `headers`）  
  <https://github.com/google-gemini/gemini-cli/blob/6061d8cac72155f7a09249defcdf52aba28632e6/docs/reference/configuration.md#mcpservers>
- Laravel MCP documentation（Web Servers）  
  <https://laravel.com/docs/12.x/mcp#web-servers>
- Laravel MCP documentation（Authentication）  
  <https://laravel.com/docs/12.x/mcp#authentication>
- Laravel MCP documentation（Sanctum）  
  <https://laravel.com/docs/12.x/mcp#sanctum>
- Laravel MCP documentation（Authorization）  
  <https://laravel.com/docs/12.x/mcp#authorization>

### 類似実装

- `karlomikus/bar-assistant` `routes/ai.php`  
  <https://github.com/karlomikus/bar-assistant/blob/f8073c3212d7720184211ea2348345256fbb8142/routes/ai.php>
- `promptlyagentai/promptlyagent` `routes/ai.php`  
  <https://github.com/promptlyagentai/promptlyagent/blob/ef220cdc5d16dd6f287cba579db214a27ce15649/routes/ai.php>

## 4. 問題の整理

## 4.1 何が不足しているか

現状の clean-room harness は `command` ベース local MCP を前提にしている。
これは以下の点で不足する。

1. **ユーザークライアント ≠ LedgerLeap サーバー** の前提を再現できない
2. 外部 AI クライアント（Gemini CLI, Claude, Dify 等）との **remote-like 接続** を検証できない
3. presentation で訴求している **外部 AI ツール連携 / 現場完結 / マルチデバイス** の土台として弱い
4. client-facing の主契約が MCP / API であるという再計画方針とずれる

## 4.2 local command MCP の位置づけ

`command` ベース local MCP は、次の用途には有用である。

- 開発環境での局所的な検証
- clean-room の contamination 制御確認
- MCP tool の初期動作確認

しかし、これは **暫定的な開発・検証手段** であり、
Issue `#109` が扱うべき主要求（remote MCP 実現）の代替にはならない。

## 5. 選択肢

## Option A — remote MCP を `Mcp::web(...)` + Sanctum bearer auth で実現する（推奨）

### 内容
- `routes/ai.php` に `Mcp::web(...)` を追加する
- `auth:sanctum` などの middleware を適用する
- MCP auth を request header ベースへ寄せる
- Gemini CLI の `mcpServers.httpUrl` / `headers.Authorization` で接続する

### Sprint 2 での具体案

#### route
- 候補: `Mcp::web('/mcp/ledgerleap', LedgerLeapServer::class)`
- 最低限 middleware: `auth:sanctum`
- 追加 middleware は Sprint 2 で tenant / access policy を見ながら決める

#### auth 解決順
`AuthenticatedMcpTool` は次の優先順へ寄せる。

1. **`Auth::user()` または `$request->user()` がある場合はそれを使う**
2. それがない場合のみ **`MCP_AUTH_TOKEN` env fallback** を使う

この形なら、

- web transport では `Authorization: Bearer ...` を正規ルートにできる
- 既存 local command MCP や既存テストの env token 前提をすぐには壊さない

#### clean-room harness への反映
- `#106` の harness は当面 `command` ベースを維持
- Sprint 3 で `httpUrl` / `headers` 前提の template を追加
- `command` template は開発・比較用 fallback として残す

### 利点
- Gemini CLI 公式 docs と整合する
- Laravel MCP の標準機能で実現できる
- 既存 REST API と同じ Sanctum bearer token 運用に寄せやすい
- remote-like evaluation を最短で実現しやすい

### 注意点
- `AuthenticatedMcpTool` の env token 前提を **header-auth 優先 + env fallback** に見直す必要がある
- web route 上での auth / tenant / permission の取り扱いを整理する必要がある
- 既存テスト（`AuthenticatedMcpToolTest` など）は env token 前提なので、後方互換を保った移行順を取る必要がある

## Option B — remote MCP を `Mcp::web(...)` + OAuth / Passport で実現する（将来候補）

### 内容
- `Mcp::oauthRoutes()` を有効にする
- Passport ベースの OAuth flow を整える

### 利点
- MCP 仕様の標準認証に近い
- より広い MCP client 互換を狙いやすい

### 注意点
- 導入コストが高い
- LedgerLeap 現行の auth / harness / clean-room より大きなスコープになる

## Option C — local command MCP を維持し、remote simulation は限定的に扱う（不採用）

### 内容
- `command` ベースを main のままにする
- `localhost HTTP` は将来課題として据え置く

### 不採用理由
- presentation とユーザーシナリオの前提に届かない
- `#109` の「remote-like evaluation」要求を満たさない
- `MCP / API が主契約` という再計画方針と一致しない

## 6. 判断

Issue `#109` では、次を固定する。

1. **remote MCP は必達要件であり、local command MCP は補助的手段に格下げする**
2. **最短の実現方針は Option A（`Mcp::web(...)` + Sanctum bearer auth）** とする
3. bootstrap REST API は補完契約として維持するが、remote MCP の代替にはしない
4. clean-room harness は、remote MCP が整うまでの暫定テンプレートとして local command 前提を維持し、実現後に `httpUrl` / `headers` テンプレートを追加する

## 6.1 Sprint 2 で固定する追加判断

Sprint 2 では、さらに次を固定する。

1. **Option A を「`Mcp::web(...)` + `auth:sanctum` + header-auth 優先 / env fallback」まで具体化して扱う**
2. `MCP_AUTH_TOKEN` は廃止を急がず、**local command / 既存テストの後方互換 fallback** とする
3. remote MCP の first implementation では **OAuth / Passport を必須化しない**
4. 類似実装にならい、route middleware で app 固有制約を重ねられる構成を保つ
5. Sprint 2 の完了条件は「実装開始前に route / auth / fallback / harness 影響が具体化されていること」とする

## 7. `#109` で扱うべきスプリント

### Sprint 1 — requirement / contract 固定
- remote MCP 必須の根拠を docs/work に固定
- `local command` と `remote HTTP` の位置づけを確定
- Option A / B / C を比較し、Option A を第一候補に固定

### Sprint 2 — transport / auth 設計
- `routes/ai.php` で `Mcp::web('/mcp/ledgerleap', LedgerLeapServer::class)` を第一候補として検討する
- `auth:sanctum` と `Authorization: Bearer ...` の整合を定義する
- `AuthenticatedMcpTool` を **header-auth 優先 / env fallback** にする移行案を固める
- 既存テストと local command harness を壊さない後方互換条件を決める
- tenant / permission / middleware の追加条件を切り分ける

### Sprint 3 — harness / docs 接続
- clean-room harness に `httpUrl` 前提 template を追加する
- `#106` と `#109` の役割分担を明文化する
- 必要なら `docs/api` / `docs/development` の接続説明を更新する

### Sprint 4 — validation / issue close 判定
- Gemini CLI から remote HTTP MCP 接続できることを確認する
- clean-room で remote-like evaluation が成立することを確認する
- `#105` の placement / delivery 再評価へ引き継ぐ

## 8. 非対象

- OAuth / Passport の全面導入をこの issue 単体で完了すること
- Gemini bootstrap contract 全体の再設計
- `#105` の placement / delivery そのものの最終実装
- client-facing capability taxonomy の全面見直し

## 9. 反映先

- 判断ログ（この文書）: `docs/work/llm-integration/*`
- clean-room 配置ルール: `docs/harnesses/gemini-clean-room/*`
- 進捗管理と受け入れ条件: GitHub Issue `#109`

## 10. 結論

`docs/work/presentation` とユーザーシナリオから見て、LedgerLeap のサーバーと利用者クライアントが同一端末である前提は取れない。

したがって、**remote MCP は nice-to-have ではなく、LedgerLeap の訴求と client-facing LLM integration を成立させるための必達要件**である。

Issue `#109` では、`Mcp::web(...)` + Sanctum bearer auth を第一候補として、
remote-accessible MCP を最優先で具体化する。
