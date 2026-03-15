# GEMINI_CLI_HOME directory

このディレクトリは、**評価 run 専用の `GEMINI_CLI_HOME`** を表します。

## 目的

- user-level `GEMINI.md` を分離する
- user-level `settings.json` を分離する
- user-level skills / sessions / shell history / trusted folders を分離する

## 原則

- 開発用 `~/.gemini` をコピーしない
- 評価前は空に近い状態から始める
- 評価中に生成された state は contaminated run と clean-room run で分けて保存する

## 典型的に入るもの

Gemini CLI 実行後、必要に応じて次が作られます。

- `.gemini/settings.json`
- `.gemini/GEMINI.md`
- `.gemini/skills/`
- `.gemini/tmp/...`
- `.gemini/trustedFolders.json`

