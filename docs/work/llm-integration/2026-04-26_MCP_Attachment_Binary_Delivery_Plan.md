# MCP 添付ファイル本体バイナリ配信計画

**作成日:** 2026年04月26日  
**関連Issue:** #169  
**関連:** `app/Mcp/Resources/LedgerAttachmentResource.php`, `app/Services/Ledger/LedgerAttachmentResourceService.php`, `app/Http/Controllers/AttachedFileDownloadController.php`, `app/Models/AttachedFile.php`, `tests/Unit/Mcp/Resources/LedgerAttachmentResourceTest.php`

## 1. 背景

現行の `ledgerleap-web-api` 経路では、`resources/read` で添付ファイルの内容を取得できることは確認済みだが、返ってくるのは JSON の resource envelope であり、ファイル本体のバイナリではない。

MCP 仕様上、resource contents は text だけでなく binary も扱え、binary は `blob` として base64 エンコードされる。Laravel MCP でも `Response::blob()` が用意されているため、MCP 経由で本体バイナリを返す実装は可能である。

ただし、現行の `LedgerAttachmentResource` は `Response::json()` ベースで envelope を返しており、添付の抽出テキストや structured data を返す用途に寄っている。したがって、「添付の中身を読む resource」と「添付の本体バイナリを返す resource」は分けるのが安全である。

## 2. Sprint 1 の確認結果

Sprint 1 では、`ledgerleap-web-api` を使った `resources/read` が実際に動作することを確認した。

- `ledgerleap://ledger/demo-tenant/32/attachments/17` を `resources/read` で取得できた
- 返却内容には `resource_uri`、`access_guide`、`payloads.text`、`payloads.structured`、`payloads.visual` が含まれていた
- 該当添付は `WhhvSoT8fPCWCiidhi32Rxme4tNXCFZcajhzMJE2.pdf` で、本文には `2022年11月19日`、`領収書`、`153,729` が含まれていた
- つまり、現状の resource は「内容確認」には使えるが、「本体バイナリの取り出し」には未対応である

## 3. Sprint 2 の確認結果

Sprint 2 では、MCP / ライブラリ実装 / 周辺 SDK の動向を確認したうえで binary contract を確定した。

- MCP 仕様は resource contents に `blob` を持つ binary content を正式に定義している
- TypeScript SDK / Python SDK の resource read 実装と例は、binary を `blob` として返す前提で揃っている
- Laravel MCP には `Response::blob()` があり、resource read でも binary payload を base64 blob に変換できる
- `Response::fromStorage()` は image / audio の自動判定に寄っており、PDF を含む汎用 binary 配信の主経路には向かない

結論として、既存の envelope resource は維持し、binary 配信用 resource を別 URI で追加する。

採用する contract は次のとおり。

- URI: `ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}/blob`
- 返却: `Response::blob($bytes)`
- MIME type: attachment の metadata から決定
- bytes source: 既存 download route の path 解決ロジックを共有 service に切り出して再利用

## 3. 実装方針

### 4.1 方針の結論

既存の envelope resource は維持し、バイナリ配信用に別 resource を追加する。

Sprint 2 の確認結果を踏まえ、推奨 URI は次のとおりに確定する。

- `ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}/blob`

`blob` を採用し、MCP の binary content と意味を揃える。

### 3.2 返却形式

- resource の `handle()` は `Response::blob($bytes)` を返す
- resource の `mimeType()` は添付ごとの MIME を返す
- MCP の wire format 上は JSON-RPC になるため、実際の binary は `blob` として base64 で返る
- そのため「raw バイトをそのまま HTTP のように返す」わけではないが、MCP クライアント側では本体バイナリとして扱える

### 3.3 取り出し元

既存のダウンロード実装が持つ以下のロジックを共通化して流用する。

- `AttachedFile::path`
- `AttachedFile::original_file_path`
- `AttachedFile::mime` / `AttachedFile::original_mime_type`
- tenant / ledger / permission の確認

`AttachedFileDownloadController` で分岐しているパス解決をそのまま resource 側に複製せず、共有サービスへ切り出す。

## 4. 推奨設計

### 4.1 resource の役割分担

- 既存 resource: 添付内容の envelope と抽出結果を返す
- 新規 binary resource: 添付本体の bytes を返す

