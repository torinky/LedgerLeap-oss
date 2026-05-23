# Sprint 0: 公開前安全確認と公開範囲の確定

## 概要
公開リポジトリへ出す前に、秘密情報・本番固有設定・内部向けドキュメントの扱いを確定し、公開してよい範囲を明確にします。

## 作成タイミング
- 作成日時: 2026-05-23T08:08:25Z
- 位置づけ: Epic #216 の Sprint 0 として、公開範囲の境界を先に固める目的で起票
- 参照タイミング: 公開移行計画の初期策定と同時期に作成

## このスプリントで作るもの
- 公開前判断用の安全確認メモ
- 公開禁止候補と除外ルールの整理
- `SECURITY.md` の改訂方針メモ
- 次スプリントへ渡す完了レポートと issue 更新用の根拠

このスプリントでは公開用ドキュメント本文はまだ作らない。まず、公開可否を決めるための材料を揃える。

## 背景 / 目的
- 既存リポジトリには内部向けの痕跡が多く、公開前に一括で洗い出したい
- `docs/` は内部実装記録が中心なので、公開する/しないの仕分けを先に固定したい
- 以降の Sprint が安全に進められるよう、境界条件を先に固める

## 現状
- `.env.example` はあるが、関連する開発・本番設定は複数ファイルに分散している
- `docs/work/` には紆余曲折を含む作業ログが多い
- `docs/development/environment-setup.md` は実装経緯の記録であり、そのまま公開向けではない
- `SECURITY.md` はテンプレート状態のため、実態へ更新が必要

## 目標 / 完了状態
- 公開禁止情報がどこにあるか把握できている
- 公開対象/非対象のルールが文書化されている
- `SECURITY.md` と `.gitignore` の公開向け調整方針が固まっている

## スコープ / 非スコープ
### 対象
- `trufflehog` によるシークレット走査
- `.env.*` / `docker-compose.prod.yml` / `prod.sh` の扱い確認
- `SECURITY.md` の改訂方針確認
- `docs/work/` を公開しない方針の明文化

### 対象外
- Git 履歴の書き換え
- まだ公開リポジトリを作らないこと自体の是非検討
- AI 資産の切り出し実作業

## 方針候補 / メモ
1. シークレット検出は `trufflehog` を一次判定にする
2. 公開範囲の判断に迷うものは、利用者/コントリビュータ向けに新規作成へ寄せる
3. `docs/work/` は公開しない前提で扱い、種本としてのみ利用する

## Sprint 内で検討すべき詳細事項
- どの設定ファイルを公開側へ残すか
- どの `docs/` を新規作成対象とするか
- `SECURITY.md` に何を載せるか
- 公開前に手動確認が必要なファイルはどれか

## スプリント分解
- [x] シークレット検出と公開禁止候補の列挙
  - Evidence: 軽量走査で `docs/development/demo-environment-setup.md` のトークン形式出力例を確認し、`docs/work/2026-05-23_oss-publication-plan.md` §4.3 / §5.3 で除外候補を確認済み
- [x] `docs/` の公開/非公開方針を確定
  - Evidence: `docs/work/2026-05-23_oss-publication-plan.md` §3.1-§3.3 と §5.2-§5.3、加えて `laravel/laravel` / `filamentphp/filament` / `spatie/laravel-permission` の公開構成比較を実施済み
- [x] `SECURITY.md` の改訂ポイントを確定
  - Evidence: `SECURITY.md` に Supported Versions / private reporting / no public issue / response flow / secret handling の方針を追記し、`laravel/laravel` / `spatie/laravel-permission` / `Next.js` / `React` / `VS Code` の SECURITY guidance を参照済み
- [x] 公開対象から除外するファイル一覧を固める
  - Evidence: `docs/work/2026-05-23_oss-publication-plan.md` §4.3 / §5.3 に除外リストを整理済み

## エビデンス / 参照先
- `docs/work/2026-05-23_oss-publication-plan.md`
- `.gitignore`
- `.env.example`
- `SECURITY.md`
- `docs/development/environment-setup.md`
- `docs/development/demo-credentials.md`

## 完了条件
- [x] シークレット走査結果が確認できた
  - Evidence: 軽量走査で `docs/development/demo-environment-setup.md` の token 例を確認し、除外候補の列挙も完了
- [x] 公開/非公開の境界が文書化された
  - Evidence: `docs/work/2026-05-23_oss-publication-plan.md` と issue 完了レポートで方針を固定済み
- [x] `SECURITY.md` の改訂方針が決まった
  - Evidence: 公開 issues を使わず private channel で報告を受けること、受領確認と進捗共有を行うこと、Supported Versions を明記することを決定済み
- [x] 後続 Sprint が参照できる除外パターンが用意された
  - Evidence: `docs/work/2026-05-23_oss-publication-plan.md` §4.3 / §5.3 に除外候補を集約済み

