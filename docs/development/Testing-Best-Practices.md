# LedgerLeap テストベストプラクティス

**最終更新:** 2025年9月30日  
**適用対象:** LedgerLeap全体のテスト開発

---

## 🎯 基本原則

### 1. テスト設計の原則
- **1テストメソッド = 1HTTPリクエスト**を厳守
- **Single Responsibility**: 各テストは1つの機能・条件のみテスト
- **明確な命名**: テスト名でテスト内容を完全に理解できること

### 2. データベーストレイトの使い分け

```php
// ✅ 全文検索(Mroonga)が必要な場合
use Illuminate\Foundation\Testing\DatabaseMigrations;

class SearchApiTest extends TestCase
{
    use DatabaseMigrations;
    // Mroongaインデックスが必要なため
}

// ✅ 通常のEloquentテストの場合
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTest extends TestCase
{
    use RefreshDatabase;
    // 高速なロールバック処理
}
```

---

## 🚫 避けるべきアンチパターン

### 1. 複数HTTPリクエストテスト
```php
// ❌ 避けるべきパターン
public function test_multiple_operations()
{
    $response1 = $this->getJson('/api/v1/resource?param1=value1');
    $response2 = $this->getJson('/api/v1/resource?param2=value2'); // BadRequestException発生リスク
}

// ✅ 推奨パターン
public function test_operation_with_param1()
{
    $response = $this->getJson('/api/v1/resource?param1=value1');
    // アサーション
}

public function test_operation_with_param2()
{
    $response = $this->getJson('/api/v1/resource?param2=value2');
    // アサーション
}
```

### 2. 重複するテスト責任
```php
// ❌ 避けるべき重複
// LedgerControllerTest.php
public function test_search_ledgers() { /* 検索テスト */ }

// SearchApiTest.php  
public function test_search_functionality() { /* 同じ検索テスト */ }

// ✅ 推奨する責任分担
// LedgerControllerTest.php - CRUD操作のみ
public function test_create_ledger() { /* 作成テスト */ }
public function test_update_ledger() { /* 更新テスト */ }

// SearchApiTest.php - 検索機能のみ
public function test_search_by_keyword() { /* キーワード検索 */ }
public function test_search_by_tags() { /* タグ検索 */ }
```

---

## 🏭 ファクトリベストプラクティス

### 1. 軽量ファクトリの実装
```php
// ✅ 推奨: 最小限のデータ
class LedgerFactory extends Factory
{
    protected $model = Ledger::class;

    public function definition(): array
    {
        return [
            'content' => [0 => 'Test Content'],
            'creator_id' => User::factory(),
            'modifier_id' => User::factory(),
        ];
    }

    // 必要に応じて追加データ
    public function withComplexContent(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => $this->generateComplexContent(),
        ]);
    }

    // パフォーマンス重視の最小構成
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => [0 => 'Minimal'],
        ]);
    }
}
```

### 2. ファクトリ使用例
```php
// ✅ デフォルトは軽量
$ledger = Ledger::factory()->create();

// ✅ 最小構成を明示的に使用
$ledger = Ledger::factory()->minimal()->create();

// ✅ 特定テストで複雑データが必要な場合のみ
$ledger = Ledger::factory()->withComplexContent()->create();
```

---

## 🔍 spatie/laravel-query-builder使用ガイド

### 1. カンマ区切りパラメータの処理
```php
// ❌ 問題のあるスコープフィルタ
AllowedFilter::scope('with_tags'), // カンマ区切りが正しく処理されない

// ✅ 推奨: コールバックフィルタ
AllowedFilter::callback('with_tags', function ($query, $value) {
    $tagNames = is_string($value) ? array_filter(explode(',', $value)) : $value;
    if (!empty($tagNames)) {
        $query->whereHas('define.tags', function ($q) use ($tagNames) {
            $q->whereIn('name', $tagNames);
        }, '=', count($tagNames)); // AND条件
    }
}),
```

### 2. 除外検索の実装
```php
// ✅ 正しい除外ロジック
AllowedFilter::callback('exclude_q', function ($query, $value) {
    $query->where(function ($q) use ($value) {
        $q->whereRaw('not match(`content`) against (? IN BOOLEAN MODE)', [$value])
          ->whereRaw('not match(`content_attached`) against (? IN BOOLEAN MODE)', [$value]);
    });
}),
```

---

## 🔬 Mroonga全文検索テスト

### 1. 必須設定
```php
class SearchTest extends TestCase
{
    use DatabaseMigrations; // RefreshDatabaseは使用不可

    public function test_mroonga_search()
    {
        // データ作成
        $ledger = Ledger::factory()->create([
            'content' => [0 => 'テスト検索キーワード']
        ]);

        // インデックス更新を待機
        sleep(1);

        // 検索実行
        $results = Ledger::where(function ($query) {
            $query->whereRaw('match(`content`) against (? IN BOOLEAN MODE)', ['テスト']);
        })->get();

        $this->assertCount(1, $results);
    }
}
```

