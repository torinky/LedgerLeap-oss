## Attachment Delivery Strategy (ADS) 設計仕様書

### 1. 背景と目的
添付ファイル（画像、PDF、テキスト、JSON等）の特性と、利用する LLM の能力（Vision/マルチモーダル機能の有無）を考慮し、トークン消費量を抑えつつ、解析精度を最大化するためのデータ提供戦略を確立する。

### 2. デリバリー・モード (Delivery Modes)
添付ファイルの種類および LLM の能力に基づき、以下の 3 つのモードから最適なものを選択してレスポンスを生成する。

#### Mode: Text (テキスト・モード)
- **対象:** `.txt`, `.md`, `.csv` 等のプレーンテキスト形式。
- **提供形式:** 行番号付きの文字列配列、または単一のテキストブロック。
- **用途:** 迅速な内容確認およびキーワード検索。

#### Mode: Structured (構造化モード)
- **対象:** OCR 解析結果（JSON）、解析済みの構造データ。
- **提供形式:** 高度に構造化された JSON オブジェクト（`pages`, `text_blocks`, `key_value_pairs` 等）。
- **用途:** 特定の項目（金額、日付等）のピン打点的な抽出・照会。

#### Mode: Visual (ビジョン・モード)
(LLM が Vision 機能を持つ場合のみ有効)
- **対象:** `image/*`, `application/pdf` 等の画像・文書形式。
- **提供形式:** ファイルへの直接アクセス可能な URL、または Base64 エンコードされたバイナリデータ。
- **用途:** 文書全体のレイアウト、印影、手書き文字等の視覚的な解析。

### 3. 決定ロジック (Strategy Selector)
以下の優先順位と条件に基づき、モードを自動判定する。

1.  **LLM 能力チェック:** セッション中の LLM が Vision 機能を利用可能かを確認。
2.  **ファイル種別 (MIME Type) による分岐:**
    - `image/*`, `application/pdf` かつ `Vision_Capable == true` $\rightarrow$ **Mode: Visual**.
    - `application/json`, `text/plain` 等の構造化可能な形式 $\rightarrow$ **Mode: Structured** または **Mode: Text**.
    - それ以外 $\rightarrow$ **Mode: Text** (Fallback).

### 4. 実装要件 (Implementation Requirements)
- 添付ファイル一覧 (`attachments[]`) の各要素に、選択された `delivery_mode` をメタデータとして付与すること。
- 構造化モードでは、既設の `key_value_pairs` 構造を維持・拡張し、解析精度を低下させないこと。
- すべてのモードにおいて、共通の識別子 (`attachment_id`, `filename`, `role`, `order`) を提供すること。

### 5. 共通ペイロード仕様 (Common Envelope)
すべての `attachments[]` は、少なくとも以下の共通メタデータを持つ。

- `attachment_id`: 添付を一意に識別する ID
- `filename`: LLM に提示する表示名
- `role`: 参照目的や業務上の役割（例: 本文、証憑、補助資料）
- `order`: レコード内での提示順
- `delivery_mode`: `text` / `structured` / `visual`
- `mime_type`: 元ファイルの MIME type
- `source`: どの抽出経路から得たか（例: `vlm_markdown`, `vlm_structured_data`, `original_file`）

### 6. モード別ペイロード (Mode Payloads)

#### 6.1 Text
- `lines[]` または `text`
- 行番号が必要な場合は `line_number` を付与する
- 長文の場合は、切り詰め位置が分かる `truncated` フラグを持たせる

#### 6.2 Structured
- `pages[]`
- `text_blocks[]`
- `key_value_pairs[]`
- 必要に応じて `page_index`、`bbox`、`source_span`、`confidence` を付与する
- 既存の `key_value_pairs` は削らず、追加情報を外側に拡張する

#### 6.3 Visual
- 直接アクセス可能な `signed_url` または `base64` を返す
- URL を返す場合は `expires_at` と認可前提を明示する
- Base64 は内部転送用を優先し、永続保存やログ出力の対象にしない

### 7. 決定ロジックの補足
1. まず Vision 可否を確認する
2. `image/*` と `application/pdf` は、Vision 可であれば Visual を優先する
3. `application/json` は Structured を優先する
4. `text/plain` / `text/csv` / `text/markdown` は Text を優先する
5. 取得失敗、空データ、未対応 MIME の場合は Text fallback とし、共通 envelope は維持する

### 8. 制約と注意事項
- `attachment_id` / `filename` / `role` / `order` は欠損させない
- 既存の `vlm_markdown` / `vlm_structured_data` と矛盾する再生成は行わない
- tenant 境界と認可を越える URL は生成しない
- 大きすぎる添付は切り詰めや分割を前提にする
