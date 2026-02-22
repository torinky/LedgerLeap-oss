# LedgerLeap テストベストプラクティス

**最終更新:** 2026年02月22日  
**適用対象:** LedgerLeap全体のテスト開発

**更新履歴:**
- 2026-02-22: Livewire `#[Computed]` プロパティのカバレッジ取得手法、`CoversClass` の重要性、`latest_diff_id` パターンを追加（Issue #69対応）
- 2026-02-11: マイグレーション管理とトラブルシューティングセクションを追加（デッドロック対策、冪等性の確保）
- 2026-02-09: Livewire 3 親子コンポーネント（IndexManager + RecordsTable）のテストベストプラクティスを追加（Issue #60対応）
- 2026-02-08: Sail環境におけるモデルイベントの発火挙動（touch() vs update()）およびトレースログによるデバッグ手法を追加
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

### 1.5. テスト環境の設定（重要）

**`.env.testing`ファイルの設定**

テスト環境では、必ず`.env.testing`ファイルで専用のデータベース接続を設定してください：

```dotenv
# .env.testing
DB_CONNECTION=mysql_testing
DB_HOST=mysql
DB_PORT=3306
DB_USERNAME=sail
DB_PASSWORD=password
DB_DATABASE=ledgerleap_test
```

**`config/database.php`でテスト専用接続を定義**

```php
'mysql_testing' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => 'ledgerleap_test', // ハードコード
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    // ...その他の設定
],
```

**理由:**
- `--env=testing`を指定しても、.envの`DB_DATABASE`が優先される場合がある
- 専用接続を作成することで、確実にテストデータベースに接続できる
- 本番データベースを誤って操作するリスクを回避

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

## 🏗️ モデルイベント & Sail環境の注意点 (2026-02-08 追加)

### 1. `touch()` vs `update()` の選択
Laravel Sail (Docker) 環境において、モデルの `updated` イベントが `$model->touch()` では安定して発火しないケース（特にテスト環境）が確認されています。

- **問題**: `touch()` はタイムスタンプのみを更新するため、DBドライバやSailのタイミングによっては変更が検知されず、ObserverやEvent Listenerがスキップされることがあります。
- **解決策**: イベント駆動のロジック（例：キャッシュクリア）をテストする場合は、**`$model->update(['column' => 'value'])`** を使用して、明示的なデータ変更を伴う更新を行うことで発火を確実にします。

### 2. トレースログによるイベントフローの可視化
Observerや `booted()` 内のクロージャが正しく実行されているか不明な場合は、一時的に `Log::info` または `dump()` を仕込みます。

- **推奨**: 開発中は `dump()` を使用してコンソールに即時出力し、期待通りにイベントが連鎖しているかを確認します。
- **注意**: 検証完了後は必ず削除し、必要に応じて永続的な `Log::info` に置き換えてください。

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

// ✅ 一般的なResource出力テスト
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

---

## ⚡ Livewire `#[Computed]` プロパティのテスト (2026-02-22 追加)

Issue #69 の実装で発見した重要な知見です。`#[Computed]` プロパティは通常のテストでカバレッジが計上されず **0%** のままになるケースがあります。

### 問題の背景

Livewire v3 の `#[Computed]` プロパティは以下の特性を持ちます：

1. **ビューから参照されて初めて実行される** — `render()` を呼ぶだけでは、ビューテンプレートが `$property` を参照していない限り実行されない
2. **初回実行結果がキャッシュされる** — 一度呼ばれた後は同じリクエスト内でキャッシュが返る
3. **`assertStatus(200)` だけではカバレッジが計上されない** — `render()` が成功してもメソッドが呼ばれていなければ 0% のまま

```php
class WorkflowStatusCard extends BaseLivewireComponent
{
    #[Computed]
    public function workflowHistory(): Collection
    {
        // ビューで $workflowHistory として参照されていない場合、render()後も 0%
        return $this->ledgerRecord->ledgerDiff()->orderBy('created_at', 'desc')->get();
    }

    #[Computed]
    public function requiredRolesProgress(): array
    {
        if ($this->ledgerRecord->define->workflow_enabled && ...) {
            return $this->ledgerRecord->getRequiredRolesProgressDetails();
        }
        return [];
    }
}
```

### 解決策: `instance()` 経由でメソッドを直接呼び出す

