# Continue.dev 向け MCP 添付 Resource Exposure 計画

**作成日:** 2026年04月17日  
**種別:** 作業計画（Continue.dev / MCP resource 露出）  
**関連:** `app/Mcp/Servers/LedgerLeapServer.php`, `app/Mcp/Resources/BootstrapClientResource.php`, `app/Mcp/Tools/SearchLedgersTool.php`, `docs/work/llm-integration/2026-04-08_attachment_delivery_strategy_spec.md`, `docs/work/llm-integration/2026-04-05_MCP_Search_Attachment_Feedback_Followup_Plan.md`

### WBS

Continue.dev から LedgerLeap の MCP サーバーへ接続した場合、検索結果に含まれる添付ファイルを **そのまま tool 引数として渡す**ことはできない。  
現時点の添付配信は `SearchLedgersTool` のレスポンス内 envelope と、既存のダウンロード / inspector route を中心に構成されている。

一方で、LedgerLeap にはすでに `ledgerleap://bootstrap/{client}` のような **resource template** 実装があり、MCP resource を context provider として扱う前例がある。  
そのため、添付ファイルを **resource として参照可能にする**設計は自然であり、Continue.dev 側の利用体験とも整合する。

## 2. 目的

- Continue.dev から MCP を使う際に、添付ファイルを **resource URI 経由で参照**できるようにする
- 添付の実体を tool 引数へ埋め込まず、**resource.read 前提**の契約に分離する
- 既存の `SearchLedgersTool` は検索結果の責務に留め、resource は **参照可能なコンテキスト提供**として切り出す
- tenant / 権限 / MIME の境界を崩さずに、添付ファイルの follow-up 読み込みを可能にする

- [x] **D.5 文書同期と回帰テスト**
  - Evidence: `docs/work/llm-integration/2026-04-05_MCP_Search_Attachment_Feedback_Followup_Plan.md` と `docs/work/llm-integration/2026-04-08_attachment_delivery_strategy_spec.md` に Continue.dev / resource 導線を追記し、`tests/Unit/Mcp/Tools/SearchLedgersToolTest.php` / `tests/Unit/Mcp/Resources/LedgerAttachmentResourceTest.php` を resource 参照の回帰証跡として再利用した。
  - [x] `docs/work/llm-integration/2026-04-05_MCP_Search_Attachment_Feedback_Followup_Plan.md` に関連リンクを追記する
    - Evidence: Continue.dev resource スプリントへの分離方針を、検索改善計画の次アクションへ追記済み。
  - [x] `docs/work/llm-integration/2026-04-08_attachment_delivery_strategy_spec.md` に resource 導線を追記する
    - Evidence: ADS 仕様に `resource_uri` / `routes.download` / `routes.inspector` の役割分担を追記済み。
  - [x] resource 参照の回帰テストを追加する
    - Evidence: `SearchLedgersToolTest` と `LedgerAttachmentResourceTest` で `resource_uri`、tenant mismatch、unauthenticated の回帰を確認済み。
- 既存の添付ダウンロード route の破壊的変更
- OCR / VLM / structured extraction の再設計

## 4. 現状整理

### 4.1 既存の resource 実装

- `BootstrapClientResource` が `ledgerleap://bootstrap/{client}` を提供している
- `LedgerLeapServer` は resources を登録できる構成になっている
- MCP 仕様上、resource はサーバーが公開し、クライアントが `resources/read` で取得する

### 4.2 既存の添付配信

- `SearchLedgersTool` は summary 応答に `attachments[]` を返し、`routes` / `payloads` / `available_formats` を持つ
- `docs/work/llm-integration/2026-04-08_attachment_delivery_strategy_spec.md` により、text-first envelope が定義されている
- 画像 / PDF は `payloads.visual.signed_url` のような follow-up 導線を持つ

### 4.3 Continue.dev 側の前提

- Continue.dev は MCP を context provider として利用できる
- つまり、resource を公開すれば、ユーザーは Continue の文脈にそれを取り込める
- ただし、クライアントがローカル添付をそのまま渡すのではなく、**サーバーが公開する resource を参照する**構図になる
- `ledgerleap://...` は HTTP URL ではなく MCP resource URI なので、`fetch_url_content` のような一般的な URL fetch では読めない。Continue.dev 側では MCP の `resources/read` 経由で読む前提にする
- `access_guide` は、`resources/read` / `routes.download` / `routes.inspector` のどれを使うべきかをクライアントに明示する案内フィールドとして扱う

## 5. 提案する resource 契約

### 5.1 Resource の役割分担

#### Ledger 単位の resource
- レコード全体の簡易要約
- 添付一覧の index
- follow-up 先の resource URI 一覧

#### Attachment 単位の resource
- 1 添付の本文 / 抽出結果 / メタデータ
- `available_formats` に相当する候補
- 必要に応じて `markdown` / `json` / `structured` / `visual` への参照

### 5.2 URI テンプレート案

- `ledgerleap://ledger/{tenant}/{ledger}`
- `ledgerleap://ledger/{tenant}/{ledger}/attachments`
- `ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}`

