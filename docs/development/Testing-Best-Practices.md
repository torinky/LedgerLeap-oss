# LedgerLeap テストベストプラクティス

**最終更新:** 2025年12月13日  
**適用対象:** LedgerLeap全体のテスト開発

**更新履歴:**
- 2026-01-31: Phase 7 リアクティブ統合の不具合是正に基づく知見を追加（データ型の厳密性、テナント初期化の重複回避、権限不足の盲点）
- 2025-12-13: Phase 2（複製機能テスト）実装に基づく重要な知見を追加（連番配列の必須性）
- 2025-11-11: Phase6実装に基づく重要な知見を追加（テナント初期化、data_get()制約、content_attached構造）
- 2025-10-01: 初版作成

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

### 3. Livewireテストと通知

MaryUIなどのトースト通知をテストする場合、`assertDispatched` を使用します。

```php
public function test_toast_notification()
{
    Livewire::test(MyComponent::class)
        ->call('saveData')
        ->assertDispatched('mary-toast', [
            'type' => 'success',
            'title' => '保存完了'
        ]);
}
```

### 4. テナント対応テストの必須セットアップ (Phase6で追加)

**重要:** LedgerLeapはマルチテナント対応のため、**全てのFeatureテストでテナント初期化が必須**です。

```php
use App\Models\Tenant;

class AttachedFilePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ 必須: テナント初期化
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
    }
    
    // テストメソッド...
}
```

**テナント初期化に関する高度な注意点 (Phase 7 追加):**
- **重複初期化の禁止**: `setUp()` で `tenancy()->initialize()` を行っている場合、テストメソッド内で **別のテナントオブジェクトを使って再初期化** してはいけません。
  - テナントIDが同じでも、オブジェクトインスタンスが異なると、Laravel のサービスコンテナ内の状態（特に `Spatie\Permission` のキャッシュや `auth()->user()` のコンテキスト）が不整合を起こし、「閲覧権限がありません」「認証エラー」等の不可解な失敗を招く原因となります。
- **権限付与のタイミング**: `actingAs($user)` を行う前に、そのユーザーが必要な全てのパーミッション（`ledgerView`, `view_auto_links` 等）を持っていることを確認してください。権限の変更は、可能な限りテナント初期化後、かつテスト操作の直前に行うのが最も安全です。

**テナント初期化を忘れた場合の症状:**
- リレーションクエリが`null`を返す
- `ledger_id`は設定されているのに`$attachment->ledger`が`null`になる
- 予期しないデータが取得される

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

## ⚡ Livewire 3 & リアクティブコンポーネントのテスト (Phase 6/7 追加)

### 1. リアクティブプロパティの同期テスト

子コンポーネント（例: `LedgerDiffViewer`）が `#[Reactive]` プロパティを持つ場合、テストは親コンポーネントの視点から行います。

```php
// ✅ 推奨: 親子統合テスト
public function test_child_reacts_to_parent_state_change()
{
    Livewire::test(Show::class, ['ledgerId' => $ledger->id])
        ->set('displayLevel', 2) // 親の状態を変更
        ->assertSeeHtml('...') // 子がリアクティブに更新された結果を確認
        ->assertDispatched('displayLevelUpdated'); // 必要に応じてイベントも確認
}
```

### 2. `#[Url]` プロパティの同期テスト

検索やフィルタなどの状態を URL に持たせる場合、初期状態の復元と変更時の URL 同期を確認します。

```php
// ✅ URLパラメータからの初期化テスト
public function test_it_initializes_from_url_parameters()
{
    Livewire::withQueryParams(['q' => '検索ワード', 'l' => [1, 2]])
        ->test(RecordsTable::class)
        ->assertSet('search', '検索ワード')
        ->assertSet('selectedLedgerDefineIds', [1, 2]);
}

// ✅ 状態変更時の URL 同期テスト
public function test_it_updates_url_when_state_changes()
{
    Livewire::test(RecordsTable::class)
        ->set('search', 'new-word')
        ->assertUrl(['q' => 'new-word']);
}
```

### 3. `CannotMutateReactivePropException` の回避

子コンポーネント内で `#[Reactive]` プロパティを書き換えるとランタイムエラーが発生します。
テストコードでも、子コンポーネントに対して直接 `set()` を行うのではなく、親の状態を変更する、または子コンポーネントがプロパティを「変異」させていないかを厳格にチェックします。

**注意点**: コレクションなどを渡す際、サービス内でリレーションをロード（`loadMissing`等）すると変異とみなされる場合があります。テストでは `clone` や `Collection::make()` で防御的にコピーを渡す実装が正しく機能しているか確認してください。

