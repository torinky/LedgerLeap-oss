---
marp: true
paginate: true
style: |
  section {
    margin: 0;
    padding: 12px 36px 18px;
    line-height: 1.34;
  }

 

  section li {
    margin: 0.1em 0;
    font-size: 0.9em;
  }

  section table {
    font-size: 0.82em;
    line-height: 1.22;
  }

  section th,
  section td {
    padding: 0.22em 0.38em;
    vertical-align: top;
  }

  section img {
    display: block;
    margin: 0.15em auto 0.35em;
    max-width: 100%;
  }

---

# AI駆動開発の具体例

LedgerLeapで実際に使っているツールと開発サイクル


### 注意

- サービスやアプリを使いこなしていません。
- アドバイスあれば教えてください。
- 利用サービス : Github Copilot, Gemini

---

## 0. 用語整理

| 区分 | 例 | この資料での意味 |
|---|---|---|
| 固有名詞 | LedgerLeap / GitHub / Gemini / Copilot / PhpStorm  | 製品名、サービス名、機能名のラベル |
| LLM | Gemini 3.1 Pro / GPT-5.4 mini | 文章生成や推論を担うモデル |
| アプリ | Gemini CLI / GitHub Copilot CLI / IDE プラグイン / Antigravity| 人が触る操作面。LLM や API を使う入口 |
| API / MCP | GitHub API / ledgerleap-api / laravel-boost / markitdown | アプリや LLM から呼ばれる機能の窓口 |

### 関係
  - 基本は、アプリが API / MCP を呼び、その先で LLM やデータに接続する。
  - この資料では、固有名詞は名前として、LLM・アプリ・API は役割として分けて説明する。

---

## 1. 心構え

### なんでも試す、組み合わせる
  - 使えるかどうかを机上で決めず、まず触って確かめる
  - 単体で決め打ちせず、ツールや手順を重ねて使う

### 型を作る
  - うまくいったやり方を、次回も使えるパターンに落とす

### 驚き屋ブログに振り回されない
  - 話題性ではなく、実際の作業に効くかどうかで判断する

### AIにしすぎない
  - AI を前提にしすぎず、人の判断や既存手順と釣り合わせて使う

### 捨てる、変える
  - 使いにくいものは残さず捨て、前提が合わなくなったらやり方を変える

---

## 1. 全体像 (1/3)

### 生成AIの複数ツール運用
  - 1つのAIに寄せず、役割の違う複数ツールとして運用する前提
  - 調査、実装、検証、公開契約で役割分担する前提

### Git / GitHub / Actions / Agent の併用
  - Git は変更履歴と差分の起点
  - GitHub は Issue、PR、Review、Actions の作業台
  - GitHub Actions は CI とテストの裏付け
  - Agent は作業後の振り返りと資産更新の入口

### 工程起点のツール選択
  - 「どのツールを使うか」より「どの工程で使うか」を先に決める
  - 工程に応じて使う道具を切り替える

---

## 1. 全体像 (2/3)

### Gemini CLI / GitHub Copilot CLI / IDE プラグイン / MCP の使い分け
  - Gemini CLI は調査と整理
  - GitHub Copilot CLI は実装と反復
  - IDE プラグインは手元の文脈確認
  - MCP は公式ドキュメント検索、文書変換、GitHub 連携

### GitHub イシュー、ドキュメント、コード更新履歴の突合
  - 単発のメモではなく、Issue / docs / code history を合わせて読む
  - 実装の前後関係や判断理由を再構成する

### Actions ログの突合
  - GitHub Actions の失敗ログを CI の裏付けとして読む
  - ローカル再現と CI 再現を分けて確認する
---

## 1. 全体像 (3/3)


### 再現性のある事例化
  - 個別の経験を、次回も使える説明材料へ落とし込む
  - 発表や計画ドキュメントで再利用できる形にする

---

## 2. AIツール整理 (2026.4)

- Gemini 系は深いロジックの検討させない。調査、整理、ビューの修正など。
- Copilot CLI は実装と反復