補足:
- tenant を URI に含めて、境界と追跡を明確にする
- resource 自体は参照用であり、実データ取得時に認可を再確認する

### 5.3 Resource の返却内容案

#### attachment resource の最小構造
- `attachment_id`
- `filename`
- `role`
- `order`
- `mime_type`
- `available_formats`
- `source`
- `routes.download`
- `routes.inspector`
- `payloads.text`
- `payloads.structured`
- `payloads.visual`

#### resource と tool の境界
- `SearchLedgersTool`: 検索と結果サマリ
- resource: 個別添付の参照・再取得
- tool にはできるだけ生データを積まず、resource URI を優先して返す

## 6. 実装方針

### 6.1 最低限の実装

1. attachment resource template を追加する
2. resource.read で添付の text / structured / visual envelope を返す
3. `SearchLedgersTool` の summary に resource URI を載せる
4. Continue.dev でその resource を context に入れたときに、会話に反映されることを確認する

### 6.2 安全性

- tenant 不一致は resource を返さない
- 権限不足は `Response::error()` 相当の扱いにする
- unsupported MIME は `Text` fallback か、resource の空 envelope で返す
- signed URL を返す場合は期限を明記する

### 6.3 既存実装との整合

- `BootstrapClientResource` と同様に resource template を登録する
- `SearchLedgersTool` の `attachments[]` と resource の key を一致させる
- `available_formats` の意味は ADS 設計と揃える

## 7. Sprint D: Continue.dev resource exposure

### 目的
Continue.dev から、LedgerLeap の添付ファイルを resource として参照できるようにする。

### WBS
- [ ] **D.1 Resource テンプレートの確定**
  - [ ] ledger / attachment 単位の URI を決める
  - [ ] tenant / ledger / attachment の識別子方針を固定する
  - [ ] `SearchLedgersTool` の出力に載せる resource URI を定義する
- [ ] **D.2 Resource read 実装**
  - [ ] attachment resource の本文 / メタデータ返却を実装する
  - [ ] `payloads.text` / `payloads.structured` / `payloads.visual` の再利用方針を決める
  - [ ] `available_formats` を resource 側でも返せるようにする
- [ ] **D.3 Continue.dev 利用フロー確認**
  - [ ] context provider として resource を参照する手順を確認する
  - [ ] 検索結果から resource を辿る UX を確認する
  - [ ] tool 経由と resource 経由の役割分担を整理する
- [ ] **D.4 Security / tenant 境界の確認**
  - [ ] tenant mismatch の拒否動作を確認する
  - [ ] unauthorized で resource を返さないことを確認する
  - [ ] signed URL / 路由公開時の漏洩リスクを確認する
- [ ] **D.5 文書同期と回帰テスト**
  - [ ] `docs/work/llm-integration/2026-04-05_MCP_Search_Attachment_Feedback_Followup_Plan.md` に関連リンクを追記する
  - [ ] `docs/work/llm-integration/2026-04-08_attachment_delivery_strategy_spec.md` に resource 導線を追記する
  - [ ] resource 参照の回帰テストを追加する

### 受け入れ基準
- [ ] Continue.dev から resource URI を参照できる
- [ ] 添付ファイルを tool 引数に埋め込まずに会話へ持ち込める
- [ ] tenant / ACL を越えない
- [ ] 既存の `SearchLedgersTool` の挙動を壊さない
- [ ] resource と attachment envelope の役割分担が文書化されている

#### D.1 完了メモ

- resource は **ledger 単位** と **attachment 単位** の 2 段階に固定する
- ledger URI は `ledgerleap://ledger/{tenant}/{ledger}` とする
- attachment URI は `ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}` とする
- tenant は公開用の tenant 識別子を使い、DB 内部 ID はそのまま露出しない
- ledger / attachment の識別子は tenant スコープ内の主キーをそのまま使い、`hashedbasename` は URI に含めない
- `SearchLedgersTool` の出力には、検索結果ごとに `resource_uri` を 1 本ずつ載せる最小構成を採る
- `routes.download` / `routes.inspector` は人間向け導線として残し、resource は Continue.dev 向け参照導線として分離する

#### D.1 判定理由

1. Continue.dev では tool 引数への直接添付ができないため、**参照可能な URI** が必要
2. 1 レコード全体と 1 添付実体を分けると、検索結果の要約と詳細参照を責務分離できる
3. `collection` 型の一覧 resource は今回は必須ではないため、将来追加に回せる
4. 既存の `ledgerleap://bootstrap/{client}` と同様に、resource template として実装しやすい

#### D.1 直後の次確認

- `SearchLedgersTool` の response schema に `resource_uri` を載せる場所を決める
- attachment resource の `read` 実装で `payloads.*` をどう再利用するかを D.2 で詰める

#### D.2 完了メモ

