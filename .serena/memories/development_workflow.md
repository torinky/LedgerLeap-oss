# LedgerLeap 開発ワークフロー

## ブランチ戦略

### ブランチ命名規則
```
feature/<issue-id>-<feature-name>   # 新機能開発
bugfix/<issue-id>-<bug-name>        # バグ修正
hotfix/<issue-id>-<fix-name>        # 緊急修正
```

### 現在のブランチ
- **メインブランチ**: `main`
- **現在の作業ブランチ**: `feature/rag-phase1-planning`（LLM統合機能開発中）

## 開発フロー

### 1. 機能開発の開始
```bash
# 最新のmainブランチを取得
git checkout main
git pull origin main

# 新しいブランチを作成
git checkout -b feature/<issue-id>-<feature-name>
```

### 2. 開発環境のセットアップ
```bash
# 開発環境起動
./vendor/bin/sail up -d

# 依存関係が更新されている場合
./vendor/bin/sail composer install
./vendor/bin/sail npm install

# マイグレーション実行（必要に応じて）
./vendor/bin/sail artisan migrate
```

### 3. 開発サイクル
```bash
# アセットのウォッチ（別ターミナル）
./vendor/bin/sail npm run dev

# コード編集
# ...

# テスト実行（頻繁に）
./vendor/bin/sail test

# または Pest
./vendor/bin/sail pest
```

### 4. コミット前の準備
```bash
# コード整形（必須）
./vendor/bin/sail pint

# 全テスト実行（必須）
./vendor/bin/sail test

# 変更内容の確認
git status
git diff
```

### 5. コミット
```bash
# ステージング
git add .

# コミット（規約に従う）
git commit -m "feat(scope): 日本語での説明"

# 例:
# git commit -m "feat(auth): ユーザー登録APIエンドポイント実装"
# git commit -m "fix(ledger): 全文検索の類義語展開バグ修正"
# git commit -m "docs(readme): セットアップ手順を更新"
```

### 6. プッシュとプルリクエスト
```bash
# リモートにプッシュ
git push origin feature/<issue-id>-<feature-name>

# GitHub上でプルリクエストを作成
# - タイトル: わかりやすく簡潔に
# - 説明: 変更内容、理由、影響範囲を記載
# - レビュワーを指定
```

## コミットメッセージ規約

### フォーマット
```
<type>(scope): <subject>

[optional body]

[optional footer]
```

### Type一覧
- `feat`: 新機能
- `fix`: バグ修正
- `docs`: ドキュメント変更
- `style`: コードスタイル修正（動作変更なし）
- `refactor`: リファクタリング
- `perf`: パフォーマンス改善
- `test`: テスト追加・修正
- `chore`: ビルド、設定、依存関係の変更

### Scope例
- `auth`: 認証・認可
- `ledger`: 台帳機能
- `search`: 検索機能
- `workflow`: ワークフロー
- `api`: API
- `ui`: ユーザーインターフェース
- `db`: データベース
- `deps`: 依存関係

### 良いコミットメッセージの例
```
feat(auth): ユーザー登録APIエンドポイント実装

Laravel Sanctumを使用したAPI認証を追加。
トークン発行・管理機能も実装。

Closes #123

---

fix(ledger): 全文検索の類義語展開バグ修正

Mroongaの制約により複合インデックスが使用できないため、
OR結合を使用した検索クエリに修正。

Fixes #456

---

docs(readme): セットアップ手順を更新

GPU環境のセットアップ手順を追加。
PaddleOCR-VLの設定方法を明記。
```

## テスト戦略

### テストの種類
1. **ユニットテスト**: クラス/メソッド単体のロジック検証
2. **フィーチャーテスト**: 機能全体の統合テスト

### テスト実行タイミング
- **頻繁**: コード変更のたびに関連テスト実行
- **コミット前**: 全テスト実行（必須）
- **プッシュ前**: 全テスト実行（必須）

### 全文検索機能のテスト
```php
use Illuminate\Foundation\Testing\DatabaseMigrations;

class LedgerSearchTest extends TestCase
{
    use DatabaseMigrations; // RefreshDatabase不可
    
    public function test_mroonga_search()
    {
        $ledger = Ledger::factory()->create([
            'content' => ['title' => 'テスト台帳']
        ]);
        
        sleep(1); // インデックス更新待機
        
        $results = Ledger::scopeSearch('テスト')->get();
        $this->assertCount(1, $results);
    }
}
```

## コードレビュー

### レビュー観点
- [ ] コーディング規約に準拠しているか
- [ ] テストが適切に実装されているか
- [ ] ドキュメントが更新されているか
- [ ] セキュリティ上の問題がないか
- [ ] パフォーマンスへの影響はないか
- [ ] 既存機能への影響はないか

### レビュー後の対応
```bash
# フィードバックを反映
# コード修正
# ...

# コード整形
./vendor/bin/sail pint

# テスト実行
./vendor/bin/sail test

# コミット・プッシュ
git add .
git commit -m "fix(scope): レビュー指摘事項を修正"
git push origin feature/<issue-id>-<feature-name>
```

## マージ後

### ローカルブランチのクリーンアップ
```bash
# mainブランチに移動
git checkout main

# 最新を取得
git pull origin main

# マージ済みブランチを削除
git branch -d feature/<issue-id>-<feature-name>

# リモートブランチも削除（必要に応じて）
git push origin --delete feature/<issue-id>-<feature-name>
```

## トラブルシューティング

### コンフリクト解決
```bash
# mainブランチの最新を取得
git fetch origin main

# リベース
git rebase origin/main

# コンフリクトがある場合は手動で解決
# ファイルを編集して競合を解消
git add <resolved-files>
git rebase --continue

# リベース完了後
git push origin feature/<issue-id>-<feature-name> --force-with-lease
```

### テスト失敗時
```bash
# 詳細なエラー表示
./vendor/bin/sail test --verbose

# 特定のテストのみ実行
./vendor/bin/sail test --filter test_method_name

# データベースをクリーンな状態にリセット
./vendor/bin/sail artisan migrate:fresh --seed --env=testing
```

### Docker環境の問題
```bash
# コンテナを完全に削除して再構築
./vendor/bin/sail down --volumes
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d

# キャッシュクリア
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan view:clear
```

## 重要なリマインダー

✅ **コミット前必須**:
1. `./vendor/bin/sail pint` - コード整形
2. `./vendor/bin/sail test` - 全テスト通過
3. ドキュメント更新
4. セキュリティ確認

✅ **開発中の心得**:
- 小さく頻繁にコミット
- わかりやすいコミットメッセージ
- テストを先に書く（TDD推奨）
- コードレビューを恐れない
