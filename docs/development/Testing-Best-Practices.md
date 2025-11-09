# LedgerLeap テストベストプラクティス

**最終更新:** 2025年10月1日  
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

## 📦 Ledgerモデルのcontentデータ構造とテスト

### 1. contentの正規化とデータベース保存

**重要**: Ledgerの`content`および`content_attached`は、`AsColumnArrayJson`キャストと`normalizeByColumnDefine()`によって特殊な変換が行われます。

```php
// ✅ 実際のデータフロー
// 1. Livewireコンポーネントでの入力
$content = [1 => 'テキスト', 3 => '値'];  // カラムIDをキーとした連想配列

// 2. normalizeByColumnDefine()による正規化（保存前）
// - カラムIDの欠番を空文字で埋める
// - maxIdまでのすべてのインデックスを作成
$normalized = [0 => '', 1 => 'テキスト', 2 => '', 3 => '値'];  // インデックス0,2が追加

// 3. AsColumnArrayJson::set()による変換（DB保存）
// - array_values()で連番配列に変換
$stored = ['', 'テキスト', '', '値'];  // JSON: ["","テキスト","","値"]

// 4. DBから読み取り時（AsColumnArrayJson::get()）
// - 連番配列として復元される
$fromDb = [0 => '', 1 => 'テキスト', 2 => '', 3 => '値'];
```

### 2. テストでのLedger作成時の注意点

**問題**: テストでLedgerを直接作成する場合、`normalizeByColumnDefine()`が呼ばれないため、データ構造が実際のアプリケーションと異なる可能性があります。

```php
// ❌ 間違ったテストデータ作成
$ledger = Ledger::factory()->create([
    'ledger_define_id' => $ledgerDefine->id,
    'content' => [1 => 'テスト値'],  // カラムID=1のみ指定
]);
// → DBには ['テスト値'] として保存される（インデックス0のみ）
// → ModifyColumnで読み取ると content[1] が存在しない！

// ✅ 正しいテストデータ作成
// normalizeByColumnDefine()と同じ形式でデータを作成
$ledger = Ledger::factory()->create([
    'ledger_define_id' => $ledgerDefine->id,
    'content' => [0 => '', 1 => 'テスト値'],  // カラムIDの欠番も含める
]);
// → DBには ['', 'テスト値'] として保存される（インデックス0,1）
// → ModifyColumnで content[1] が正しく読み取れる
```

### 3. カラムIDと配列インデックスの対応関係

```php
// ✅ normalizeByColumnDefine()の動作理解
$ledgerDefine->column_define = [
    new ColumnDefine(0, 'フィールド0', ...),  // カラムID=0
    new ColumnDefine(2, 'フィールド2', ...),  // カラムID=2（ID=1は欠番）
];

// maxId = 2 なので、インデックス0,1,2が作成される
$content = [1 => '値'];  // カラムID=1に値を設定

// normalizeByColumnDefine()後:
// [0 => '', 1 => '値', 2 => '']
//  ↑        ↑         ↑
//  カラム0   カラム1    カラム2
//  (空)     (値)      (空)

// DBから読み取り後も同じ構造
// $ledger->content[0] → ''（カラムID=0の値）
// $ledger->content[1] → '値'（カラムID=1の値）
// $ledger->content[2] → ''（カラムID=2の値）
```

### 4. 実際のアプリケーションフロー

**重要**: 実際のアプリケーションでは、Livewireコンポーネント経由で保存されるため、常に正規化が行われます。

```php
// app/Livewire/Ledger/CreateColumn.php (line 648-649)
protected function processFilesForSave(): void
{
    // ... ファイル処理 ...
    
    // 保存前に必ず正規化
    $this->content = $this->ledgerDefineRecord->normalizeByColumnDefine($this->content);
    $this->contentAttached = $this->ledgerDefineRecord->normalizeByColumnDefine($this->contentAttached);
}
```

### 5. テストのベストプラクティス

```php
// ✅ 推奨パターン1: 実際のフローを使う
Livewire::test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine->id])
    ->set('content.1', 'テスト値')
    ->call('saveDraft');
// → 正規化が自動的に行われる

// ✅ 推奨パターン2: 正規化された形式で直接作成
$ledger = Ledger::factory()->create([
    'content' => [
        0 => '',           // カラムID=0（空の場合でも含める）
        1 => 'テスト値',   // カラムID=1
        2 => '',           // カラムID=2（空の場合でも含める）
    ],
]);

// ✅ 推奨パターン3: ヘルパーメソッドを作成
protected function createLedgerWithContent(LedgerDefine $define, array $content): Ledger
{
    // normalizeByColumnDefine()を明示的に呼び出す
    $normalized = $define->normalizeByColumnDefine($content);
    
    return Ledger::factory()->create([
        'ledger_define_id' => $define->id,
        'content' => $normalized,
    ]);
}
```

