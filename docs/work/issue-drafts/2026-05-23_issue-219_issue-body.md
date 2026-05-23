# Sprint 2: 利用者・コントリビュータ向け公開ドキュメント新規作成

## 概要
既存 `docs/` をコピーせず、公開リポジトリに置くドキュメントを外部向けに新規作成します。対象は利用者、コントリビュータ、API/MCP 利用者です。

## 作成タイミング
- 作成日時: 2026-05-23T08:08:27Z
- 位置づけ: Epic #216 の Sprint 2 として、公開範囲と公開基盤の判断が揃った段階で起票
- 参照タイミング: 既存 docs を種本にした新規執筆フェーズの開始時点で作成

## このスプリントで作るもの
- `docs/README.md` の公開ドキュメント index
- `docs/getting-started/*` のセットアップ・デモ・設定ページ
- `docs/features/*` の利用者向け機能ガイド
- `docs/architecture/*` の構成・境界・データ説明
- `docs/contributing/*` の開発・テスト・運用ガイド
- `docs/api/*` の API / MCP 概要

このスプリントで作るのは、公開リポジトリに置く doc 群そのもの。内部の `docs/work/` はこのスプリントでは公開しない。

## 背景 / 目的
- 既存ドキュメントは内部経緯や実装記録が多い
- 公開用には、読み手別に「何をどう始めるか」が先に分かる構成が必要
- 初見の利用者がインストールからデモまで迷わない導線を作りたい

## 現状
- `docs/development/environment-setup.md` などは経緯記録として残っている
- 公開向けの `docs/getting-started/` / `docs/features/` / `docs/architecture/` は未作成
- `README.md` も公開向けのドキュメントハブとして作り直しが必要

## 目標 / 完了状態
- 利用者向けにインストール・デモ・設定がまとまっている
- コントリビュータ向けに開発環境・テスト・ブランチ運用がまとまっている
- API/MCP 利用者向けに概要が分かる
- 既存の紆余曲折ではなく、完成した使い方が読める
- すべての公開 doc が、1 ファイルごとの再構成で書かれている

## スコープ / 非スコープ
### 対象
- `docs/getting-started/*`
- `docs/features/*`
- `docs/architecture/*`
- `docs/contributing/*`
- `docs/api/*`
- `docs/README.md` の新規作成

### 対象外
- `docs/work/` の公開
- AI 資産の説明を公開ドキュメントへ混ぜること
- 内部調査ログを残すこと

## 方針候補 / メモ
1. 既存ドキュメントを「種本」にして、公開用はゼロベースで書く
2. 利用者・コントリビュータ・API 利用者で章を分ける
3. 各ページの末尾に、必要最小限の補足と関連リンクだけを置く

## Sprint 内で検討すべき詳細事項
- どの既存 docs を参照元にするか
- どこまで図や表を入れるか
- デモ手順にどのアカウントを載せるか
- API/MCP の公開範囲をどこまでにするか
- 英語/日本語の比率をどうするか

## スプリント分解
- [ ] `docs/README.md` の構成を決める
- [ ] Getting Started の最小セットを決める
- [ ] 機能ガイドの対象範囲を決める
- [ ] 開発者向けガイドの構成を決める
- [ ] API/MCP の公開範囲を決める
- [ ] 既存 docs をそのまま流用せず、新規執筆の切り出し基準を固める

## エビデンス / 参照先
- `docs/work/2026-05-23_oss-publication-plan.md`
- `docs/development/environment-setup.md`
- `docs/development/MCP_Architecture_and_Flow.md`
- `docs/development/Testing-Best-Practices.md`
- `docs/features/related-ledgers.md`
- `docs/development/demo-credentials.md`

## 完了条件
- [ ] 公開用 `docs/` の新規構成が確定している
- [ ] 利用者向けページが作成されている
- [ ] コントリビュータ向けページが作成されている
- [ ] API/MCP 利用者向けページが作成されている
- [ ] 既存 docs のコピーではなく、新規執筆になっている
