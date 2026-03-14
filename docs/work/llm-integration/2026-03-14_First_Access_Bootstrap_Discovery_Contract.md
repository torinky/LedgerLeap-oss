# LedgerLeap first-access bootstrap discovery contract

**作成日:** 2026年03月14日  
**ドキュメント種別:** 作業ファイル（Sprint 6: bootstrap discovery contract）  
**関連Issue:** [#83](https://github.com/torinky/LedgerLeap/issues/83), [#89](https://github.com/torinky/LedgerLeap/issues/89)

## 1. 目的

この文書は、LedgerLeap にクライアントが**初回アクセス**したときに、
**役割・クライアント種別・モデル特性に応じた最小 bootstrap bundle** を返す discovery 契約を具体化するための Sprint 6 成果物です。

Sprint 4 では責務分担までを整理しました。
Sprint 6 では、次を固定します。

- 初期公開契約として何を使うか
- MCP の `resource` / `prompt` / `tool` をどう使い分けるか
- local model 向け text budget / schema budget をどう解釈するか
- client-facing と developer-facing の境界を bootstrap discovery でどう守るか
- 次の実装 Issue にどう分解するか

## 2. Sprint 6 で固定する判断

### 2.1 初期公開契約

Sprint 6 時点での **初期 discovery contract** は REST API とする。

- `GET /api/v1/ai/bootstrap-manifest`
- `POST /api/v1/ai/bootstrap-manifest/resolve`

理由:

1. すでに実装済みで、client_type / role_profile / model_profile に応じた bundle 解決を返せる
2. MCP `resources` / `prompts` が未登録の現状でも、closed network 内で機械可読な contract を提供できる
3. MCP 側の設計比較を続けながら、サーバー側の bundle 解決ロジックを先に固定できる

### 2.2 MCP 側の位置づけ

MCP は Sprint 4 の整理どおり、**能力理解の主導線**として扱う。
ただし Sprint 6 時点では、MCP discovery の**説明責務**と**将来実装の優先順位**までを固定し、REST の代替初期契約にはしない。

## 3. carrier 比較

### 3.1 MCP `resource` / `prompt` / `tool` 比較

| carrier | 候補 | 役割 | 向いている内容 | 向いていない内容 | Sprint 6 の判断 |
|---|---|---|---|---|---|
| Resource | `ledgerleap://bootstrap/{client}` | 初回導線の短い参照カード | client 別の概要、導入順、ガイド参照先 | role / model / client 依存の動的 bundle 解決 | **Issue #92 で実装済み**。静的 bootstrap card として提供 |
| Prompt | `bootstrap-client-skills` | 最初の問い方を支援する補助導線 | 初回の質問例、確認事項、短い開始文 | discovery の主契約、長い SoT、client 別 file 配布 | **補助導線**。主契約にしない |
| Tool | `GetClientBootstrapManifestTool` | 認証後に動的 bundle を返す MCP discovery | role / model / client 依存の最小 bundle 解決 | 静的カードの常設配布 | **MCP parity の本命** |
| Tool | `GenerateClientSkillPackTool` | file export / package 生成 | client 側へ配置する派生ファイルのまとめ出力 | 初回 discovery の主契約 | **後続実装へ分離** |

### 3.2 MCP と REST API の比較

| 観点 | MCP | REST API |
|---|---|---|
| 初回理解 | 対話的に「何ができるか」を短く理解させやすい | request / response を明示的に確認しやすい |
| 接続契約 | prompt / resource / tool の組み合わせで説明 | URL / auth / schema を直接確認できる |
| 現在の実装状況 | discovery 用 `resources` / `prompts` / `tool` は未実装 | bootstrap manifest API は実装済み |
| Sprint 6 の役割 | 候補比較と責務固定 | **初期公開契約として採用** |
| 次実装 | `GetClientBootstrapManifestTool` と `ledgerleap://bootstrap/{client}` の追加 | 実装済み contract の継続改善 |

## 4. 初回アクセス時の理想フロー

1. クライアントは LedgerLeap の接続方式を選ぶ
   - MCP client
   - REST API client
2. クライアントは認証前後で利用できる discovery 導線を確認する
3. クライアントは `client_type` / `role_profile` / `model_profile` / `language` を渡す
4. サーバーは最小 bootstrap bundle を返す
   - 推奨 capability
   - 参照すべき guide/resource
   - 補助 prompt
   - client 側に保存する file 候補
   - placement instruction
5. クライアントは bundle を自分の環境へ保存・有効化する
6. 利用者は最初の業務フローへ進む
   - 例: 検索 / 登録 / 更新 / 承認
7. 以後は通常の MCP / API 利用へ移る

## 5. request / response の最小 contract

### 5.1 入力

- `client_type`
- `language`
- `role_profile`
- `model_profile`

### 5.2 出力

- `recommended_capabilities`
- `resources`
- `prompts`
- `files`
- `placement_instructions`
- `warnings`

### 5.3 placement の扱い

placement は client 側で「どこへ置くべきか」を案内するための補助情報です。
Sprint 6 時点では、**client 別の suggested root / activation note / model-specific notes** までを初期契約に含めます。
一方で、zip 生成・完全な client 別 file export・バイナリ配布は discovery contract の主責務に入れません。

## 6. local model 向け text budget / schema budget

### 6.1 profile 別の解釈

| model_profile | text_budget | schema_budget | Sprint 6 の運用基準 |
|---|---|---|---|
| `small-local` | `compact` | `minimal` | capability は **2〜3 件程度** を優先。1 capability あたり summary + goals + guide 参照を短く返し、長い補足説明は返さない |
| `general-local` | `balanced` | `standard` | role に必要な能力を標準件数で返す。guide は論理参照に留め、本文の埋め込みは避ける |
| `remote-capable` | `expanded` | `standard` | 標準 bundle に加え補助説明を返せるが、初回 discovery では list→detail を維持する |

### 6.2 文面ルール

- 1 capability = 1 主目的
- summary は業務語彙で短く書く
- 最初から guide 本文全文を返さない
- placement instruction は手順を短い箇条書きにする
- warning は「次に何を確認するか」を示し、内部実装名の説明を入れない

### 6.3 schema ルール

- bootstrap response は list→detail を守る
- `recommended_capabilities` には初回判断に必要な最小要約だけを入れる
- guide は URI / logical reference として返し、本文実体は別 carrier で解決する
- file list は client 側配置に必要な相対パスと種別に留める

## 7. client-facing と developer-facing の境界

### 7.1 bootstrap discovery に出してよいもの

- capability 名
- capability の短い要約
- primary user goals
- client 別の保存先案内
- role / model / client に応じた最小 bundle
- guide の**client-safe な logical reference**

### 7.2 bootstrap discovery に出してはいけないもの

- DB テーブル / カラム設計
- Mroonga / Laravel / tenancy / Livewire などの内部事情
- cast / queue / observer / service class の説明
- developer-facing trap 集や保守 runbook への直接誘導

### 7.3 guide ID の原則

`required_guides` は client-facing で見せても意味が通る logical reference だけを返す。
したがって、`constraints` のような developer-facing 名前空間は bootstrap discovery の返却対象に使わない。

## 8. 次の実装 Issue に分解する単位

Sprint 6 の契約定義後、次は少なくとも次の単位に分けて実装できる。

1. **MCP bootstrap resource registration**
   - `ledgerleap://bootstrap/{client}` を static card として返す
   - Tracking Issue: #92
2. **MCP bootstrap prompt starter**
   - `bootstrap-client-skills` を補助導線として追加する
   - Tracking Issue: #93
3. **MCP bootstrap manifest tool parity**
   - `GetClientBootstrapManifestTool` を追加し、REST bootstrap manifest と同じ bundle 解決を返す
   - Tracking Issue: #94
4. **Optional file export / package generation**
   - `GenerateClientSkillPackTool` など、discovery の後段として派生ファイルをまとめる
   - Tracking Issue: #95
5. **UI evaluation plan for local LLM + Continue**
   - VSCode + Continue + ローカルLLM を主対象に、ダミーデータ・シナリオ・期待応答・低能力SaaS比較で UI から検証する
   - Tracking Issue: #96

## 9. Sprint 6 の結論

1. **REST bootstrap manifest API を初期公開契約とする**
2. **MCP は Resource=短い参照 / Prompt=開始支援 / Tool=動的解決** の役割分担を維持する
3. **MCP の本命は `GetClientBootstrapManifestTool`** とし、file export は別 issue に分ける
4. **local model 向けには list→detail と compact schema を守る**
5. **bootstrap discovery は client-facing のみを返し、developer-facing 制約を露出しない**

## 10. 実装反映メモ（2026-03-14）

- Issue #94 により `GetClientBootstrapManifestTool` を実装
- MCP からも `client_type` / `role_profile` / `model_profile` / `language` を入力として、REST bootstrap manifest と同じ bundle 解決を取得可能になった
- 実装は `BootstrapManifestService::resolve()` を再利用し、Sprint 6 で固定した client-facing / developer-facing 境界を維持する
- Issue #92 により static resource template `ledgerleap://bootstrap/{client}` を実装し、concrete URI から client 別の静的 bootstrap card を返せるようになった
- bootstrap prompt starter (`bootstrap-client-skills`) は引き続き別 Issue（#93）の責務とする

この判断により、Issue #89 で求められていた
「ideal first access flow」「MCP / API の役割比較」「local model budget」「client-facing / developer-facing 境界」「次実装 issue への分解粒度」を一通り定義できます。

