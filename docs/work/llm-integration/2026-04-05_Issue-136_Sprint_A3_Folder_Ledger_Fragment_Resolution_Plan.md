# Issue #136 Sprint A3: Lookup-First Tag / Folder / Ledger Partial Match Search Plan

**作成日:** 2026年04月05日  
**種別:** 作業計画（Sprint A3 分解案）  
**親Issue:** #136  
**参照:** `docs/work/llm-integration/2026-04-05_MCP_Search_Attachment_Feedback_Followup_Plan.md`, `resources/ai/capabilities/ledger-search.yaml`, `app/Mcp/Tools/SearchLedgersTool.php`, `app/Services/LedgerService.php`, `app/Services/Ledger/SearchContext.php`

## 1. 目的

Sprint A3 では、`#136` の残課題である **MCP ツールでのタグ / フォルダ名 / 台帳名の部分一致検索** を、既存の `q` / `tags` / `folder_id` 契約を壊さない範囲で整理する。

フォルダや台帳定義には一覧出力機能があってよいが、運用時はそれぞれ 1000 件を超える前提であるため、**一覧閲覧よりも部分検索を第一優先** とする。

現時点の確認結果は次のとおり。

- `tags` は、少なくとも「断片から候補を絞る」導線が必須
- `folder_id` は既に直接条件として使える
- `folder name fragment` をそのまま受ける既存契約はない
- `ledger name fragment` は、現状では `q` か候補解決の導線に寄せる必要がある
- `SearchContext` / `SynonymService` / `GetSearchTermsTool` は既にあるため、A3 では **fragment resolution の責務境界** と **部分一致の優先順** を明確化することが中心になる
- フォルダ / 台帳定義については、一覧出力は補助導線として残してよいが、実運用の主導線は **部分検索 → 候補確認 → ID 取得** である

## 2. 先に結論

A3 は「fragment を何でも直接受ける」方向ではなく、**検索意図ごとに最小の解決経路を用意する** 方向で進める。

1. **folder_id / ledgerDefineId を知らない前提** では、まず候補を検索して ID を得る lookup-first 導線が必須
2. フォルダ / 台帳定義の一覧出力は存在してよいが、1000 件超を前提にすると一覧は補助であり、**部分検索が第一優先** である
3. **tags の部分一致** は、MCP ツール利用時の最初の手がかりとして必須
4. **folder fragment** は、一覧から探すのではなく、まず部分検索で候補を絞って `folder_id` に解決する
5. **ledger fragment** は、一覧からの網羅閲覧に寄せず、`q` / 候補解決 / 既存 search API の組み合わせで吸収できるかを先に判断する

そのうえで、必要なら **新しい resolver を作るか、既存の検索導線に委ねるか** を決める。

## 2.5. 基本シナリオ / ペルソナ補強

部分一致検索が必須になるのは、「正式名を覚えていないが、断片だけ思い出せる」場面である。A3 ではこの前提を、次の基本シナリオで補強する。

また、`folder_id` / `ledgerDefineId` を知らない前提では、**先に folder / ledger definition の候補を検索して ID を得る** 必要がある。A3 ではこの lookup-first を、部分一致検索と同じ必須導線として扱う。

加えて、候補数が 1000 件を超える運用では、一覧を順に眺めるのではなく、**断片で絞ってから一覧で最終確認する** 形を基本にする。

### 2.5.1 実務担当者

- 例1: 「請求」「要確認」「未処理」のようなタグ断片で、今すぐ確認すべき記録を探したい
- 例2: フォルダ名の一部しか覚えていないが、部署名や案件名の断片から候補を絞りたい
- 例3: 台帳名をうろ覚えでも、`q` の断片から候補を見て詳細に進みたい

**大事なこと**
- 正式名称を覚えていなくても検索を再開できること
- まず候補一覧を見て、そこから詳細に進めること
- 部分一致の対象が tags / folder / ledger のどれかを意識せずに始められること

### 2.5.2 管理者