### 4. 通信の単一化（リクエスト集約）の検証

`#[Reactive]` の最大の利点は通信の集約ですが、これを PHPUnit で直接検証するのは困難です。
代わりに、以下の点を確認します：
- 子コンポーネントが `#[On]` によるイベントリスナーで独自の `render` を走らせていないか（冗長なリクエストの原因）。
- 親の `set()` 後のレスポンスに、期待される子の HTML 差分が含まれているか。

### 5. リアクティブな更新の「待ち合わせ」とアサーション (Phase 7 追加)

Livewire 3 の親子間での状態伝播には、PHPUnit テスト上でも微小な処理順序の意識が必要です。

- **明示的なレンダリングのトリガー**: `set()` や `call()` の直後に `assertSee` を行う場合、Livewire はそのリクエストのレスポンス内での出力を確認します。
- **データの正規化とインデックスの厳密性**: 
  - `AsColumnArrayJson` のように Mroonga 特有のキャストを使用している場合、テストデータのインデックスが **文字列 (`'0'`) か整数 (`0`) か** で挙動が劇的に変わります。
  - サービス層（`AutoLinkService` 等）がデータを処理する際、キーの型が不一致だと検索結果にヒットせず、レンダリングがスキップされるケースがあります。
  - **教訓**: `content` 配列には必ず **整数型のキー (Int indices)** を使用してください。

---

## 🛠 テスト時のトラブルシューティング

### Q: テストで `wire:loading` が確認できない
**A:** `wire:loading` はフロントエンドの挙動ですが、`assertSeeHtml` で `wire:loading` 属性を持つ要素が存在するか、または Livewire 内部の状態を確認することで一部検証可能です。基本的には `LedgerDiffViewer::placeholder()` のようなプレースホルダー自体の出力をテストします。

### Q: ファイルアップロードのテストが通らない
**A:** `Livewire::test()->set('file', UploadedFile::fake()->image('test.jpg'))` を使用してください。また、`AsColumnArrayJson` 等のキャストが介在する場合、データのパッキング形式（0始まりの連番配列など）が正しいかを確認してください。

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
// ❌ 間違ったテストデータ作成（Phase 2で発見）
$ledger = Ledger::factory()->create([
    'ledger_define_id' => $ledgerDefine->id,
    'content' => [
        0 => 'テストタイトル',
        7 => ['タグ1', 'タグ2'],  // キー7のみ指定（キー1-6が欠番）
    ],
]);
// → DBには ["テストタイトル", ["タグ1", "タグ2"]] として保存される
// → $ledger->content[7] でアクセスすると NULL!
// → 実際は $ledger->content[1] に保存されている

// ✅ 正しいテストデータ作成（Phase 2で修正）
// 0から始まる連番配列として、全てのカラムIDを指定
$ledger = Ledger::factory()->create([
    'ledger_define_id' => $ledgerDefine->id,
    'content' => [
        0 => 'テストタイトル',
        1 => '',           // カラムID 1（空）
        2 => '',           // カラムID 2（空）
        3 => '',           // カラムID 3（空）
        4 => [],           // カラムID 4（空配列）
        5 => '',           // カラムID 5（空）
        6 => '',           // カラムID 6（空）
        7 => ['タグ1', 'タグ2'],  // カラムID 7
    ],
]);
// → DBには ["テストタイトル", "", "", "", [], "", "", ["タグ1", "タグ2"]] として保存
// → $ledger->content[7] で正しくアクセスできる

// ✅ 最小構成の例（カラムID 1を使用する場合）
$ledger = Ledger::factory()->create([
    'ledger_define_id' => $ledgerDefine->id,
    'content' => [
        0 => '',           // カラムID 0（空要素必須）
        1 => 'テスト値',   // カラムID 1
    ],
]);
// → DBには ['', 'テスト値'] として保存される（インデックス0,1）
// → ModifyColumnで content[1] が正しく読み取れる
```

**Phase 2（複製機能テスト）で判明した重要な制約:**
- テストデータは**必ず0から始まる連番配列**として作成する
- カラムIDに欠番がある場合は、空文字列`''`または空配列`[]`で埋める
- 欠番があると、DBに保存される際に配列が詰められ、インデックスがずれる
- `$ledger->content[$columnId]`でアクセスできるのは、正しく連番配列として保存された場合のみ

**Phase6で判明した重要な制約:**
- `AsColumnArrayJson`キャストの`set()`メソッドは`array_values()`を使用
- **0から始まる連番配列**として扱う必要がある
- カラムIDが1から始まる場合、インデックス0に空要素が必須

```php
// ✅ content_attachedの正しい構造（Phase6実装例）
'content_attached' => [
    0 => [],  // カラムID 0（空）※必須
    1 => [    // カラムID 1
        'test.pdf' => [
            'meta' => ['content' => 'OCR extracted text'],
        ],
    ],
],
```

**ログ出力によるデバッグの重要性（Phase 2で活用）:**
```php
// テスト内でログ出力してデータ構造を確認
\Log::info('=== DEBUG ===', [
    'content' => $ledger->content,
    'content[7]' => $ledger->content[7] ?? 'NOT SET',
]);