| 区分 | ツール | 主な役割 | 使う場面 |
|---|---|---|---|
| 調査・整理 | Gemini CLI | 既存文書の読み解き、論点整理、下書き作成 | まず全体像を掴むとき |
| IDE 補助 | PhpStorm Gemini プラグイン | ローカル文脈の確認、軽い補助 | 手元のコードや文書を見ながら詰めるとき |
| 実験環境 | Antigravity | 接続設定や制約の確認 | 新しい連携を試すとき |
| 実装・反復 | GitHub Copilot CLI | 実装ログ、Issue 連動、反復修正 | コード変更を進めるとき |
| IDE 補助 | GitHub Copilot プラグイン | コード読解、修正補助、レビュー支援 | 既存コードを追いながら直すとき |
| 公開契約 | MCP | AIツールでできることを増やす | 公式ドキュメント検索、文書変換、GitHub 連携をまとめて扱う |


---

## 3. モデル選択 (2026.4)
- コスパが良い Copilot GPT-5.4 mini を第一候補にする。
- Gemini は free request が残っている範囲では Pro モデルも使えるが、すぐ底をつきやすく、待ちが発生しやすいため、長く回す前提では使い分ける。

| サービス | 推奨モデル | 感触 | 使い方 |
|---|---|---|---|
| GitHub Copilot | GPT-5.4 mini | プレミアムリクエストの消費が少なく、破綻が少ない | まずこれを第一候補にする |
| GitHub Copilot | Claude Sonnet 4.6 | 優秀だがプレミアムリクエストを消費するのでコスパは悪い | GPT-5.4 mini で詰まった複雑な課題に限定して使う |
| Gemini | 3.1 Pro | フリーのリクエストが残っている範囲では有効 | すぐ底をつくため長時間運用には向きにくい |
| Gemini | 3 flash | フリー枠が尽きた後のつなぎ | 1週間待ちを避けたいとき |
| GitHub Copilot | GPT 4.1, GPT5 mini | どうしようもなくなったとき | ドキュメント整備程度。複雑なことはさせない |


---

## 4. MCPサーバー整理 (2026.4)

開発では、LedgerLeap 本体の MCP と、補助・連携用の MCP を分けて使っている。

| サーバー名 | 役割 | 備考 |
|---|---|---|
| laravel-boost | Laravel 開発支援 | `boost:mcp` を使う診断や補助用サーバー |
| ledgerleap-api | LedgerLeap の業務 API / MCP | 検索・詳細・更新の導線を担う本体サーバー |
| microsoft/markitdown | 文書変換・取り込み補助 | Markdown 化や文書処理の補助用サーバー |
| io.github.upstash/context7 | 外部ライブラリ文脈の参照 | ライブラリ調査や docs 参照に使う |
| io.github.github/github-mcp-server | GitHub 連携 | イシューやリポジトリ情報の参照に使う |
| awesome-copilot | Copilot サンプル / 実験 | MCP の試行やサンプル確認に使う |
| Phpstorm/phpstorm-mcp | IDE 連携 | PhpStorm 側の文脈や作業連携に使う |

各AIツールにはファイル検索、読み書き、Web検索などの内蔵機能もあるが、正本の置き場は `.github` と `docs` 側に分けている。

---

## 5. 開発サイクル (1/2)

1. 調査する
    - 既存 docs、Issue メモ、実装ログ、制約メモを読む。

2. 整理する
    - 何を基準にするか、何を別ファイルに逃がすか、何をクライアント向けに隠すかを決める。

3. 要件・仕様を決める
    - ペルソナとシナリオから大まかな仕様と使い方を定める。
    - 「誰が、何のために、どの導線で使うか」を固める。

4. 計画ドキュメントを作成する
    - 具体的な要件と中粒度の仕様に分割し、実装の前に見通しを持てる形へ整理する。

---

## 5. 開発サイクル (2/2)

5. GitHub Issue でスプリント計画を立てる
    - 作業管理の単位を切り、進捗と成果の裏付けを残す。完了条件、担当範囲、検証方針をここで固定する。

6. 試す
   - Gemini CLI で下書きし、Copilot CLI で実装し、IDE プラグインで補助しながら検証する。
   - GitHub Actions で CI の裏付けを取り、ローカル差分と切り分ける。

7. 振り返る
   - 結果を整理し、何が有効だったか、どこで詰まったかを記録する。必要ならスキルや指示書をブラッシュアップする。
   - Agent に渡す再利用可能な判断を抽出する。

8. 残す
   - docs/work に実行記録を残し、必要なら .github に再利用ルールとして昇格させる。
   - GitHub Issue、PR、Actions の証跡を紐づける。

