# LedgerLeap update path public contract

**作成日:** 2026年03月13日  
**ドキュメント種別:** 作業ファイル（Sprint 5: update path definition）  
**関連Issue:** [#83](https://github.com/torinky/LedgerLeap/issues/83), [#88](https://github.com/torinky/LedgerLeap/issues/88)

## 1. 目的

この文書は、LedgerLeap の `ledger-update` を **client-facing な公開契約** として定義するための Sprint 5 成果物です。

Sprint 5 では、検索・登録に続く更新系の workflow を、
**検索 → 単一レコード確認 → 差分確認 → 更新 → 状態確認** の流れで説明できるようにします。

ここで扱うのは **公開契約と利用者向け workflow** です。
実際の API / MCP Tool 実装、差分計算ロジック、権限制御のコード化は後続の implementation issue へ送ります。

## 2. この文書が決めること / 決めないこと

### 2.1 決めること

- `ledger-update` の client-facing workflow
- Update API / Update MCP Tool の公開要件
- 単一レコード read path の必要性
- 更新前確認 / 差分確認 / 権限不足時の振る舞い
- 実装 Issue に分解できる単位

### 2.2 決めないこと

- 実際の route / controller / MCP Tool 実装
- OpenAPI の非実装 path 追加
- diff エンジンの実装詳細
- 楽観的排他の最終方式
- bootstrap discovery への統合方法

## 3. 現状整理（2026-03-13）

### 3.1 現在の公開契約

- REST API の公開入口では、`search` / `ledger-defines` / `ledgers(create)` が先行している
- MCP server では `SearchLedgersTool` / `CreateLedgerTool` / workflow / stats 系が先行している
- `UpdateLedgerTool` は未実装
- `PATCH /api/v1/ledgers/{ledger}` / `PUT /api/v1/ledgers/{ledger}` は未公開
- 単一レコード read path (`GET /api/v1/ledgers/{ledger}` 相当) も公開契約としては未整理

### 3.2 既存資料から確定していること

- `ledger-update` は Sprint 2 で client-facing capability として定義済み
- `ledger-update` manifest は `planned`
- 現場リーダーの主要シナリオには、代理更新・差し戻し対応・再提出支援が含まれる
- ワークフロー仕様では、`PENDING_INSPECTION` / `PENDING_APPROVAL` の編集保存時に状態が `DRAFT` に戻る
- `APPROVED` は原則編集不可として扱われている

## 4. Sprint 5 の結論

Sprint 5 では、更新系の公開契約を次のように定義する。

1. **更新前に単一レコード read path が必要**
2. **初期公開契約の主軸は PATCH に置く**
3. **検索結果だけで即更新せず、必ず現在値と状態を確認する**
4. **差分確認は client-facing workflow の必須段階とする**
5. **承認待ち中の編集は可能だが、保存後に DRAFT へ戻ることを説明する**
6. **APPROVED は初期公開契約では原則更新不可** とする

## 5. client-facing workflow

## 5.1 標準フロー

1. **対象候補を絞る**
   - 検索結果や現在の会話文脈から対象レコードを絞る
2. **単一レコードを確認する**
   - 現在の主要項目、状態、更新日時、必要なら差し戻し理由や履歴を確認する
3. **編集可否を確認する**
   - 権限不足でないか
   - 現在状態で更新可能か
   - 承認待ち中なら保存後に `DRAFT` へ戻るか
4. **更新内容を組み立てる**
   - 変更する column id と値を `content_patch` として整理する
   - タグ変更があれば `tag_operation` と対象値を決める
5. **差分を確認する**
   - 変更前 / 変更後の要約を見て、意図した更新か確認する
6. **更新を実行する**
   - 必要項目だけ更新する
   - 必要なら理由コメントを残す
7. **結果を確認する**
   - 更新後の主要項目
   - 状態変化の有無
   - 再提出 / 再確認 / 承認依頼が必要か

## 5.2 なぜ read path が必要か

検索結果の summary だけでは、次の判断に不足しやすい。

- 本当にそのレコードで合っているか
- 現在値が何か
- いま編集可能か
- 差し戻しや pending 状態なのか
- どの項目だけ変更すべきか

そのため、**単一レコード read path は update path の前提契約**とする。

## 6. API 公開契約

## 6.1 初期公開契約

### supporting read path
- **GET** `/api/v1/ledgers/{ledger}`
- 役割: 更新前確認のための単一レコード取得
- 返したい情報:
  - 主要項目
  - 状態
  - 更新日時
  - 台帳定義参照に必要な ID
  - 必要ならコメント / 差し戻し理由の参照導線

### primary update path
- **PATCH** `/api/v1/ledgers/{ledger}`
- 役割: 必要項目だけの部分更新
- 初期入力候補:
  - `content_patch`
  - `comment`
  - `tag_operation`
  - `tag_values`
  - `dry_run`（初期実装に含めるかは implementation issue で決定）

### deferred contract
- **PUT** `/api/v1/ledgers/{ledger}`
- 位置づけ: 将来の完全置換向け候補
- Sprint 5 結論: **初期公開契約の主契約には含めない**

## 6.2 API で返すべき結果

- 更新後の主要項目
- 最新状態
- pending 状態から `DRAFT` に戻ったか
- 次に必要な操作（再提出 / 再確認 / 承認申請）
- 権限不足や状態ロック時の短い説明

## 7. MCP 公開契約

## 7.1 役割

MCP では、自然言語の更新依頼を次の構造に落とし込む。

- `ledger_id`
- `content_patch`
- `comment`
- `tag_operation`（初期 MCP 契約では受理後に未対応メッセージを返す）
- `tag_values`（初期 MCP 契約では受理後に未対応メッセージを返す）
- `dry_run`（補助機能として有力）

## 7.2 MCP 側の利用フロー

1. `SearchLedgersTool` などで候補を絞る
2. 単一レコード read path で現在内容を読む
3. `GetLedgerDefinesTool` で column_define を確認する
4. 更新したい項目だけ patch を組み立てる
5. 必要なら差分要約や `dry_run` で確認する
6. `UpdateLedgerTool` で更新する
7. 更新後の状態と次アクションを返す

2026-03-14 時点では、`GetLedgerDetailTool` / `UpdateLedgerTool` の初期実装が入り、
`dry_run` による列単位差分確認、および `APPROVED` ロック / pending 保存時の `DRAFT` 戻しを
MCP 側からも扱えるようになった。

## 7.3 MCP と API の違い

| 観点 | MCP | REST API |
|---|---|---|
| 入力の起点 | 自然言語・会話文脈 | 構造化 JSON |
| 必要な確認 | LLM が対象特定と差分確認を補助 | クライアント実装が明示的に処理 |
| 差分確認 | `dry_run` や要約表示と相性がよい | 必須ではないが事前 read が必要 |
| 主な責務 | 人間に分かる更新支援 | 明確な機械可読 contract |

## 8. 状態・権限の client-facing ルール

## 8.1 状態別の扱い

| 状態 | 初期公開契約での扱い | client-facing に伝えること |
|---|---|---|
| `DRAFT` | 更新可能 | 通常の編集保存として扱う |
| `PENDING_INSPECTION` | 更新可能 | 保存すると `DRAFT` に戻り、再確認が必要になる |
| `PENDING_APPROVAL` | 更新可能 | 保存すると `DRAFT` に戻り、再承認ルートが必要になる |
| `APPROVED` | 原則更新不可 | まずはロック扱いとし、別手段や運用判断が必要 |

## 8.2 権限不足時の扱い

client-facing では、内部 ACL 実装を露出せず、次の粒度で説明する。

- このレコードを編集する権限がない
- 現在の保存先では更新できない
- 対象レコードを表示できないため更新できない

必要なら、次に確認すべきことを短く返す。

- 保存先フォルダ
- 対象レコードの可視性
- 担当者 / 承認状態

## 9. 差分確認の扱い

Sprint 5 では、**差分確認を公開 workflow の必須段階**として定義する。

ただし初期実装では、差分確認の提供方法は 2 段階でよい。

- **最低ライン**: read path の現状値と、クライアント側で組み立てた patch を比較して確認する
- **拡張候補**: API または MCP Tool が `dry_run` / 変更要約を返す

### Sprint 5 の判断

- `dry_run` は有力だが、**初期 API 実装の必須要件にはしない**
- ただし MCP 側では補助機能として相性がよく、implementation issue で優先候補とする

## 10. 実装 Issue へ分解する単位

## 10.1 API 実装 Issue

対象:
- `GET /api/v1/ledgers/{ledger}`
- `PATCH /api/v1/ledgers/{ledger}`

主な論点:
- read path の返却粒度
- `content_patch` の受け取り方
- タグ更新方式
- pending 状態保存時の `DRAFT` 戻し
- 権限不足 / 状態ロック時のエラー設計

## 10.2 MCP 実装 Issue

対象:
- 単一レコード read path の MCP 露出方法
- `UpdateLedgerTool`
- 差分要約 / `dry_run` 支援

実装メモ:
- `GetLedgerDetailTool` は単一レコード read path の正式導線として実装済み
- `UpdateLedgerTool` は `ledger_id` / `content_patch` / `comment` / `dry_run` を実装済み
- `tag_operation` / `tag_values` は schema で受け取り、初期契約では明示的に未対応メッセージを返す

主な論点:
- 会話文脈からの対象特定
- column id ベース patch 生成
- 変更理由の残し方
- 更新後の次アクション提示

## 11. Sprint 6 へ送る非対象

Sprint 5 では扱わず、Sprint 6 に送るものは次です。

- bootstrap discovery との接続
- update capability を role / model / client ごとにどう bundle するか
- placement instruction への落とし込み

## 12. Sprint 5 の完了条件への対応

- **更新系の公開契約が文書化されている** → 本文書と `ledger-update.yaml` で対応
- **client-facing workflow が確立している** → 検索→確認→更新→結果確認の流れを定義
- **次の実装 Issue を起こせる粒度になっている** → API 実装 / MCP 実装の 2 単位に分解

## 13. Sprint 5 の結論

Sprint 5 の結論は次のとおりです。

1. update path は **単一レコード read path を前提**にする
2. 初期公開契約の主契約は **PATCH** とする
3. **PUT は deferred** とし、初期実装の必須対象にしない
4. pending 状態の更新は **保存後に DRAFT に戻る** ことを明示する
5. `APPROVED` は初期公開契約では **原則更新不可** とする
6. `dry_run` は拡張候補だが、**初期 API 実装の必須要件にはしない**

これにより、client-facing な更新導線と、後続の API / MCP 実装タスクを切り分けやすくする。