// テスト実行後、ログファイルを確認
// storage/logs/laravel.log を参照
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

### 4. AsColumnArrayJsonキャストの制約（Phase6で判明）

**重要な制約: `data_get()`ヘルパーとの非互換性**

`AsColumnArrayJson`キャストは内部で`___serialized___`プレフィックスを使用したシリアライゼーションを行うため、Laravelの`data_get()`ヘルパー関数が正しく動作しません。

```php
// ❌ 動作しない
$text = data_get($ledger->content_attached, '1.test.pdf.meta.content');
// => NULL （期待する値が取得できない）

// ✅ 正しい方法：直接配列アクセスを使用
$text = $ledger->content_attached[$column_id][$filename]['meta']['content'] ?? null;
// => 'OCR extracted text' （正しく取得できる）
```

**テストでの影響:**

```php
// ❌ 避けるべきパターン
$this->assertEquals(
    'expected value',
    data_get($ledger->content, '1')  // 動作しない可能性
);

// ✅ 推奨パターン
$this->assertEquals(
    'expected value',
    $ledger->content[1] ?? null  // 確実に動作
);
```

**実装での注意点:**
- `content`や`content_attached`へのアクセスには**必ず直接配列アクセス**を使用
- `data_get()`, `Arr::get()`などのヘルパー関数は使用不可
- Null-safe演算子（`??`）で安全にアクセス

**参考実装:**
- `AttachedFile::getPreviewableText()`: 直接配列アクセスの実装例
- `docs/work/vlm-rag-integration/2025-11-11_phase6-wbs1-implementation-report.md`: 詳細な問題と解決策

### 5. 実際のアプリケーションフロー

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

---

## 🛡️ データ整合性テスト (2026-01-25 追加)

マルチテナント環境において、`tenant_id` の欠落は致命的なセキュリティリスク（またはデータのサイレントな消失）を招きます。

### 1. DatabaseIntegrityTest の運用
モデル単体のテストだけでなく、データベース全体の「不備」を検知するテストスイートを維持してください。

```php
// tests/Feature/DatabaseIntegrityTest.php
public function test_tenant_tables_have_no_missing_tenant_id()
{
    $tables = ['ledgers', 'ledger_diffs', 'folders', ...];
    foreach ($tables as $table) {
        $count = DB::table($table)->whereNull('tenant_id')->count();
        $this->assertEquals(0, $count, "Table {$table} has records with NULL tenant_id");
    }
}
```

### 2. バックグラウンド生成レコードの注意
`LedgerDiff` のように、ユーザー操作の副産物としてバックグラウンドで作成されるレコードは `tenant_id` を忘れやすいため、必ず生成サービス (`RollbackService` 等) のテストで `tenant_id` が継承されているかを検証してください。

---

## 🔗 Livewire 3 URL 同期のベストプラクティス (2026-01-25 追加)

親子コンポーネント間で URL パラメータを共有する場合の設計指針です。

### 1. #[Url] 属性の共有
親と子の両方で同じプロパティを `#[Url]` として定義することで、Livewire 3 はそれらが同一の URL クエリを指していることを自動認識します。

### 2. 明示的なパラメータ渡しの回避 (リロード対策)
Blade で `<livewire:child :param="$param" />` のように明示的に渡すと、ページリロード時に「親の初期値(null)」が「URL から復元された子の値」を上書きしてしまう競合が発生します。
- **推奨**: URL と同期するプロパティは、親子間で明示的に渡さず、それぞれのコンポーネントが URL から独立して復元するように設計します。

### 3. 初期化時のガード
`mount` 内でデフォルト値をセットする際は、既に URL から値が復元されていないかを確認してください。
```php
public function mount() {
    if (! $this->myParam) {
        $this->myParam = 'default';
    }
}
```