### 6. トラブルシューティング

**症状**: `Undefined array key X` エラーが発生

```php
// 問題のコード例
$component = Livewire::test(ModifyColumn::class, ['ledgerId' => $ledger->id]);
$component->assertSet('content.1', 'expected_value');
// → Error: Undefined array key 1
```

**チェックポイント**:
1. テストでLedgerを作成する際、カラムIDの欠番を空文字で埋めているか？
2. `column_define`のmaxIdまでのすべてのインデックスを含めているか？
3. DBに保存された実際のJSON構造を確認したか？

```php
// デバッグ方法
$ledger = Ledger::find($ledgerId);
dd([
    'content' => $ledger->content,
    'content_keys' => array_keys($ledger->content),
    'db_raw' => $ledger->getAttributes()['content'],  // 生のJSON文字列
]);
```

---

## 🧩 MCPツール専用テストパターン

### 1. 統合テスト vs 詳細テストの責任分担

**重要な教訓**: MCPツールでは認証機能が共通化されているため、責任分担を明確にしないと重複テストが大量発生する。

```php
// ✅ 統合テスト (McpToolsAuthenticationTest.php)
// 複数ツールの認証一貫性を検証
/**
 * MCPツールの統一認証機能テスト
 * 
 * 責任範囲:
 * - 全MCPツールの認証動作の一貫性検証
 * - AuthenticatedMcpTraitの統合動作確認
 * - トークン検証・権限チェックの基本動作
 */
public function test_all_tools_reject_invalid_tokens()
{
    $tools = [
        new CreateLedgerTool(),
        new GetLedgerDefinesTool(),
        new SearchLedgersTool(),
    ];
    
    foreach ($tools as $tool) {
        // 各ツールで統一された認証動作を確認
    }
}

// ✅ 詳細テスト (CreateLedgerToolTest.php)  
// 認証後のビジネスロジックに集中
/**
 * CreateLedgerToolの詳細テスト
 * 
 * 責任範囲:
 * - 台帳作成のビジネスロジック
 * - リクエストパラメータのバリデーション
 * - サービス層との連携
 * - エラーハンドリング
 * 
 * 注意: 認証関連のテストはMcpToolsAuthenticationTest.phpで統合的にテストされます
 */
public function test_creates_ledger_with_valid_data()
{
    // 認証は前提として、台帳作成ロジックのみテスト
}
```

### 2. MCPツール用モック設定パターン

**課題**: Userモデルのイベントリスナーが外部サービス（WritableFolderRepository）を呼び出すため、モックが複雑化。

```php
// ✅ setUp()でのデフォルトモック設定
protected function setUp(): void
{
    parent::setUp();
    
    // サービスをモック
    $this->folderRepository = Mockery::mock(WritableFolderRepository::class);
    
    // Userモデルのイベントリスナー用のメソッドをデフォルトでモック
    $this->folderRepository->shouldReceive('clearAllCache')->byDefault()->andReturn(true);
    $this->folderRepository->shouldReceive('refreshAllCache')->byDefault()->andReturn(true);
    
    $this->app->instance(WritableFolderRepository::class, $this->folderRepository);
}

// ✅ テストメソッドでの具体的な期待値設定
public function test_specific_behavior()
{
    // 特定の動作のみをオーバーライド
    $this->folderRepository->shouldReceive('getAccessibleFolderIds')
        ->with(Mockery::type(User::class), \App\Enums\FolderPermissionType::WRITE)
        ->andReturn([$folder->id]);
        
    // デフォルトモックは引き続き有効
}
```

### 3. Resourceクラスのテストパターン

**課題**: MCPツールの出力はResourceクラスで加工されるため、モデル属性と異なる形式になる。

