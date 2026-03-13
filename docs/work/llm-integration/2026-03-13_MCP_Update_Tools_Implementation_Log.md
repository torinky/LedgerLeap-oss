# LedgerLeap MCP update tools implementation log

**作成日:** 2026年03月13日  
**ドキュメント種別:** 作業ファイル（Issue #91 実装ログ）  
**関連Issue:** [#83](https://github.com/torinky/LedgerLeap/issues/83), [#90](https://github.com/torinky/LedgerLeap/issues/90), [#91](https://github.com/torinky/LedgerLeap/issues/91)

## 1. 目的

この文書は、`ledger-update` の MCP 側初期実装として追加した
単一レコード確認ツールと更新ツールの判断を残し、
後から公式ドキュメントへ昇格させやすくするための実装ログです。

今回の対象は次の 2 tool です。

- `GetLedgerDetailTool`
- `UpdateLedgerTool`

## 2. 今回のスプリントで実装した範囲

### 2.1 本スプリントの主対象

Sprint 5 までに定義した `ledger-update` workflow のうち、
現場リーダー / 差し戻し対応 / 代理更新の主要シナリオを満たす最小導線を実装した。

具体的には次を対象とした。

1. `SearchLedgersTool` の次に使う **単一レコード確認**
2. `content_patch` + `comment` による **部分更新**
3. `dry_run` による **軽量な差分確認**
4. pending 状態保存時の **DRAFT 戻し** の明示

### 2.2 今回あえて含めなかったもの

次は、想定外に実行範囲が膨らむため **別スプリント** に切り出す前提とした。

- `tag_operation` / `tag_values` を使うタグ**実更新**
- `PUT /api/v1/ledgers/{ledger}` に相当する完全置換
- 汎用 diff engine / 変更要約の高度化
- optimistic locking (`expected_version` 等)
- bootstrap discovery との統合
- 添付ファイル更新

## 3. ペルソナ / シナリオとの対応

### 3.1 主対象ペルソナ

- **現場リーダー / 作業班長**
  - 代理更新
  - 差し戻し内容の反映
  - 更新後に状態がどう変わったかの確認

### 3.2 補助的に満たすシナリオ

- 検索後に単一レコードを確認してから安全に更新したい
- 実務担当者の代わりに必要項目だけ修正したい
- 差し戻し理由を踏まえて pending 中レコードを再編集したい

### 3.3 今回の非対象

- 管理者向けの bootstrap / capability bundle 解決
- client ごとの導線最適化
- 管理者向け監査・集計からの update 直接導線

## 4. 実装方針

### 4.1 単一レコード確認を専用 tool に分けた

`dry_run=true` を read path 代わりにせず、責務を分離するため
`GetLedgerDetailTool` を追加した。

これにより、MCP でも次の導線を保てる。

1. 検索で候補を絞る
2. 単一レコード詳細を確認する
3. `GetLedgerDefinesTool` で列定義を確認する
4. patch を組み立てる
5. `dry_run` で変更候補を確認する
6. 更新を適用する

初期契約では `tag_operation` / `tag_values` も受け取れるようにしたが、
これは **forward-compatible な入力受理** に留め、実際には
「タグ更新はまだ初期 MCP 更新契約では未対応」という client-facing メッセージを返す。

### 4.2 更新ロジックは REST と同じサービス経由にした

`UpdateLedgerTool` は内部で次を再利用している。

- `LedgerService::getLedgerForApi()`
- `LedgerService::previewLedgerUpdateForApi()`
- `LedgerService::updateLedgerForApi()`

これにより、REST と MCP で次の挙動を揃えている。

- 不明な column id の拒否
- `content_patch` のみを使う部分更新
- `APPROVED` の更新拒否
- `PENDING_INSPECTION` / `PENDING_APPROVAL` 保存時の `DRAFT` 戻し
- `tag_operation` / `tag_values` 入力時の明示的な未対応返却

### 4.3 権限チェックは既存 MCP パターンに合わせた

新規 tool は `UserService` を直接依存にせず、
既存の `AuthenticatedMcpTool` と `WritableFolderRepository` を使う権限確認へ寄せた。

これにより、既存の成功している MCP unit test パターン
（例: `GetWorkflowHistoryToolTest`）と同じ構造を維持できる。

### 4.4 差分確認は列単位の最小要約に留めた

`dry_run` では、変更後の全 content を返すだけでなく、
列単位の差分配列 `changed_columns` を返す実装にした。

返す項目は最小限とし、各要素は次の形に留める。

- `column_id`
- `column_name`
- `before`
- `after`

これは client-facing workflow の「差分確認」を満たす最低ラインであり、
複雑な natural language summary は後続へ送った。

## 5. テスト方針

今回は、初期化ループを避けるために
`GetLedgerDetailToolTest` / `UpdateLedgerToolTest` では
`LedgerService` を **モック** している。

つまり MCP unit test の責務は次に限定した。

- 入力の解釈
- 権限分岐
- dry-run / 本更新のレスポンス組み立て
- エラー変換
- 初期契約で未対応な tag update 入力の明示拒否

一方で、実際の更新処理・workflow 状態遷移は
既存の `tests/Feature/Api/LedgerReadUpdateApiTest.php` を主な回帰保護として扱う。

## 6. 追加した主要要素

- `app/Mcp/Tools/GetLedgerDetailTool.php`
- `app/Mcp/Tools/UpdateLedgerTool.php`
- `LedgerService::previewLedgerUpdateForApi()`
- `tests/Unit/Mcp/Tools/GetLedgerDetailToolTest.php`
- `tests/Unit/Mcp/Tools/UpdateLedgerToolTest.php`
- `tests/Unit/Mcp/Tools/McpToolsAuthenticationTest.php`

## 7. 公式ドキュメント化の手掛かり

後で `docs/development/MCP_Architecture_and_Flow.md` などの公式文書へ昇格させる際は、次を整理するとよい。

1. なぜ update 前に単一レコード確認が必要か
2. `dry_run` がどの粒度の差分を返すか
3. pending 中更新で `DRAFT` に戻ることをどう説明するか
4. `APPROVED` をなぜ初期契約でロックしたか
5. `content_patch` のキーが column definition id であること

## 8. この実装後の別スプリント候補

1. bootstrap discovery 連携
2. `ledger-update` capability の bundle 解決
3. タグ更新の公開契約
4. optimistic locking
5. 添付更新
6. 高度 diff / natural language summary

