# Gemini CLI clean-room harness

**対象 Issue:** [#106](https://github.com/torinky/LedgerLeap/issues/106)  
**用途:** Gemini CLI の bootstrap / placement / first-access 挙動を、開発用 repo コンテキストが混ざらない条件で比較評価する

## この harness が解決すること

Gemini CLI 公式 docs では、少なくとも次が確認されています。

- `GEMINI.md` は user / workspace / parent / subdirectory から階層的に読み込まれる
- workspace skill は `.gemini/skills/` または `.agents/skills/` から自動 discovery される
- user-level settings / skills / sessions / trusted folders は `GEMINI_CLI_HOME` 配下で分離できる
- project-level `.gemini/settings.json` は user settings を override する

そのため、**LedgerLeap の開発用 repo をそのまま評価 workspace に使う** と clean-room になりません。

この harness は、Gemini CLI 評価用に次を固定します。

1. **copy 対象は curated harness root のみ** とする
2. **`GEMINI_CLI_HOME` を専用ディレクトリへ分離**する
3. **親ディレクトリ・子ディレクトリの context 混入条件**を明文化する
4. contaminated run / clean-room run の比較証跡を揃える

## 使い方の考え方

この repo 内の `base/` は **runtime directory そのものではなく、コピーして使う原本** です。

## 最短セットアップ

まずは次の順で準備します。

1. `base/` を **home directory の外** にコピーする
2. `workspace/.gemini/settings.clean-room.template.jsonc` を `settings.json` に複製する
3. `settings.json` の placeholder を埋める
4. `gemini-home/` を `GEMINI_CLI_HOME` に割り当てる
5. `workspace/` を current working directory にして Gemini CLI を起動する
6. 実行前後に `/memory show` と `/skills list` を記録する

実コマンドは OS 別ノートを参照してください。

- macOS: [`platforms/macos.md`](/docs/harnesses/gemini-clean-room/platforms/macos.md)
- Windows: [`platforms/windows.md`](/docs/harnesses/gemini-clean-room/platforms/windows.md)

> [!NOTE]
> 現在の harness template は **`command` ベースの local MCP** を前提にしています。
> Gemini CLI 側は `httpUrl` / `headers` をサポートしますが、LedgerLeap 側はまだ
> `localhost` の **HTTP-accessible MCP endpoint** と request-header ベース認証へ揃っていません。
> この差分は follow-up Issue [`#109`](https://github.com/torinky/LedgerLeap/issues/109) で整理します。

### 推奨フロー

1. `base/` の内容を neutral parent 配下へコピーする
2. copy 先を必要なら独立 `.git` boundary にする
3. `workspace/.gemini/settings.clean-room.template.jsonc` を `settings.json` に複製し、placeholder を埋める
4. `gemini-home/` を `GEMINI_CLI_HOME` 用に使う
5. Gemini CLI は `workspace/` を current working directory として起動する
6. 評価前後の観測結果を `evidence-template.md` に記録する

## ディレクトリ構成

- [`base/`](/docs/harnesses/gemini-clean-room/base/README.md)
  - copy して使う最小ハーネス
- [`allowed-artifacts.md`](/docs/harnesses/gemini-clean-room/allowed-artifacts.md)
  - clean-room に持ち込んでよいもの
- [`forbidden-artifacts.md`](/docs/harnesses/gemini-clean-room/forbidden-artifacts.md)
  - clean-room に持ち込まないもの
- [`evidence-template.md`](/docs/harnesses/gemini-clean-room/evidence-template.md)
  - contaminated / clean-room 比較記録
- [`platforms/macos.md`](/docs/harnesses/gemini-clean-room/platforms/macos.md)
  - macOS 向け配置ノート
- [`platforms/windows.md`](/docs/harnesses/gemini-clean-room/platforms/windows.md)
  - Windows 向け配置ノート
- [`overlays/README.md`](/docs/harnesses/gemini-clean-room/overlays/README.md)
  - 将来の persona overlay 契約

## Mac / Windows 共通の必須条件

- **home directory 配下には置かない**
  - `~/...`, `%USERPROFILE%\...` は避ける
- **親ディレクトリに余計な `GEMINI.md` / `.env` / 別 repo を置かない**
- **`GEMINI_CLI_HOME` を開発用 home と分離する**
- **開発用 `.gemini/settings.json` をそのままコピーしない**
- **評価前に `/memory show` と `/skills list` を確認する**

## 公式 docs に基づく判断ログ

- [`docs/work/llm-integration/2026-03-15_Gemini_CLI_Clean_Room_Harness_Constraints.md`](/docs/work/llm-integration/2026-03-15_Gemini_CLI_Clean_Room_Harness_Constraints.md)

## 非対象

- Gemini bootstrap contract の最終実装
- initialization gate の最終実装
- `#105` の placement / delivery の最終判断
- persona 別 expected-first-steps の詳細化

これらは `#108`, `#105`, `#101` へ接続します。



