# Client skill initialization gating proposal

**作成日:** 2026年03月14日  
**ドキュメント種別:** 作業ファイル（提案・検討メモ）  
**関連Issue:** [#83](https://github.com/torinky/LedgerLeap/issues/83)

## 1. 目的

Issue #83 で進めている **tool description から process guidance を減らす方針** を、
より安定して成立させるための追加案として、
**client-side skill の初期化が終わるまで通常 tool を解放しない initialization gate** を検討する。

イメージとしては、serena のように最初に初期化手順を通さないと通常機能へ進めないモデルである。

この提案で整理したいのは次の点である。

1. `bootstrap discovery` と `initialization` と `gate` を別責務として切り分ける
2. どの bootstrap 資産を pre-init で許可し、どの tool を post-init で解放するかを定義する
3. `optional export` と `required initialization` を混同しない
4. process guidance を client-side skill 側へ集約する前提条件を明文化する

## 2. 背景

これまでの設計では、次の流れを整備してきた。

- `GetClientBootstrapManifestTool` / REST bootstrap manifest による bundle discovery
- `ledgerleap://bootstrap/{client}` による静的 bootstrap card
- `bootstrap-client-skills` prompt による開始支援
- `ai:bootstrap-client-skills` による optional downstream export

一方で、現状は **bootstrap を取得しなくても通常 tool を使えてしまう** 前提であり、
次の問題が残る。

1. process guidance を tool description に書かないと、初回利用時の導線が弱くなる
2. client に skill が入っている保証がなく、tool の自己説明が厚くなりやすい
3. discovery / prompt / export の責務分離はできても、**初期化の強制力** がない

したがって、tool description の slim 化を本気で進めるなら、
**通常 tool 利用前に client-side skill を有効化させるゲート** がある方が整合的である。

## 3. 提案の要点

### 3.1 三層分離

この提案では、初回導線を次の 3 層に分ける。

| 層 | 役割 | 代表資産 |
|---|---|---|
| Discovery | 何を入れるべきかを返す | REST bootstrap manifest / `GetClientBootstrapManifestTool` / bootstrap card |
| Initialization | 必要な skill / prompt / snippet / template を client 側に作る・有効化する | client-side skill 生成、配置、ACK、activation note |
| Gate | 初期化完了まで通常 tool を解放しない | MCP server 側の pre-init allowlist / init status check |

### 3.2 狙う状態

- pre-init では bootstrap 系しか使えない
- client は manifest から必要 bundle を取得する
- client は required な skill 群を配置・有効化する
- server は初期化完了を確認した後で通常 tool を解放する
- その後は process guidance を client-side skill に任せ、tool description は契約中心にできる

## 4. 現行資産との関係

### 4.1 `GetClientBootstrapManifestTool`

役割は引き続き **bundle discovery** である。

ここで返すのは次のような「何を入れるべきか」の情報に留める。

- `recommended_capabilities`
- `resources`
- `prompts`
- `files`
- `placement_instructions`
- `warnings`

### 4.2 `bootstrap-client-skills`

役割は **最初の問い方支援** であり、初期化完了の証跡にはしない。

### 4.3 `ai:bootstrap-client-skills`

現状どおり **optional downstream export** という位置づけは維持する。

ただし、initialization gate 導入後は次を区別する必要がある。

- **optional export**: 実ファイルの自動生成手段の一つ
- **required initialization**: client が必要 skill を利用可能にしたことの確認

つまり、file export は optional でも、**初期化完了確認そのもの** は required になり得る。

## 5. gate の最小設計案

### 5.1 pre-init で許可するもの

初期化前は、少なくとも次の bootstrap 系だけを許可する案が自然である。

- `GetClientBootstrapManifestTool`
- `ledgerleap://bootstrap/{client}`
- `bootstrap-client-skills`
- 初期化状態確認用の専用 tool（新設候補）
- 初期化完了通知 / ACK 用の専用 tool（新設候補）

### 5.2 pre-init でブロックするもの

通常業務 tool は原則すべてブロック候補である。

- search
- detail / related
- create / update
- workflow / approval
- audit / analytics

### 5.3 ブロック時の返答原則

ブロック時は developer-facing な事情を出さず、client-facing に短く返す。

例:

- まず初期設定を完了してください
- 必要な skill を取得して有効化すると検索・更新・承認ツールが使えます
- 先に bootstrap manifest を取得しますか

## 6. initialization 完了の定義候補

ここは follow-up issue で詰めるべき中心論点である。

### Option A: 実ファイル生成 + 完了 ACK

- client が required files を生成・配置する
- client が completion tool で ACK する
- server は role/client/model と manifest version を記録する

**利点**
- 実体が最も分かりやすい
- serena 的な「最初に作る」感覚に近い

**弱点**
- client ごとの配置差分が大きい
- file generation できない client で扱いが難しい

### Option B: bundle digest ACK

- client は manifest を受け取る
- required capability IDs / files / version を確認したと ACK する
- 実ファイル生成は client 実装側の責務に寄せる

**利点**
- client 非依存度が高い
- REST / MCP 共通に寄せやすい

**弱点**
- 実際に skill が使えるかは保証しづらい

### Option C: client 種別ごとの完了条件

- Copilot / Claude Code / Gemini CLI / OpenAI Agents で完了条件を分ける
- 物理 file がある client は生成確認、そうでない client は activation ACK

**利点**
- 現実的

**弱点**
- 実装・運用がやや複雑

### 推奨

初期 issue では **Option C を前提にしつつ、実装は Option B 寄りの最小 ACK から始める** のが安全である。

## 7. manifest 更新時の再初期化

gate を導入するなら、**いつ再初期化が必要か** を必ず決める必要がある。

最低限の論点:

1. `recommended_capabilities` が変わったとき
2. `files` / `placement_instructions` が変わったとき
3. `role_profile` または `client_type` が変わったとき
4. `model_profile` の変更で text/schema budget が変わったとき

推奨方針:

- role/client の変更は **必ず再初期化**
- model_profile の変更は bundle 差分があるときだけ再初期化
- server は manifest version / digest を持ち、差分検知に使う

## 8. tool description 分離への影響

この gate があると、通常 tool の description は次の前提を置ける。

- client はすでに bootstrap 済み
- capability ごとの標準フローは client-side skill で読める
- tool 側は契約説明に集中できる

したがって、`SearchLedgersTool` などの長い process guidance を skill 側へ移す根拠が強くなる。

## 9. 非機能・運用論点

### 9.1 残すべき境界

client-facing では次を出さない。

- 内部認証テーブル
- server-side state の保存実装
- class 名や service 名
- Laravel / DB 実装都合

### 9.2 必要な reset / recovery

初期化失敗や bundle 変更に備え、少なくとも以下が必要になる。

- init status の確認
- 再初期化要求
- 破損状態からの reset
- role 変更時の再bootstrap

### 9.3 評価観点

UI evaluation では次を追加確認するべきである。

- pre-init では bootstrap 系しか出てこないか
- post-init で通常 tool が解放されるか
- 再初期化が必要な条件を低能力モデルでも誤読しないか

## 10. 推奨 follow-up issue の範囲

この提案から起こす新規 issue では、少なくとも次を扱う。

1. pre-init allowlist の定義
2. initialization completion の定義
3. ACK / status / reset API or MCP tool の有無
4. manifest version 変更時の re-init policy
5. client-facing / developer-facing 境界
6. `optional export` との切り分け
7. docs/work / UI evaluation / description slimming との接続

## 11. 結論

Issue #83 の「process と tool の使い方を分離する」という方向性をさらに進めるなら、
**bootstrap discovery だけでなく initialization gate まで含めた設計** に進めるのが自然である。

重要なのは、次を混同しないこと。

- discovery: 何を入れるべきかを知る
- initialization: 必要 skill を使える状態にする
- optional export: 実ファイル化の手段の一つ
- gate: 初期化完了まで通常 tool を閉じる

この整理が入ることで、tool description の責務縮小と client-side skill への process 移送を、より一貫した設計として説明できる。