```php
#[Test]
public function workflow_history_returns_empty_collection_when_no_diffs(): void
{
    // ❌ 悪い: assertStatus(200) だけではメソッドが実行されない
    Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $this->ledger])
        ->assertStatus(200);  // workflowHistory() は呼ばれていない → 0%

    // ✅ 良い: instance() 経由でメソッドを直接呼び出す
    $instance = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $this->ledger])
        ->instance();

    $history = $instance->workflowHistory();  // ← 直接呼び出し → カバレッジ計上
    $this->assertInstanceOf(Collection::class, $history);
}
```

### キャッシュ問題への対処

`render()` が走った時点で Computed プロパティがキャッシュされます。**テストに渡すモデルは `Livewire::test()` を呼ぶ前に正しい状態にしておく必要があります。**

```php
#[Test]
public function required_roles_progress_is_computed_when_workflow_enabled(): void
{
    // ❌ 悪い: setUp()で workflow_enabled=false → render()時に空配列がキャッシュされる
    $this->ledgerDefine->update(['workflow_enabled' => true]);  // 手遅れ
    $instance = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $this->ledger])
        ->instance();
    $progress = $instance->requiredRolesProgress();  // キャッシュの空配列が返る

    // ✅ 良い: workflow_enabled=true のデータを最初から作成して渡す
    $ledgerDefineEnabled = LedgerDefine::factory()
        ->for($this->folder)
        ->create(['workflow_enabled' => true]);  // ← 最初からtrue

    $ledger = Ledger::with(['define.folder', 'latestDiff'])->find(
        Ledger::factory()->create(['ledger_define_id' => $ledgerDefineEnabled->id])->id
    );

    $instance = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $ledger])
        ->instance();  // ← render()時点で workflow_enabled=true が確定している

    $progress = $instance->requiredRolesProgress();  // 正しく実行される
    $this->assertArrayHasKey('inspection', $progress);
}
```

> **補足**: Livewire v3 では `unset($instance->propertyName)` でキャッシュをクリアできますが、
> `render()` が走った後では既にキャッシュが確定しているため効果がない場合があります。

### `#[CoversClass]` アトリビュートの重要性

PHPUnit のコードカバレッジは `#[CoversClass]` が付いているテストのみを該当クラスのカバレッジとして厳密に計上します。**既存テストに `#[CoversClass]` がない場合、新しいテストを追加しても 0% のままになる可能性があります。**

```php
// ❌ 悪い: CoversClass なし → カバレッジが計上されないことがある
class WorkflowStatusCardTest extends TestCase
{
    // ...
}

// ✅ 良い: 必ず CoversClass を付ける
#[CoversClass(WorkflowStatusCard::class)]
class WorkflowStatusCardTest extends TestCase
{
    // ...
}
```

> 既存テストファイルに `#[CoversClass]` がない場合は追加してください。

---

## 🔑 Ledger ワークフローテストのデータ準備パターン (2026-02-22 追加)

`Ledger.latestDiff()` は `belongsTo(LedgerDiff::class, 'latest_diff_id')` です。`LedgerDiff::factory()` で差分を作成しただけでは `Ledger.latest_diff_id` が更新されません。

### `latest_diff_id` の正しい設定方法

```php
// ❌ 悪い: latest_diff_id が null のまま → latestDiff()->first() が null を返す
$ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);
$diff = LedgerDiff::factory()->create(['ledger_id' => $ledger->id]);
// $ledger->latestDiff()->first() === null  ← Xmatch fails

// ✅ 良い: latest_diff_id を明示的に設定する
$ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);
$diff = LedgerDiff::factory()->create(['ledger_id' => $ledger->id]);
$ledger->update(['latest_diff_id' => $diff->id]);
$ledger = $ledger->fresh();  // latest_diff_id を反映した新しいインスタンスを取得
// $ledger->latestDiff → $diff が返る ✅
```

### `PendingList::openApproverSelectModal()` のテストで必須

`openApproverSelectModal()` は内部で `$ledger->latestDiff()->first()` の `inspector_id` と `Auth::id()` を比較します。`latest_diff_id` が設定されていないと権限チェックで早期 return し、モーダルが開きません。

```php
// PendingList の openApproverSelectModal をテストする場合の必須手順
private function createPendingLedgerWithDiff(): array
{
    $ledger = Ledger::factory()->create([
        'ledger_define_id' => $this->ledgerDefine->id,
        'status'           => WorkflowStatus::PENDING_INSPECTION,
    ]);
    $diff = LedgerDiff::factory()->create([
        'ledger_id'    => $ledger->id,
        'inspector_id' => $this->inspector->id,  // ← Auth::id() と一致させる
        'status'       => WorkflowStatus::PENDING_INSPECTION,
    ]);
    $ledger->update(['latest_diff_id' => $diff->id]);  // ← 必須

    return [$ledger->fresh(), $diff];
}
```

