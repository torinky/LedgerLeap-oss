# 不具合対応プレイブック

LedgerLeap で開発中に不具合、例外、CI失敗、挙動不良が発生したときの標準フローです。

関連資料:
- [Bug Investigation Prompt](/.github/prompts/bug-investigation.prompt.md)
- [Bug Execution Prompt](/.github/prompts/bug-execution.prompt.md)
- [Bug Investigation Template](/docs/templates/bug-investigation-template.md)
- [AI Asset Maintenance Playbook](/docs/runbooks/ai-asset-maintenance-playbook.md)
- [CI Failure Investigation Skill](/.github/skills/ci-failure-investigation/SKILL.md)

---

## 1. 目的

次を毎回同じ順序で行えるようにする。

1. 症状を整理する
2. 証拠を集める
3. 類似事例とベストプラクティスを調べる
4. 仮説を比較する
5. 対応方針を決める
6. 最小変更で修正する
7. 検証し、学びを残す

---

## 2. 使い分け

### まず使うもの
- 調査: `/.github/prompts/bug-investigation.prompt.md`
- 実装: `/.github/prompts/bug-execution.prompt.md`
- 記録: `docs/templates/bug-investigation-template.md`
- CI専用: `/.github/skills/ci-failure-investigation/SKILL.md`

### 入口
- GitHub Issue を起票する場合は `/.github/ISSUE_TEMPLATE/bug_report.yml` を使う
- 既存 Issue がある場合も、証拠・仮説・対応方針は investigation template に寄せる

---

## 3. 標準フロー

## Step 1 — Intake

最低限、次を揃える。

- 期待される動作
- 実際の動作
- 再現手順
- 発生環境
- 影響範囲
- ログ / エラー / スクリーンショット

不明点は空欄のままにせず、「不明」と書く。

## Step 2 — Triage

最初に次を判断する。

- まず被害拡大防止が必要か
- ローカル問題か、tenant 固有か、全体影響か
- 実装修正に進んでよいか、先に調査を広げるべきか
- CI / 権限 / 検索 / Livewire / 外部サービス など、どの系統の問題か

## Step 3 — Evidence Collection

証拠は次の順で集める。

1. ログ / stack trace / browser logs / failing test / CI log
2. 関連コード、呼び出し元、使用箇所、直近変更
3. 既存 docs、runbook、skills、過去の debug log
4. 類似実装、類似修正、類似 issue

### CI / GitHub Actions quick checks

- まず `gh auth status`
- `gh run list` は **plain text から始める**
- run の job 一覧は `gh api repos/.../actions/runs/{RUN_ID}/jobs --jq ...` を優先する
- log は `gh api /repos/.../actions/jobs/{JOB_ID}/logs > file` で保存してから `grep` する
- `python3 -c` による JSON 整形は最終 fallback。macOS では Xcode license 未同意で止まることがある
- 長い shell quoting は避け、取得と整形を 2 ステップに分ける

### LedgerLeap 固有の quick checks

- tenancy 初期化漏れ
- permission cache / tenant access cache の未クリア
- Mroonga の single-column full-text 制約
- Livewire public state に object が混ざっていないか
- `#[Lazy]` で tenant 参照を `tenant()?->id` に依存していないか
- テストが Embedding / OCR / LDAP へ依存していないか
- Tailwind utility 追加後に build が必要ではないか

## Step 4 — External Research

内部証拠を確認したあとに、次の順で調べる。

1. 公式ドキュメント / 公式ガイド
2. package docs / release notes
3. GitHub Issues / Discussions
4. 類似 OSS 実装
5. 信頼できる技術記事

外部調査では、次を分けて記録する。

- 類似実装事例
- 類似エラー事例
- ベストプラクティス

## Step 5 — Hypothesis Review

仮説は複数並べる。

- 仮説A / B / C
- 各仮説の根拠
- 反証
- 信頼度

誤っていた仮説や失敗した切り分けも残す。

## Step 6 — Response Design

対応方針は複数案から選ぶ。

- Option A: 最小修正
- Option B: 構造修正
- Option C: 暫定回避

最終的に 1 つを推奨し、次を明記する。

- 推奨理由
- 影響範囲
- リスク
- rollback 方法
- 検証方法

## Step 7 — Execution

実装時の原則:

- 根本原因に近い箇所を最小変更で直す
- 必要に応じて回帰テストを追加する
- 無関係な refactor を混ぜない
- 変更後に lint / test / smoke test を行う

## Step 8 — Validation

変更内容に応じて、以下を選んで実行する。

- `./vendor/bin/sail pint`
- `./vendor/bin/sail test`
- 対象テストのみ
- browser logs / UI smoke
- `./vendor/bin/sail npm run build`（Tailwind utility 追加時）

結果は PASS / FAIL で残す。

## Step 9 — Learnings

修正後は次を更新候補として確認する。

- `docs/templates/bug-investigation-template.md`
- この playbook
- 関連 skill / prompt
- 該当機能の docs
- `docs/runbooks/ai-asset-maintenance-playbook.md`

同じパターンが再発しそうなら、負の結果も含めて記録する。
再利用可能な学びが確定したら、**完了前に `/skill-maintenance` を実行**して `.github` 資産へ同期する。

---

## 4. 出力物

1件の不具合対応で最低限残すもの:

1. Issue または同等のチケット
2. 調査記録
3. 実装結果
4. 検証結果
5. 必要なら follow-up

---

## 5. 完了条件

以下を満たしたら完了。

- [ ] 期待値 / 実際値 / 再現手順が整理されている
- [ ] 内部証拠を確認した
- [ ] 外部調査を必要範囲で行った
- [ ] 仮説比較のうえで対応方針を選んだ
- [ ] 実装 / lint / test / smoke を実施した
- [ ] rollback と残リスクを明記した
- [ ] 学びを docs / prompt / skill に反映するか判断した
- [ ] 再利用可能な学びは `/skill-maintenance` で `.github` に同期した