```php
// ❌ 間違ったアサーション（モデル属性で検証）
$this->assertEquals('Test Title', $responseData['title']);

// ✅ 正しいアサーション（Resource出力で検証）
// LedgerDefineResource では title → name に変換される
$this->assertEquals('Test Title', $responseData['name']);

// ✅ Resourceの構造を事前確認
// app/Http/Resources/LedgerDefineResource.php:
// return ['name' => $this->title, ...];

// ✅ 汎用的なResource出力テスト
public function test_resource_output_structure()
{
    $responseData = json_decode($response->content(), true);
    
    // 基本構造の確認
    $this->assertIsArray($responseData);
    $this->assertArrayHasKey('id', $responseData);
    
    // 実際のResourceクラスの構造に基づいた具体的な検証
    $this->assertArrayHasKey('name', $responseData); // titleがnameに変換
}
```

### 4. enum値のモック指定パターン

**注意点**: FolderPermissionTypeは小文字のvalue（'read', 'write'）だが、定数名は大文字（READ, WRITE）。

```php
// ❌ 間違った enum 参照
FolderPermissionType::read  // 存在しない
FolderPermissionType::write // 存在しない

// ✅ 正しい enum 参照  
FolderPermissionType::read  // value = 'read'
FolderPermissionType::WRITE // value = 'write'
FolderPermissionType::ADMIN // value = 'admin'

// ✅ enum値の確認方法
// app/Enums/FolderPermissionType.php を確認
// case READ = 'read';
// case WRITE = 'write';
// case ADMIN = 'admin';
```

### 5. ファクトリ属性の正規化

**実装中に発見した問題**: データベースカラム名とファクトリ属性名の不一致。

```php
// ❌ 古いファクトリ定義
Folder::factory()->create(['name' => 'Test Folder']);
// → Database column 'name' not found エラー

// ✅ 正しいファクトリ定義
Folder::factory()->create(['title' => 'Test Folder']);
// → foldersテーブルのtitleカラムに対応

// ✅ マイグレーション確認の重要性
// database/migrations/xxx_create_folders_table.php を確認し、
// 実際のカラム名に合わせてファクトリを調整

// ✅ 一般的な検証パターン
// テスト失敗時は以下をチェック：
// 1. マイグレーションファイルでのカラム名
// 2. Eloquentモデルでのfillable設定
// 3. ファクトリでの属性名
```

---

## 🏗️ テスト構造設計パターン

### 1. 共通機能テストの階層化

**Phase 0実装で確立したパターン**:

```
tests/Unit/Mcp/
├── Tools/                           # 個別ツールの詳細テスト
│   ├── McpToolsAuthenticationTest.php    # 【統合】認証一貫性 (6テスト)
│   ├── CreateLedgerToolTest.php         # 【詳細】台帳作成機能 (5テスト)
│   ├── GetLedgerDefinesToolTest.php     # 【詳細】データフィルタリング (5テスト)
│   └── SearchLedgersToolTest.php        # 【詳細】検索機能 (5テスト)
└── Traits/                          # 共通トレイトの内部ロジック
    └── AuthenticatedMcpToolTest.php     # 【内部】トレイト単体テスト (15テスト)

総計: 36テスト / 113アサーション / 100%通過率
```

**責任分担の原則**:
- **統合テスト**: 複数コンポーネント間の一貫性
- **詳細テスト**: 個別機能のビジネスロジック  
- **内部テスト**: トレイト・ヘルパーの単体動作

### 2. MCPテストの品質指標

**Phase 0で達成した指標** (2025-10-01):
- **テスト数**: 36テスト
- **アサーション数**: 113件  
- **テスト通過率**: 100%
- **実行時間**: 18.99秒
- **カバレッジ項目**: 認証・権限・機能・エラーハンドリング・エッジケース

**パフォーマンス目標** (MCPツール専用):
| テストタイプ | 目標時間 | 達成時間 |
|--------------|----------|---------|
| MCPツール統合テスト | 3秒以内 | 1.41秒 |
| MCPツール詳細テスト | 2秒以内 | 0.96秒 |
| MCPトレイトテスト | 5秒以内 | 3.29秒 |

---

## 🧪 ファクトリとテストデータ作成の高度なパターン

### 1. ファクトリの副作用に注意

**Phase5 Hotfix実装時の教訓** (2025-11-09):

Eloquentファクトリは便利だが、`definition()`メソッド内で多くの依存データを自動作成する場合、テストで意図しないデータ構造が作られる可能性がある。

#### 問題例: AttachedFileFactory