### 4.2 共有サービス

`LedgerAttachmentResourceService` とは別に、バイナリ取得専用の service を用意する。

例:
- `LedgerAttachmentBinaryResourceService`

責務:
- tenant / ledger / attachment の整合性確認
- 返却対象のストレージパス決定
- MIME type 決定
- `Storage::disk('public')->get($path)` で bytes を取得

### 4.3 既存コードとの整合

- `LedgerAttachmentResource` は現状維持
- `LedgerLeapServer` に binary resource を追加登録
- download route は継続維持し、人間向けの HTTP 導線として残す
- Continue.dev / MCP クライアント向けには resource URI を優先する

## 5. 実装タスク

### Sprint 2: binary contract の確定

- [x] binary resource の URI を `.../blob` にするか `.../binary` にするか決める
- [x] 本体 bytes と original bytes のどちらを標準にするか決める
- [x] MIME type の優先順位を確定する
- [x] `Response::blob()` の meta / annotations の扱いを決める

決定事項:
- 標準 URI は `.../blob`
- 標準 bytes は現行 download route と同じく attachment の本体 bytes
- `original` 系は後続の別 URI で扱う余地を残す
- `Response::blob()` は binary 本体のみを返し、付随メタは最小限にする

### Sprint 3: binary resource 実装

- [x] `LedgerAttachmentBinaryResource` を追加する
- [x] bytes を返す service を追加する
- [x] tenant mismatch / unauthorized / file missing を拒否する
- [x] `LedgerLeapServer` に resource を登録する

実装結果:
- `LedgerAttachmentBinaryResource` を追加し、`ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}/blob` で `Response::blob()` を返すようにした
- 共有 service `LedgerAttachmentBinaryResourceService` を追加し、binary 取得と MIME 解決を共通化した
- `AttachedFileDownloadController` は同じ resolver を使うように切り替えた
- `LedgerLeapServer` に binary resource を登録し、`resources/templates/list` で discoverable にした
- 回帰テストとして `tests/Feature/Mcp/LedgerAttachmentBinaryResourceTest.php` を追加し、`resources/read` の blob 返却を検証した

### Sprint 4: 回帰テスト

- [x] binary resource が `blob` を返すことを確認する
- [x] MIME type が添付に一致することを確認する
- [x] tenant mismatch と未認証を確認する
- [x] 既存 envelope resource と共存できることを確認する

実施結果:
- `tests/Feature/Mcp/LedgerAttachmentBinaryResourceTest.php` に回帰ケースを追加し、`blob` 返却、MIME 一致、tenant mismatch、未認証、missing file を固定した
- `tests/Feature/Mcp/BootstrapClientResourceTest.php` ですでに `blob` と既存 envelope の両方が resource template に並ぶことを確認済み
- これにより Sprint 4 の回帰範囲はテストでカバーされた

検証コマンド:
- `./vendor/bin/sail test tests/Feature/Mcp/LedgerAttachmentBinaryResourceTest.php tests/Feature/Mcp/BootstrapClientResourceTest.php`

結果:
- 7 passed, 31 assertions

### Sprint 5: client validation と文書同期

- [ ] `ledgerleap-web-api` で binary resource を取得する
- [ ] Continue.dev で blob 受け取りを確認する
- [ ] issue / docs / links を同期する

## 6. 判定基準

- `resources/read` で attachment のバイナリ本体を `blob` として返せる
- 既存の text / structured / visual envelope を壊さない
- HTTP download route と役割が競合しない
- tenant / ACL を越えない
- Continue.dev 側で follow-up 取得できる

## 7. 参照