---

## 🧪 Livewire 3 親子コンポーネントのテスト (2026-02-09 追加)

Issue #53/60 の実装により、`IndexManager` + `RecordsTable` のような親子構造が導入されました。この構造のテストには特有の注意点があります。

### 1. 基本原則

#### 親コンポーネント（IndexManager）のテスト対象
- ✅ 状態管理（search, selectedLedgerDefineIds, currentFolderId など）
- ✅ URL パラメータとの同期
- ✅ 子コンポーネントへのプロパティ伝播
- ✅ イベントハンドリング（sortRequested, currentFolderChangeRequested など）

#### 子コンポーネント（RecordsTable）のテスト対象
- ✅ 受け取ったプロパティに基づく表示ロジック
- ✅ データのフィルタリング・ソート
- ✅ ページネーション
- ✅ 個別のユーザーインタラクション

### 2. 親コンポーネントテストの推奨パターン

#### ❌ 避けるべきパターン
```php
// 悪い例: 子コンポーネントのレンダリングを考慮していない
$component = Livewire::test(IndexManager::class)
    ->set('search', 'term')  // ← wire:loading.remove.delay が発動
    ->assertSee('ExpectedContent');  // ← 子が削除されている可能性
```

#### ✅ 推奨パターン1: withQueryParams() での初期化
```php
// 良い例: クエリパラメータで初期状態を設定
$component = Livewire::withQueryParams([
    'q' => 'search term',
    'l' => [$ledgerDefineId],
    'cf' => $folderId,
])->test(IndexManager::class);

$component->assertOk()
    ->assertSet('search', 'search term')
    ->assertSet('selectedLedgerDefineIds', [$ledgerDefineId]);
```

**メリット:**
- 子コンポーネントが確実にマウントされる
- `wire:loading.remove.delay` の影響を受けない
- ページリロード後の状態を正確に再現

#### ✅ 推奨パターン2: 具体的なマーカーによる順序検証
```php
// 良い例: wire:key を使った具体的なマーカー検証
$html = $component->html();

// 各要素の位置を取得
$posB = strpos($html, 'wire:key="ledger_record_' . $defineB->id . '"');
$posC = strpos($html, 'wire:key="ledger_record_' . $defineC->id . '"');
$posA = strpos($html, 'wire:key="ledger_record_' . $defineA->id . '"');

// 順序を検証
$this->assertNotFalse($posB, 'Element B should exist');
$this->assertNotFalse($posC, 'Element C should exist');
$this->assertLessThan($posC, $posB, 'B should appear before C');
```

**メリット:**
- テキストの重複による誤検出を回避
- DOM構造の変更に強い
- 正確な順序検証が可能

#### ✅ 推奨パターン3: 状態管理に焦点を当てる
```php
// 良い例: IndexManager の状態管理を検証
$component = Livewire::withQueryParams([
    'l' => [$ledgerDefineId],
    'cf' => $folderId,
])->test(IndexManager::class);

// 検索語を設定
$component->set('search', 'Target')
    ->assertSet('search', 'Target');

// SearchContext の初期化を検証
$keywords = $component->get('keywords');
$this->assertNotEmpty($keywords, 'Keywords should be initialized');
$this->assertContains('Target', $keywords);

// 検索語をクリア
$component->set('search', '')->assertSet('search', '');
$this->assertEmpty($component->get('keywords'), 'Keywords should be cleared');
```

**メリット:**
- テストの責務が明確
- 親子間の非同期通信に依存しない
- デバッグしやすい

### 3. よくある落とし穴と対処法

#### 問題1: 子コンポーネントの HTML が含まれない
**原因:**
- Livewire 3 では、子コンポーネントは初回マウント後、独立して更新される
- 親の `html()` には子の内容が含まれない場合がある

**対処法:**
```php
// ❌ 悪い: 子の内容に直接依存
$component->assertSee('RecordTitle');

// ✅ 良い: 親の状態管理を検証
$component->assertSet('totalRecords', 10);

// ✅ 良い: 子コンポーネントを直接テスト（別のテストファイル）
Livewire::test(RecordsTable::class, [
    'search' => 'term',
    'selectedLedgerDefineIds' => [$id],
])->assertSee('RecordTitle');
```

