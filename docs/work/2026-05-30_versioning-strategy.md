# LedgerLeap バージョン番号戦略

**作成日:** 2026-05-30
**最終更新:** 2026-05-30
**ステータス:** 提案
**関連 Issue:** #216 (OSS 公開準備 Epic)
**関連ドキュメント:** `docs/work/2026-05-23_oss-publication-plan.md`（§12.9 CHANGELOG 参照）

---

## 1. 採用方式

**CalVer（JetBrains スタイル）: `YYYY.MINOR.PATCH`**

```
形式:   YYYY  . MINOR  . PATCH
例:     2026  . 1     . 0
        2026  . 1     . 0-alpha.1
        2026  . 2     . 0
        2027  . 1     . 0
```

### 1.1 選定理由

| 観点 | 判断 |
|------|------|
| LedgerLeap の性質 | Composer パッケージ（ライブラリ）ではなく**自己ホスト型アプリケーション**。SemVer の厳密な破壊的変更管理の必要性は低い |
| 現在の成熟度 | 主要機能が既に実装済みであり、`0.y.z` でスタートするのは実態と乖離する |
| 利用者視点 | 年号を Major に据えることで、ユーザーがサポート状況・鮮度を直感的に判断できる |
| エコシステム | Laravel エコシステムの慣習とは異なるが、GitHub Releases / Git Tag ベースの配布形態では実用上の支障なし |
| OSS 運用 | バージョン ⇔ リリース年 の対応が自明であり、脆弱性報告やサポートポリシーと相性が良い |

### 1.2 他方式との比較

| 方式 | 事例 | LedgerLeap 適性 |
|------|------|----------------|
| **SemVer** (`MAJOR.MINOR.PATCH`) | Laravel, Composer パッケージ多数 | 破壊的変更の明示には優れるが、初期バージョンが `0.1.0` になる違和感がある |
| **CalVer YY.MINOR.MICRO** | Ubuntu, Twisted | 月粒度は LedgerLeap のリリース頻度に対して過剰 |
| **CalVer YY.0M.MICRO** | Ubuntu | 同上 |
| **CalVer YYYY.MINOR.PATCH** | JetBrains, PyCharm, Unity | ✅ 採用。年粒度、構造が SemVer と同型で混乱が少ない |

---

## 2. プレリリース戦略

公開当初は段階的なプレリリースを実施し、品質を段階的に引き上げる。

### 2.1 リリース段階

| 段階 | バージョン例 | 目的 | 対象者 | GitHub Release 設定 |
|------|-------------|------|--------|-------------------|
| **Alpha** | `v2026.1.0-alpha.1`, `alpha.2` | 内部動作確認、クローズドテスト | 開発チーム、協力者 | `prerelease: true` |
| **Beta** | `v2026.1.0-beta.1`, `beta.2` | 公開テスト、Issue 受付開始 | アーリーアダプター | `prerelease: true` |
| **RC** | `v2026.1.0-rc.1` | 最終確認、リリース判定 | 全ユーザー | `prerelease: true` |
| **Stable** | `v2026.1.0` | 正式版 | 全ユーザー | `prerelease: false` |

### 2.2 プレリリース命名規則

- **alpha**: 機能未完成・既知の不具合あり。API/DB スキーマが変わりうる。
- **beta**: 全機能実装済み。重大な不具合のみ修正対象。外部テスト募集。
- **rc** (Release Candidate): 正式版と同等品質。重大な問題がなければそのまま Stable 化。

### 2.3 プレリリース期間の CHANGELOG 運用

`[Unreleased]` セクションに変更を蓄積し、Stable リリース時に確定する。

```markdown
## [Unreleased]
### Added
- 住所管理の基本CRUD
- ユーザー一括エクスポート機能

### Fixed
- Excelエクスポート時の文字化け修正 (beta.1 からの修正)
```

---

## 3. Git タグ運用

### 3.1 タグ命名

```bash
# プレリリース
git tag v2026.1.0-alpha.1
git tag v2026.1.0-beta.1
git tag v2026.1.0-rc.1

# 正式版
git tag v2026.1.0

# 機能追加リリース
git tag v2026.2.0

# バグ修正（パッチ）
git tag v2026.1.1

# 年跨ぎ
git tag v2027.1.0
```

- タグ名は `v` プレフィックス付き（GitHub Releases 標準表記）
- **annotated tag**（`-a` または `-m` 付き）を推奨

### 3.2 バージョン決定フロー

