# MCP Search / Attachment Feedback Follow-up Plan

**作成日:** 2026年04月05日  
**種別:** 作業計画（MCP テストフィードバック整理）  
**関連:** `resources/ai/capabilities/ledger-search.yaml`, `app/Mcp/Tools/SearchLedgersTool.php`, `app/Services/LedgerService.php`, `app/Services/Ledger/SearchContext.php`, `app/Mcp/Tools/GetPendingApprovalsTool.php`, `app/Models/AttachedFile.php`, `app/Jobs/Ledger/ProcessVlmExtraction.php`, `app/Http/Controllers/AttachedFileDownloadController.php`

## 1. 目的

MCP テストで得られた次の 4 件のフィードバックを、
**既存で足りている部分**と**追加実装が必要な部分**に切り分ける。

1. タグ、フォルダ名の一部、台帳名の一部で検索範囲を絞りたい
2. 同義語の検索と、同義語リストを使った検索ワード構成
3. 添付ファイルが複数あるレコードで、1 ファイルしかないように見える回答を改善したい
4. 添付ファイルごとに、抽出メタデータ / 文字列維持情報 / JSON / Markdown / ページ内位置 / 項目名との関係を取り出したい

## 2. 結論

### 2.1 先に結論

- **Item 1**: 既存の `q` / `tags` / `folder_id` で一部は対応済みだが、
  **タグの部分一致・フォルダ名の一部・台帳名の一部での絞り込み** に加えて、
  **`folder_id` / `ledgerDefineId` を知らない前提で候補を先に探す lookup-first 導線** が必要。
  小さな検索契約拡張として扱う。
- **Item 2**: 既存の `SearchContext` / `SynonymService` はあるが、
  **MCP の検索経路に同義語展開が明示的に入っていない**ため、実装スプリント対象。
- **Item 3**: 添付が複数あるときの回答要約は、
  **出力整形とレコード表示方針の改善**が必要で、実装スプリント対象。
- **Item 4**: 位置情報や項目名との関係まで含む per-attachment 出力は、
  **抽出契約そのものを拡張する必要がある**ため、別スプリントとして分離する。

### 2.2 優先順位

1. **Item 1 + Item 2**: 検索の絞り込み精度を上げる
2. **Item 3**: 複数添付の回答品質を上げる
3. **Item 4**: 添付抽出の詳細化を行う

理由:
- 現場担当者は「断片的な記憶」から探す頻度が最も高い
- 管理者 / 確認者は、検索精度と添付の見え方で作業効率が大きく変わる
- 位置情報や項目対応の詳細化は有用だが、実装・検証コストが最も高い

## 3. ユーザーシナリオ / ペルソナ評価

### 3.1 現場担当者

**主な行動**
- 「A社」「請求」「先月」など曖昧な条件で探す
- 添付が多いレコードから、必要なファイルだけを見たい

**重要項目**
- Item 1
- Item 3

**理由**
- 断片条件での再探索が多い
- 複数添付の見落としは、業務判断の誤りに直結しやすい

### 3.2 管理者 / マネージャー

**主な行動**
- キーワードの揺れを吸収して、担当レコードを横断的に探す
- 重要な添付が複数ある場合でも、どれが根拠かを把握したい

**重要項目**
- Item 1
- Item 2
- Item 3

**理由**
- 同義語を吸収できると、問い合わせの再試行が減る
- 補足情報の見え方が良いと、確認の往復が減る

### 3.3 承認者 / 監査確認者

**主な行動**
- 添付元の根拠を辿る
- 抽出内容がどのページ・どの項目に由来するかを確認したい

**重要項目**
- Item 3
- Item 4

**理由**
- 「何が根拠か」を追えることが重要
- 位置情報や項目対応は監査性を上げる

## 4. 現状確認

### 4.1 既にあるもの

- `SearchLedgersTool` は `q` / `tags` / `exclude_q` / `exclude_tags` / `folder_id` / `ledger_define_id` / `creator_id` / `created_from` / `created_to` / `mode` / `limit` / `offset` / `include_content` / `order_by` を持つ
- `LedgerService` は API 側で `tags` や全文検索フィルタを扱う
- `SearchContext` と `SynonymService` はアプリ内に存在する
- `AttachedFile` には `vlm_markdown` / `vlm_structured_data` / `vlm_model` / `vlm_confidence` / `vlm_processed_at` がある
- `ProcessVlmExtraction` で抽出結果を永続化している
- `AttachedFileDownloadController` で Markdown / JSON としてダウンロードできる