#### 問題2: wire:loading.remove.delay の影響
**原因:**
- 検索など「重い処理」では、`wire:loading.remove.delay` により子が一時的に削除される

**対処法:**
```php
// ❌ 悪い: set() 後すぐに検証
$component->set('search', 'term')
    ->assertSee('Result');  // 子が削除されている可能性

// ✅ 良い: クエリパラメータで初期化
$component = Livewire::withQueryParams(['q' => 'term'])
    ->test(IndexManager::class)
    ->assertSet('search', 'term');
```

#### 問題3: totalRecords が 0 のまま
**原因:**
- `totalRecords` は子コンポーネントから `recordsUpdated` イベント経由で更新される
- Livewire テスト環境では、このイベントが同期的に実行されない

**対処法:**
```php
// ❌ 悪い: totalRecords に依存
$this->assertGreaterThan(0, $component->get('totalRecords'));

// ✅ 良い: IndexManager の責務（状態管理）のみ検証
$component->assertSet('selectedLedgerDefineIds', [$id])
    ->assertSet('search', 'term');

// ✅ 良い: RecordsTable を直接テストして totalRecords を検証
```

### 4. テスト設計のチェックリスト

#### 親コンポーネント（IndexManager）テスト
- [ ] `withQueryParams()` で初期状態を設定しているか？
- [ ] 状態管理（プロパティの更新）を検証しているか？
- [ ] 子コンポーネントの表示内容に依存していないか？
- [ ] イベントハンドリングを検証しているか？

#### 子コンポーネント（RecordsTable）テスト
- [ ] 必要なプロパティを全て渡しているか？
- [ ] 表示ロジックを個別に検証しているか？
- [ ] ページネーション・フィルタリングを検証しているか？

#### 統合テスト（必要に応じて）
- [ ] ブラウザテスト（Dusk等）で E2E を検証しているか？
- [ ] 親子間の実際のインタラクションを検証しているか？

### 5. 実装例

詳細な実装例は以下を参照してください:
- ✅ `tests/Feature/Livewire/Ledger/RecordsTableLedgerDefineSortTest.php`
- ✅ `tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php`
- ✅ `tests/Feature/Livewire/Ledger/RecordsTableCompositeScoreSortTest.php`

関連ドキュメント:
- 📄 `docs/work/testing/2026-02-09_issue-60-test-failure-investigation.md`
- 📄 `docs/work/ui-ux/2026-02-01_issue-53-completion-report.md`

---

## 🔄 マイグレーション管理とトラブルシューティング

### 1. テスト環境でのマイグレーションリセット

**推奨方法: 専用スクリプトの使用**

```bash
# 最も確実な方法
./bin/reset-test-db.sh
```

**このスクリプトの動作:**
1. データベースを完全に削除・再作成（docker execで直接実行）
2. 設定キャッシュをクリア
3. テーブル数を確認（0になることを期待）
4. マイグレーションを実行
5. マイグレーション状態を表示

**手動実行が必要な場合:**

```bash
# データベースを削除・再作成
docker exec ledgerleap-mysql-1 mysql -uroot -ppassword -e "DROP DATABASE IF EXISTS ledgerleap_test;"
docker exec ledgerleap-mysql-1 mysql -uroot -ppassword -e "CREATE DATABASE ledgerleap_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# キャッシュクリアとマイグレーション実行
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan migrate --env=testing
```

**⚠️ 注意:**
- `migrate:fresh` - MySQLモニタに入ってしまう場合があります
- `migrate:refresh` - デッドロックリスクがあります（使用非推奨）
- `--env=testing` だけでは `.env` の設定が優先される場合があります
- 必ず `.env.testing` で `DB_CONNECTION=mysql_testing` を設定してください

### 2. 必須設定

**`.env.testing`**
```dotenv
DB_CONNECTION=mysql_testing  # 重要！
DB_DATABASE=ledgerleap_test
```

**`config/database.php`**
```php
'mysql_testing' => [
    'driver' => 'mysql',
    'database' => 'ledgerleap_test', // ハードコード
    // ...その他env()で取得
],
```

### 3. マイグレーションファイルの冪等性（必須）

**カラム追加:**
```php
$afterColumn = Schema::hasColumn('table', 'preferred_col') 
    ? 'preferred_col' : 'fallback_col';

if (! Schema::hasColumn('table', 'new_col')) {
    $table->timestamp('new_col')->nullable()->after($afterColumn);
}
```

**インデックス追加:**
```php
if (! Schema::hasIndex('table', 'idx_name')) {
    $table->index('column', 'idx_name');
}
```