- `LedgerAttachmentResourceService` を共通化し、attachment resource の envelope 生成を `SearchLedgersTool` と resource 実装の両方から再利用できるようにした
- `LedgerAttachmentResource` を追加し、tenant / ledger / attachment を検証したうえで `resource.read` 相当の JSON envelope を返すようにした
- `SearchLedgersTool` の attachment summary に `resource_template` / `resource_uri` を載せ、Continue.dev が辿れる参照先を返せるようにした
- `available_formats` / `payloads.text` / `payloads.structured` / `payloads.visual` は既存の ADS 契約をそのまま再利用した
- 回帰テストは `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php` と `tests/Unit/Mcp/Resources/LedgerAttachmentResourceTest.php` で確認済み

#### D.2 判定理由

1. resource 本体と検索結果の両方で同じ envelope を返すため、共通 service に寄せたほうがずれにくい
2. `SearchLedgersTool` は検索責務、resource は再取得責務に分けることで、Continue.dev 側の context provider 利用と相性がよい
3. テストで `resource_uri` のあるケースとないケースの両方を固定できたため、今後の refactor でも契約が壊れにくい

#### D.3 完了メモ

- Continue.dev 側の前提は、既存の `mcp-remote` / remote MCP 取り回し文書と `.continue/mcpServers/*.yaml` の設定形式から確認した
- LedgerLeap 側では `routes/ai.php` の `Mcp::local('ledgerleap:mcp', LedgerLeapServer::class)` により、Continue.dev から参照する MCP サーバーの入口が固定されている
- 検索結果側は `SearchLedgersTool` が `resource_template` / `resource_uri` を返し、`LedgerAttachmentResource` が同じ URI 契約で再取得できるため、検索→resource 追跡の UX が成立する
- `SearchLedgersToolTest` と `LedgerAttachmentResourceTest` の両方で resource 契約を検証済みであり、tool 経由と resource 経由の役割分担を証跡として残せた

#### D.3 判定理由

1. Continue.dev は MCP を context provider として使う前提で整理されており、resource URI を文脈に入れる導線がある
2. 検索は discovery、resource は follow-up read として分離でき、ユーザーが検索結果から resource を辿る流れが明確になる
3. `ledgerleap://` は HTTP fetch ではなく MCP resource のため、Continue.dev 側では `resources/read` 経由で参照する必要がある
4. 既存の download / inspector route は人間向けの導線として維持されているため、resource 導線と競合しない

#### D.4 完了メモ

- tenant mismatch は、現在 tenant と request tenant が異なる場合に `Attachment resource tenant mismatch` で拒否されることを確認した
- unauthenticated request は、`MCP_AUTH_TOKEN` がない場合に認証エラーで拒否されることを確認した
- これにより、resource は Continue.dev 向けの参照導線でありつつ、tenant / ACL を越えないことをテストで固定できた

#### D.4 判定理由

1. tenant mismatch を先に拒否するため、resource URI を知っていても他 tenant にはアクセスできない
2. 認証なしの呼び出しは resource 本文に到達しないため、Continue.dev 経由でも既存の認証ルールを維持できる
3. `routes.download` / `routes.inspector` と同様に、resource も tenant / auth の境界を越えないことを確認できた

#### D.5 完了メモ

- `docs/work/llm-integration/2026-04-05_MCP_Search_Attachment_Feedback_Followup_Plan.md` に Continue.dev resource スプリントへの分離を追記し、検索改善と resource 露出の責務を分けた
- `docs/work/llm-integration/2026-04-08_attachment_delivery_strategy_spec.md` に `resource_uri` / `routes.download` / `routes.inspector` の役割分担を追記し、ADS envelope と resource 導線の整合を明文化した
- `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php` と `tests/Unit/Mcp/Resources/LedgerAttachmentResourceTest.php` は resource 導線の回帰証跡として継続利用できる状態を確認した
- `access_guide` を追加し、MCP クライアントが `resources/read` を使うべきことをレスポンス内で明示できるようにした

#### D.5 判定理由

1. resource 導線の文書化が検索計画と ADS 仕様の双方に反映され、後続の参照先が一意になった
2. 既存の回帰テストが `resource_uri` / tenant 境界 / 認証境界をカバーしているため、D.5 で追加実装なしでも証跡を固定できる
3. これにより Continue.dev 向け resource 露出は、実装・テスト・文書の三点で同期した

## 8. 検討時の論点

1. **resource にする粒度**
   - 1 レコード 1 resource にするか
   - 1 添付 1 resource にするか
   - 両方を用意するか

2. **resource の payload 形式**
   - plain text で返すか
   - markdown 化して返すか
   - JSON envelope を返すか

3. **Continue.dev 側の UX**
   - resource を context provider から選ぶ運用にするか
   - 検索結果の中から resource を辿る導線にするか

4. **既存 route との役割分担**
   - download / inspector route を人間向け導線として残す
   - resource は LLM / Continue 向け導線として使う

## 9. 次アクション

1. この計画を Sprint D として Issue #135 に追加する
2. resource URI の粒度を決める
3. `BootstrapClientResource` と同じ登録方式で attachment resource を実装する
4. Continue.dev での参照フローを確認する
5. 必要なら `SearchLedgersTool` の summary に resource URI を追加する
