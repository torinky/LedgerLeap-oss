# LedgerLeap UI evaluation plan for local LLM + VSCode Continue

**作成日:** 2026年03月14日  
**ドキュメント種別:** 作業ファイル（Issue #83 follow-up: UI evaluation plan）  
**関連Issue:** [#83](https://github.com/torinky/LedgerLeap/issues/83), [#96](https://github.com/torinky/LedgerLeap/issues/96)

## 1. 目的

この文書は、Sprint 1-6 で整理した client-facing contract / bootstrap discovery / local model onboarding を、
**実際のクライアントUI** から評価するための計画書です。

主対象は次の利用形態です。

- **VSCode + Continue + ローカルLLM**
- 比較対象として **低能力な SaaS LLM**

ここでいう UI 評価は、ブラウザ自動テストだけではなく、
**チャットUI 上で利用者が何を見て、どう入力し、どのような応答が返るべきか** を確認する評価を含みます。

## 2. 評価したいこと

### 2.1 primary goal

- bootstrap discovery で返した情報が、Continue などの chat UI から実際に理解・利用できるか
- client-facing capability の説明が、低い推論能力のモデルでも破綻しにくいか
- local model 向けの `list → detail` / `compact schema` / `short guide` が UI 上で過不足なく機能するか

### 2.2 non-goal

- MCP transport / REST transport の性能ベンチマーク
- Browser automation の詳細実装
- 実装内部（DB / Mroonga / Laravel / tenancy）の妥当性確認
- SaaS LLM の高性能モデル最適化

## 3. 対象クライアントと比較軸

| 区分 | 想定クライアント | モデル特性 | 主な確認ポイント |
|---|---|---|---|
| 主対象 | VSCode + Continue | `small-local`, `general-local` | bootstrap 導線、短い guide、曖昧入力時の再質問、list→detail |
| 比較対象 | SaaS LLM（低能力モデル） | `remote-capable` だが低能力寄り運用 | 長文耐性の低さ、誤読、内部事情の露出有無、応答の最小化 |

## 4. 評価前提

### 4.1 評価対象の contract

- `GET /api/v1/ai/bootstrap-manifest`
- `POST /api/v1/ai/bootstrap-manifest/resolve`
- client-facing capability
  - `ledger-search`
  - `ledger-create`
  - `ledger-update`
  - `workflow-review`
  - `activity-audit`
  - `analytics-report`
- bootstrap discovery 後続の案内
  - `resource`
  - `prompt`
  - `tool`
  - `files`
  - `placement_instructions`
- 初期化ゲート案（follow-up 候補）
  - pre-init では bootstrap 系のみ許可
  - post-init で通常 tool を解放
  - manifest / role / client 変更時は re-init 要否を再判定

### 4.2 参照する正本

- `docs/work/llm-integration/2026-03-14_First_Access_Bootstrap_Discovery_Contract.md`
- `docs/work/llm-integration/2026-03-10_Client_Facing_Capability_Taxonomy.md`
- `docs/work/llm-integration/2026-03-13_OnPrem_Local_Model_Onboarding_Design.md`
- `docs/function/PersonaUseCaseScenario.md`
- `docs/development/test-data-design.md`
- `docs/development/demo-credentials.md`

## 5. ダミーデータ計画

### 5.1 基本方針

UI 評価では、すでに存在する demo / integration 用データを最大限再利用し、
必要最小限の追加入力だけで Continue 上の評価ができるようにする。

### 5.2 再利用候補

#### ユーザー
- `superadmin@example.com` — 管理者シナリオの基点
- `demo@example.com` — 実務担当者の簡易シナリオ
- `sales1@example.com` — 実務担当者（営業）
- `inspector1@example.com` — workflow-review 用
- `approver1@example.com` — workflow-review / 承認系用

#### 台帳定義
- `[DEMO] 営業日報`
- `[DEMO] 経費申請`
- `[DEMO] 設備点検表`
- `[DEMO] 週報`

#### 状態分布
- `DRAFT`
- `PENDING_INSPECTION`
- `PENDING_APPROVAL`
- `APPROVED`
- `NONE`

### 5.3 追加したいダミーデータ

UI 評価専用に、以下を後続で追加検討する。

1. **Continue onboarding 用の短い説明レコード**
   - 検索で見つけやすい、client-facing な例文を含む
2. **曖昧検索向けの近似レコード**
   - 似たタイトル・似た顧客名・似たタグを持つ記録
3. **差し戻しコメント付き workflow レコード**
   - low-capability model が「何をすべきか」を読み違えやすいケースを作る
4. **集計と drill-down が両方必要なデータ**
   - 件数だけでなく、次の詳細確認先が必要なケース

## 6. シナリオ計画

### 6.1 bootstrap discovery シナリオ

#### Scenario A: 実務担当者 / Continue / small-local
- 入力:
  - `client_type=copilot` 相当の軽量 client
  - `role_profile=operator`
  - `model_profile=small-local`
- 期待:
  - capability は 2〜3 件程度の短い bundle
  - 最初に `ledger-search` / `ledger-create` / `workflow-review` が提示される
  - guide は logical reference のみ
  - placement は短い箇条書き

#### Scenario B: 管理者 / Continue / general-local
- 入力:
  - `role_profile=administrator`
  - `model_profile=general-local`
- 期待:
  - `activity-audit` / `analytics-report` を含む
  - 「件数確認 → drill-down」の導線が伝わる
  - 内部実装用語を含まない

#### Scenario C: 現場リーダー / low-capability SaaS model
- 入力:
  - `role_profile=field-leader`
  - `model_profile=remote-capable`
- 期待:
  - `ledger-update` / `workflow-review` の関係が短く伝わる
  - 低能力モデルでも「まず対象を確認してから更新」が崩れない
  - 冗長説明なしでも次アクションが取れる

### 6.2 capability 実利用シナリオ

#### Scenario D: 検索 → 詳細確認
- ペルソナ: 実務担当者
- 例: 「昨日の営業日報を見せて」
- 期待:
  - 一覧で候補を返す
  - 必要なら日付や作成者で絞り直す
  - 詳細を見せる前に候補確認を挟む

#### Scenario E: 作成
- ペルソナ: 実務担当者
- 例: 「今日の営業日報を作りたい」
- 期待:
  - 台帳定義の確認
  - 必須項目の案内
  - 保存後の状態説明

#### Scenario F: 更新
- ペルソナ: 現場リーダー
- 例: 「さっきの議事録の開催場所だけ会議室Bに直して」
- 期待:
  - 対象確認
  - 編集可否確認
  - 一部項目だけ更新
  - 状態変化の説明

#### Scenario G: 承認 / 差し戻し
- ペルソナ: 承認者
- 例: 「今日の未処理承認を見せて」
- 期待:
  - タスク一覧
  - 優先度順または期限順の整理
  - 承認 / 差し戻し後の状態説明

#### Scenario H: 監査 / 集計
- ペルソナ: 管理者
- 例: 「今月の承認待ち件数と滞留案件を知りたい」
- 期待:
  - 集計値の提示
  - drill-down すべき対象の案内
  - 詳細確認先への橋渡し

#### Scenario I: pre-init gate / post-init unlock
- ペルソナ: 任意
- 例: 初回接続直後に通常検索を要求する
- 期待:
  - pre-init では bootstrap manifest / bootstrap card / starter prompt へ誘導される
  - 通常 tool の詳細な process guidance を返す代わりに、初期設定完了を短く案内する
  - 初期化完了後は通常の capability 導線へ進める

#### Scenario J: re-init required after bundle change
- ペルソナ: 管理者または現場リーダー
- 例: role_profile または required capability が変わった後に既存 tool を呼ぶ
- 期待:
  - 再初期化が必要な理由を client-facing に短く説明する
  - 変更後 bundle の取得手順へ戻せる
  - developer-facing な内部用語を出さない

## 7. 期待する応答の設計

### 7.1 共通原則

- **短く返す**
- **候補を列挙してから詳細へ進む**
- **次に何を確認すべきかを明示する**
- **DB / Laravel / Mroonga などを出さない**

### 7.2 low-capability model 向け期待値

低能力モデルでは、次を最低ラインとする。

1. capability 名を取り違えない
2. role に無関係な能力を大量に返さない
3. 曖昧な入力では勝手に確定せず、短く再質問する
4. 「検索 → 確認 → 更新」の順序を壊さない
5. status 変化は 1 文で説明する

### 7.3 期待応答フォーマット例

#### bootstrap discovery
- 期待フォーマット:
  - 推奨能力 2〜4 件
  - 各能力の一行説明
  - 次の質問例 1〜2 件
  - guide 参照 1〜3 件

#### 検索
- 期待フォーマット:
  - 候補一覧
  - 絞り込み提案
  - 詳細を開く候補ID

#### 更新
- 期待フォーマット:
  - 対象確認
  - 変更箇所要約
  - 状態変化の有無

## 8. 評価観点

### 8.1 内容品質
- capability の選定が role と一致しているか
- client-facing 概念だけで応答できているか
- 不要に長文化していないか

### 8.2 UI 運用性
- Continue の chat UI で一読して次アクションが分かるか
- コピペや再質問が最小で済むか
- 低能力モデルでも「候補 → 確認 → 実行」が崩れないか
- pre-init / post-init の切り替えが UI 上で理解しやすいか
- re-init が必要になったときに迷わず bootstrap 導線へ戻れるか

### 8.3 比較評価
- `small-local` と `general-local` で bundle の粒度差が適切か
- low-capability SaaS model で誤読しやすい記述がないか
- local model 向け compact schema が有効か

## 9. テスト実施方法（計画）

### 9.1 手動評価
- Continue chat に bootstrap discovery 相当の入力を与える
- 返答を記録し、シナリオごとの期待値と比較する
- 失敗パターンを分類する

### 9.2 比較記録
- モデル種別
- 入力文
- 返答全文
- 期待との一致/不一致
- 誤読・冗長・内部事情露出の有無

### 9.3 将来の拡張
- prompt fixture 化
- expected response snapshot 化
- demo data の固定 ID / 固定検索語の整備
- Playwright 等による UI 補助自動化

### 9.4 着手順（推奨実施順）

Issue #96 の着手時は、次の順序で確認すると bundle 差分と UI 上の失敗要因を切り分けやすい。

1. **bootstrap discovery の bundle 固定**
   - Scenario A / B / C の順で、role・model ごとの推奨 capability と guide 参照先を記録する
   - ここでは「出し過ぎていないか」「内部事情が混ざっていないか」を優先確認する
2. **一覧 → 詳細 の導線確認**
   - Scenario D（検索 → 詳細確認）を先に実施し、Continue の chat UI で候補提示と絞り込み提案が短く伝わるかを見る
3. **実行系シナリオの確認**
   - Scenario E（作成）→ F（更新）→ G（承認 / 差し戻し）の順で、対象確認と状態変化説明が崩れないかを確認する
4. **管理系シナリオの確認**
   - Scenario H（監査 / 集計）を最後に実施し、件数確認から drill-down 先の案内までを記録する
5. **比較対象モデルで再実施**
   - 低能力 SaaS LLM では Scenario A / D / F / H の代表ケースを再実施し、冗長化・誤読・順序崩れを比較する
6. **#97 への引き渡し候補整理**
   - 「ユーザーはやりたいが、現行 contract では安全に扱えない」シナリオを抽出し、後述の引き渡しテンプレートで記録する

### 9.5 Continue 手動評価テンプレート

各評価ケースは、最低限次の形式で記録する。

| 項目 | 記録内容 |
|---|---|
| 実施日 | 例: `2026-03-14` |
| evaluator | 実施者名またはイニシャル |
| client | `VSCode + Continue` / 比較対象 SaaS chat |
| role_profile | `operator` / `administrator` / `field-leader` |
| model_profile | `small-local` / `general-local` / 低能力 SaaS |
| scenario | `A`〜`H` |
| 対象 capability | `ledger-search` など 1〜2 件 |
| 入力文 | Continue の chat UI にそのまま貼る文面 |
| 期待する最低ライン | 候補提示 / 再質問 / 状態説明 / drill-down 案内 など |
| 実際の応答 | 応答全文または要点 |
| 判定 | `pass` / `needs-fix` / `handoff-to-#97` |
| メモ | 冗長、誤読、内部事情露出、未 API / MCP 化シナリオなど |

記録例:

```markdown
- 実施日: 2026-03-14
- evaluator: kk
- client: VSCode + Continue
- role_profile: operator
- model_profile: small-local
- scenario: D
- 対象 capability: ledger-search
- 入力文: 昨日私が作成した営業日報を見せて
- 期待する最低ライン: 候補一覧を返し、必要なら日付や作成者での絞り込みを提案し、詳細候補の確認を挟む
- 実際の応答: 3件の候補を箇条書きで提示。詳細表示前に「どれを開くか」を確認した
- 判定: pass
- メモ: 応答は短い。内部実装用語なし
```

### 9.6 #97 への引き渡しテンプレート

UI 評価で未 API / MCP 化シナリオや contract の粒度不足を見つけた場合は、実装要求に直結させず、次の形式で記録して #97 に渡す。

| 項目 | 記録内容 |
|---|---|
| 発見元 scenario | `A`〜`H` |
| ペルソナ | 実務担当者 / 管理者 / 現場リーダー |
| やりたいこと | ユーザーが UI 上で達成したい業務目的 |
| 現行 contract で不足している点 | capability / guide / prompt / tool / endpoint のどこが不足か |
| 現時点で無理に実装要求へしない理由 | 安全性、導線順序、client-facing 境界、粒度不足など |
| 暫定回避策 | 手動での確認手順、別 capability での代替など |
| #97 での論点 | capability 拡張 / 新規 capability / 非対象 のどれを検討したいか |

記録例:

```markdown
- 発見元 scenario: H
- ペルソナ: 管理者
- やりたいこと: 滞留案件一覧から、関連案件をまとめて見つけたい
- 現行 contract で不足している点: analytics-report から related cases を直接辿る client-facing contract がない
- 現時点で無理に実装要求へしない理由: まずは一覧 → 詳細 → 実行の順序を守れる粒度に分解する必要がある
- 暫定回避策: 集計で滞留件数を確認し、ledger-search で条件を絞り直して個別レコードへ進む
- #97 での論点: 既存 capability 拡張か、新規 capability 候補かを判定する
```

### 9.7 軽量自動化へ分離しやすい単位

将来の軽量自動化では、次の単位に分離すると保守しやすい。

1. **bootstrap manifest fixture**
   - role / model ごとの推奨 capability 件数、guide 参照数、内部事情非露出を比較する
2. **starter prompt fixture**
   - 最初の質問例が role に合っているか、冗長でないかを比較する
3. **scenario prompt set**
   - Scenario D / F / H の代表入力を固定し、期待する最小応答原則を snapshot 化する
4. **gap intake log**
   - `handoff-to-#97` 判定になったケースだけを別シート化し、後続 triage の入力にする

## 10. 後続 Issue でやること

1. Scenario A / B / C の bootstrap discovery 記録を採取し、role / model ごとの bundle 差分を固定する
2. Scenario D / E / F / G / H を上記テンプレートで記録し、一覧 → 詳細 → 実行の順序が守られるか確認する
3. UI 評価用ダミーデータの追加要否を判定し、必要なら別 issue へ分離する
4. 低能力 SaaS LLM 比較で `needs-fix` / `handoff-to-#97` ケースを抽出する
5. 必要なら prompt fixture / snapshot / gap intake log 単位で軽量自動化へ分離する

## 11. この計画の位置づけ

この文書は、**contract を定義した後に、実際の UI 利用でそれが通用するかを検証するための計画**です。

したがって、
- `#89` の contract definition
- `#92`〜`#95` の MCP 実装/配布

とは別に、**利用体験と期待応答の観点から確認する横断計画** として扱います。