```
変更内容は？
├─ 年が変わった最初のリリース
│   └─ YYYY を今年に更新、MINOR を 1 にリセット（例: 2027.1.0）
├─ 破壊的変更がある
│   └─ CHANGELOG に明記し、MINOR を上げる（例: 2026.3.0）
├─ 新機能を追加した
│   └─ MINOR を上げる（例: 2026.2.0）
├─ バグ修正のみ
│   └─ PATCH を上げる（例: 2026.1.1）
├─ セキュリティ修正
│   └─ PATCH を上げる
└─ ドキュメントのみ
    └─ バージョン変更不要
```

---

## 4. リリースフロー

### 4.1 ブランチ連携

```
feature/xxx ──PR──→ develop ──PR──→ main
                                      │
                                git tag v2026.1.0
                                git push origin v2026.1.0
                                      │
                                GitHub Actions:
                                  ├─ Release 自動作成
                                  ├─ リリースノート自動生成
                                  └─ プレリリース自動判定
```

- `main` ブランチへのマージをトリガーにリリース作業を実施
- ホットフィックスは `main` からブランチを切り、修正後 `main` にマージ → PATCH バージョンタグ

### 4.2 GitHub Actions による自動化

`.github/workflows/release.yml` により、タグプッシュをトリガーに以下を自動化する。

| 自動化項目 | 内容 |
|-----------|------|
| GitHub Release 作成 | `softprops/action-gh-release@v2` を使用 |
| プレリリース判定 | タグ名に `alpha` / `beta` / `rc` を含む場合は `prerelease: true` |
| リリースノート生成 | 前回タグからのコミットログを自動収集 |

**手動で行う項目:**
- バージョン番号の決定とタグ作成
- `CHANGELOG.md` の `[Unreleased]` → バージョン確定
- リリースノートの補足編集（必要に応じて）

詳細実装は `docs/work/2026-05-30_release-workflow-implementation.md` に記載予定。

---

## 5. CHANGELOG 管理

### 5.1 形式

[Keep a Changelog](https://keepachangelog.com/ja/1.0.0/) 形式を採用する。

```markdown
# Changelog

本プロジェクトは [CalVer](https://calver.org/) に準拠します。
このフォーマットは [Keep a Changelog](https://keepachangelog.com/ja/1.0.0/) に従っています。

## [Unreleased]
### Added
- 新機能の説明

### Changed
- 既存機能の変更

### Fixed
- バグ修正の内容

## [2026.1.0] - 2026-06-15
### Added
- 住所管理の基本CRUD
- ユーザー一括エクスポート機能
```

### 5.2 変更種別

| 種別 | 説明 |
|------|------|
| `Added` | 新機能 |
| `Changed` | 既存機能の変更 |
| `Deprecated` | 非推奨化（削除予定） |
| `Removed` | 削除された機能 |
| `Fixed` | バグ修正 |
| `Security` | セキュリティ修正 |

### 5.3 運用ルール

- `[Unreleased]` セクションに変更を随時追記する
- 機能ブランチの PR マージ時に、PR 作成者が CHANGELOG 更新を推奨
- Stable リリース時に `[Unreleased]` をバージョン日付付きセクションに昇格
- このファイルは公開リポジトリ同期対象に含める（§5.1 参照）

---

## 6. Conventional Commits（推奨）

コミットメッセージに Conventional Commits を推奨する。CHANGELOG 自動生成や変更内容把握の効率が向上する。

```
feat: 住所の一括インポート機能を追加
feat(address)!: 住所テーブル構造を刷新
fix: Excelエクスポート時の文字化けを修正
docs: READMEにインストール手順を追加
```

| プレフィックス | 意味 |
|------|------|
| `feat` | 新機能 |
| `fix` | バグ修正 |
| `docs` | ドキュメント変更 |
| `refactor` | リファクタリング |
| `perf` | パフォーマンス改善 |
| `test` | テスト追加・修正 |
| `chore` | 雑務（ビルド、CI 等） |
| `BREAKING CHANGE`（フッター）または `!` | 破壊的変更 |

---

## 7. 既存ドキュメントとの整合

### 7.1 OSS 公開計画書との関係

`docs/work/2026-05-23_oss-publication-plan.md` §5.2 で CHANGELOG.md は公開対象として明記されている。本戦略はその具体化にあたる。

- `CHANGELOG.md` は公開リポジトリ同期対象（既存計画と一致）
- `docs/work/` 以下のバージョニング関連ドキュメントはプライベートリポジトリに留める

### 7.2 Issue #221（Sprint 4）との関係

§12.9 で CHANGELOG.md の新規作成は Sprint 4（#221）のスコープとして計画済み。本戦略は #221 の完了基準として参照される。

### 7.3 SECURITY.md との関係

`SECURITY.md` の Supported Versions セクションは、本戦略のバージョニングに基づいて最新の Stable バージョンを明記する。

---

## 8. 更新履歴

| 日付 | 変更内容 |
|------|---------|
| 2026-05-30 | 初版作成 |