**安全な削除:**
```php
public function down(): void
{
    Schema::table('table', function (Blueprint $table) {
        if (Schema::hasIndex('table', 'idx_name')) {
            $table->dropIndex('idx_name');
        }
        
        $cols = [];
        foreach (['col1', 'col2'] as $col) {
            if (Schema::hasColumn('table', $col)) {
                $cols[] = $col;
            }
        }
        if (! empty($cols)) {
            $table->dropColumn($cols);
        }
    });
}
```

### 4. チェックリスト

マイグレーション作成時:
- [ ] `hasColumn()`/`hasIndex()`で存在チェック
- [ ] `after()`句は動的に決定
- [ ] `down()`メソッドも冪等性を確保
- [ ] `comment()`で日本語説明を記述

参考: `database/migrations/2025_11_03_014829_add_vlm_columns_to_attached_files_table.php`

---

## 📊 カバレッジ測定

**最終更新:** 2026年02月15日

### 1. カバレッジ測定ツール

LedgerLeapでは**Pest**（PHPUnitベース）を使用してコードカバレッジを測定します。

**利用可能なツール:**
- **Pest**: テスト実行とカバレッジ測定
- **PHPUnit**: Pestの内部で使用
- **Xdebug**: カバレッジデータ収集（Sail環境で有効化済み）

### 2. カバレッジ測定コマンド

#### 2.1 基本的なカバレッジ測定

```bash
# 全テストのカバレッジをHTMLレポートで生成
./vendor/bin/sail composer test:coverage

# 特定のテストディレクトリのカバレッジ
./vendor/bin/sail pest tests/Unit/Rules --coverage

# 特定のテストファイルのカバレッジ
./vendor/bin/sail pest tests/Unit/Services/PermissionServiceTest.php --coverage
```

#### 2.2 最小カバレッジ率の指定

```bash
# 最小カバレッジ率を80%に設定（達成できない場合は失敗）
./vendor/bin/sail pest tests/Unit/Rules --coverage --min=80

# 最小カバレッジ率を指定しない（0%でもOK）
./vendor/bin/sail pest tests/Unit/Services --coverage --min=0
```

#### 2.3 HTMLレポートの生成

```bash
# HTMLレポートを生成（coverage/index.htmlに出力）
./vendor/bin/sail pest tests/Unit/Rules --coverage-html=coverage

# ブラウザで確認（macOS）
open coverage/index.html
```

### 3. カバレッジレポートの読み方

#### 3.1 コンソール出力の見方

```
Rules/RequiredCheckbox ............................................. 22 / 75.0%  
Rules/UniqueAutoNumber ............................................. 89 / 96.4%  
Rules/UniqueColumnValue .................................. 92..115 / 63.6%  
Rules/ValidAutoLinkPattern ......................................... 100.0%  

Services/NotificationService ........................ 53..468 / 26.0%  
Services/PermissionService .................... 51..417 / 10.8%  
────────────────────────────────────────────────────────────────────
                                                        Total: 45.2 %
```

**解説:**
- `75.0%`: カバレッジ率（75%のコードがテストされている）
- `22`: カバーされていない行番号
- `92..115`: カバーされていない行範囲
- `100.0%`: 完全にカバーされている（未カバー行なし）

#### 3.2 HTMLレポートの活用

HTMLレポートでは以下が確認できます：
- **緑色**: カバーされたコード
- **赤色**: カバーされていないコード
- **黄色**: 部分的にカバーされたコード（条件分岐の一部のみ）

**確認手順:**
1. `coverage/index.html`をブラウザで開く
2. 対象のクラスをクリック
3. 行ごとのカバレッジ状況を確認
4. 赤色の行を重点的にテスト追加

### 4. Phase 1の実測カバレッジ結果（2026-02-15）

#### 4.1 Rules（目標: 95%以上）

| ファイル | カバレッジ率 | 評価 | 未カバー行 |
|:---|---:|:---:|:---|
| RequiredCheckbox | 75.0% | ⚠️ | 行22 |
| UniqueAutoNumber | 96.4% | ✅ | 行89 |
| UniqueColumnValue | 63.6% | ⚠️ | 行92-115 |
| ValidAutoLinkPattern | 100% | ✅ | なし |

**総合評価**: 83.8%（目標95%未達）

**改善方針**:
- RequiredCheckbox: `translate()`エラーケースのテスト追加
- UniqueColumnValue: エラーハンドリングのテスト追加

