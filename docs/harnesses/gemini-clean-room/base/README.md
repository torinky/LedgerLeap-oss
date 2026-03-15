# Base harness layout

`base/` は **そのまま runtime に使う場所ではなく、評価用 root へコピーして使う原本** です。

## コピー後の想定レイアウト

```text
<evaluation-root>/
  .git/                    # 推奨: parent discovery 境界を切るための独立 repo
  workspace/               # Gemini CLI を起動する current working directory
    .gemini/
      settings.json        # settings.clean-room.template.jsonc から作成
  gemini-home/             # GEMINI_CLI_HOME に割り当てる
    .gemini/
      ...                  # user-level settings / skills / sessions / trust が入る
  evidence/                # 比較記録やスクリーンショット置き場
```

## 使い方

1. この `base/` を neutral parent 配下へコピーする
2. `workspace/.gemini/settings.clean-room.template.jsonc` を `settings.json` に複製する
3. placeholder を環境ごとに埋める
4. `gemini-home/` を `GEMINI_CLI_HOME` に設定する
5. Gemini CLI は `workspace/` を current working directory にして起動する

実際のコピー・環境変数設定・`git init`・`settings.json` 作成の参考コマンドは OS 別ノートを参照してください。

OS 別メモ:
- [macOS](/docs/harnesses/gemini-clean-room/platforms/macos.md)
- [Windows](/docs/harnesses/gemini-clean-room/platforms/windows.md)