### 4.2 足りないもの

- SearchLedgersTool の検索経路に、タグの部分一致や同義語展開を明示的に使う契約がない
- `folder_id` はあるが、**folder name fragment** を直接受ける入力はない
- **ledger name fragment** を検索入力に落とす、明示的な resolver / filter がない
- `folder_id` / `ledgerDefineId` を知らない前提で、先に候補を探す lookup tool が不足している
- 複数添付のときに、どの添付が何を根拠にしているかを返す標準化された応答がない
- per-attachment の位置情報 / 項目対応を返す契約がない

### 4.3 テストの概略検討結果

- `app/Mcp/Tools/SearchLedgersTool.php` と `app/Mcp/Tools/GetRelatedLedgersTool.php` には `#[Name]` 属性や `$name` 上書きがないため、公開 MCP 名は既定の kebab-case でそれぞれ `search-ledgers-tool` / `get-related-ledgers-tool` になる
- `routes/ai.php` の公開経路は `/mcp/ledgerleap`、`/{tenant}/mcp/ledgerleap`、`ledgerleap:mcp` の 3 系統で、HTTP 経路の確認は transport / auth / tenant 境界に絞るのが妥当
- `tests/Feature/Mcp/RemoteMcpHttpRouteTest.php` は `tools/call` の疎通確認と `get-client-bootstrap-manifest-tool` を使った経路テストに残し、検索ロジックのアサーションは置かない
- 検索ロジックは `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php`、`tests/Feature/Mcp/SearchLedgersToolKeywordSearchTest.php`、`tests/Feature/Mcp/SearchLedgersToolSemanticSearchTest.php`、`tests/Feature/Mcp/SearchLedgersToolSortingTest.php`、`tests/Unit/Mcp/Tools/GetRelatedLedgersToolTest.php` に分離して維持する
- 公開名の命名規則は `tests/Unit/Mcp/Tools/McpToolNameConventionTest.php` で `search-ledgers-tool` / `get-related-ledgers-tool` として固定し、route test では命名規則を重ねて検証しない
- この分離により、A4 では capability 文言と route / test の責務を同期し、business-search の重複検証を増やさない

### 4.4 実装反映メモ

- `SearchContext` は `search_trace` の元データとして `original_q` / `normalized_q` / `selected_terms` / `excluded_terms` を持つようになった
- `selected_terms` には `synonym` / `technical` / `original` の kind を含められる
- `GetSearchTermsTool` を追加し、同義語 / 技術用語候補だけを先に取り出して検索語を組み立てられるようにした
- `SearchLedgersTool` の MCP 応答にも `search_trace` を含め、MCP でアクセスしたときの説明と実際の出力を揃えた
- これにより、`ledger-search` capability の案内は「同義語で探せる」だけでなく「MCP で trace を見ながら q を調整できる」内容へ更新する必要がある

## 5. 施策方針

### 5.1 Item 1: 検索の断片条件対応

**判断:** 実装スプリント対象

**対象ユーザー**
- 現場担当者
- 管理者

**やること**
- `tags` の部分一致を、MCP ツール側の必須導線として扱う
- `folder_id` / `ledgerDefineId` を知らない前提で、先に候補を探す lookup tool を作る
- `folder name fragment` と `ledger name fragment` を、検索時に解決できるようにする
- `q` の自然文検索だけでなく、タグ / フォルダ / 台帳名の候補を明示的に使えるようにする
- prompt 側には「まず既存の tag / folder / name を優先して絞る」案内を残す

**短期の prompt 改善**
- `ledger-search` capability に、
  「タグは部分一致検索、フォルダは `folder_id`、台帳名の断片はまず `q` で補う」ことを明記する
- `folder_id` / `ledgerDefineId` が不明なら、まず候補一覧を出して ID を得る lookup-first を明記する
- ただし、これは **UI / LLM の使い方の補助**であり、欠けている検索能力そのものの代替にはしない

### 5.2 Item 2: 同義語検索

**判断:** 実装スプリント対象

**対象ユーザー**
- 現場担当者
- 管理者

**やること**
- MCP 検索経路に `SearchContext` を接続する
- 同義語リストから、検索語を展開・正規化する
- 同義語トグルや展開ルールがある場合は、検索時に明示的に扱う

