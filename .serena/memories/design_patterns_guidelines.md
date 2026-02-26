# LedgerLeap デザインパターンとガイドライン

## アーキテクチャパターン

### MVC + Service Layer
LedgerLeapは、LaravelのMVCパターンに加えて、サービス層を導入した階層アーキテクチャを採用しています。

```
┌─────────────────────────────────┐
│ Presentation Layer              │
│ ├── Controllers (薄く保つ)      │
│ ├── Livewire Components         │
│ └── Blade Views                 │
└─────────────────────────────────┘
            ↓
┌─────────────────────────────────┐
│ Application Layer               │
│ ├── Services (ビジネスロジック) │
│ ├── Policies (認可)             │
│ └── Jobs (非同期処理)           │
└─────────────────────────────────┘
            ↓
┌─────────────────────────────────┐
│ Domain Layer                    │
│ ├── Models (Eloquent)           │
│ ├── Enums                       │
│ └── Value Objects               │
└─────────────────────────────────┘
            ↓
┌─────────────────────────────────┐
│ Infrastructure Layer            │
│ ├── Repositories (オプション)   │
│ └── External Services           │
└─────────────────────────────────┘
```

## 主要デザインパターン

### 1. Repository Pattern（部分採用）
複雑なデータアクセスロジックは、リポジトリクラスに分離することを検討します。
ただし、現状はEloquentモデルがその役割を担うことが多いです。

```php
// 必要に応じて実装
interface LedgerRepositoryInterface
{
    public function findByFolder(Folder $folder): Collection;
    public function search(string $query, array $filters): Collection;
}
```

### 2. Service Pattern（必須）
ビジネスロジックはサービスクラスに集約します。

```php
class LedgerService
{
    public function __construct(
        private LedgerRepository $ledgerRepository,
        private NotificationService $notificationService,
        private WorkflowService $workflowService
    ) {}
    
    public function createLedger(array $data): Ledger
    {
        return DB::transaction(function () use ($data) {
            // 1. 台帳作成
            $ledger = $this->ledgerRepository->create($data);
            
            // 2. タグ関連付け
            $ledger->tags()->sync($data['tags'] ?? []);
            
            // 3. ワークフロー開始（必要に応じて）
            if ($data['workflow_enabled'] ?? false) {
                $this->workflowService->startWorkflow($ledger);
            }
            
            // 4. 通知送信
            $this->notificationService->notifyLedgerCreated($ledger);
            
            return $ledger;
        });
    }
}
```

### 3. Policy Pattern（認可）
リソースへのアクセス制御は、Policyクラスで管理します。

```php
class LedgerPolicy
{
    public function create(User $user, Folder $folder): bool
    {
        return $user->hasPermissionTo('ledger.create') &&
               $user->canAccessFolder($folder, 'WRITE');
    }
    
    public function update(User $user, Ledger $ledger): bool
    {
        return $user->hasPermissionTo('ledger.update') &&
               $user->canAccessFolder($ledger->folder, 'WRITE');
    }
}

// コントローラでの使用
public function store(StoreLedgerRequest $request, Folder $folder)
{
    $this->authorize('create', [Ledger::class, $folder]);
    
    $ledger = $this->ledgerService->createLedger($request->validated());
    return new LedgerResource($ledger);
}
```

### 4. Observer Pattern（イベント監視）
モデルのライフサイクルイベントを監視し、関連処理を実行します。

```php
class LedgerObserver
{
    public function created(Ledger $ledger): void
    {
        // アクティビティログ記録
        activity()
            ->performedOn($ledger)
            ->causedBy(auth()->user())
            ->log('created');
    }
    
    public function updated(Ledger $ledger): void
    {
        // 変更履歴記録
        activity()
            ->performedOn($ledger)
            ->causedBy(auth()->user())
            ->withProperties(['old' => $ledger->getOriginal()])
            ->log('updated');
    }
}

// AppServiceProviderで登録
public function boot(): void
{
    Ledger::observe(LedgerObserver::class);
}
```

### 5. Strategy Pattern（戦略パターン）
アルゴリズムの切り替えが必要な場合に使用します。

```php
// 例: スコアリング戦略
interface ScoringStrategyInterface
{
    public function calculate(Ledger $ledger): float;
}

class ActivityScoreStrategy implements ScoringStrategyInterface
{
    public function calculate(Ledger $ledger): float
    {
        // アクティビティベースのスコア計算
    }
}

class FreshnessScoreStrategy implements ScoringStrategyInterface
{
    public function calculate(Ledger $ledger): float
    {
        // 新鮮度ベースのスコア計算
    }
}

class CompositeScoringService
{
    public function calculateScore(Ledger $ledger, array $strategies): float
    {
        $totalScore = 0;
        foreach ($strategies as $strategy) {
            $totalScore += $strategy->calculate($ledger);
        }
        return $totalScore;
    }
}
```

## Livewireパターン

### Single Source of Truth
Livewireコンポーネントでは、状態を単一の配列に集約します。

```php
// ○ 良い例
public array $columns = [
    ['type' => 'text', 'name' => 'title', 'required' => true],
    ['type' => 'number', 'name' => 'amount', 'required' => false]
];

public function addColumn(): void
{
    $this->columns[] = [
        'type' => 'text',
        'name' => '',
        'required' => false
    ];
}

// × 悪い例
public array $columnTypes = ['text', 'number'];
public array $columnNames = ['title', 'amount'];
public array $columnRequired = [true, false];
```

### イベント駆動
親子コンポーネント間の通信はイベントで行います。

