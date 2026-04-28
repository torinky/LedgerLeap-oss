# GitHub Issue Body Sync Playbook

LedgerLeap の GitHub issue 本文を、ローカルの canonical markdown から安全に同期するときの標準手順です。

関連資料:
- [github-issue-workflow Skill](/.github/skills/github-issue-workflow/SKILL.md)
- [Comment & Sprint Format Reference](/.github/skills/github-issue-workflow/references/comment-format.md)

---

## 1. 目的

GitHub issue 本文はコメントよりも本文更新を正にする。部分的な修正を重ねず、1 つの canonical file から全体を同期する。

## 2. 使う場面

- issue のスコープやスプリント構成が変わった
- 完了メモ、進捗、GitHub 追跡ブロックを本文に反映したい
- コメントだけでは履歴が見づらいので、本文そのものを更新したい

## 3. 標準フロー

### Step 1 — canonical file を決める

- `.tmp/issue-<number>-body.md` のような単一のソースを決める
- GitHub 上の本文は source of truth にしない

### Step 2 — issue を先に読む

- `gh issue view <number> --repo torinky/LedgerLeap --json body,title`
- 既存の見出し、GitHub 追跡、未反映のメモを確認する

### Step 3 — body を全文で書き直す

- canonical file を必要に応じて更新する
- 追記ではなく全文置換を前提にする

### Step 4 — GitHub に反映する

- `gh issue edit <number> --repo torinky/LedgerLeap --body-file <canonical-file>`
- コメントの追記では本文の drift は解消しない

### Step 5 — 反映確認をする

- `gh issue view <number> --repo torinky/LedgerLeap --json body`
- 重要見出しが 1 回だけ出るかを確認する
- 旧表現や旧スプリント番号が残っていたら、再度 canonical file から全文を上書きする

## 4. 判定基準

- 本文は canonical file と一致している
- `GitHub 追跡` ブロックが最新の issue / sprint 番号を示している
- 進捗コメントは本文の補助であり、本文の代わりになっていない

## 5. 失敗しやすい点

- コメントだけで済ませて本文の更新を忘れる
- 既存本文に対して部分的に patch して、旧セクションを残す
- issue 番号や sprint 番号の drift を本文に残したままにする
- 反映確認をせず、編集成功だけをもって完了とみなす

## 6. 出力物

1. 更新済み canonical file
2. `gh issue edit` の反映
3. `gh issue view` による確認結果
4. 必要なら運用コメント

## 7. 完了条件

- [ ] canonical file が最新になっている
- [ ] GitHub issue 本文が全文置換されている
- [ ] 反映後の issue body を再取得して確認した
- [ ] 旧見出しや旧番号が残っていない