### 2. 全文検索の注意点
- **複合インデックス不可**: Mroongaでは`MATCH(col1, col2)`が動作しない
- **OR結合が必要**: `MATCH(col1) OR MATCH(col2)`で検索
- **インデックス更新遅延**: テスト時は`sleep(1)`で待機

---

## 📊 パフォーマンス監視

### 1. テスト実行時間の目標値
| テストタイプ | 目標時間 | 最大許容時間 |
|--------------|----------|--------------|
| Unit Test | 1秒以内 | 3秒 |
| Feature Test (単純) | 5秒以内 | 10秒 |
| Feature Test (DB含む) | 12秒以内 | 20秒 |
| API Test Suite全体 | 6分以内 | 10分 |

### 2. パフォーマンス劣化の兆候
- 個別テストが20秒を超える
- 全体実行時間が10分を超える
- メモリ使用量が1GB を超える

### 3. 最適化手法
```php
// ✅ Eager Loading でN+1問題回避
$ledgers = Ledger::with(['define', 'define.folder', 'tags'])->get();

// ✅ ファクトリでの最小限データ生成
Ledger::factory()->minimal()->count(10)->create();

// ✅ 不要なフィールド計算の回避
Ledger::select(['id', 'content', 'created_at'])->get();
```

---

## 🧪 テストケース設計パターン

### 1. 権限テストパターン
```php
// ✅ 権限レベル別のテスト分離
public function test_admin_can_access_all_data()
{
    $this->actingAs($this->adminUser, 'sanctum')
        ->getJson('/api/v1/ledgers')
        ->assertOk()
        ->assertJsonCount(3, 'data');
}

public function test_writer_can_access_writable_data_only()
{
    $this->actingAs($this->writerUser, 'sanctum')
        ->getJson('/api/v1/ledgers')
        ->assertOk()
        ->assertJsonCount(1, 'data');
}

public function test_viewer_cannot_create_ledger()
{
    $this->actingAs($this->viewerUser, 'sanctum')
        ->postJson('/api/v1/ledgers', $this->validData)
        ->assertForbidden();
}
```

### 2. エラーハンドリングテストパターン
```php
// ✅ バリデーションエラーのテスト
public function test_validation_error_for_missing_required_field()
{
    $invalidData = ['content' => '']; // required fieldが空

    $this->actingAs($this->writerUser, 'sanctum')
        ->postJson('/api/v1/ledgers', $invalidData)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['content']);
}

// ✅ 認証エラーのテスト
public function test_unauthenticated_access_returns_401()
{
    $this->getJson('/api/v1/ledgers')
        ->assertStatus(401);
}
```

### 3. 検索・フィルタテストパターン
```php
// ✅ 正常ケース
public function test_search_returns_matching_results()
{
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/search?q=keyword')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
}

// ✅ エッジケース
public function test_search_with_no_results()
{
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/search?q=nonexistent')
        ->assertOk()
        ->assertJsonCount(0, 'data');
}

// ✅ 複雑な条件
public function test_search_with_multiple_filters()
{
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/search?q=keyword&tags=tag1,tag2&created_from=2025-01-01')
        ->assertOk();
}
```

---

## 🔄 CI/CD環境での注意点

### 1. 並列実行の制限
```bash
# Mroongaテストは並列実行不可
./vendor/bin/sail test tests/Feature/Api/SearchApiTest.php --process-isolation
```

### 2. タイムアウト設定
```php
// 長時間実行が予想される場合
/**
 * @timeout 60
 */
public function test_complex_search_operation()
{
    // 複雑な検索処理
}
```

### 3. 環境固有の設定
```php
// CI環境でのスキップ
public function test_requires_specific_environment()
{
    if (app()->environment('testing-ci')) {
        $this->markTestSkipped('CI環境では実行不可');
    }
    
    // テスト処理
}
```

---

## 📋 チェックリスト

### テスト作成時のチェックポイント
- [ ] 1テストメソッド = 1HTTPリクエストを守っているか
- [ ] テスト名が内容を明確に表現しているか
- [ ] 適切なデータベーストレイトを使用しているか
- [ ] ファクトリは最小限のデータで設計されているか
- [ ] 権限テストが適切に分離されているか
- [ ] エラーケースも含めてテストされているか

### レビュー時のチェックポイント
- [ ] 複数HTTPリクエストを含むテストがないか
- [ ] テストの責任分担が明確か
- [ ] spatie/laravel-query-builderの使用が適切か
- [ ] パフォーマンスに問題がないか（実行時間）
- [ ] テストデータの生成が軽量か

### リファクタリング時のチェックポイント
- [ ] 既存テストが破綻していないか
- [ ] 新しい実装が全てのテストケースでカバーされているか
- [ ] パフォーマンスが向上しているか
- [ ] テストの可読性が向上しているか

---

**このベストプラクティスに従うことで、安定性・実行速度・保守性を兼ね備えたテストスートを構築できます。**