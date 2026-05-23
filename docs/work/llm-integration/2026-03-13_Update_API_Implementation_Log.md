# LedgerLeap update API implementation log

**作成日:** 2026年03月13日  
**ドキュメント種別:** 作業ファイル（Issue #90 実装ログ）  
**関連Issue:** [#83](https://github.com/torinky/LedgerLeap/issues/83), [#88](https://github.com/torinky/LedgerLeap/issues/88), [#90](https://github.com/torinky/LedgerLeap/issues/90)

## 1. 目的

この文書は、`#90` の REST API 実装で行った設計判断と、
後で公式ドキュメントへ昇格させる際の手掛かりを残すための実装ログです。

対象は次の 2 endpoint です。

- `GET /api/v1/ledgers/{ledger}`
- `PATCH /api/v1/ledgers/{ledger}`

## 2. 今回の実装で採った方針

### 2.1 read path を update path の前提にした

Sprint 5 で決めた通り、検索結果だけで更新させず、
**単一レコード read path** で最新状態を確認してから更新する流れを実装した。

### 2.2 PATCH を主契約にした

初期実装では **部分更新** に絞り、`PATCH` を主契約として実装した。
`PUT` は今回も非対象のままとした。

### 2.3 タグ更新はまだ入れなかった

Sprint 5 では `tag_operation` / `tag_values` を候補に挙げていたが、
既存データ構造上のタグが **ledger_define 単位** に紐づいており、
レコード単位更新としての意味づけが曖昧だったため、
今回の REST update API では **明示的に禁止** した。

これにより、初期実装は `content_patch` + `comment` に集中できる。

### 2.4 workflow 状態の扱い

- `DRAFT` → 更新可
- `PENDING_INSPECTION` → 更新可、保存後 `DRAFT` へ戻す
- `PENDING_APPROVAL` → 更新可、保存後 `DRAFT` へ戻す
- `APPROVED` → 初期公開契約では更新不可（409）

### 2.5 既存更新ロジックの再利用

- workflow 無効時は `LedgerService::saveDirectly()` を再利用
- workflow 有効時は
  - pending 中: `WorkflowService::saveEditedRecord()`
  - それ以外: `WorkflowService::saveDraft()`
  を使う

このため、UI と API で完全一致ではないものの、
**既存の状態遷移ロジックをできるだけ共有**する実装になっている。

## 3. 新しく追加した主要要素

- `UpdateLedgerRequest`
- `LedgerDetailResource`
- `LedgerController::show()`
- `LedgerController::update()`
- `LedgerService::getLedgerForApi()`
- `LedgerService::updateLedgerForApi()`
- `tests/Feature/Api/LedgerReadUpdateApiTest.php`

## 4. レスポンス設計メモ

### show

`show` は、更新前確認に必要な次の情報を返す。

- current content
- `content_by_column_id`
- `column_definitions`
- `workflow.status`
- `workflow.editable`
- `workflow.returns_to_draft_on_save`
- `workflow.latest_comment`
- `version`

### update

`update` は `LedgerDetailResource` に加え、`meta` として次を返す。

- `previous_status`
- `current_status`
- `status_changed`
- `returned_to_draft`

この `meta` は、クライアントが「保存で pending が崩れたか」を短く判定するための補助情報として入れた。

## 5. 未解決 / 今後の論点

### 5.1 optimistic locking

今回は `expected_version` などを入れていない。
同時編集の厳密制御は今後の検討事項。

### 5.2 tag update

`tag_operation` / `tag_values` は prohibited にした。
将来、レコード単位タグ更新を導入するなら、
現在のタグモデルの意味づけを再確認する必要がある。

### 5.3 approved 以外の細かい状態ロック

今回は `APPROVED` のみ明確にロックした。
今後、業務要件次第で追加制約が必要かもしれない。

### 5.4 OpenAPI の生成元

`docs/api/openapi.json` は実装済み契約の公開物として更新したが、
将来的には annotation / generator / committed artifact の責務を整理した方がよい。

## 6. 公式ドキュメント化の手掛かり

後で公式 API ドキュメントや runbook に昇格させる場合は、次の観点を整理するとよい。

1. 更新前確認がなぜ必須か
2. `PATCH` を主契約にした理由
3. pending 保存で `DRAFT` に戻る意味
4. `APPROVED` ロック時の利用者向け説明
5. `content_patch` のキーが **column definition id** であること
6. `content` と `content_by_column_id` をどう使い分けるか

## 7. 今回の実装で意図的に避けたこと

- `PUT` 実装
- tag update 実装
- bootstrap discovery との接続
- MCP 側の update 実装
- 複雑な差分要約 / dry run API

これらは `#91` または後続 issue で扱う前提。
