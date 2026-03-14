# LedgerLeap クライアント接続モデル再計画（MCP / API First）

**作成日:** 2026年03月09日  
**最終更新日:** 2026年03月14日
**ドキュメント種別:** 作業ファイル（再計画・スプリント設計）  
**ステータス:** Sprint 1-6 完了（MCP parity 実装は後続 Issue へ分離）
**関連Issue:** [#83](https://github.com/torinky/LedgerLeap/issues/83) （本再計画の親Issue・進捗管理先）

## 0. 今回の再計画で確定した前提

この文書は、同日作成した初期案を**全面的に見直した再計画版**である。今回の見直しでは、以下を LedgerLeap の前提条件として固定する。

1. **クライアントは MCP または REST API を通じてのみ LedgerLeap に接続する**  
   CLI でファイルを生成して配る方式は補助的な検証手段に留め、主要なオンボーディング導線にはしない。
2. **クライアントが知るべきなのは WebUI で観測できる台帳構造だけ**  
   DB テーブル構造、Mroonga の制約、内部キャストや実装都合は client-facing 資産から隠蔽する。
3. **説明対象を分離する**  
   - client-facing: LLM クライアントや利用者に見せる説明
   - developer-facing: LedgerLeap 開発者が保守・実装に使う説明
4. **オンプレ運用を前提にする**  
   インターネット接続なし、ローカル LLM 利用、閉域ネットワーク、認証情報の外部送信不可を前提に設計する。
5. **ローカルモデルを考慮する**  
   小さなモデルでも破綻しにくい、短く明瞭な schema / prompt / resource を優先する。

---

## 1. エグゼクティブサマリー

これまでの計画は「LedgerLeap の能力を各クライアントにどう配るか」を広く捉えられていた一方で、次の混線があった。

- `CLI bootstrap` が主役のように見える
- client-facing と developer-facing が混在する
- client が知らなくてよい内部事情（DB / Mroonga / tenancy など）が表に出ている
- 生成物の単位が「クライアント別ファイル」に寄りすぎていて、**MCP / API 契約そのものの設計** が相対的に弱い

今回の再計画では、主軸を次のように置き直す。

### 新しい主軸
- **第一階層:** MCP / API 契約の整備
- **第二階層:** client-facing の業務スキル定義（検索 / 登録 / 更新 / 承認など）
- **第三階層:** developer-facing の保守用資産（内部制約、同期、生成、運用）
- **第四階層:** 必要ならクライアント別テンプレート生成

つまり、これから優先すべきは `generator` ではなく、**クライアントが MCP / API 越しに自然に理解できる公開契約と説明レイヤー** である。

---

## 2. 現状評価（何が良く、何を直すべきか）

## 2.1 良かった点

現行の計画には次の強みがある。

- MCP を中心に検索・登録・ワークフロー・統計まで接続可能な基盤がある
- `.github` を正本とする AI 資産運用の方向性は妥当
- 「検索 / 登録 / 更新」を軸に再編する発想は正しい
- 将来の複数クライアント対応を見据えている

## 2.2 修正すべき点

### A. CLI bootstrap を主計画に据えない
クライアントは LedgerLeap に直接ファイルを置きに来ない。主要導線はあくまで **MCP の `tools / prompts / resources` または REST API** である。よって、CLI generator は次の位置づけに落とす。

- **主要導線ではない**
- 開発者向けの補助ツール
- 実運用の SoT ではない

### B. client-facing に内部事情を出さない
client-facing の説明は次に限定する。

- どんな台帳があるか
- どんな列が見えるか
- 検索 / 登録 / 更新で何を要求されるか
- 結果として何が返るか
- 権限不足や確認不足のとき何が起こるか

逆に、次は client-facing に出さない。

- Mroonga の単一カラム制約
- DB スキーマ構造
- cast 実装
- tenancy 初期化ルール
- Laravel / Livewire 内部都合

### C. スキル説明の主語を揃える
今後のスキルは「SearchLedgersTool の説明」でも「LedgerLeap 開発者向け設計メモ」でもなく、**LedgerLeap を MCP/API 経由で使うクライアントの行動単位** で記述する。

例:
- `ledger-search`
- `ledger-create`
- `ledger-update`
- `workflow-review`

---

## 3. 対象読者の分離

## 3.1 client-facing 資産

### 読者
- MCP クライアント
- API クライアント
- クライアント上で動く LLM
- それらを設定する運用担当者

### 目的
- LedgerLeap の公開能力を理解する
- WebUI で見える概念だけで操作できる
- 閉域・オンプレ環境でも動かせる

### 記述してよい内容
- 台帳定義
- 列名・入力型・必須情報
- 検索の使い分け
- 更新・登録の要求項目
- 承認などの業務ステップ
- エラー時の振る舞い

## 3.2 developer-facing 資産

### 読者
- LedgerLeap 開発者
- MCP / API 実装者
- 保守担当者

### 目的
- client-facing 契約をどう保守するかを理解する
- 内部制約と公開契約の分離を守る
- 同期・生成・移行・テストを安全に行う

### 記述してよい内容
- DB / 検索エンジン都合
- Mroonga 制約
- tenancy / permission cache の罠
- `.github` SSOT
- generator / 同期スクリプト

## 3.3 ペルソナと代表ユーザーシナリオ

client-facing capability は、既存の `docs/function/PersonaUseCaseScenario.md` を基準に次の代表ペルソナへ対応付ける。

また、`docs/work/llm-integration/2025-09-27_MCP_Prompt_and_Response_Design.md` は、**ペルソナ別の対話例・ユーザーシナリオの参考資料**として扱う。
ただしこれは current SoT ではなく、**client-facing capability の具体例母集団**として再読し、内部事情が混ざる箇所はそのまま採用しない。

### 実務担当者 (Operational Staff)
- 主要ニーズ: 迷わず入力、すばやい検索、承認待ちタスクの処理
- 初期 skill 候補:
  - `ledger-search`
  - `ledger-create`
  - `workflow-review`
- 代表シナリオ:
  - 日報の作成と提出
  - 過去記録の検索と参照
  - 自分に割り当てられた承認・点検タスクの処理

### 管理者 (Administrator / Manager)
- 主要ニーズ: 台帳定義管理、権限管理、活動監査、運用把握
- 初期 skill 候補:
  - `ledger-search`
  - `workflow-review`
  - `activity-audit`
  - `analytics-report`
- 代表シナリオ:
  - 台帳定義の確認と公開
  - 活動状況の監査
  - 状況集計と運用介入

### 現場リーダー / 作業班長 (Team Leader / Foreman)
- 主要ニーズ: チーム代理入力、代理更新、情報共有、チーム状況把握
- 初期 skill 候補:
  - `ledger-search`
  - `ledger-update`
  - `workflow-review`
- 代表シナリオ:
  - メンバー代理での台帳更新
  - 添付資料付きの情報共有と確認

### 開発者 (Developer)
- 主要ニーズ: 内部制約、保守、実装、テスト
- 扱う資産: **developer-facing のみ**
- 注意: 開発者ペルソナは client-facing skill の対象ではなく、運用・保守設計の対象とする

### ペルソナ適用原則
1. 初回オンボーディングで提示する skill は、**接続クライアントの利用目的** と **ユーザーの役割** に応じて絞る
2. すべての skill を最初から配布しない
3. client-facing の説明はシナリオ起点で書き、内部ツール起点で書かない

### 3.4 ユーザーシナリオ要件の抽出観点

`PersonaUseCaseScenario.md` と `2025-09-27_MCP_Prompt_and_Response_Design.md` を読むときは、少なくとも次の観点で抽出漏れがないかを確認する。

- **一覧 → 詳細の二段階導線**: まず候補を短く示し、必要時だけ詳細へ進む流れ
- **緊急度・期限・優先順位**: 承認待ちや未処理タスクで優先度判断を助ける情報
- **版比較 / 差分確認**: 最新版と前版の比較、変更点要約のニーズ
- **添付ファイル込みの内容把握**: 請求書・契約書など、本文だけでなく添付内容も含めた確認ニーズ
- **監査・集計・ルール確認**: 不審操作、未記載チェック、種類別集計など管理者向けの確認導線

特に `2025-09-27_MCP_Prompt_and_Response_Design.md` のうち、**実務担当者 / 管理者の対話例は client-facing capability 抽出の参考**とし、**開発者セクションは developer-facing 側へ再分類**する。

---

## 4. 再定義するアーキテクチャ

## 4.1 第一階層: 公開契約（最優先）

クライアントが接する唯一の契約は次の 2 種である。

- **MCP server contract**
  - tools
  - prompts
  - resources
- **REST API contract**
  - search / ledger-defines / ledgers / update 系

今後の LLM 連携整備は、この公開契約を最上位の SoT として扱う。

## 4.2 第二階層: client-facing capability / skill

公開契約の上に、業務能力として次を整理する。

- 検索
- 登録
- 更新
- 承認 / 点検
- 統計 / レポート
- 版比較 / 差分確認
- 添付資料を含む内容確認
- 監査 / ルール確認

この層では「どのツールをどう順番に使うと失敗しにくいか」を、**内部事情を伏せた短い workflow** として提供する。

補助根拠として、`2025-09-27_MCP_Prompt_and_Response_Design.md` にある一覧提示・承認タスク優先度・契約書差分・請求書確認・監査・集計の対話例を、skill 命名と workflow 粒度の確認材料に使う。

## 4.3 第三階層: developer-facing maintenance assets

ここで初めて、内部事情を扱う。

- 検索実装上の制約
- テストトラップ
- スキル同期方針
- 将来 generator をどう保守するか

## 4.4 第四階層: 補助的 generator / template

クライアント別ファイル生成は、次の条件を満たした後に扱う。

- MCP / API 契約が整っている
- client-facing capability が整理済み
- developer-facing SoT が固定されている

したがって `php artisan ai:bootstrap-client-skills` は、今後は **主計画ではなく補助的実験実装** と位置づける。

---

## 5. client-facing で見せるべき世界観

LedgerLeap を使うクライアントに見せるのは、**WebUI で見える業務概念**だけでよい。

### 見せる概念
- 台帳定義（種類）
- 各台帳の列
- 各列の型
- フォルダ / 保存先
- タグ
- 更新日時 / 作成者 / 状態
- ワークフロー上の状態

### 見せない概念
- テーブル / カラム実装
- 全文検索エンジンの都合
- observer / queue / cast 実装
- テナント初期化のテスト事情

### client-facing skill の原則
1. **WebUI で説明できる言葉だけを使う**
2. **検索・登録・更新は業務行動として説明する**
3. **内部最適化は server 側の責務として隠す**
4. **小さなローカルモデルでも理解できる短さに保つ**

---

## 6. オンプレ / ローカル LLM 前提の追加要件

## 6.1 ネットワーク前提

- インターネット接続がない環境でも利用できること
- 外部 SaaS 依存を必須にしないこと
- ドキュメントと設定はローカル配布可能であること

## 6.2 モデル前提

- 小型ローカルモデルでも解釈できる schema / prompt にする
- 長文の tool description に頼らない
- prompts と resources を分けて、必要なときだけ読ませる
- JSON schema / 列構造は簡潔にする

## 6.3 運用前提

- 認証情報はオンプレ内で閉じる
- OpenAPI / MCP discovery はローカル URL で完結する
- docs もローカルの repo 内で参照できるようにする

## 6.4 初回アクセス時オンボーディング / Skill Bootstrap 構想

クライアントが初めて LedgerLeap に接続したとき、クライアント側の環境に skill を生成・配置しやすくするため、**サーバーが bootstrap 情報を返す discovery 契約** を用意する。

### 目標
- クライアントは初回接続時に「どの skill / prompt / resource を導入すべきか」を判断できる
- サーバーはクライアントの種類・言語・利用ロールに応じた最小構成を返せる
- on-prem / local model 前提で、外部サービスに依存せず完結する

### 推奨する二段構成

#### 方式A: MCP discovery 経由
MCP クライアント向けに、次のいずれかを用意する。

- **MCP Resource**: `ledgerleap://bootstrap/{client}`
- **MCP Prompt**: `bootstrap-client-skills`
- **MCP Tool**: `GetClientBootstrapManifestTool` または `GenerateClientSkillPackTool`

**返す内容のイメージ:**
- client-facing capability 一覧
- 推奨 skill セット
- クライアント側に保存すべきファイル定義
- ロール別の初期構成
- local model 向けの短い system prompt / usage notes

#### 方式B: REST discovery API 経由
API クライアント向けに、次のような endpoint を検討する。

- `GET /api/v1/ai/bootstrap-manifest`
- `POST /api/v1/ai/bootstrap-manifest/resolve`

**リクエスト候補:**
- `client_type`
- `language`
- `role_profile`
- `model_profile` (`small-local`, `general-local`, `remote-capable` など)

**レスポンス候補:**
- `recommended_capabilities`
- `files`
- `resources`
- `prompts`
- `placement_instructions`

### 初回アクセス時の理想フロー
1. クライアントが MCP または API で LedgerLeap に接続する
2. サーバーは bootstrap discovery を返せることを宣言する
3. クライアントは自分の `client_type` / `model_profile` / `role_profile` を渡す
4. サーバーは最小 skill bundle / prompt bundle / resource list を返す
5. クライアントは自分の環境へ保存・有効化する
6. 以後は通常の MCP / API 利用へ入る

### なぜ CLI よりこちらを優先するか
- 実際の接点が MCP / API だから
- クライアントごとの初回接続に自然に組み込めるから
- 閉域環境でもサーバー内だけで完結できるから
- 将来の複数クライアント実装でも契約を共有しやすいから

### 設計上の注意
- bootstrap は **server-side capability discovery** であり、内部実装の露出ではない
- client-facing bootstrap で返すのは **WebUI で観測できる概念** のみ
- Mroonga や DB 事情は bootstrap manifest に出さない
- ローカルモデル用には短く・低トークンで・曖昧さの少ない文面にする

---

## 7. 今後の設計原則

1. **MCP / API first**  
   まずはサーバー公開契約を整える。
2. **client-facing / developer-facing の明確な分離**  
   同じ表に混ぜない。
3. **WebUI observable model を client-facing の唯一の世界観とする**
4. **CLI generator は補助**  
   主導線にはしない。
5. **オンプレ / ローカルモデル適合性を必ず確認する**
6. **内部制約は developer-facing に閉じ込める**

---

## 8. 再計画後の優先順位

## P0: 情報設計の修正

### 目的
client-facing と developer-facing の混線を止める。

### 具体作業
- 現行 strategy doc の主語修正
- `README` の説明更新
- 既存 capability / skill の記述方針を整理
- 「CLI は補助」の位置づけを明記

### 関連ドキュメント
- [GitHub Issue #83](https://github.com/torinky/LedgerLeap/issues/83): 本再計画の親Issue。まずは全体方針と参照整理を集約する。
- [LLM連携 README](./README.md): `MCP / API first` への方針転換と親計画の導線。
- [AI 指示書の同期と共有計画](./20260308_ai_instructions_sync_plan.md): `.github` を SSOT にする developer-facing 整理の前提。
- [AGENTS.md](../../../AGENTS.md): AI 資産の配置先と重複防止の原則。
- [README](../../../README.md): repo 入口から再計画方針へ辿れるようにするための参照元。

## P1: client-facing 契約整理

### 目的
MCP / API 越しにクライアントが見える能力を整理する。

### 具体作業
- 検索 / 登録 / 更新 / 承認 の公開能力定義
- WebUI 由来の列モデルを SoT として整理
- MCP prompts / resources へどこまで載せるか方針確定
- 更新系 API / MCP の要件定義

### 関連ドキュメント
- [GitHub Issue #83](https://github.com/torinky/LedgerLeap/issues/83): client-facing capability taxonomy の親管理先。
- [ペルソナ、ユースケース、シナリオ](../../function/PersonaUseCaseScenario.md): 初期 skill セットと対象読者の根拠。
- [台帳管理機能](../../function/Ledger.md): WebUI で見える台帳・列・保存導線の参照元。
- [全文検索機能](../../function/Search.md): 検索 capability を業務行動に落とす際の機能参照。
- [ワークフロー（承認フロー）機能](../../function/WorkFlow.md): 承認 / 点検 capability の業務導線。
- [LedgerLeap API仕様概要](../../api/README.md): REST 公開契約の現行整理先。
- [MCP アーキテクチャと動作フロー](../../development/MCP_Architecture_and_Flow.md): MCP 公開契約の現行整理先。
- [MCP プロンプトガイドライン](../../development/MCP_Prompt_Guidelines.md): 小さなローカルモデル向けの prompt / resource 分離の参照元。

## P2: developer-facing SoT 整理

### 目的
内部事情を client-facing から切り離して保守可能にする。

### 具体作業
- `.github` と `docs/work` の役割再整理
- capability manifest の対象読者整理
- generator / sync を補助資産として再定義
- 内部制約の移送先決定

### 関連ドキュメント
- [GitHub Issue #83](https://github.com/torinky/LedgerLeap/issues/83): developer-facing SoT の整理対象を束ねる親Issue。
- [AGENTS.md](../../../AGENTS.md): ルールの配置先と maintenance loop の正本。
- [.github/copilot-instructions.md](../../../.github/copilot-instructions.md): repo-wide invariants の正本。
- [AI 指示書の同期と共有計画](./20260308_ai_instructions_sync_plan.md): `.github` とクライアント向け派生資産の同期方針。
- [LLM連携 README](./README.md): 計画書・完了済み設計・公式文書のインデックス。
- [MCP アーキテクチャと動作フロー](../../development/MCP_Architecture_and_Flow.md): developer-facing に閉じるべき内部制約の既存記述。

## P3: オンプレ / ローカルモデル導線

### ゴール
閉域・小型モデル・ローカル推論でも破綻しない導線を作る。

### 完了条件
- tool / prompt / resource の token budget 方針
- オンプレ導入時の最小構成整理
- モデルサイズ別の推奨運用メモ整理
- **初回アクセス時 bootstrap discovery 契約の整理**
- **クライアント側への skill 配置手順の抽象化**

### 関連ドキュメント
- [GitHub Issue #83](https://github.com/torinky/LedgerLeap/issues/83): on-prem / local model onboarding の親管理先。
- [MCP プロンプトガイドライン](../../development/MCP_Prompt_Guidelines.md): 低トークン・短文プロンプト設計の参照元。
- [MCP アーキテクチャと動作フロー](../../development/MCP_Architecture_and_Flow.md): MCP server contract の現行整理先。
- [LedgerLeap API仕様概要](../../api/README.md): REST discovery API を検討する際の現行公開契約。
- [LLM連携機能 開発ロードマップ](./2025-09-23_LLM_Integration_Roadmap.md): これまでの LLM 連携ロードマップ。
- [MCP包括的実装計画](./2025-09-29_Comprehensive_MCP_Implementation_Plan.md): 既存計画から引き継ぐ技術論点の参照元。

## P4: 補助的 generator 再定義

### 目的
既存 generator prototype を、主役ではなく補助として位置づけ直す。

### 具体作業
- 現行 prototype の扱いを「experimental」に変更
- 出力対象を developer convenience に限定
- client-facing 契約が固まるまで機能拡張を止める

### 関連ドキュメント
- [GitHub Issue #83](https://github.com/torinky/LedgerLeap/issues/83): generator の再位置づけを含む親計画。
- [AI 指示書の同期と共有計画](./20260308_ai_instructions_sync_plan.md): 生成より同期を優先する判断材料。
- [LLM連携 README](./README.md): 現行の主計画と補助的計画の見取り図。
- [AGENTS.md](../../../AGENTS.md): 生成物を SoT にしないための配置ルール。

---

## 9. スプリント分解

> **現状メモ:** Sprint 1（情報設計のリセット）と Sprint 2（client-facing capability taxonomy）は 2026-03-10 に完了。Sprint 3（developer-facing maintenance taxonomy）は 2026-03-12 に完了し、内部制約の保守先・`.github` / `docs/work` / generator prototype の境界・SoT / 派生物の関係を整理しました。Sprint 4（on-prem / local model onboarding）は 2026-03-13 に完了し、MCP / REST API / offline docs の役割分担、prompt / resource / tool の責務分担、local model 向け text budget、Sprint 5 / 6 への引き継ぎ境界を整理しました。Sprint 5（update path definition）は 2026-03-13 に完了し、単一レコード read path を前提にした更新契約、PATCH を主軸とする API 契約、MCP 側の更新 workflow、pending 状態編集時の `DRAFT` 戻し、実装 Issue へ切り出す単位を整理しました。Sprint 6（first-access bootstrap discovery contract）は 2026-03-14 に完了し、REST bootstrap manifest を初期公開契約として固定しつつ、MCP `resource / prompt / tool` の役割比較、local model 向け text/schema budget、client-facing / developer-facing の境界、後続 Issue への分解単位を整理しました。

## Sprint 1: 情報設計のリセット

### ゴール
LLM 連携計画の主語を `generator-first` から `MCP/API first` に切り替える。

### 完了条件
- 主計画文書が再計画方針に更新されている
- `README` からも新方針が辿れる
- client-facing / developer-facing の分類方針が明文化されている

### 完了メモ（2026-03-10）
- 親計画を 2026年以降の主計画として固定
- `README.md` / `docs/README.md` / `docs/work/README.md` / `docs/work/llm-integration/README.md` から新方針へ辿れる導線を追加
- client-facing / developer-facing / bootstrap discovery の3層整理を README 側にも反映

## Sprint 2: client-facing capability taxonomy

### ゴール
検索 / 登録 / 更新 / 承認 を、WebUI 由来の業務能力として再定義する。

### 完了条件
- client-facing capability の定義が揃う
- 内部制約が除去される
- ローカル LLM でも読める記述量に収まる
- **主要ペルソナごとの初期 skill セットが整理される**

### 完了メモ（2026-03-10）
- `docs/work/llm-integration/2026-03-10_Client_Facing_Capability_Taxonomy.md` を追加し、`ledger-search` / `ledger-create` / `ledger-update` / `workflow-review` / `activity-audit` / `analytics-report` を client-facing capability として定義
- `docs/function/PersonaUseCaseScenario.md` に、ペルソナの人物像・現場背景・判断軸・代表シナリオと capability / 初期 skill セットの対応を補強
- `docs/function/Ledger.md` / `docs/function/Search.md` / `docs/function/WorkFlow.md` は developer-facing の正式仕様として維持し、内部実装や技術詳細の置き場として保持
- `docs/api/README.md` に client-facing capability 参照導線を追加し、公開契約の入口として参照しやすくした

## Sprint 3: developer-facing maintenance taxonomy

### ゴール
内部実装制約と保守資産の SoT を整理する。

### 完了条件
- 内部事情の置き場所が明確
- `.github` / `docs/work` / generator prototype の関係が整理される
- stale な重複説明の削除方針が決まる

### 関連ドキュメント
- [GitHub Issue #83](https://github.com/torinky/LedgerLeap/issues/83)
- [GitHub Issue #86](https://github.com/torinky/LedgerLeap/issues/86)
- [AGENTS.md](../../../AGENTS.md)
- [.github/copilot-instructions.md](../../../.github/copilot-instructions.md)
- [developer-facing maintenance taxonomy](./2026-03-12_Developer_Facing_Maintenance_Taxonomy.md)
- [AI 指示書の同期と共有計画](./20260308_ai_instructions_sync_plan.md)
- [LLM連携 README](./README.md)

### 完了メモ（2026-03-12）
- `docs/work/llm-integration/2026-03-12_Developer_Facing_Maintenance_Taxonomy.md` を追加し、AI 資産の一次反映先、SoT / 派生物 / 補助資産の境界、client-facing に残さない内部事情を整理
- `.github` / `AGENTS.md` / `docs/runbooks` / `docs/work` / `resources/ai/capabilities` / generator prototype の責務を再確認し、重複説明ではなく参照中心でつなぐ方針を明文化
- `ai:bootstrap-client-skills` と `ClientSkillBootstrapService` は manifest 由来の派生物であり、親計画や routing ルールの正本ではないことを明記
- Sprint 3 の既知ギャップとして、`workflow-review` / `activity-audit` / `analytics-report` の manifest 未整備、および `ledger-update` が `planned` のままである点を次スプリントへ送る

## Sprint 4: MCP/API onboarding for on-prem clients

### ゴール
オンプレ環境・ローカルモデル前提の接続導線を整理する。

### 完了条件
- MCP prompts/resources の役割分担が決まる
- API / MCP どちらで初回理解させるかが整理される
- local model 前提の短いプロンプト設計ルールがまとまる
- **初回アクセス時 bootstrap discovery の比較軸と Sprint 6 への引き継ぎ境界が整理される**

### 関連ドキュメント
- [GitHub Issue #83](https://github.com/torinky/LedgerLeap/issues/83)
- [GitHub Issue #87](https://github.com/torinky/LedgerLeap/issues/87)
- [on-prem / local model onboarding design](./2026-03-13_OnPrem_Local_Model_Onboarding_Design.md)
- [MCP プロンプトガイドライン](../../development/MCP_Prompt_Guidelines.md)
- [MCP アーキテクチャと動作フロー](../../development/MCP_Architecture_and_Flow.md)
- [LedgerLeap API仕様概要](../../api/README.md)
- [LLM連携機能 開発ロードマップ](./2025-09-23_LLM_Integration_Roadmap.md)

### 完了メモ（2026-03-13）
- `docs/work/llm-integration/2026-03-13_OnPrem_Local_Model_Onboarding_Design.md` を追加し、on-prem / local model 前提の onboarding 制約、offline docs / MCP / REST API の役割分担、prompt / resource / tool の責務分担を整理
- **MCP は能力理解の主導線、REST API は接続契約の主導線** と整理し、bootstrap discovery の最終 contract は Sprint 6 に送る境界を固定
- local model 向け text budget（capability card / guide resource / bootstrap summary / prompt template）と、一覧→詳細の二段階導線を明文化
- `workflow-review` / `activity-audit` / `analytics-report` の manifest 未整備を、Sprint 6 の discovery contract 具体化前に解消すべき依存として整理

## Sprint 5: update path definition

### ゴール
更新系を client-facing 契約として定義する。

### 完了条件
- Update API / Update MCP Tool の要件定義がまとまる
- 検索→確認→更新の client-facing workflow が確立する

### 関連ドキュメント
- [GitHub Issue #83](https://github.com/torinky/LedgerLeap/issues/83)
- [GitHub Issue #88](https://github.com/torinky/LedgerLeap/issues/88)
- [update path public contract](./2026-03-13_Update_Path_Public_Contract.md)
- [台帳管理機能](../../function/Ledger.md)
- [ワークフロー（承認フロー）機能](../../function/WorkFlow.md)
- [LedgerLeap API仕様概要](../../api/README.md)
- [MCP アーキテクチャと動作フロー](../../development/MCP_Architecture_and_Flow.md)

### 完了メモ（2026-03-13）
- `docs/work/llm-integration/2026-03-13_Update_Path_Public_Contract.md` を追加し、`ledger-update` の client-facing workflow を **検索 → 単一レコード確認 → 差分確認 → 更新 → 状態確認** として定義
- 更新前に **単一レコード read path が必要**であること、初期公開契約の主契約は **PATCH** であること、`PUT` は deferred であることを整理
- `PENDING_INSPECTION` / `PENDING_APPROVAL` の保存で `DRAFT` に戻ること、`APPROVED` は初期公開契約では原則更新不可であることを client-facing に明文化
- API 実装 Issue と MCP 実装 Issue に分割できる粒度まで公開要件を固定

## Sprint 6: first-access bootstrap discovery contract

### ゴール
クライアント初回接続時に、役割・モデル・用途に応じた最小 skill / prompt / resource を返せる discovery 契約を定義する。

### 完了条件
- MCP 側の候補（resource / prompt / tool）が、Sprint 4 で整理した責務分担に基づいて具体 contract まで落ちている
- REST 側の候補（bootstrap manifest API）が整理されている
- client 側への保存・有効化に必要な placement 情報が定義されている
- ペルソナ別の推奨 bootstrap bundle が説明できる
- taxonomy と capability manifest の差分が、bundle 解決対象の範囲で解消されている

### 関連ドキュメント
- [GitHub Issue #83](https://github.com/torinky/LedgerLeap/issues/83)
- [GitHub Issue #89](https://github.com/torinky/LedgerLeap/issues/89)
- [GitHub Issue #92](https://github.com/torinky/LedgerLeap/issues/92)
- [GitHub Issue #93](https://github.com/torinky/LedgerLeap/issues/93)
- [GitHub Issue #94](https://github.com/torinky/LedgerLeap/issues/94)
- [GitHub Issue #95](https://github.com/torinky/LedgerLeap/issues/95)
- [GitHub Issue #96](https://github.com/torinky/LedgerLeap/issues/96)
- [Issue #83 UI evaluation plan](./2026-03-14_Issue-83_UI_Evaluation_Plan.md)
- [ペルソナ、ユースケース、シナリオ](../../function/PersonaUseCaseScenario.md)
- [MCP プロンプトガイドライン](../../development/MCP_Prompt_Guidelines.md)
- [MCP アーキテクチャと動作フロー](../../development/MCP_Architecture_and_Flow.md)
- [LedgerLeap API仕様概要](../../api/README.md)
- [first-access bootstrap discovery contract](./2026-03-14_First_Access_Bootstrap_Discovery_Contract.md)

### 完了メモ（2026-03-14）
- `docs/work/llm-integration/2026-03-14_First_Access_Bootstrap_Discovery_Contract.md` を追加し、**REST bootstrap manifest API を初期 discovery contract** として固定
- MCP の `Resource=短い参照 / Prompt=開始支援 / Tool=動的解決` を維持しつつ、`GetClientBootstrapManifestTool` を MCP parity の本命、`GenerateClientSkillPackTool` を後続の派生 file export として分離
- `small-local` / `general-local` / `remote-capable` ごとの text budget / schema budget の運用基準を具体化
- bootstrap discovery では **client-facing の能力説明だけを返し、developer-facing 制約を返さない** 境界を固定
- 後続実装を `MCP bootstrap resource` / `MCP bootstrap prompt` / `MCP bootstrap manifest tool` / `optional file export` の単位へ分解可能な状態にした
- 後続実装 Issue として #92 / #93 / #94 / #95 を起票し、契約定義と実装作業を分離した
- VSCode + Continue + ローカルLLM を主対象にした UI 評価計画文書を追加し、ダミーデータ / シナリオ / 期待応答 / 低能力SaaS比較の観点を整理した
- UI 評価計画の execution/evaluation issue として #96 を起票し、Issue #83 の follow-up として関連付けた

---

## 10. GitHub Issue 起草方針

今回の再計画は、1つの巨大 Issue ではなく **親 Issue + スプリント Issue** に分けるのがよい。

### 現時点の扱い
- 親Issueは [#83](https://github.com/torinky/LedgerLeap/issues/83) とし、本書の更新・関連ドキュメント・受け入れ基準を先に集約する。
- **Sprint 1-5 は完了管理済み**。
- Sprint 6 までの契約定義は完了し、残タスクは MCP parity 実装と optional file export の後続 Issue で扱う。

### 親 Issue
- 対象: [#83](https://github.com/torinky/LedgerLeap/issues/83)
- 目的: LLM 連携計画の `MCP/API first` 再設計を管理する
- 役割: 全体方針、スプリント進捗、受け入れ基準、関連 docs へのリンク

### 子 Issue / Sprint Issue
1. Sprint 1: 情報設計のリセット
2. Sprint 2: client-facing capability taxonomy
3. Sprint 3: developer-facing maintenance taxonomy
4. Sprint 4: on-prem / local model onboarding
5. Sprint 5: update path definition
6. Sprint 6: first-access bootstrap discovery contract

---

## 11. 受け入れ基準（再計画版）

- [ ] client-facing 文書に内部 DB / Mroonga / Laravel 実装事情が残っていない
- [ ] developer-facing 文書に内部制約の保守先が整理されている
- [ ] MCP / API を唯一の client 接点として扱う方針が全体で一致している
- [ ] オンプレ / ローカルモデル前提の制約が計画に反映されている
- [ ] 更新系の公開契約が次スプリントで着手可能な粒度まで分解されている
- [ ] generator prototype が補助資産として適切に再位置づけされている
- [ ] **ペルソナ別の代表シナリオと初期 skill セットが整理されている**
- [ ] **初回アクセス時の bootstrap discovery 契約が定義されている**（Sprint 6 の対象）

---

## 12. 今回の結論

方向性として「検索・更新・登録を中心に整理する」こと自体は正しい。
ただし、中心に据えるべきなのは **クライアントへ配るファイル** ではなく、**クライアントが MCP / API 越しに理解する公開契約** である。

したがって、LedgerLeap の次の計画は次の順に進める。

1. **情報設計を修正する**
2. **client-facing capability を再定義する**
3. **developer-facing の内部制約を隔離する**
4. **オンプレ / ローカルモデル前提の導線を作る**
5. **その後に必要な範囲で generator を再評価する**

---

**作成者:** LedgerLeap 開発チーム
**更新方針:** 今後の LLM 連携計画は本書を親計画として更新し、実装は Issue #83 と後続の別 Issue / 作業ログで追跡する