#### 4.2 Services（目標: PermissionService 80%、NotificationService 70%）

| ファイル | カバレッジ率 | 評価 | 未カバー行 |
|:---|---:|:---:|:---|
| PermissionService | 10.8% | ❌ | 行51-417（大部分） |
| NotificationService | 26.0% | ❌ | 行53-468（大部分） |

**総合評価**: 18.4%（目標大幅未達）

**現状分析**:
- Phase 1では**基本スモークテスト**のみ実装（メソッドが呼び出せることを確認）
- 詳細なロジックテストは未実装
- フォルダベース権限、通知配信ロジックなどは未カバー

**改善方針（Phase 2以降）**:
- PermissionService: フォルダ階層権限、キャッシュ無効化のテスト追加
- NotificationService: 通知配信ロジック、ユーザー/ロール別通知のテスト追加

### 5. カバレッジ測定のベストプラクティス

#### 5.1 測定対象の絞り込み

```bash
# ❌ 全体のカバレッジを測定すると時間がかかる
./vendor/bin/sail pest --coverage

# ✅ 関心のあるディレクトリのみ測定
./vendor/bin/sail pest tests/Unit/Rules --coverage

# ✅ 複数のテストファイルを指定
./vendor/bin/sail pest \
  tests/Unit/Services/PermissionServiceTest.php \
  tests/Unit/Services/NotificationServiceTest.php \
  --coverage
```

#### 5.2 段階的なカバレッジ向上

**Phase 1**: 基本スモークテスト（10-30%）
```bash
# メソッドが呼び出せることを確認
./vendor/bin/sail pest tests/Unit/Services --coverage --min=10
```

**Phase 2**: 主要パスのテスト（50-70%）
```bash
# 正常系と主要な異常系をカバー
./vendor/bin/sail pest tests/Unit/Services --coverage --min=50
```

**Phase 3**: エッジケースのテスト（80-95%）
```bash
# 境界値、エラーハンドリングを網羅
./vendor/bin/sail pest tests/Unit/Services --coverage --min=80
```

#### 5.3 カバレッジ率の目標設定

| コンポーネント | 目標カバレッジ | 理由 |
|:---|---:|:---|
| **Casts** | 100% | データ破損防止の最重要ロジック |
| **Rules** | 95%以上 | バリデーションは高精度が必須 |
| **Services（Core）** | 80%以上 | ビジネスロジックの信頼性確保 |
| **Services（Support）** | 70%以上 | 補助的なサービス |
| **Controllers** | 50%以上 | 統合テストでカバー可能 |
| **Livewire** | 60%以上 | UIロジックの動作確認 |

#### 5.4 Mutation Testingとの併用

カバレッジ率が高くても、テストの質が低い場合があります。**Mutation Testing**で確認：

```bash
# Mutation Testing実行（Phase 2以降）
./vendor/bin/sail composer test:mutation -- \
  --filter=app/Rules/UniqueAutoNumber.php \
  --test-framework-options="--filter=UniqueAutoNumber" \
  --map-source-class-to-test
```

**Mutation Score Indicator (MSI)の目標:**
- Casts: 80%以上
- Rules: 85%以上
- Services: 75%以上

### 6. トラブルシューティング

#### 6.1 カバレッジが0%になる

**原因**: Xdebugが有効になっていない

**解決策**:
```bash
# Xdebugの状態確認
./vendor/bin/sail php -v | grep Xdebug

# Sailの再起動
./vendor/bin/sail down && ./vendor/bin/sail up -d
```

#### 6.2 カバレッジ測定が遅い

**原因**: 全体のカバレッジを測定している

**解決策**:
```bash
# 必要なテストのみ測定
./vendor/bin/sail pest tests/Unit/Rules --coverage

# HTMLレポート生成をスキップ
./vendor/bin/sail pest tests/Unit/Rules --coverage --min=80
```

#### 6.3 特定のファイルが表示されない

**原因**: テストがそのファイルを全く実行していない

**解決策**:
- そのファイルを使用するテストを追加
- `phpunit.xml`の`<source>`設定を確認

### 7. 参考リンク

- [Pest公式ドキュメント - Coverage](https://pestphp.com/docs/coverage)
- [PHPUnit公式ドキュメント - Code Coverage](https://docs.phpunit.de/en/11.0/code-coverage.html)
- [Infection (Mutation Testing)](https://infection.github.io/)

---

**次の更新予定:** Phase 1.5完了後、Castsのカバレッジ結果を追加