```php
// 子コンポーネント
class ChildComponent extends Component
{
    public function submit(): void
    {
        // イベント発行
        $this->dispatch('item-saved', ['id' => $this->item->id]);
    }
}

// 親コンポーネント
class ParentComponent extends Component
{
    public function getListeners(): array
    {
        return [
            'item-saved' => 'refreshList'
        ];
    }
    
    public function refreshList($data): void
    {
        // リスト更新処理
    }
}
```

## DDD的な考え方

### Value Objects
不変の値オブジェクトを使用して、ビジネスロジックをカプセル化します。

```php
class ColumnDefine
{
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly bool $required,
        public readonly ?array $options = null
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            name: $data['name'],
            required: $data['required'] ?? false,
            options: $data['options'] ?? null
        );
    }
    
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'required' => $this->required,
            'options' => $this->options,
        ];
    }
}
```

### Enums（型安全性）
固定された値セットにはEnumを使用します。

```php
enum FolderPermissionType: string
{
    case READ = 'READ';
    case WRITE = 'WRITE';
    case INSPECT = 'INSPECT';
    case APPROVE = 'APPROVE';
    case ADMIN = 'ADMIN';
    
    public function includes(self $permission): bool
    {
        return match($this) {
            self::ADMIN => true,
            self::APPROVE => in_array($permission, [self::INSPECT, self::WRITE, self::READ]),
            self::INSPECT => in_array($permission, [self::WRITE, self::READ]),
            self::WRITE => $permission === self::READ,
            self::READ => $permission === self::READ,
        };
    }
    
    public function label(): string
    {
        return match($this) {
            self::READ => '閲覧',
            self::WRITE => '書き込み',
            self::INSPECT => '点検',
            self::APPROVE => '承認',
            self::ADMIN => '管理',
        };
    }
}
```

## 設計原則

### SOLID原則

#### 1. Single Responsibility Principle（単一責任の原則）
一つのクラスは一つの責任のみを持つべきです。

```php
// ○ 良い例
class LedgerCreator
{
    public function create(array $data): Ledger
    {
        // 台帳作成のみに責務を集中
    }
}

class LedgerNotifier
{
    public function notify(Ledger $ledger): void
    {
        // 通知のみに責務を集中
    }
}

// × 悪い例
class LedgerManager
{
    public function create(array $data): Ledger
    {
        // 台帳作成
        // 通知送信
        // ファイル処理
        // ワークフロー開始
        // ... 責務が多すぎる
    }
}
```

#### 2. Open/Closed Principle（開放閉鎖の原則）
拡張に対して開いており、修正に対して閉じているべきです。

```php
// インターフェースで抽象化
interface SearchStrategyInterface
{
    public function search(string $query, array $filters): Collection;
}

// 具体的な実装を追加しても既存コードは変更不要
class FullTextSearchStrategy implements SearchStrategyInterface { }
class SemanticSearchStrategy implements SearchStrategyInterface { }
```

#### 3. Liskov Substitution Principle（リスコフの置換原則）
派生クラスは基底クラスと置き換え可能であるべきです。

#### 4. Interface Segregation Principle（インターフェース分離の原則）
クライアントは使用しないメソッドに依存すべきではありません。

```php
// × 悪い例（大きすぎるインターフェース）
interface LedgerInterface
{
    public function create(array $data): Ledger;
    public function update(Ledger $ledger, array $data): Ledger;
    public function delete(Ledger $ledger): bool;
    public function search(string $query): Collection;
    public function export(Ledger $ledger): string;
}

// ○ 良い例（適切に分離）
interface LedgerCreatorInterface { }
interface LedgerUpdaterInterface { }
interface LedgerSearchInterface { }
interface LedgerExporterInterface { }
```

#### 5. Dependency Inversion Principle（依存性逆転の原則）
具象ではなく抽象に依存すべきです。

```php
// ○ 良い例
class LedgerService
{
    public function __construct(
        private LedgerRepositoryInterface $repository,  // インターフェースに依存
        private NotificationServiceInterface $notifier
    ) {}
}

// × 悪い例
class LedgerService
{
    public function __construct(
        private EloquentLedgerRepository $repository,  // 具象クラスに依存
        private EmailNotificationService $notifier
    ) {}
}
```

### その他の原則
- **DRY (Don't Repeat Yourself)**: 同じコードの繰り返しを避ける
- **KISS (Keep It Simple, Stupid)**: シンプルで理解しやすい実装
- **YAGNI (You Ain't Gonna Need It)**: 現時点で必要とされていない機能は実装しない

## エラーハンドリング

### 例外の階層化
```php
// 基底例外
class LedgerLeapException extends Exception {}

// ドメイン固有の例外
class LedgerNotFoundException extends LedgerLeapException {}
class InvalidLedgerDataException extends LedgerLeapException {}
class PermissionDeniedException extends LedgerLeapException {}

// 使用例
public function find(int $id): Ledger
{
    $ledger = Ledger::find($id);
    
    if (!$ledger) {
        throw new LedgerNotFoundException("Ledger #{$id} not found");
    }
    
    return $ledger;
}
```

## まとめ

LedgerLeapでは、以下のデザインパターンとガイドラインを重視しています：

1. **Service Layer Pattern**: ビジネスロジックの集約
2. **Policy Pattern**: 認可の管理
3. **Observer Pattern**: モデルイベントの監視
4. **Strategy Pattern**: アルゴリズムの切り替え
5. **Value Objects**: 不変の値オブジェクト
6. **Enums**: 型安全な列挙型
7. **SOLID原則**: 保守性の高い設計
8. **Livewireパターン**: 状態管理とイベント駆動

これらのパターンを適切に適用することで、保守性が高く、拡張しやすいコードベースを維持できます。
