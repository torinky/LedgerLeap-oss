# Comment & Sprint Format — github-issue-workflow Reference

## Heading Templates

```markdown
## ✅ Sprint X 完了 — タイトル (YYYY-MM-DD)
## 📐 Sprint X〜Y 計画 — タイトル (YYYY-MM-DD)
## 🔍 調査タイトル (YYYY-MM-DD)
## 🐛 本番コードバグ修正 — Sprint X 発見分 (YYYY-MM-DD)
## 📊 カバレッジ評価タイトル (YYYY-MM-DD)
## 📝 ドキュメント更新 + 完了判定 (YYYY-MM-DD)
## 🧭 イシュー起草 — 機能 / 改善 / 言語 / 調査 (YYYY-MM-DD)
```

## Issue Drafting Entry Point

通常の起票フォーマットは `/.github/ISSUE_TEMPLATE/issue_request.yml` に集約する。
バグ / CI 失敗 / 回帰は `/.github/ISSUE_TEMPLATE/bug_report.yml` を使う。
コメント側では sprint 計画・完了報告・証拠の書式だけを扱う。

## Retrospective / 対応経緯メモ

Issue の完了後に後続者が追えるよう、必要に応じて次の小節を追記する。

```markdown
## 対応経緯メモ（YYYY-MM-DD）

### 問題
（何が起きたかを一文で）

### 原因
（どこで止まったか / 何が不足していたか）

### 修正
（どのファイル・どのシンボル・どの振る舞いを変えたか）

### 確認
（実行したテスト / UI 確認 / 期待した遷移先）

### 学び
（次回に再利用できるルールや、廃棄した誤り）
```

- 表示確認だけでなく、クリック後の遷移先や `href` の実値も併記する。
- tenant-aware な共有コンポーネントでは、親から受け取った tenant 情報を明示する。
- コミット参照を残すと、後続の再調査で変更差分を辿りやすい。

### 振り返り構造（深い調査・バグ修正後に推奨）

技術的な根本原因と作業プロセスを分離して記録すると、skill-maintenance への昇格が容易になる。

```markdown
## 対応経緯メモ（YYYY-MM-DD）

### 良かったこと
#### 技術要素
- ...

#### 作業の進め方
- ...

### 悪かったこと
#### 技術要素
- ...

#### 作業の進め方
- ...

### 上書き指示されたこと
#### 技術要素
- ...

#### 作業の進め方
- ...

### 修正・エビデンス
- コミット: `abc1234`
- テスト: `tests/Feature/Components/ConfidentialityStampTest.php`

### 学び（ reusable / local / retire ）
- `reusable`: tenant-aware 共有 Blade コンポーネントは親から `tenantId` を渡す
- `local`: ...
```

- `良かったこと` / `悪かったこと` / `上書き指示されたこと` は skill-maintenance の Retrospective Gate と同じ分類で記述する。
- 各項目を `技術要素` と `作業の進め方` に分けると、後続者が「どちらのレベルで学ぶべきか」を判断しやすい。
- 必ずコミット参照とテストファイルを記載し、証拠を辿れるようにする。

## Emoji Conventions

| Emoji | Meaning |
|---|---|
| ✅ | done / achieved |
| ❌ | failed / not met |
| ⚠️ | conditional / needs review |
| 🔴 | high priority / critical |
| 🟡 | medium / partial |
| 🟢 | low / good |
| 📊 | coverage / stats |
| 📐 | plan / design |
| 🔍 | investigation |
| 🐛 | bug fix |
| 🧭 | drafting / scoping |
| 🎉 | milestone achieved |
| 🏁 | final completion |

## Sprint Completion Comment Structure

```markdown
## ✅ Sprint X 完了 — タイトル (YYYY-MM-DD)

### 実施内容
（簡潔な説明）

### 新規テストファイル（X ファイル・計 X テスト）
#### `tests/path/TargetTest.php`（X テスト）
| テスト | 検証内容 |
|---|---|
| `test_method` | 説明 |

### エビデンス
```
Tests: X passed (Y assertions) / Duration: Zs
ClassName: XX.X% ✅（目標 Y% 達成）
```

### Sprint X 完了基準
- [x] 項目1 → **XX%** ✅
```

## Sprint Plan Comment Structure

```markdown
## 📐 Sprint X〜Y 計画 — タイトル (YYYY-MM-DD)

### 現状と目標
現在: X,XXX / XX,XXX 行 = XX.XX%
目標: +XXX 行 → XX.XX%

### 効率分析（コスパ順）
| # | ターゲット | 現在 | 見込み追加 | 優先度 |
|---|---|---|---|---|
| 1 | `ClassName` | 0% | +XX行 | 🔴 HIGH |

### Sprint X タスク
- [ ] 実施内容
```

## Completion Criteria Table

```markdown
| 完了基準 | 目標 | 実測値 | 判定 |
|---|---|---|---|
| ClassName 行カバレッジ | 60% | **93.85%** | ✅ 達成 |
| 全体行カバレッジ | 65% | **62.90%** | ❌ 未達 |
```

## Evidence Types

| Type | Example |
|---|---|
| Test result | `6 passed (12 assertions) / Duration: 2.3s` |
| Coverage | `` `ClassName`: 0% → **75.64%** ✅ `` |
| File change | `` `app/Livewire/Ledger/Show.php` の型を修正 `` |
| Commit ref | `commit a1b2c3d にて実装` |

## Parent/Child Issue Reference

```markdown
## 親Issue
#XX
```

Epic phase tracking:
```markdown
### Phase X: フェーズ名 ✅ 完了
**期間**: Week X-Y / **完了日**: YYYY-MM-DD
- [x] #XX サブイシュー

**実績**: X テスト追加 / ClassName XX%
```

