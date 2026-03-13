# LedgerLeap on-prem / local model onboarding design

**作成日:** 2026年03月13日  
**ドキュメント種別:** 作業ファイル（Sprint 4: onboarding design）  
**関連Issue:** [#83](https://github.com/torinky/LedgerLeap/issues/83), [#87](https://github.com/torinky/LedgerLeap/issues/87), [#89](https://github.com/torinky/LedgerLeap/issues/89)

## 1. 目的

この文書は、LedgerLeap を **on-prem / 閉域ネットワーク / local model** 前提で使い始めるクライアントに対して、
**MCP / REST API / offline docs** のどこに何を持たせるかを整理するための Sprint 4 成果物です。

Sprint 4 では、初回導線の情報設計と責務分担を定義します。
一方で、**bootstrap discovery の最終 contract / request / response schema** は Sprint 6 の対象に残します。

## 2. この文書が決めること / 決めないこと

### 2.1 決めること

- on-prem 前提で外せない onboarding 制約
- local model に合わせた説明量・text budget の基準
- MCP / REST API / offline docs の役割分担
- MCP における prompt / resource / tool の責務分担
- Sprint 5 / Sprint 6 に送る未確定事項

### 2.2 決めないこと

- Update API / Update MCP Tool の公開仕様詳細
- bootstrap discovery の最終 endpoint 名・resource URI・prompt 名・tool schema
- client 環境への保存形式や placement instruction の最終 JSON schema
- MCP server への prompt / resource 実装そのもの

これらは Sprint 5 または Sprint 6 で固定する。

## 3. 現状整理（2026-03-13）

### 3.1 現在の公開契約

- **MCP server**: `tools` は実装済み、`resources` / `prompts` は未登録
- **REST API**: `search`, `ledger-defines`, `ledgers` の公開契約が先行
- **capability manifest**: `ledger-search`, `ledger-create`, `ledger-update(planned)` の 3 件が存在

### 3.2 既知ギャップ

- `workflow-review` / `activity-audit` / `analytics-report` の manifest が未整備
- bootstrap discovery は構想段階で、transport 別の具体 contract は未定
- MCP resource / prompt を使う前提の論理 guide ID はあるが、登録方式は未確定

## 4. on-prem onboarding で固定する前提

### 4.1 ネットワーク / 配布前提

- インターネット接続なしでも完結できること
- 認証情報・業務データを外部 SaaS へ送らないこと
- ベース URL / OpenAPI / MCP 接続先はローカル URL で説明できること
- onboarding 時に参照する主要文書は repo 内の Markdown で参照できること

### 4.2 client-facing 情報の境界

client-facing onboarding で出してよいのは、次の内容だけです。

- 台帳の種類
- 列・入力項目
- 保存先や状態の見え方
- 検索 / 登録 / 承認 / 集計の業務フロー
- 権限不足や入力不足のときに次に確認すべきこと

次は onboarding に出しません。

- DB / テーブル / カラム実装
- Mroonga / Laravel / Livewire などの技術都合
- cast / queue / observer / service class の話
- tenancy / test trap / permission cache などの保守事情

### 4.3 local model 前提

- capability 説明は **1 capability = 1 主目的** を守る
- 一覧 → 詳細の二段階で情報量を調整する
- 最初から全文・全 schema を渡さない
- 実装語彙より業務語彙を優先する

## 5. onboarding 導線の役割分担

## 5.1 全体像

| 層 | 主担当 | 役割 | Sprint 4 時点の扱い |
|---|---|---|---|
| offline docs | 人間の運用担当 / 導入担当 | 初回理解、接続先確認、役割選択、制約共有 | **今すぐ使う入口** |
| MCP | MCP 対応クライアント | 対話的な能力利用、将来の discovery 入口 | **主導線候補** |
| REST API | 非MCP クライアント / 既存統合 | 機械可読 contract、認証、OpenAPI 参照 | **代替導線 / 補完導線** |
| bootstrap discovery contract | server-side capability resolution | role / model / client に応じた最小 bundle 解決 | **Sprint 6 で具体化** |

## 5.2 MCP と REST API の分担

| 観点 | MCP | REST API |
|---|---|---|
| 向いている初回導線 | 対話で「何ができるか」を短く理解させる | 接続設定・認証・固定 schema を確認させる |
| 向いている利用者 | MCP クライアントを使う運用担当 / LLM クライアント | 既存システム統合、HTTP クライアント、API gateway |
| Sprint 4 の結論 | **能力説明の主導線** | **接続契約の主導線** |
| Sprint 6 への持ち越し | discovery carrier の最終選定 | bootstrap endpoint の最終 request/response |

Sprint 4 では、**MCP は「使い始め方の理解」寄り、REST API は「接続契約の確認」寄り** と整理する。

## 6. MCP における prompt / resource / tool の責務分担

| 手段 | 役割 | 向いている内容 | 向いていない内容 |
|---|---|---|---|
| Prompt | 対話の開始テンプレート | 最初の質問例、用途別の短い誘導、ロール別の開始文 | 長い SoT、頻繁に変わる capability 一覧、ユーザー依存の解決結果 |
| Resource | 安定した参照カード | capability card、短い guide、入力項目一覧、一覧→詳細の参照導線 | ユーザー別 bundle 決定、動的権限解決、長大な内部資料 |
| Tool | 動的な解決と実データ取得 | 認証後の実データ取得、role/model/client に応じた解決、将来の bootstrap discovery | 長文の常設説明、毎回同じ静的ガイドの配布 |

### Sprint 4 の判断

- **Prompt** は「最初の問い方」を短く支援する
- **Resource** は「短い正引き資料」を返す
- **Tool** は「認証済み・状況依存の解決」を返す

したがって、**bootstrap discovery の最終実体は Tool 寄り** で考えるのが自然ですが、
**導入時の軽量な参照カードは Resource でもよい**ため、Sprint 6 では `resource / tool` を主比較対象とし、`prompt` は補助導線として扱う。

## 7. offline onboarding の標準フロー

### 7.1 人間向け初回導線

1. 利用者は自分の接続方式を選ぶ
   - MCP client
   - REST API client
2. 利用者は自分の役割を選ぶ
   - 実務担当者
   - 管理者
   - 現場リーダー
3. 利用者はモデル特性を選ぶ
   - `small-local`
   - `general-local`
   - `remote-capable`（参考比較のみ）
4. ローカル URL / 認証方式 / 参照文書を確認する
5. 最小 capability セットを読み、最初の操作へ進む

### 7.2 ペルソナ別の最小セット（Sprint 4 時点）

| ペルソナ | 最初に見せる capability | 備考 |
|---|---|---|
| 実務担当者 | `ledger-search`, `ledger-create`, `workflow-review` | taxonomy では確定済み。manifest は `workflow-review` 未整備 |
| 管理者 | `ledger-search`, `workflow-review`, `activity-audit`, `analytics-report` | manifest 未整備分があり、Sprint 6 前に整合が必要 |
| 現場リーダー | `ledger-search`, `ledger-update`, `workflow-review` | `ledger-update` は manifest 上 `planned` |

### 7.3 実装前の運用ルール

- taxonomy を capability naming の正本とする
- manifest 未整備の capability は、onboarding 文書では説明できても discovery contract の自動解決対象にはまだしない
- Sprint 6 までに taxonomy と manifest の整合を取る

## 8. local model 向け text budget

Sprint 4 では、onboarding で参照させる文字量の目安を次のように置く。

| 資産 | 目安 | ルール |
|---|---|---|
| capability card | 400〜900 文字 | 1目的・必須入力・次アクションを短く |
| guide resource | 600〜1,500 文字 | 一覧 → 詳細で読む前提 |
| bootstrap summary | 300〜800 文字 | role / model / client に応じた最小説明のみ |
| prompt template | 200〜600 文字 | 最初の質問例と確認事項だけ |

### 文面ルール

1. 1段落を短く保つ
2. 箇条書きを優先する
3. 必須入力は 3〜6 項目程度までに抑える
4. 「できること」と「次に聞くこと」を明示する
5. 内部実装の理由説明は出さない

## 9. Sprint 5 / Sprint 6 への引き継ぎ

## 9.1 Sprint 5 に送るもの

- `ledger-update` の公開 workflow 詳細
- 更新前の確認導線（read path / diff / dry run の扱い）
- ワークフロー中レコード更新制限の client-facing 表現

## 9.2 Sprint 6 に送るもの

- bootstrap discovery の最終 carrier 比較
  - MCP `resource`
  - MCP `tool`
  - REST endpoint
- request / response schema
- placement instruction
- capability manifest 整合 (`workflow-review`, `activity-audit`, `analytics-report`)

### Sprint 6 への提案

Sprint 6 では discovery contract の定義だけでなく、
**taxonomy と manifest の差分解消を subtask として明記**した方がよい。
これにより、bundle 解決対象と文書上の capability 一覧のズレを減らせる。

## 10. Sprint 4 の結論

Sprint 4 の結論は次の 5 点です。

1. on-prem onboarding は **offline docs + MCP/API 公開契約** の組み合わせで説明する
2. **MCP は能力理解の主導線、REST API は接続契約の主導線** とする
3. MCP では **Prompt=開始支援 / Resource=短い参照 / Tool=動的解決** と役割分担する
4. local model 向けには短い capability card と list→detail 導線を維持する
5. bootstrap discovery の**最終 contract は Sprint 6**へ送る

これにより、Sprint 4 は「導線設計」と「責務分担」の整理に集中し、
Sprint 5 / 6 で更新契約と discovery contract をそれぞれ具体化しやすくなる。