**期待効果**
- 「請求」「インボイス」「請求書」などの揺れを吸収しやすくなる
- ユーザーが同義語を知らなくても、検索成功率が上がる

### 5.3 Item 3: 複数添付の回答品質

**判断:** 実装スプリント対象

**対象ユーザー**
- 現場担当者
- 承認者 / 監査確認者

**やること**
- 複数添付があるときに、**添付ごとの要約**を返す
- 1 件のレコードに複数ファイルがあることを、回答文と構造化出力の両方で明示する
- 「どの添付がどの論点に効くか」を返す

**期待効果**
- 「ファイルは 1 つしかないように見える」誤認を防ぐ
- 添付の見落としを減らす

### 5.4 Item 4: 添付ごとの詳細出力

**判断:** 別スプリント対象

**対象ユーザー**
- 監査確認者
- 管理者
- 開発 / 検証担当

**やること**
- 添付ごとに JSON / Markdown を返せる option を定義する
- 抽出メタデータ、文字列維持情報、ページ位置、項目名との対応を表現できるようにする
- 既存の `vlm_markdown` / `vlm_structured_data` だけでは足りない情報を追加する

**注意点**
- OCR / VLM の出力品質に依存する
- 実装範囲が広いので、まず最小契約を決めてから進める

## 6. スプリント分割案

### Sprint A: Search narrowing / synonym wiring

**Tracking Issue:** #136

**ゴール**
- Item 1 と Item 2 を、同じ検索改善スプリントとしてまとめる

**主なタスク**
1. `SearchLedgersTool` の入力契約を見直す
2. `SearchContext` / `SynonymService` を MCP から使えるようにする
3. folder / ledger 名の fragment 取り回しを整理する
4. 検索結果の説明文を、実際の挙動に合わせる

**進捗管理の細分化**
- A1: 既存契約の棚卸しと fragment 可否の明文化
- A2: 同義語展開の接続方針と最小実装の確定
- A3: folder / ledger name fragment の解決方針確定
- A4: `ledger-search.yaml` / README / plan doc / route-test scope の同期

**受け入れ基準**
- fragment 条件での検索意図が、LLM から自然に指定できる
- 同義語が検索に反映される
- capability の説明が過大になっていない

#### A1 完了メモ

- `SearchLedgersTool` の契約は `q` / `tags` / `exclude_q` / `exclude_tags` / `folder_id` / `ledger_define_id` / `creator_id` / `created_from` / `created_to` / `mode` / `limit` / `offset` / `include_content` / `order_by` で構成されていることを確認した
- `folder_id` は直接条件として使える一方で、`folder name fragment` / `ledger name fragment` は直接の入力契約ではないことを明文化した
- `SearchContext` / `SynonymService` は存在するが、MCP 検索経路へまだ接続されていないため、A2 以降の実装論点として切り出した
- `ledger-search.yaml` と README の索引は現状に合わせて同期済みであり、A1 では「既存契約の棚卸し」と「不足点の明文化」を完了した

#### A2 完了メモ

- `LedgerService::searchLedgersForApi()` の `q` を同義語展開対象にし、MCP 検索経路へ `SearchContext` を接続した
- `tags` / `folder_id` / `exclude_*` / permission check はそのまま維持し、現場担当者・管理者が自然文で入力する `q` だけを補強した
- `SearchLedgersTool` と `ledger-search.yaml` の説明を、同義語を含む自然文検索に合わせて更新した
- 回帰テストで `請求` → `インボイス` の検索成功を確認し、言い換え・略称に強い検索導線へ前進した

#### A2 再分解案

同義語は量が増える前提のため、A2 は「自動展開を増やす」ではなく、**対話からの候補選択・説明可能性・ペルソナ別のクエリ組み立て** に再分割する。

- **A2-1: 同義語・技術用語候補の分離と選択ポリシー整理**
  - Tracking Issue: #137
  - 同義語は日本語として一般的な同じ意味合いの用語、技術用語は業界内や会社内で使われる用語として扱う
  - 同義語は対話の中で広く候補化し、最終的には大きく刈り込む
  - 業務に慣れている人 / 慣れていない人で候補の優先度を分ける
  - 半角/全角や空白などの正規化は既存検索ロジックで対応済みとして再実装しない

- **A2-2: 検索クエリのトレース可視化と説明可能性の設計**
  - Tracking Issue: #138
  - original q / normalized q / selected terms / excluded terms を追える最小 trace を決める
  - selected terms に同義語 / 技術用語の kind を含める
  - 後続対話で `q` を調節できる粒度にする
  - 開発者向け詳細ログと LLM に返す要約を分離する