- 例1: 監査ラベルや運用タグの断片から、対象レコードを横断的に探したい
- 例2: 部門フォルダ名の一部だけで、権限範囲内の候補を見たい
- 例3: 台帳名の一部を手がかりに、担当者・保存先・更新状況をまとめて確認したい

**大事なこと**
- 権限外の候補を出さないこと
- 候補が複数あるときに、次の絞り込みができること
- 監査・問い合わせの説明に使えること

### 2.5.3 現場リーダー / 作業班長

- 例1: チームで共有したタグの断片から、作業中の記録をすぐ見つけたい
- 例2: 現場フォルダの一部しか覚えていなくても、共有資料の置き場所に辿り着きたい
- 例3: 台帳名の断片で検索し、必要なら関連レコードへ横断したい

**大事なこと**
- 代理確認・共有・再提出の導線にすぐ乗れること
- 複数候補でも、チーム内で説明しやすいこと
- 関連レコードへの横断が途切れないこと

## 3. A3 の分解案（must-have 順）

### A3-0. folder / ledger definition lookup tools を決める

**目的**
- `folder_id` / `ledgerDefineId` を知らない状態からでも、候補一覧を出して ID を取得できる導線を作る
- ただし、候補一覧は大規模運用の主導線ではなく、**部分検索で絞り込んだ候補表示** として扱う
- lookup-first の後に、取得した ID を使って `SearchLedgersTool` を組み立てられるようにする

**対象候補**
- folder 名の断片検索から候補 ID を返す tool
- ledger definition 名の断片検索から候補 ID を返す tool
- 既存の `GetLedgerDefinesTool` を拡張するか、lookup 専用 tool を新設するかの判断

**確認項目**
- folder の候補を accessible / manageable scope に限定できるか
- ledger definition の候補を folder 権限と整合させられるか
- 候補一覧が 1000 件超の前提でも破綻しないよう、部分検索を先に通す設計にできるか
- 候補一覧の出し方が、後続の `SearchLedgersTool` へ渡せるか

**完了条件**
- `folder_id` / `ledgerDefineId` を知らなくても、候補一覧から ID を得る方法が決まる
- search 実行の前に lookup を挟む順序が明文化される
- 既存の search contract を壊さない

### A3-1. fragment 解決の責務境界を決める

**目的**
- どこまでを `SearchLedgersTool` の入力契約で受けるかを明確化する
- `q` / `tags` / `folder_id` / `ledger_define_id` の既存契約を壊さない
- `ledger-search.yaml` に書くべき範囲と、書かない方がよい範囲を分ける

**確認項目**
- tags の部分一致を `SearchLedgersTool` で直接受けるべきか
- folder / ledger 名の fragment を、検索契約に直接追加するべきか
- fragment を候補解決に寄せる場合、どの層で解決するか
- MCP と REST で責務を分けるか、共通化するか

**完了条件**
- tags / folder / ledger fragment の扱いを説明できる
- 実装対象と非対象が issue 内で明文化される
- capability の説明が過大にならない

### A3-2. tags の部分一致検索を定義する

**目的**
- tag 名を完全一致前提にせず、断片から候補を絞る導線を明確にする

**確認項目**
- 部分一致の対象は prefix / contains / token 単位のどれにするか
- 複数タグの条件指定をどう扱うか
- 一覧結果に「候補に入った理由」を必要とするか

**完了条件**
- tags の部分一致仕様が決まる
- `SearchLedgersTool` の `tags` 契約と衝突しない
- 必要なら検索 API 側と MCP 側の役割分担が決まる

### A3-3. folder name fragment の解決方針を決める

**目的**
- `folder name fragment` から `folder_id` へ寄せる導線を作るか判断する
- フォルダの一覧出力は補助とし、運用では部分検索を先に通す

**検討候補**
- 既存の folder 一覧 / 権限情報に加え、部分検索で候補を先に絞る
- `q` で folder 名を補助検索し、候補が一意なら `folder_id` に変換する
- fragment を直接受けず、候補提示のみを行う補助導線にする

