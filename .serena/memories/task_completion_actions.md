# LedgerLeap タスク完了時のアクション

## コミット前の必須チェックリスト

### 1. コード整形（最重要）
```bash
./vendor/bin/sail pint
```
- Laravel Pintでコードスタイルを整形
- **必ず実行**: コミット前に必須

### 2. テスト実行
```bash
# 全テスト実行
./vendor/bin/sail test

# または Pest フレームワークで
./vendor/bin/sail pest
```
- 全てのテストが通ることを確認
- 既存機能の破壊がないか確認

### 3. 関連ドキュメントの更新
以下のドキュメントが影響を受ける場合は更新:
- `/docs/` 直下の公式ドキュメント
  - API仕様、機能仕様、モデル仕様など
- `/docs/work/` の作業ファイル（必要に応じて）
- `README.md`（大きな機能追加の場合）

### 4. 権限・セキュリティ影響確認
- 新機能が権限管理に影響する場合
  - ポリシークラスの実装
  - 権限チェックの追加
- API追加の場合
  - 認証（Sanctum）の実装
  - 認可チェックの実装
- 機密情報の扱い
  - `.env`への適切な設定
  - ログへの機密情報出力回避

## 実装時の必須チェックリスト

### 1. 既存テストのリグレッション確認
```bash
# 変更前に全テスト実行して基準を確認
./vendor/bin/sail test

# 変更後に再度実行
./vendor/bin/sail test

# 差分があれば原因を調査・修正
```

### 2. Livewire状態管理パターン適用
```php
// ○ 良い例（単一配列）
public array $columns = [
    ['type' => 'text', 'name' => 'title'],
    ['type' => 'number', 'name' => 'amount']
];

// × 悪い例（複数プロパティ分離）
public array $columnTypes = ['text', 'number'];
public array $columnNames = ['title', 'amount'];
```

### 3. サービスクラスへのロジック分離
```php
// コントローラは薄く保つ
public function store(StoreLedgerRequest $request)
{
    $ledger = $this->ledgerService->createLedger($request->validated());
    return new LedgerResource($ledger);
}

// ビジネスロジックはサービスクラスに
class LedgerService
{
    public function createLedger(array $data): Ledger
    {
        return DB::transaction(function () use ($data) {
            // 複雑な処理をカプセル化
        });
    }
}
```

### 4. 適切な認証・認可実装
```php
// ポリシー認可
$this->authorize('create', [Ledger::class, $folder]);

// サービス内権限確認
if (!$this->permissionService->canUserAccess($user, $folder, 'WRITE')) {
    throw new UnauthorizedException();
}
```

## Git コミット

### コミットメッセージ規約
```
<type>(scope): <subject>

例:
feat(auth): ユーザー登録APIエンドポイント実装
fix(ledger): 全文検索の類義語展開バグ修正
chore(deps): Laravel 12.0にアップグレード
docs(readme): セットアップ手順を更新
```

**type**:
- `feat`: 新機能
- `fix`: バグ修正
- `docs`: ドキュメント変更
- `style`: コードスタイル修正（動作変更なし）
- `refactor`: リファクタリング
- `test`: テスト追加・修正
- `chore`: ビルド、設定変更など

### ブランチ戦略
```
feature/<issue-id>-<feature-name>
bugfix/<issue-id>-<bug-name>
hotfix/<issue-id>-<fix-name>
```

## 特殊ケースでの注意事項

### 全文検索機能の実装・修正
- Mroongaの制約に注意（複合インデックス不可）
- テストは`DatabaseMigrations`トレイト使用
- `RefreshDatabase`は使用不可
- 検索実装は`Ledger::scopeSearch()`メソッド活用

### データベーススキーマ変更
```bash
# マイグレーション作成
./vendor/bin/sail artisan make:migration create_xxx_table

# マイグレーション実行
./vendor/bin/sail artisan migrate

# テスト環境でもマイグレーション確認
./vendor/bin/sail artisan migrate --env=testing
```

### Livewireコンポーネント追加
- `wire:key`を適切に設定（DOM追跡）
- イベント制御（Alpine.jsとの競合回避）
- Toast通知は`dispatch('mary-toast')`でテスト対応

## コミット後の確認

### CI/CDパイプライン確認
- GitHub Actionsなどで自動テストが通ることを確認
- ビルドエラーがないか確認

### ドキュメントの整合性確認
- コードとドキュメントの内容が一致しているか
- 古い情報が残っていないか

## 重要なリマインダー

✅ **コミット前必須**:
1. `./vendor/bin/sail pint` 実行
2. `./vendor/bin/sail test` 通過確認
3. ドキュメント更新
4. 権限・セキュリティ確認

✅ **実装時必須**:
1. 既存テストのリグレッション確認
2. Livewire状態管理パターン適用
3. サービスクラスへのロジック分離
4. 適切な認証・認可実装
