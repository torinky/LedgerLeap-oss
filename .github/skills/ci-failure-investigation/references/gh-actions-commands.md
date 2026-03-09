# GitHub Actions / `gh` 安定コマンド集

CI の実行状況確認で毎回同じところに詰まらないように、LedgerLeap で実績のある順に並べています。

## 1. まずは plain text で run を見る

```bash
gh run list --repo torinky/LedgerLeap --limit 10

gh run list --repo torinky/LedgerLeap \
  --workflow "Laravel CI (PHPUnit / Pest)" \
  --branch develop \
  --limit 5

gh run list --repo torinky/LedgerLeap \
  --workflow "Parallel Tests Canary (Sprint 4)" \
  --branch develop \
  --limit 5
```

理由:
- `--json` から始めるより壊れにくい
- run ID / workflow / conclusion を先に人間が確認しやすい

## 2. commit SHA が分かっているとき

```bash
gh run list --repo torinky/LedgerLeap --commit {SHA} --limit 10
```

## 3. job 名と conclusion を取る

```bash
gh api repos/torinky/LedgerLeap/actions/runs/{RUN_ID}/jobs \
  --jq '.jobs[] | [.name, .databaseId, .conclusion] | @tsv'
```

## 4. fail step だけ見る

```bash
gh api repos/torinky/LedgerLeap/actions/runs/{RUN_ID}/jobs \
  --jq '.jobs[] | {name, failedSteps: [.steps[] | select(.conclusion != null and .conclusion != "success") | .name]}'
```

## 5. job log を落として grep する

```bash
gh api /repos/torinky/LedgerLeap/actions/jobs/{JOB_ID}/logs > /tmp/ci-job.log

grep -E "FAIL|Exception|timeout|SQLSTATE|Error" /tmp/ci-job.log | head -40
```

## 6. うまくいかないときの fallback

### `gh run list --json ...` が空に見える
- まず plain-text `gh run list` に戻る
- 次に `gh api repos/.../actions/runs/{RUN_ID}/jobs --jq ...` を使う

### `gh run view --json ...` が不安定
- `gh run view` に依存せず `gh api repos/.../actions/runs/{RUN_ID}/jobs` を使う

### shell quoting が壊れる
- 1 コマンドを短く保つ
- `bash -c` の中にさらに複雑な引用を重ねない
- 取得 → 整形を 2 ステップに分ける

### macOS で `python3` が Xcode license を要求する
- `python3 -c` を使わない
- `gh api --jq` に切り替える
- どうしても Python を使う必要がある場合のみ、事前に `sudo xcodebuild -license` が必要

## 7. 推奨順序

1. `gh auth status`
2. `gh run list`（plain text）
3. `gh run list --workflow ... --branch ...`
4. `gh api repos/.../actions/runs/{RUN_ID}/jobs --jq ...`
5. `gh api /repos/.../actions/jobs/{JOB_ID}/logs > file`
6. `grep` / `head` で絞る

