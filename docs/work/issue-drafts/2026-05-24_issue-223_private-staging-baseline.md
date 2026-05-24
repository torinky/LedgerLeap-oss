# Sprint 1-A: private staging baseline と setup 完走確認

## 概要
private staging リポジトリに、setup.sh を含む本体一式を積み、クリーン環境からのセットアップ完走とデモログイン確認を行います。Bootstrap 用の .github 基盤とは別に、アプリ本体の baseline を別スプリントとして切り出します。

## 背景 / 目的
- 現在の private staging は bootstrap 用の .github 最小基盤のみで、setup.sh を完走確認できる本体一式はまだ載っていない
- 本体 baseline と bootstrap を分けることで、どこで壊れたかを追いやすくし、以降の同期判定も明確にできる
- クリーンな private staging で setup.sh が最後まで通ることを、公開切替前の最低条件として確認したい

## 現状
- private staging リポジトリは作成済みで、初回 bootstrap commit は push 済み
- 現在の staging には `.github/workflows/sync-to-public.yml` と `.github/sync-excludes.txt` のみが入っている
- 変更中の作業ツリーには本体ファイルやドキュメントの修正が残っているため、そのままでは staging の baseline には使えない
- `bin/setup.sh` は存在するが、staging に本体一式が入っていないため、完走確認の前提がまだ整っていない

## 目標 / 完了状態
- private staging に setup.sh を含む本体一式が取り込まれている
- クリーンな環境で `./bin/setup.sh` が最後まで完走する
- setup 完了後にデモアカウントでログインできる
- 失敗時に、Docker / composer / migration / demo login のどこで止まったかを切り分けできる

## スコープ / 非スコープ
### 対象
- private staging に載せる本体 baseline の選定
- `./bin/setup.sh` の完走確認
- デモ Seeder とログイン確認
- 失敗箇所の切り分け記録

### 対象外
- 公開リポジトリへの visibility 切り替え
- 公開リポジトリへの実ファイル反映（issue #218 の Phase 2 で扱う）
- AI 資産の切り出し
- 公開ドキュメント本文の新規作成

## 方針候補 / メモ
1. まずは staging 用の baseline commit を 1 つ作り、その commit の上で setup.sh を実行する
2. 本体 baseline は、変更の余地がある作業ツリーと分離し、クリーンな worktree で作る
3. baseline commit の中には、将来変わる可能性が高い docs/work や個人設定を入れない

## スプリント分解
- [ ] private staging に載せる本体 baseline を確定
  - Evidence: 現在の staging には bootstrap 用 .github 基盤しかないため、どのファイルを追加するか先に固定する必要がある
- [ ] private staging baseline commit を作成し push
  - Evidence: private staging 側で `setup.sh` を含む本体一式を受ける初回 commit を作る
- [ ] `./bin/setup.sh` の完走確認
  - Evidence: クリーン環境で `./bin/setup.sh` を実行し、最後までエラーなく終わることを確認する
- [ ] デモログイン確認
  - Evidence: `superadmin@example.com` と `demo@example.com` でログインし、最低限の画面表示と権限差分を確認する

## エビデンス / 参照先
- `bin/setup.sh`
- `docs/development/environment-setup.md`
- `docs/development/demo-credentials.md`
- `docs/work/2026-05-23_oss-publication-plan.md`
- `docs/work/issue-drafts/2026-05-23_issue-218_issue-body.md`

## 完了条件
- [ ] private staging に setup.sh を含む本体一式が入っている
- [ ] `./bin/setup.sh` がクリーン環境で完走する
- [ ] デモアカウントでログインできる
- [ ] 失敗箇所の切り分け結果が issue 本文またはコメントに残っている

## 関連リンク
- Epic: #216
- Sprint 1: #218
- 既存の private staging bootstrap: `aafcb7bb3d2702e7bc159583f447a0734b6d08b3`

## 確認事項
- この issue はバグ報告ではなく、private staging baseline の詳細スプリントです
- 背景 / 現状 / 目標 / スコープ / スプリント分解 / 完了条件を分けて書いています