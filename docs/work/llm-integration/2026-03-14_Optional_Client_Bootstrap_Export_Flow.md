# LedgerLeap optional client bootstrap export flow

**作成日:** 2026年03月14日  
**ドキュメント種別:** 作業ファイル（Issue #95: optional export / package generation）  
**関連Issue:** [#83](https://github.com/torinky/LedgerLeap/issues/83), [#95](https://github.com/torinky/LedgerLeap/issues/95)

## 1. 目的

この文書は、bootstrap discovery の**後段**として client 側へ配置する file 群をどう扱うかを整理し、Issue #95 の実装判断を残すための記録です。

ここで扱うのは、discovery contract そのものではなく、既存の bundle 解決結果を **optional downstream export** として実ファイル化する流れです。

## 2. 判断結果

Issue #95 では次を採用する。

1. **Option A を採用する**
   - 既存の `ai:bootstrap-client-skills` を optional export flow の既存実装として正式化する
   - discovery contract と file export / package generation の責務境界を docs と実装文言で明確にする
2. **package は当面「client 別ディレクトリ生成」を指す**
   - zip / tar などの archive 生成はこの Issue の主対象に含めない
3. **MCP export tool は follow-up 候補として分離する**
   - `GetClientBootstrapManifestTool` は discovery parity を担う
   - export はその後段で、既存 CLI を正規ルートとして扱う

## 3. このIssueで維持する境界

### 3.1 discovery が担うこと

- `client_type` / `role_profile` / `model_profile` / `language` に応じた最小 bundle 解決
- `recommended_capabilities`, `resources`, `prompts`, `files`, `placement_instructions` の返却
- client-facing のみを返し、developer-facing internals を露出しない

### 3.2 export が担うこと

- `files` / `placement_instructions` をもとに、client 別の派生ファイルを出力する
- README / skill / prompt / agent / snippet / template をローカル配置用にまとめる
- 非空ディレクトリへの上書きを避け、必要時だけ `--force` で再生成する

### 3.3 export が担わないこと

- 初回 discovery の主契約
- capability 定義の SoT
- role / model ごとの動的 bundle 解決そのもの
- archive 配布、バイナリ配布、クライアントへの自動インストール

## 4. ペルソナ / シナリオから抽出した要求

根拠:
- `docs/function/PersonaUseCaseScenario.md`
- `app/Services/Ai/BootstrapManifestService.php`
- `resources/ai/capabilities/*.yaml`

### 実務担当者
- 一覧 → 詳細 → 実行の導線がすぐ分かること
- 長文より短い開始導線を優先すること
- `ledger-search` / `ledger-create` / `workflow-review` を最小セットで導入できること

### 管理者
- 集計 → 絞り込み → 詳細確認へ進めること
- `activity-audit` / `analytics-report` を含む bundle を client 別に説明できること
- capability 数が増えても README で全体像を短く把握できること

### 現場リーダー
- 検索 → 詳細 → 更新 / 差し戻しを安全に辿れること
- `ledger-update` を含む bundle を discovery 後に明示的に配布できること
- dry-run / 差分確認を前提にした update 導線を崩さないこと

### 横断要件
- role_profile ごとに最小 bundle を切り替えられること
- client_type ごとに出力種別が異なること
- generated output が SoT ではないと明示されること
- 再生成前提・配置責任・overwrite policy を README / docs で説明できること

## 5. 既存コードから流用する範囲

### 5.1 bundle 解決

`app/Services/Ai/BootstrapManifestService.php`

流用するもの:
- `ROLE_PROFILES`
- `MODEL_PROFILES`
- `resolve()`
- `buildFiles()`
- `buildPlacementInstructions()`

この service を、export 対象の **入力仕様** とみなす。

### 5.2 実ファイル生成

- `app/Console/Commands/GenerateClientSkillPack.php`
- `app/Services/Ai/ClientSkillBootstrapService.php`

流用するもの:
- client 別 pack 生成
- ディレクトリ準備
- `--force` を使わない非空ディレクトリ上書き拒否
- README / skill / prompt / agent / snippet / template 書き出し

## 6. client 別の出力物

| client | 主な出力物 | 配置メモ |
|---|---|---|
| `copilot` | `skills/{capability}/SKILL.md`, `prompts/{capability}.prompt.md`, `README.md` | skill + prompt を揃えた bundle |
| `claude-code` | `skills/{capability}/SKILL.md`, `agents/{capability}-agent.md`, `CLAUDE.md.snippet`, `README.md` | agent + snippet を含む |
| `gemini-cli` | `skills/{capability}/SKILL.md`, `GEMINI.md.snippet`, `README.md` | snippet 主導の補助導線 |
| `openai-agents` | `templates/ledger_agents.py`, `README.md` | template 中心 |

placement の案内は discovery 側の `placement_instructions` を正本とし、export 側はその実ファイル化を担う。

## 7. overwrite policy

既存 CLI / service の挙動を正式な export policy として扱う。

- 出力先が存在しない場合は新規作成する
- 出力先が空でない場合は **失敗** する
- 明示的に `--force` を指定した場合のみ既存ディレクトリを削除して再生成する
- generated output は再生成前提であり、手編集した内容を SoT として扱わない

## 8. package の解釈

Issue #95 時点では、**package = client 別ディレクトリとしてまとまった派生出力** と解釈する。

この判断により、次は見送る。

- zip / tar の archive 生成
- checksum / signed package の配布
- client への自動展開
- MCP からバイナリ package を返す仕組み

必要なら follow-up Issue で検討する。

## 9. 実装反映メモ（2026-03-14）

- `ai:bootstrap-client-skills` を **optional downstream export** として正式化した
- generated README の文言を現行 capability 状態に合わせて更新した
- discovery と export の責務境界、client 別出力物、overwrite policy を docs に整理した
- `GetClientBootstrapManifestTool` / REST bootstrap manifest は引き続き discovery の主契約として維持した

## 10. follow-up 候補

1. `GenerateClientSkillPackTool` の追加
   - MCP から optional export を起動したい場合の候補
2. archive/package 形式の導入
   - zip / tar などの物理 package が必要になった場合のみ検討
3. export README の client 別 activation 例追加
   - 実 client の配置例が安定した段階で追記