```php
// database/factories/AttachedFileFactory.php
public function definition(): array
{
    // ❌ 問題: 自動的に新しいTenant、Ledger、LedgerDefine、Userを作成
    $tenant = \App\Models\Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $ledgerDefine = LedgerDefine::factory()->create();
    $ledger = Ledger::factory()->create();
    $user = User::factory()->create();
    
    return [
        'ledger_id' => $ledger->id,  // ← テストで指定した値が無視される
        'ledger_define_id' => $ledgerDefine->id,
        'creator_id' => $user->id,
        // ...
    ];
}
```

#### 問題点

```php
// テストコード
$ledger = Ledger::factory()->create(['id' => 100]);
$file = AttachedFile::factory()->create([
    'ledger_id' => $ledger->id,  // 100を指定
]);

// 実際の動作:
// AttachedFileFactoryが新しいLedgerを作成してしまい、
// $file->ledger_id は 100 ではなく、新しく作成されたLedgerのIDになる
```

#### 解決策

**重要なテストでは、ファクトリを使わず`Model::create()`を使用:**

```php
// ✅ 推奨: 明示的な依存データ作成とフィールド指定
public function test_finalize_command()
{
    // 依存データを明示的に作成
    $folder = Folder::factory()->create();
    $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
    $user = User::factory()->create();
    $ledger = Ledger::factory()->create([
        'ledger_define_id' => $ledgerDefine->id,
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
    ]);

    // AttachedFileを全フィールド明示で作成（ファクトリを使わない）
    $file = AttachedFile::create([
        'ledger_id' => $ledger->id,  // ← 確実に指定した値が使われる
        'ledger_define_id' => $ledgerDefine->id,
        'column_id' => 1,
        'filename' => 'test.jpg',
        'hashedbasename' => 'test.jpg',
        'mime' => 'image/jpeg',
        'path' => "public/Ledger/Attachments/{$ledgerDefine->id}/test.jpg",
        'size' => 1000,
        'status' => \App\Enums\AttachedFileStatus::READY_FOR_FINALIZATION,
        'contain_content' => false,
        'optimized' => false,
        'tika_processed_at' => now()->subMinutes(2),
        'vlm_processed_at' => now()->subMinute(),
        'vlm_markdown' => '# Test VLM Result',
        'ocr_processed_at' => now()->subMinute(),
        'processing_finalized_at' => null,
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
    ]);
}
```

### 2. データベース必須フィールドの完全指定

テストデータ作成時は、マイグレーションとモデルの`$fillable`を確認し、**必須フィールドを全て指定**する。

#### チェックすべき項目

```php
// 1. マイグレーションで NOT NULL のカラム
// database/migrations/xxx_create_attached_files_table.php
$table->string('filename');           // NOT NULL
$table->string('mime');               // NOT NULL
$table->string('path');               // NOT NULL
$table->integer('size');              // NOT NULL
$table->boolean('contain_content');   // NOT NULL (default なし)
$table->boolean('optimized');         // NOT NULL (default なし)

// 2. 外部キー
$table->foreignId('ledger_id');
$table->foreignId('ledger_define_id');
$table->foreignId('creator_id');
$table->foreignId('modifier_id');

// 3. Enum型
$table->string('status');  // AttachedFileStatus enum
```

#### エラーパターンと対策

```bash
# エラー例
SQLSTATE[HY000]: General error: 1364 Field 'contain_content' doesn't have a default value

# 原因: フィールドの指定漏れ
$file = AttachedFile::create([
    'ledger_id' => $ledger->id,
    'filename' => 'test.jpg',
    // contain_content が指定されていない ← エラー
]);

# ✅ 修正: 全必須フィールドを指定
$file = AttachedFile::create([
    'ledger_id' => $ledger->id,
    'ledger_define_id' => $ledgerDefine->id,
    'column_id' => 1,
    'filename' => 'test.jpg',
    'hashedbasename' => 'test.jpg',
    'mime' => 'image/jpeg',
    'path' => "public/Ledger/Attachments/{$ledgerDefine->id}/test.jpg",
    'size' => 1000,
    'status' => \App\Enums\AttachedFileStatus::READY_FOR_FINALIZATION,
    'contain_content' => false,  // ← 追加
    'optimized' => false,         // ← 追加
    'creator_id' => $user->id,
    'modifier_id' => $user->id,
]);
```

### 3. カスタムキャストの動作理解

Laravelのカスタムキャスト（`AsColumnArrayJson`など）は、保存時・読み込み時に配列を変換する。この動作を理解していないと、予期しないインデックスのずれが発生する。

#### content_attachedの配列構造問題

