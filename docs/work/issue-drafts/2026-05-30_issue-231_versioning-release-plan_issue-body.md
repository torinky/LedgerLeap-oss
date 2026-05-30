# バージョン番号戦略の導入と初回リリース準備 (CalVer `YYYY.MINOR.PATCH`)

## 概要
LedgerLeap の OSS 公開準備に伴い、バージョン番号の運用を開始する。
JetBrains スタイルの CalVer (`YYYY.MINOR.PATCH`) を採用し、Alpha → Beta → RC → Stable の段階的リリースを行う。
また GitHub Actions によるリリース自動化と CHANGELOG.md の運用を整備する。

## 作成タイミング
- 作成日時: 2026-05-30
- 位置づけ: Epic #216 の後続 Issue として、公開前のバージョニング基盤を整備するために起票
- 参照タイミング: 公開範囲の境界（#217）と CI 同期（#218）の基盤が整い、コミュニティ基盤（#221）の具体化が必要な段階で実施

## この Issue で作るもの
- `docs/work/2026-05-30_versioning-strategy.md`（バージョン番号戦略の全体設計文書）
- `CHANGELOG.md`（Keep a Changelog 形式の初期セットアップ）
- `.github/workflows/release.yml`（タグプッシュ自動リリース CI）
- 初回リリースタグと GitHub Release（`v2026.1.0-alpha.1` → `v2026.1.0`）

## 背景 / 目的
- 現状、バージョン番号の運用が存在せず、composer.json の `version` フィールドも未設定
- OSS 公開にあたり、利用者・コントリビュータが変更を追跡できる仕組みが必要
- `SECURITY.md` の Supported Versions を明確にするためにもバージョニング基盤が必要
- JetBrains スタイルの CalVer は、機能が充実している LedgerLeap の現状と自然に整合し、バージョンからリリース年を直感的に判断できる

## 現状
- `CHANGELOG.md` は未作成
- `composer.json` の `name` は `laravel/laravel`、`version` は未設定
- `SECURITY.md` の Supported Versions は実態に合わせた更新が必要
- `.github/workflows/` にリリース自動化ワークフローは未設置
- 既存の OSS 公開計画書（#216）§12.9 で CHANGELOG 作成は #221 のスコープとされている

## 目標 / 完了状態
- [ ] バージョン番号戦略が決定され、`docs/work/2026-05-30_versioning-strategy.md` に文書化されている
- [ ] `CHANGELOG.md` が作成され、Keep a Changelog 形式で運用開始されている
- [ ] `.github/workflows/release.yml` が稼働し、タグプッシュで自動リリースされる
- [ ] プレリリース判定（alpha/beta/rc の自動検出）が動作している
- [ ] 初回バージョンタグが打たれ、GitHub Release が作成されている

## スコープ / 非スコープ

### 対象
- バージョン番号方式の決定と文書化
- CHANGELOG.md の作成と初期セクションの記入
- GitHub Actions によるリリース自動化ワークフロー
- タグ命名規則の確定（`vYYYY.MINOR.PATCH[-alpha.N][-beta.N][-rc.N]`）
- composer.json の `name` / `version` フィールド修正
- SECURITY.md の Supported Versions をバージョニング戦略と整合させる

### 対象外
- Conventional Commits の強制導入（推奨に留める）
- semantic-release / release-please 等の導入（手動タグ + 自動 Release で開始し、必要に応じて後日検討）
- バージョニングに基づく自動アップグレードパスの実装

## スプリント分解

### Phase 1: 戦略策定
- [ ] バージョン番号方式（CalVer `YYYY.MINOR.PATCH`）の決定とチーム内合意
  - Evidence: `docs/work/2026-05-30_versioning-strategy.md` が作成されていること
- [ ] composer.json の `name` を `ledgerleap/ledgerleap` に修正
  - Evidence: `composer.json` の `name` フィールド確認
- [ ] タグ命名規則・プレリリース規則の文書化完了
  - Evidence: 戦略文書 §2-3 が記述済み

### Phase 2: 基盤構築
- [ ] `CHANGELOG.md` を作成（Keep a Changelog 形式、`[Unreleased]` セクション付き）
  - Evidence: `CHANGELOG.md` がリポジトリルートに存在すること
- [ ] `.github/workflows/release.yml` を作成
  - Evidence: ワークフローファイルが存在すること
- [ ] プレリリース判定ロジックの動作確認
  - Evidence: alpha タグのプッシュで `prerelease: true` の Release が作成されること

### Phase 3: Alpha リリース (`v2026.1.0-alpha.1`)
- [ ] `SECURITY.md` の Supported Versions テーブルを更新
  - Evidence: `SECURITY.md` に `2026.1.0-alpha.1` のエントリが追加されていること
- [ ] `git tag v2026.1.0-alpha.1` の作成・プッシュ
  - Evidence: GitHub Release (Pre-release) が自動生成されていること
- [ ] クローズドな範囲での動作確認・不具合収集

### Phase 4: Beta リリース (`v2026.1.0-beta.1`)
- [ ] Alpha で収集した不具合の修正
  - Evidence: 修正内容が `CHANGELOG.md` の `[Unreleased]` に追記されていること
- [ ] 全機能の動作確認完了
- [ ] DB マイグレーションの正常動作確認（新規セットアップ + アップグレード両方）
- [ ] セットアップ手順ドキュメントの整備
- [ ] `git tag v2026.1.0-beta.1` の作成・プッシュ
- [ ] 公開 Issue 受付開始

### Phase 5: RC リリース (`v2026.1.0-rc.1`)
- [ ] Beta で報告された全バグの修正
- [ ] 回帰テスト実施
- [ ] 最終ドキュメントレビュー
- [ ] `git tag v2026.1.0-rc.1` の作成・プッシュ

### Phase 6: 正式版リリース (`v2026.1.0`)
- [ ] RC で報告された全バグの修正
- [ ] `CHANGELOG.md` の `[Unreleased]` を `[2026.1.0] - YYYY-MM-DD` に確定
- [ ] `git tag v2026.1.0` の作成・プッシュ
- [ ] `SECURITY.md` の Supported Versions を Stable として更新
- [ ] リリースアナウンス

## 完了条件
1. `v2026.1.0` タグが GitHub に存在し、Release が作成されている（`prerelease: false`）
2. `CHANGELOG.md` に `[2026.1.0]` セクションが存在し、変更点が列挙されている
3. `.github/workflows/release.yml` が正常動作し、`alpha`/`beta`/`rc` のプレリリース判定が正しく機能している
4. `composer.json` の `name` と `version` が正しく設定されている
5. `SECURITY.md` の Supported Versions が最新の Stable バージョンを指している

## エビデンス / 参照先
- `docs/work/2026-05-30_versioning-strategy.md`（バージョン番号戦略の全体設計）
- `docs/work/2026-05-23_oss-publication-plan.md`（OSS 公開移行計画、§5.2 / §12.9）
- `CHANGELOG.md`（作成後）
- `.github/workflows/release.yml`（作成後）
- `SECURITY.md`
- `composer.json`

## 参考資料
- [Calendar Versioning](https://calver.org/)
- [Keep a Changelog](https://keepachangelog.com/ja/1.0.0/)
- [Conventional Commits](https://www.conventionalcommits.org/ja/v1.0.0/)
- [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html)