A2-2 の最終アウトプットは `docs/work/llm-integration/2026-04-05_Issue-138_Search_Query_Trace_Explainability_Memo.md` とする。

- **A2-3: 対話文脈からの検索クエリ構成ルールとペルソナ案内**
  - Tracking Issue: #139
  - 会話文脈に基づいて、どの同義語を q に採用するかを案内する
  - capability / tool description をペルソナに合わせて補足する
  - 既存の正規化は前提化し、再実装の対象にしない

A2-1 の最終アウトプットは `docs/work/llm-integration/2026-04-05_Issue-137_Synonym_Technical_Term_Selection_Policy_Memo.md` とする。

#### A2-4: 実装接続と実地検証

- Tracking Issue: #140
- `GetSearchTermsTool` を追加し、`LedgerLeapServer` に登録した
- `SearchContext` の候補抽出を一本化し、`setSearch()` が同義語展開の重複で重くならないことを確認した
- `GetSearchTermsToolTest` は tenant DB 初期化を外した純粋な unit test に整理し、`Sanctum::actingAs()` で認証を代替した
- 実地確認では `SearchContext::setSearch('請求')` が短時間で終了し、無限ループではなくテスト準備負荷が原因だったことを切り分けた
- 確認済みテスト:
  - `./vendor/bin/sail pest tests/Unit/Mcp/Tools/GetSearchTermsToolTest.php`
  - `./vendor/bin/sail pest tests/Unit/Mcp/Tools/GetSearchTermsToolTest.php tests/Unit/Services/Ledger/SearchContextTest.php tests/Unit/Mcp/Tools/McpToolNameConventionTest.php`
- 期待どおりだった点:
  - `get-search-terms-tool` の公開名は kebab-case で固定された
  - `search_trace` / `selected_terms` / `kind` の案内と実装が一致した
  - 重い初期化を外した後、`GetSearchTermsToolTest` は 1 秒未満で完了した
- まだ残る制約:
  - `GetSearchTermsToolTest` には legacy PHPCS 警告と `JsonException` の weak warning が残るが、機能上の問題ではない

### Sprint B: Multi-attachment response quality

**ゴール**
- Item 3 を改善する

**主なタスク**
1. 複数添付時の応答テンプレートを定義する
2. 添付ごとの識別子 / ファイル名 / 役割を明示する
3. 1 件のレコードに複数添付があることを、要約文で必ず伝える

**受け入れ基準**
- 添付数が 2 件以上のとき、回答が 1 件前提のように見えない
- LLM が参照すべき添付を区別できる

### Sprint C: Per-attachment extraction contract

**ゴール**
- Item 4 を段階的に実装する

**主なタスク**
1. 出力フォーマット案を決める
2. JSON / Markdown / metadata / position mapping の最小共通スキーマを決める
3. 添付別のダウンロード / 取得 API または MCP 契約を分解する
4. テストデータを整備する

**受け入れ基準**
- 添付ごとの出力を再現できる
- ページ位置や項目名との関係を返せる
- 既存 `vlm_markdown` / `vlm_structured_data` と矛盾しない

## 7. GitHub Issue 方針

### 7.1 起票するべきもの

- **検索改善 issue**: Item 1 + Item 2（Tracking: #136）
- **複数添付の回答改善 issue**: Item 3
- **添付詳細出力 issue**: Item 4

### 7.2 起票を 1 本にまとめるか

1 本にまとめてもよいが、実装コストと影響範囲が大きく異なるため、
**少なくとも 2 本、できれば 3 本に分ける**。

### 7.3 この計画の使い方

- まずこの計画書をレビューする
- 次に issue を作る
- 検索改善だけ先行して、残りを後続スプリントへ回す

## 8. 実施完了の定義

- 検索の断片条件が、現場の記憶ベースの問い合わせに耐える
- 同義語が MCP 検索に反映される
- 複数添付のレコードで、回答が 1 ファイル前提にならない
- 添付ごとの詳細出力契約を、後続で実装できる粒度まで分解できている

## 9. 次アクション

1. この計画書をレビューする
2. 検索改善 / 複数添付改善 / 添付詳細出力の issue を起票する
3. 既存の `ledger-search` capability の文言を、実際の挙動に合わせて整理する
4. 実装優先度の高い Sprint A から着手する