**問題:**
```php
// ❌ 誤り - カラムID 1を直接使用
$ledger = Ledger::factory()->create([
    'content_attached' => [
        1 => [
            'test.jpg' => ['meta' => ['content' => 'OCR text']],
        ],
    ],
]);

// DBに保存される: [["test.jpg" => ["meta" => ["content" => "OCR text"]]]]
// 読み込まれる:   [0 => ["test.jpg" => ["meta" => ["content" => "OCR text"]]]]
//                  ↑ カラムID 1 ではなく 0 になる！
```

**原因:**  
`AsColumnArrayJson`カスタムキャストは、保存時に`array_values()`で連番に変換する。

**正しい方法:**
```php
// ✅ 正解 - カラムID 0も含める
$ledger = Ledger::factory()->create([
    'content_attached' => [
        0 => [],  // カラムID 0（空でも必要）
        1 => [
            'test.jpg' => ['meta' => ['content' => 'OCR text']],
        ],
    ],
]);

// DBに保存される: [[], ["test.jpg" => ["meta" => ["content" => "OCR text"]]]]
// 読み込まれる:   [0 => [], 1 => ["test.jpg" => ["meta" => ["content" => "OCR text"]]]]
//                  ↑ カラムID 1 が正しく保持される
```

#### 対策

1. **カスタムキャストのコードを確認**
2. **配列のインデックスが保持されるか検証**
3. **必要に応じて、欠番を空配列で埋める**

```php
// テストヘルパー関数の例
protected function createLedgerWithContent(
    LedgerDefine $define,
    array $content,
    array $contentAttached = []
): Ledger {
    // maxColumnIdまでの配列を作成
    $maxId = collect($define->column_define)->max('id');
    
    $normalizedContent = array_fill(0, $maxId + 1, '');
    foreach ($content as $columnId => $value) {
        $normalizedContent[$columnId] = $value;
    }
    
    $normalizedAttached = array_fill(0, $maxId + 1, []);
    foreach ($contentAttached as $columnId => $value) {
        $normalizedAttached[$columnId] = $value;
    }
    
    return Ledger::factory()->create([
        'ledger_define_id' => $define->id,
        'content' => $normalizedContent,
        'content_attached' => $normalizedAttached,
    ]);
}
```

### 4. 処理フローに沿ったテストデータ設計

実際の処理フロー（Tika → OCR → VLM → 最終化）を理解し、各段階でのデータ構造の変化を考慮する。

#### OCR処理のファイル名変換

**問題:**  
OCR処理は画像ファイルをPDFに変換するため、`content_attached`内のキーも変更される。

```php
// ❌ 誤り: OCR結果がtest.jpgに格納されると仮定
$ledger = Ledger::factory()->create([
    'content_attached' => [
        0 => [],
        1 => [
            'test.jpg' => ['meta' => ['content' => 'OCR extracted text']],
        ],
    ],
]);

$file = AttachedFile::create([
    'hashedbasename' => 'test.jpg',  // 元のファイル名
    'ocr_processed_at' => now(),
]);

// コマンド実行
$this->artisan('ledger:finalize-processing');

// ✗ OCRテキストが見つからない（test.pdfを探すため）
```

**正しい方法:**
```php
// ✅ 正解: TikaとOCRの結果を分離
$ledger = Ledger::factory()->create([
    'content_attached' => [
        0 => [],
        1 => [
            'test.jpg' => [
                'meta' => ['content' => 'Tika extracted text'],  // Tika結果
            ],
            'test.pdf' => [
                'meta' => ['content' => 'OCR extracted text'],   // OCR結果（PDF変換後）
            ],
        ],
    ],
]);

$file = AttachedFile::create([
    'hashedbasename' => 'test.jpg',  // 元のファイル名
    'ocr_processed_at' => now(),
]);

// FinalizeAttachedFileProcessingコマンドは:
// 1. test.jpg でTikaテキストを探す
// 2. test.pdf でOCRテキストを探す（拡張子を変換して試す）
```

### 5. テストの独立性を保つ

グローバルなデータカウントではなく、テストで作成した特定のデータに限定してアサーションを行う。