**確認項目**
- 多数候補がある場合の UI / MCP 挙動
- 権限のない folder を候補に出さないか
- `folder_id` への変換を search API に寄せるか、別 resolver に切り出すか

**完了条件**
- folder fragment の扱いが決まる
- `SearchLedgersTool` の責務を壊さない
- 必要なら後続 issue に分割できる

### A3-4. ledger name fragment の解決方針を決める

**目的**
- `ledger name fragment` をどう扱うかを決める
- 台帳定義も一覧出力は補助に留め、運用では部分検索を先に通す

**検討候補**
- `q` に寄せる
- 候補解決ツールを経由する
- `SearchLedgersTool` の契約ではなく、検索導線の案内で吸収する

**確認項目**
- 台帳名 fragment を直接入力として受ける必要があるか
- 既存の全文検索・意味検索と重複しないか
- 同義語 / 技術用語の候補解決と混同しないか

**完了条件**
- ledger fragment は `q` に寄せるか、別導線にするかが明確になる
- capability の文言を過不足なく直せる

### A3-5. 曖昧性処理 / 文書とテスト観点の同期

**目的**
- 実装前に、曖昧性処理と文書・テスト観点の不足を先に揃える
- 一覧出力と部分検索の優先順位を、ドキュメントとテストで同じ表現に揃える

**やること**
- 断片が複数候補にまたがる場合の優先順位を決める
- `ledger-search.yaml` の案内に fragment について書く / 書かないを決める
- `docs/work/llm-integration/README.md` の索引に A3 文書を載せる
- 必要なら `SearchLedgersTool` / `SearchContext` / `LedgerService` の回帰観点を追記する

**完了条件**
- どのテストで A3 を守るかが見える
- 実装に入る前の確認ポイントが揃う

## 4. 受け入れ基準

- tags / folder / ledger fragment の扱いを、`SearchLedgersTool` と `ledger-search.yaml` の責務として説明できる
- `q` / `tags` / `folder_id` の既存挙動を壊さない
- フォルダ / 台帳定義は一覧出力を持てるが、運用上は 1000 件超を前提に部分検索が第一優先であると明記されている
- どの fragment が直接対応で、どの fragment が候補解決なのかが明確になる
- 実装に進む場合、後続の issue 分割がしやすい粒度になっている

## 5. 非ゴール

- 新しい fragment 用検索契約の全面追加
- `SearchContext` の再実装
- `SearchLedgersTool` の全面改修
- すべての fragment を 1 つの tool で解決する設計
- 一覧出力を主導線にして、大規模運用で候補を順番に探す設計

## 6. 次の判断ポイント

この文書を読んで、次のどちらに進むかを判断する。

1. **実装に進む**
   - folder fragment の解決を先に作る
   - ledger fragment は `q` / 候補解決で吸収する
2. **さらに分割する**
   - folder fragment と ledger fragment を別 issue に分ける
   - search API 側と MCP 側を別々に整理する

## 7. 実施結果 / 完了メモ

A3 の lookup-first 導線は、以下の形で実装と検証を完了した。

### 実施内容

- `SearchLedgersTool` の説明と schema を lookup-first 方針に合わせて調整した
- `LedgerService` で `folder_id` / `ledger_define_id` の配列受け取りと検索条件伝播を整理した
- `GetTagsTool` を追加し、`GetFoldersTool` / `GetLedgerDefinesTool` と合わせて候補解決の入口を揃えた
- `LedgerLeapServer` に lookup tool を登録し、MCP から呼び出せるようにした

### 検証結果

- `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php`
- `tests/Unit/Mcp/Tools/GetTagsToolTest.php`
- `tests/Unit/Mcp/Tools/GetFoldersToolTest.php`
- `tests/Unit/Mcp/Tools/GetLedgerDefinesToolTest.php`

上記 4 本は、単独実行でいずれも完走を確認した。

### まとめ

- A3 の lookup-first 導線は実装済み
- 断片入力は lookup tool で候補化してから `SearchLedgersTool` に渡す方針を明文化済み
- A3 の回帰観点は、lookup tools / search tool / capability / work doc に反映済み