- [Issue #169](https://github.com/torinky/LedgerLeap/issues/169)
- Sprint 1 の確認結果はこのドキュメントの「2. Sprint 1 の確認結果」に集約する
- [MCP HTTPアクセスに関する調査とトラブルシューティング](2026-03-26_MCP_HTTP_Access_Troubleshooting.md)
- [Continue.dev 向け MCP 添付 Resource Exposure 計画](2026-04-17_Continue_MCP_Attachment_Resource_Exposure_Plan.md)

## 8. 補足

MCP の binary 返却は base64 blob で運ぶ前提になるため、実装のゴールは「HTTP の生バイナリを直接返すこと」ではなく、「MCP resource として本体 bytes を表現できること」に置く。

この方針により、
- envelope は discovery / summarization
- binary resource は follow-up / 取得
- download route は人間向け direct download

という役割分担が保てる。

## 9. 外部情報の根拠

Sprint 2 の方針決定では、次の外部情報を参照した。

- MCP 仕様: Resources / Binary Content / Custom URI Schemes / Security Considerations
	- `resources/read` は text と binary の両方を返せる
	- binary content は `blob` として base64 で表現される
	- custom URI scheme は許容され、`https://` や `file://` 以外の scheme も使える
	- 参照先: https://modelcontextprotocol.io/specification/latest/server/resources
- MCP TypeScript SDK
	- `ReadResourceResult` は `TextResourceContents | BlobResourceContents` を返す
	- resource template と resource read の例で binary / text の分離が確認できる
	- 参照先: https://github.com/modelcontextprotocol/typescript-sdk
- MCP Python SDK
	- resource read は `str | bytes` を受ける設計で、bytes は binary resource として扱われる
	- `ReadResourceResult` と `BlobResourceContents` の運用例がある
	- 参照先: https://github.com/modelcontextprotocol/python-sdk
- Laravel MCP
	- `Response::blob()` が generic binary 用の主要 API
	- `Response::fromStorage()` は image / audio の自動判定に寄っており、PDF など汎用 binary の主経路にはしない
	- resource read 実装は `Response::blob()` / `Blob` content を `blob` payload に変換できる
	- 参照先: https://github.com/laravel/mcp

実装時に特に重視したのは、MCP 仕様に合わせて envelope と binary を分離すること、そして Laravel MCP の API と整合することだった。

## 10. Sprint 3 の実施結果

Sprint 3 は完了した。

- binary resource の追加と server 登録を完了
- download route と binary resource の byte resolver を共通化
- `resources/read` で binary content が `blob` として返ることを確認
- `resources/templates/list` で `blob` と既存 envelope の両方が discoverable であることを確認

検証コマンド:
- `./vendor/bin/sail test tests/Feature/Mcp/BootstrapClientResourceTest.php tests/Feature/Mcp/LedgerAttachmentBinaryResourceTest.php`
- `./vendor/bin/sail test tests/Unit/Mcp/Resources/LedgerAttachmentResourceTest.php`

追加確認（2026-04-26）:
- `ledgerleap://ledger/demo-tenant/32/attachments/17` の対象レコード `ledger_id=32` で、`添付ファイル` に `receipt_01.jpg` が含まれることを確認
- `content_attached` では `WhhvSoT8fPCWCiidhi32Rxme4tNXCFZcajhzMJE2.jpg` が `receipt_01.jpg` に対応しており、添付メタデータも保持されていることを確認
- この確認により、Sprint 3 の binary resource 導線が実データでも追えることを補強できた
- ただし、この作業環境では MCP の blob リソース本体を直接 read するところまではできていないため、実 bytes の受信確認は未実施
- そのため、ここでの確認結果は resource 登録・返却形状・添付メタデータの整合性確認までに限る

残りの作業は Sprint 4 以降の回帰範囲に移る。

## 11. 振り返り

### 良かったこと
- 既存の issue コメントと plan ドキュメントを先に揃えたことで、Sprint ごとの完了条件がぶれなかった。
- blob 本体の直接読取はできない制約を早めに明示し、確認範囲を resource 登録・返却形状・メタデータ整合性に限定できた。
- 未認証ケースは Laravel の `actingAsGuest()` に寄せたことで、guard を直接壊す回避策よりも再現性の高いテストにできた。

### 悪かったこと
- 最初に未認証の解除を `Auth::logout()` や `setUser(null)` で処理しようとして、guard 実装差と型制約にぶつかった。
- blob 実体の取得可否を、利用可能なクライアント能力より先に期待してしまい、確認可能範囲の見極めに一度戻る必要があった。

### 次に残す学び
- tenant-aware な MCP feature test では、認証解除は `actingAsGuest()` を第一候補にする。
- MCP の binary resource 検証は、bytes 取得と resource 形状確認を分けて考え、直接 read できるクライアントがある場合のみ bytes の実取得まで進める。
