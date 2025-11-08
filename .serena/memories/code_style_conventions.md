# LedgerLeap コードスタイルと規約

## フォーマッター
- **ツール**: Laravel Pint
- **実行**: `./vendor/bin/sail pint`
- **タイミング**: コミット前に必ず実行

## 命名規則

### PHP
- **変数名**: スネークケース（例: `$ledger_item`, `$user_list`）
- **メソッド名**: キャメルケース（例: `getUserProfile()`, `calculateTotalAmount()`）
- **クラス名**: パスカルケース（例: `LedgerController`, `UserService`）
- **定数**: アッパースネークケース（例: `MAX_ITEMS`, `DEFAULT_LIMIT`）

### データベース
- **テーブル名**: スネークケース複数形（例: `ledgers`, `user_profiles`）
- **カラム名**: スネークケース（例: `item_name`, `created_at`）

### ルーティング
- **ルート名**: ケバブケース（例: `ledger-items.show`）※LedgerLeap推奨
- **URL**: ケバブケース（例: `/ledger-items/{id}`）

### ビュー
- **Bladeファイル**: ケバブケース（例: `user-profile.blade.php`）
- **Livewireクラス**: パスカルケース（例: `UserProfile`）
- **Livewireビュー**: ケバブケース（例: `user-profile.blade.php`）

### 環境変数・設定
- **環境変数**: アッパースネークケース（例: `DB_CONNECTION`, `APP_DEBUG`）
- **設定キー**: スネークケース（例: `database.connections.mysql.host`）

## コメント

### PHPDoc（必須）
- クラス、メソッド、プロパティにはPHPDocを記述
- 特に公開APIメソッドには必須

```php
/**
 * 指定されたユーザーIDのプロファイルを取得する
 *
 * @param  int  $userId ユーザーID
 * @return \App\Models\UserProfile|null ユーザープロファイル、見つからない場合はnull
 * @throws \App\Exceptions\InvalidUserIdException 無効なユーザーIDの場合
 */
public function getUserProfile(int $userId): ?UserProfile
{
    // ...
}
```

### インラインコメント
- 複雑なロジックには適宜追加
- 自明なコードにはコメント不要

### タスクマーカー
- `// TODO:`: 後で対応が必要なタスク
- `// FIXME:`: 既知のバグや問題

## アーキテクチャパターン

### コントローラ
- **責務**: HTTPリクエスト受付とレスポンス返却のみ
- **原則**: ファットコントローラの回避
- **ビジネスロジック**: サービスクラスに委譲

```php
// 良い例
public function store(StoreLedgerRequest $request)
{
    $ledger = $this->ledgerService->createLedger($request->validated());
    return new LedgerResource($ledger);
}
```

### モデル
- **リレーション**: Eloquentリレーションを明確に定義
- **スコープ**: クエリの再利用にローカル/グローバルスコープ活用
- **マスアサインメント**: `$fillable`を明示的に設定（推奨）
- **キャスト**: `$casts`プロパティでデータ型を明示
- **Enum**: ステータス管理などにEnumを積極的に活用

### サービスクラス
- 再利用可能なビジネスロジックを集約
- 依存性注入コンテナを通じて利用
- トランザクション管理を適切に実装

```php
public function createLedger(array $data): Ledger
{
    return DB::transaction(function () use ($data) {
        // 台帳作成 + タグ関連付け + 権限チェック等
    });
}
```

### Livewireコンポーネント
- **状態管理**: 単一配列に状態を集約（Single Source of Truth）
- **コンポーネント粒度**: 再利用可能で単一の関心事に集中
- **データ受け渡し**: プロパティ（親→子）、イベント（子→親）
- **wire:key**: DOM追跡を確実に

```php
// 良い例（単一配列）
public array $columns = [
    ['type' => 'text', 'name' => 'title'],
    ['type' => 'number', 'name' => 'amount']
];
```

## ビュー（Blade）

### 基本原則
- インデントを適切に
- PHPロジックの分離（表示ロジックのみ）
- XSS対策: `{{ $variable }}`を使用
- 再利用可能なUI: BladeコンポーネントまたはLivewireコンポーネント化

## 設計原則
- **DRY**: 同じコードの繰り返しを避ける
- **KISS**: シンプルで理解しやすい実装
- **YAGNI**: 現時点で必要とされていない機能は実装しない
- **単一責任**: 一つのクラス/メソッドは一つの関心事に集中

## Git規約

### ブランチ命名
```
feature/<issue-id>-<feature-name>
bugfix/<issue-id>-<bug-name>
hotfix/<issue-id>-<fix-name>
```

### コミットメッセージ
```
<type>(scope): <subject>

例:
feat(auth): ユーザー登録APIエンドポイント実装
fix(ledger): 全文検索の類義語展開バグ修正
chore(deps): Laravel 12.0にアップグレード
docs(readme): セットアップ手順を更新
```

**type**: feat, fix, docs, style, refactor, test, chore

## 設定管理
- **機密情報**: `.env`ファイルに記述（バージョン管理に含めない）
- **設定値アクセス**: `config()`ヘルパー経由（`env()`を直接使用しない）
- **`.env.example`**: 全ての環境変数キーを記述し常に最新に保つ

## 重要な制約とベストプラクティス

### Mroonga全文検索
```php
// ○ 動作する（単一インデックス）
Ledger::where(DB::raw('MATCH(content) AGAINST(?)'), [$keyword])->get();

// × 動作しない（複合インデックス）
// MATCH(content, content_attached) は使用不可

// ○ 正解（OR結合）
Ledger::where(function($query) use ($keyword) {
    $query->where(DB::raw('MATCH(content) AGAINST(?)'), [$keyword])
          ->orWhere(DB::raw('MATCH(content_attached) AGAINST(?)'), [$keyword]);
})->get();
```

### テスト
- **全文検索テスト**: `DatabaseMigrations`トレイト使用（`RefreshDatabase`不可）
- **Livewireテスト**: Toast通知は`assertDispatched('mary-toast')`で検証
- **モック**: Pest=`mock()`, PHPUnit=`$this->mock()`

## リファレンス
詳細は `/docs/development/coding_standards.md` を参照してください。