```php
// ❌ 間違い: グローバルカウント（他のテストの影響を受ける）
public function test_respects_limit_parameter()
{
    // ... 3つのファイルを作成 ...
    
    $this->artisan('ledger:finalize-processing', ['--limit' => 2]);
    
    $finalized = AttachedFile::whereNotNull('processing_finalized_at')->count();
    $this->assertEquals(2, $finalized);  // 前のテストで作成されたファイルも含まれる
}

// ✅ 正しい: 特定Ledgerにスコープ
public function test_respects_limit_parameter()
{
    $ledger = Ledger::factory()->create();
    // ... $ledgerに関連する3つのファイルを作成 ...
    
    $this->artisan('ledger:finalize-processing', ['--limit' => 2]);
    
    $finalized = AttachedFile::where('ledger_id', $ledger->id)
        ->whereNotNull('processing_finalized_at')
        ->count();
    $this->assertEquals(2, $finalized);  // このテストのファイルのみ
}
```

### 6. テストデータ作成のチェックリスト

#### 作成前の確認事項
- [ ] マイグレーションで NOT NULL のカラムをリスト化
- [ ] 外部キー制約をリスト化
- [ ] Enum型のフィールドを確認
- [ ] カスタムキャストの動作を理解

#### 作成時の注意事項
- [ ] ファクトリの自動作成を確認（意図しない副作用がないか）
- [ ] 全必須フィールドを明示的に指定
- [ ] 配列フィールドは正規化された形式で作成
- [ ] 処理フローに沿ったデータ構造（ファイル名変換など）

#### テスト実行後の確認
- [ ] `Undefined array key` エラーがないか
- [ ] `Field 'xxx' doesn't have a default value` エラーがないか
- [ ] 他のテストに影響を与えていないか（独立性）

### 7. トラブルシューティング

#### よくあるエラーと対処法

**エラー1: Undefined array key**
```php
// 症状
$component->assertSet('content.1', 'value');
// Error: Undefined array key 1

// チェックポイント
// 1. カラムID 0 を含めているか？
// 2. maxColumnIdまでの配列を作成しているか？
$ledger = Ledger::factory()->create([
    'content' => [0 => '', 1 => 'value'],  // ← 0を追加
]);
```

**エラー2: Field doesn't have a default value**
```php
// 症状
SQLSTATE[HY000]: General error: 1364 Field 'contain_content' doesn't have a default value

// チェックポイント
// マイグレーションで NOT NULL かつ default なしのカラムを全て指定
$file = AttachedFile::create([
    // ...
    'contain_content' => false,  // ← 追加
    'optimized' => false,        // ← 追加
]);
```

**エラー3: ファクトリが指定値を無視**
```php
// 症状
$file->ledger_id !== $expectedLedgerId

// 原因
// ファクトリのdefinition()で新しいLedgerを作成している

// 対策
// ファクトリを使わず、Model::create()で全フィールド指定
```

### 8. 関連ドキュメント

- [FinalizeAttachedFileProcessingTest修正記録](../work/vlm-rag-integration/2025-11-09_phase5-hotfix-ledger-updated-at.md#追加修正-finalizeattachedfileprocessingtest-テストの修正) - 実際の修正事例
- [Ledgerモデル](../models/Ledger.md) - content_attached構造の詳細
- [AttachedFileモデル](../models/AttachedFile.md) - 必須フィールドの一覧

---

## 📋 チェックリスト

### テスト作成時のチェックポイント
- [ ] 1テストメソッド = 1HTTPリクエストを守っているか
- [ ] テスト名が内容を明確に表現しているか
- [ ] 適切なデータベーストレイトを使用しているか
- [ ] ファクトリは最小限のデータで設計されているか
- [ ] 権限テストが適切に分離されているか
- [ ] エラーケースも含めてテストされているか

### MCPツール専用チェックポイント (2025-10-01追加)
- [ ] 統合テストと詳細テストの責任分担が明確か
- [ ] Userモデルのイベントリスナー用のデフォルトモックが設定されているか
- [ ] Resourceクラスの出力形式でアサーションを行っているか
- [ ] enum定数名（大文字）で参照しているか
- [ ] ファクトリ属性名がデータベースカラム名と一致しているか

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

### MCPツール実装時の追加チェックポイント (2025-10-01追加)
- [ ] 認証の重複テストが発生していないか
- [ ] モックの設定でイベントリスナーが考慮されているか
- [ ] 共通トレイトの内部テストが適切に分離されているか
- [ ] MCPレスポンス形式の検証が正しく行われているか

---

**このベストプラクティスに従うことで、安定性・実行速度・保守性を兼ね備えたテストスートを構築できます。**

**Phase 0 (2025-10-01完了) では、MCPツール専用のテストパターンを確立し、36テスト/113アサーション/100%通過率を達成しました。これらの知見はPhase 1以降のワークフロー管理機能実装で直接活用できます。**