---

## 6. 作業の定型化 (1/2)

### `.github` は AI と開発の入口
  - `copilot-instructions.md`: repo 全体の短い制約
  - `instructions/`: app / tests / design などの path 固有ルール
  - `prompts/`: `/bug-investigation` などの起動入口
  - `skills/`: 再利用する判断木や診断知識
  - `workflows/`, `actions/`, `ISSUE_TEMPLATE/`: Actions、Issue 入力、自動化の入口

### `docs` は人が読む知識の本体
  - `development/`: コーディング規約、環境構築、テスト、Git 運用
  - `runbooks/`: 日常運用と再現可能な手順
  - `work/`: 調査結果、実装ログ、発表草稿、振り返り
  - `architecture/`, `api/`, `features/`, `testing/`: 設計・仕様・テストの説明
  - `templates/`, `harnesses/`: 調査テンプレートと再現用の土台
---

## 6. 作業の定型化 (2/2)

### 開発サイクルとの対応
  - 調査する → `docs/work/`, `docs/development/`, `docs/api/` を読む
  - 整理する → `.github/instructions/`, `.github/prompts/`, `.github/skills/` で扱いをそろえる
  - 要件・仕様を決める → `docs/features/`, `docs/api/`, `docs/architecture/` で言葉を固定する
  - 計画ドキュメントを作成する → `docs/work/` や issue draft に落とす
  - Issue でスプリント計画 → `.github/ISSUE_TEMPLATE/` と GitHub Issues
  - 試す → `docs/testing/`, `.github/skills/`, GitHub Actions
  - 振り返る → `docs/work/` に evidence を残し、再利用できるものを `.github` に昇格する

---

## 7. 事例再構成の材料

- `.github` と `docs` の役割を先に押さえると、GitHub イシュー、ドキュメント、コード更新履歴を合わせて読んだときに、単独の変更では見えない設計の流れが追える。

| ソース | 何がわかるか | この資料での使い方 |
|---|---|---|
| GitHub イシュー | 論点、優先順位、未解決点 | #137〜#139 の検索設計を再構成する |
| ドキュメント | 正本の方針、運用ルール、背景 | `.github` と `docs/*` と運用メモの関係を示す |
| コード更新履歴 | 何が実際に入ったか、どの順で固まったか | MCP tool 実装の流れを時系列で示す |

##### 代表事例
  - 検索クエリ設計の Issue #137〜#139
  - `.github` を SSOT にする指示書同期
  - `Search → Detail → dry_run → Update` の更新導線
  - client-facing と developer-facing を分ける公開契約整備


---

以降メモ

---

## 8. コード更新履歴の見せ方

| 履歴 | 何が起きたか | スライドでの見せ方 |
|---|---|---|
| `feat(mcp): add attachment delivery envelope` | 付帯情報の返し方を拡張 | MCP が単なる検索ではないことを示す |
| `feat(mcp): improve attachment summaries #135` | 要約の質を上げた | 出力の品質改善を示す |
| `feat(mcp-search): add term extraction and trace guidance` | 検索語抽出と説明性を追加 | Issue #137〜#139 とつなぐ |
| `feat(mcp): implement ledger update workflow` | 更新系の導線を実装 | Search → Detail → Update の流れを示す |
| `refactor(mcp): slim tool descriptions for capabilities` | tool 説明を整理 | client-facing との分離を示す |

この種の履歴は、いつ何を入れたかだけでなく、どの順で固めたかを示す材料として使う。

---

## 9. 発表での見せ方

1. まず工程を見せる。
  何のためのAIかを先に理解してもらう。
2. 次にツール整理を見せる。
  役割分担を表で固定する。
3. その後にモデル選択を見せる。
  GPT-5.4 mini を第一候補にする理由を示す。
4. 最後に MCP サーバーと事例再構成をつなげる。
  ツール名ではなくサーバー単位で整理し、Issue / docs / code history のつながりを見せる。

---

## 10. まとめ

AI ツールは工程ごとの役割で使い分ける。
Copilot は GPT-5.4 mini を第一候補にし、Gemini は free request の範囲では Pro を使うが、使い切りやすい前提を織り込む。
MCP はツール名ではなくサーバー名で整理し、GitHub イシュー、ドキュメント、コード更新履歴を合わせて説明する。
