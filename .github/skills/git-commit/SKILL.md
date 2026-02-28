---
name: git-commit
description: >
  コミットメッセージの作成とgit commitコマンドの実行手順。
  日本語を含むメッセージのシェル文字化け問題と、Conventional Commits規約を一元管理する。
  コミット操作を行う際は必ずこのスキルを参照すること。
---

# git commit 操作スキル

## このスキルを使うタイミング

- `git commit` を実行するとき（**毎回必ずこのスキルを参照すること**）
- コミットメッセージのフォーマットに迷ったとき
- コミットが文字化けで失敗したとき

---

## 1. コミットメッセージのフォーマット（Conventional Commits）

LedgerLeap では **Conventional Commits** 形式を採用。メッセージは**日本語**で記述する。

```
<type>(<scope>): <subject>

<body>

<footer>
```

### type 一覧

| type | 用途 |
|---|---|
| `feat` | 新機能の追加 |
| `fix` | バグ修正 |
| `docs` | ドキュメントのみの変更 |
| `style` | コードの整形・フォーマット変更（動作に影響しないもの）|
| `refactor` | リファクタリング（バグ修正・機能追加を含まない）|
| `perf` | パフォーマンス改善 |
| `test` | テストコードの追加・修正 |
| `build` | ビルドシステム・外部依存関係の変更 |
| `ci` | CI/CD 設定ファイルの変更 |
| `chore` | 上記いずれにも当てはまらない雑多な変更 |
| `revert` | 以前のコミットの取り消し |

### subject のルール

- 50文字以内
- 体言止め、または「〜する」「〜した」で記述
- 例: `ユーザー認証機能を追加`、`台帳検索時のN+1問題を修正`

### コミットメッセージの例

```
feat(auth): ユーザー登録機能のAPIエンドポイント実装

ユーザーがメールアドレスとパスワードで新規登録できるAPIを追加。
登録成功時にはユーザートークンを返却する。

Closes #42
```

---

## 2. コミット実行手順（文字化け対策）

### ❌ やってはいけない方法

```bash
# 日本語・記号($, (, ), `, #, ! など)を含むメッセージを -m で渡すと文字化け・展開が起きる
git commit -m "feat(scope): 日本語の説明 $変数 (詳細)"
```

**失敗する理由:**
- `$変数` → シェル変数として展開される
- `(` `)` → サブシェルとして解釈される場合がある
- `` ` `` → コマンド置換として展開される
- `!` → history展開される場合がある

---

### ✅ 正しい方法: Python でメッセージファイルを作成してから -F で渡す

#### ステップ1: メッセージファイルを Python で作成する

```python
python3 -c "
open('/tmp/commit_msg.txt', 'w').write('''feat(scope): 件名（50文字以内）

変更の詳細説明。
なぜこの変更が必要か、何を変えたかを記述。

Closes #123
''')
print('OK')
"
```

**Python を使う理由:**
- `cat > file << 'EOF'` はメッセージに `$`, `(`, `)`, `#` 等が含まれると失敗する
- Python の文字列リテラルはシェル展開を受けない
- ファイルが既に存在する場合は `open(..., 'w')` で上書きできる

#### ステップ2: 内容を確認する

```bash
cat /tmp/commit_msg.txt
```

#### ステップ3: `git commit -F` でコミットする

```bash
git commit -F /tmp/commit_msg.txt
```

---

## 3. よくある失敗パターンと対処

### パターン1: `cat > /tmp/commit_msg.txt << 'EOF'` が空ファイルになる

**原因:** メッセージに `$`, `(`, `)`, `` ` ``, `#` 等が含まれていると heredoc が途中で終了する。

**対処:** Python で作成する（§2 を参照）。

### パターン2: `/tmp/commit_msg.txt` に古い内容が残っている

**原因:** 前回のコミット操作で作成したファイルが残っている。

**対処:**
```bash
rm /tmp/commit_msg.txt
python3 -c "open('/tmp/commit_msg.txt', 'w').write('...')"
```

### パターン3: `python3 -c` 内でシングルクォートが使えない

**原因:** Python の `-c` 引数がシングルクォートで囲まれているため、内部でシングルクォートが使えない。

**対処:** Python 内でダブルクォートを使う。または `\\'` でエスケープする。

```python
# OK: ダブルクォートを使う
open('/tmp/commit_msg.txt', 'w').write("feat: 説明")

# OK: \\' でエスケープ（シェルから渡す場合）
python3 -c 'open("/tmp/commit_msg.txt", "w").write("feat: 説明")'
```

---

## 4. 複数ファイルのステージングからコミットまでの完全手順

```bash
# 1. 変更状態を確認
git status --short

# 2. 必要なファイルをステージング
git add path/to/file1 path/to/file2

# 3. ステージング内容を確認
git status --short

# 4. メッセージファイルを Python で作成
python3 -c "
open('/tmp/commit_msg.txt', 'w').write('''docs(testing): ドキュメントを分割

1529行の単一ファイルをテーマ別に分割し参照性を向上。

変更ファイル
- docs/development/testing/ 以下に新規作成
- Testing-Best-Practices.md を転送案内に縮小
''')
print('OK')
"

# 5. 内容確認
cat /tmp/commit_msg.txt

# 6. コミット
git commit -F /tmp/commit_msg.txt

# 7. プッシュ
git push origin main
```

---

## 5. 参考ドキュメント

- [Gitブランチ戦略とコミット規約](/docs/development/branch_strategy.md) — 公式のtype一覧・フォーマット定義

