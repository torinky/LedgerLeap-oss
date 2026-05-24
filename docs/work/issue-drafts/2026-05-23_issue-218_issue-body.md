# Sprint 1: 公開リポジトリ初期化と CI ミラー構築

## 概要
private のまま公開用リポジトリの土台を初期化し、プライベート main から同期できる仕組みを作ります。公開切替は全体計画完了後に行い、setup.sh の完走確認とデモログイン確認は Sprint 1-A (#223) に分離します。

## 作成タイミング
- 作成日時: 2026-05-23T08:08:26Z
- 位置づけ: Epic #216 の Sprint 1 として、Sprint 0 の公開範囲確定直後に起票
- 参照タイミング: 公開用ドキュメント整備の前提となる公開リポジトリ基盤の段階で作成

## このスプリントで作るもの
- private staging リポジトリの初期化手順
	- Evidence: `docs/work/2026-05-23_oss-publication-plan.md` §4.1 に初期化手順を明記済み
- `sync-to-public.yml` と公開同期の土台
	- Evidence: `docs/work/2026-05-23_oss-publication-plan.md` §4.2〜§4.4 で継続同期フローと除外管理を確定済み
- `.github/sync-excludes.txt` などの除外管理
	- Evidence: `.github/sync-excludes.txt` を新規作成済み
- Sprint 1-A (#223) へ引き渡すための参照整理
	- Evidence: issue #223 側で本体 baseline と `setup.sh` 完走確認を扱う前提を明記済み

このスプリントでは公開ドキュメント本文はまだ作らない。同期と公開基盤だけを先に用意する。

## 背景 / 目的
- 公開後のコントリビュータがまず触るのは、README とセットアップ手順
- 履歴は公開しない方針なので、初期公開はスカッシュされた状態から始める
- 以降の更新を手でコピーせず、CI で追従したい

## 現状
- `phpunit.yml` はすでにテスト用の CI を持っている
- ただし公開同期用ワークフローは未整備
- 公開用リポジトリ `LedgerLeap-oss` は作成済みで、全体計画完了まで private のまま保持する
- `sync-to-public.yml` の雛形を追加済みで、Phase 2 の同期基盤を private staging で整え始めた
- 初回コミットを private staging リポジトリへ push 済み（commit: `aafcb7bb3d2702e7bc159583f447a0734b6d08b3`）

## 目標 / 完了状態
- private staging リポジトリに初回コミットが push される
- 初回は orphan ブランチ由来の 1 コミットから始まる
- `sync-to-public.yml` がプライベート側で動作する
- GitHub 側で private main から private staging 側への同期可否を確認できる

## スコープ / 非スコープ
### 対象
- private staging リポジトリの bootstrap
- `sync-to-public.yml` の実装
- 除外パターンの管理（`.github/sync-excludes.txt`）
- ブランチ保護と force push 回避
- GitHub 側の同期可否確認

### 対象外
- ドキュメント本文の新規執筆
- AI 資産の切り出し
- 公開後のコミュニティ文書整備

## 方針候補 / メモ
1. 公開側は cherry-pick ベースでコミットを追加する
2. 公開側のコミットには private SHA を追跡できる印を残す
3. 逆同期は自動化せず、当面は手動で戻す

## Sprint 内で検討すべき詳細事項
- 公開対象コミットの判定条件
- 除外パスの保守方法
- 初回公開時のコミットメッセージ規約
- 公開リポジトリの branch protection 設定
- デモ動作の最小確認手順

## スプリント分解
- [x] 公開リポジトリの初期化方法を確定
	- Evidence: `docs/work/2026-05-23_oss-publication-plan.md` §4.1 で、private 側の orphan ブランチ `public-bootstrap` から初回 private staging コミットを作る方式を確定済み
- [x] CI ミラー実装方針を確定
	- Evidence: `docs/work/2026-05-23_oss-publication-plan.md` §4.2〜§4.4 で、`sync-to-public.yml` の継続同期フロー、除外管理、逆同期の扱いを確定済み
- [x] 除外パターンと秘密情報の扱いを確定
	- Evidence: `.github/sync-excludes.txt` を新規作成し、除外対象と秘密情報の扱いを明示済み。`docs/development/demo-environment-setup.md` のトークン例も `<generated-token>` に置換済み
- [x] private staging 側でのセットアップ・デモ検証手順を確定
	- Evidence: `bin/setup.sh` で環境を立ち上げ、`docs/development/demo-credentials.md` の `superadmin@example.com` / `demo@example.com` でログインして基本動作と権限差分を確認する手順に確定済み

## 完了判定
- このスプリントで作るものに挙げた 4 項目は、いずれも evidence 付きで揃っている。
- ただし、`## 完了条件` は公開リポジトリ実体の作成や同期動作確認に依存するため、Sprint 1 の issue 完了判定とは分けて扱う。
- 現時点の判定は「計画・検証成果物は完了、実運用の公開基盤は後続実装待ち」。

## エビデンス / 参照先
- `docs/work/2026-05-23_oss-publication-plan.md`
- `.github/workflows/phpunit.yml`
- `bin/setup.sh`
- `docker-compose.yml`
- `docs/development/demo-credentials.md`

## 完了条件
- [x] 公開用リポジトリの土台が作成されている（private 保持）
	- Evidence: `gh repo view torinky/LedgerLeap-oss` で `isPrivate: true` を確認済み。visibility の切り替えは全体計画完了後に延期
- [x] 初回コミットが private staging リポジトリに push される
	- Evidence: `public-bootstrap` の orphan commit `aafcb7bb3d2702e7bc159583f447a0734b6d08b3` を `staging:main` へ push 済み
- [x] 公開リポジトリへ実ファイルを反映する push step を実装する
	- Evidence: `sync-to-public.yml` で public repo を clone し、`rsync` で source snapshot を mirror して `git commit` → `git push origin HEAD:main` する処理を追加済み。コミットは `362b8b727b4d3009019da0345c8f6d5b09cd5c9d`。
- [x] プライベート main から private staging 側へ同期できる
	- Evidence: `git ls-remote --heads origin main` で private main を確認済み、`git ls-remote --heads staging main` で private staging を確認済み。rerun 26347054600 は `Preview public sync scope` と `Sync snapshot to public repo` が success し、`LedgerLeap-oss` の `pushedAt` は `2026-05-24T01:32:53Z`、`updatedAt` は `2026-05-24T01:33:00Z` に更新された。`staging/main` の head SHA は `3656fcc0f094a2318214343dccb566a25eb14966`。
	- 実行手順:
	  1. `.github/workflows/*` を公開同期対象に含めるかを決める。
	  2. 含める場合は `contents: write` に加えて `workflow` scope を持つ `PUBLIC_REPO_TOKEN` を GitHub 側で設定する。
	  3. 含めない場合は `.github/workflows/` を `.github/sync-excludes.txt` に追加し、除外方針を commit してから再 push する。
	  4. `PUBLIC_SYNC_ENABLED=true` を GitHub 側で設定する。
	  5. `main` へ対象変更を push するか、`workflow_dispatch` を実行する。
	  6. Actions の `Preview public sync scope` で `should_sync=true` と `included_files` を確認する。
	  7. `Sync snapshot to public repo` が success し、`LedgerLeap-oss` の `pushedAt` / `updatedAt` と file tree が更新されていることを確認する。
	  8. run URL、public commit SHA、変更内容を issue に evidence として記録する。

> `./bin/setup.sh` の完走確認とデモログイン確認は issue #223 に移し、ここでは GitHub 側の同期確認に集中する。

## 完了判定
- Sprint 1 の成果物リストは evidence 付きで揃っている。
- 公開化は全体計画完了後に延期するため、現時点の判定は **private staging 作業を進行中、公開切替待ち**。
- 次に必要なのは、issue #223 で本体 baseline と `setup.sh` 完走をローカルで確認し、その後に 218 側で GitHub の同期検証へ進むこと。

## ローカル確認 / GitHub 確認
### ローカルで確認すること
- `issue #223` で private staging baseline を作る
- `./bin/setup.sh` をクリーン環境で完走させる
- デモアカウントでログインできることを確認する

### GitHub 側に任せること
- `staging:main` への初回 push を受ける
- `sync-to-public.yml` の実行条件を確認する
- private main から private staging への同期可否を確認する
- visibility 切り替えのタイミングを制御する

## GitHub 追跡
- Epic: #216（本 Issue）
- Sprint 1-A: #223

## GitHub 再現手順
1. GitHub の private repo `torinky/LedgerLeap` を開く。
2. `Settings` → `Secrets and variables` → `Actions` を開く。
3. `Variables` で `PUBLIC_SYNC_ENABLED=true` を設定する。
4. `Secrets` で `PUBLIC_REPO_TOKEN` を登録する。`LedgerLeap-oss` を更新するなら `contents: write` に加えて `workflow` 権限を付ける。
5. `Actions` タブで `Sync to public` ワークフローを開く。
6. `Run workflow` で branch を `main` にして実行するか、`main` へ対象変更を push する。
7. `Preview public sync scope` で `should_sync` と `included_files` を確認する。
8. `Sync snapshot to public repo` が success し、`LedgerLeap-oss` の `pushedAt` / `updatedAt` と file tree の変化を確認する。
9. run URL、public commit SHA、変更内容を issue に evidence として記録する。

## `PUBLIC_REPO_TOKEN` の取得方法
1. GitHub の右上プロフィールから `Settings` を開く。
2. 左メニューの `Developer settings` を開く。
3. `Personal access tokens` → `Fine-grained tokens` を選び、`Generate new token` を押す。
4. トークン名を付け、必要なら有効期限を設定する。
5. `Repository access` で `Only select repositories` を選び、`torinky/LedgerLeap-oss` を対象にする。
6. `Repository permissions` で少なくとも `Contents: Read and write` を許可し、workflow ファイルを更新する場合は `workflow` 権限も付与する。
7. 必要に応じて `Metadata: Read-only` はそのまま付与する。
8. トークンを生成し、表示された値をコピーして `PUBLIC_REPO_TOKEN` として secret に登録する。
9. fine-grained token が使えない、または workflow 権限を付けられない場合は、workflow 更新権限付きの代替 PAT を管理者に依頼する。

## 同期の注意
- 現行の sync は repo ツリーを広く mirror するため、`.github/sync-excludes.txt` に入っていないものは `LedgerLeap-oss` に反映される。
- もし `LedgerLeap-oss` に含めたくないものが見つかったら、除外ファイルを更新してから再実行する。
