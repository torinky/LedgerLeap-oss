---
name: test-external-dependency-isolation
description: >
  外部サービス（Embedding/VLM/LDAP等）に依存するコードのテスト設計方針。
  テストを新規作成・変更する際は必ずこのスキルを参照し、外部依存の分離が適切かを確認すること。
---

# 外部サービス依存テストの分離パターン

## このスキルを使うタイミング

以下のいずれかに該当するとき、**このスキルを参照すること**:

- テストを新規作成・変更するとき（特に `Ledger`, `AttachedFile` 等を扱う場合）
- `Ledger::factory()->create()` や `$this->postJson(route('api.v1.ledgers.store'), ...)` を含むテストを書くとき
- CI でテストが「60秒タイムアウト」または「即時失敗（0秒）」するとき
- Observer / Job / Service が外部コンテナ（Embedding/VLM/LDAP/OCR）を呼び出すコードに触れるとき

---

## 1. なぜ分離が必要か

### 問題の構造

```
Ledger::factory()->create()
  → LedgerObserver::created()
    → config('rag.enabled') が true
      → dispatchRagJob()
        → QUEUE_CONNECTION=sync の場合
          → EmbeddingService::embed() を直接同期呼び出し
            → http://embedding:8000 への接続試行
              → CI環境にコンテナなし → 60秒タイムアウト
```

### 本番動作との切り離し

テストの目的は「台帳が作成できること」や「全文検索が動作すること」であり、
**RAG/Embedding/VLM の実行は責務外**である。
外部サービスをテスト内で実行させることは：
- CI の不安定化を招く（コンテナがあるかどうかで結果が変わる）
- テスト時間を増大させる（タイムアウト60秒）
- テストの責務を超えた結合度を生む

---

## 2. 外部サービスの種類と対応方針

| サービス | 関連クラス | CI で使用可能か | 対応方針 |
|---|---|---|---|
| Embedding | `EmbeddingService`, `ProcessLedgerForRagJob` | ❌ | `Queue::fake()` |
| VLM | `VlmClientService`, `ProcessVlmJob` | ❌ | `Queue::fake()` または `#[Group('external')]` |
| LDAP | `LdapService` | ❌ | `#[Group('external')]` |
| OCR (ocrmypdf) | `OcrService` | ❌ | `#[Group('external')]` |
| MySQL/Mroonga | DB接続 | ✅ | そのまま（CIにMroongaコンテナあり） |

---

## 3. Queue::fake() パターン（最重要）

### いつ使うか

`Ledger::factory()->create()` を呼ぶテストすべてに必須。
`LedgerObserver` が `ProcessLedgerForRagJob` を dispatch するため。

### 書き方

```php
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessLedgerForRagJob;

protected function setUp(): void
{
    parent::setUp();

    // Ledger::factory()->create() が LedgerObserver 経由で ProcessLedgerForRagJob を
    // dispatch する。Queue::fake() でジョブを実際には実行させず、
    // Embeddingコンテナへの接続を防ぐ（このテストの責務外）。
    Queue::fake();

    // ...通常のsetUp...
}
```

### RAGジョブのdispatch検証（オプション）

台帳作成APIのテストなど、RAGジョブが dispatch されることを確認したい場合：

```php
// RAGが有効な場合、台帳作成時にRAGジョブがdispatchされることを確認
if (config('rag.enabled')) {
    Queue::assertPushed(ProcessLedgerForRagJob::class);
}
```

### ローカル動作との関係

- **`Queue::fake()` あり**: ジョブはキューに積まれるだけで実行されない（ローカル・CI 共通）
- **`Queue::fake()` なし + `QUEUE_CONNECTION=sync`**: ジョブが同期実行される
  - ローカル（Embeddingコンテナあり）: 動作する
  - CI（Embeddingコンテナなし）: 60秒タイムアウト ❌

`Queue::fake()` はテストの「実行環境への依存」を切り離すための設計であり、
ローカルでの手動確認を妨げるものではない。

---

## 4. Observer の dispatch 設計方針

### 問題のあるパターン（禁止）

```php
// ❌ QUEUE_CONNECTION=sync 時に直接同期実行している
if (config('queue.default') === 'sync') {
    (new ProcessLedgerForRagJob($ledger->id))->handle(
        app(EmbeddingService::class),
        app(RuriChunkFormatter::class)
    );
}
```

この設計では `Queue::fake()` が効かず、Embeddingサービスが直接呼ばれる。

### 正しいパターン

```php
// ✅ 常に dispatch() を使う
// Queue::fake() → キューに積まれるだけ
// QUEUE_CONNECTION=sync → Laravel が同期実行（コンテナがあれば動作）
// 非同期キュー → QueueTenancyBootstrapper がテナントを処理
ProcessLedgerForRagJob::dispatch($ledger->id);
```

**Observer や Service が外部サービスを直接呼び出すコードを書く際は、
必ず `dispatch()` を経由させること。**

---

## 5. グループ分類（#[Group]属性）

テストを新規作成する際は、外部依存の種類に応じてグループを付与すること。

| グループ | 付与条件 | CI での扱い |
|---|---|---|
| `external` | 実コンテナ（VLM/LDAP/OCR）への接続が必須 | 除外（unit/feature ジョブ） |
| `database-migrations` | `DatabaseMigrations` トレイトを使用（migrate:rollback が他テストを破壊） | 専用ジョブで実行 |
| （なし）| `Queue::fake()` 等で外部依存を分離済み | 通常実行 |

### グループ付与の判断フロー

```
テストを書いた
  ↓
Ledger::factory()->create() を呼ぶ？
  → YES → Queue::fake() を setUp に追加（グループ不要）
  → NO  → 続く
  ↓
実コンテナ（VLM/LDAP/OCR）への接続が必須？
  → YES → #[Group('external')] を付与
  → NO  → 続く
  ↓
DatabaseMigrations トレイトを使う？
  → YES → #[Group('database-migrations')] を付与 + DB Migrations Jobs で実行
  → NO  → グループ不要（通常テスト）
```

---

## 6. CI ワークフロー構成

`.github/workflows/phpunit.yml` の現在の構成：

| ジョブ名 | 実行コマンド | 対象 |
|---|---|---|
| `unit` | `--testsuite=Unit --exclude-group=external,database-migrations` | 外部依存なしのユニットテスト |
| `feature` | `--testsuite=Feature --exclude-group=external,database-migrations` | 外部依存なしの機能テスト |
| `db-migrations` | `--group=database-migrations` | `DatabaseMigrations` 使用テスト |

`external` グループは CI で実行しない（ローカルのみ）。

---

## 7. TestCase デフォルト Queue::fake() と $fakeQueue オプトアウト

Issue #74（2026-02-28）で `tests/TestCase.php` に以下が追加された。

```php
protected bool $fakeQueue = true;  // デフォルトで全テストに Queue::fake() を適用

protected function setUp(): void
{
    parent::setUp();
    if ($this->fakeQueue) {
        Queue::fake();
    }
}
```

**`Ledger::factory()->create()` を含む新規テストでは、追加作業なしで保護される。**

### $fakeQueue = false が必要なケース

`Queue::fake()` が有効だと `Bus::fake()` と競合するため、以下のテストはオプトアウトが必要：

```php
class MyJobDispatchTest extends TestCase
{
    // Bus::fake() を使う場合（Queue::fake() と競合するため）
    // または dispatch 自体を Queue::assertPushed() で検証する場合
    protected bool $fakeQueue = false;

    public function setUp(): void
    {
        parent::setUp();
        Bus::fake();  // or Queue::fake() をメソッド内で個別に呼ぶ
    }
}
```

**$fakeQueue = false を設定すべきテストの種類:**
- `Queue::assertPushed()` で dispatch の発火を検証するテスト
- `Bus::fake()` を使うテスト（Bus と Queue の fake は競合する）
- `->handle()` を直接呼び出してジョブのロジックをテストするテスト
- `dispatchSync()` を使うテスト
- `#[Group('external')]` の実コンテナテスト

---

## 7a. BusFake が dispatchSync を横取りする問題

### 問題

`Queue::fake()` を呼ぶと内部で `BusFake` が有効になる。
`BusFake::dispatchSync()` は `shouldFakeJob($command)` が `true` の場合、
**ジョブを実際には実行せず** `commandsSync` コレクションに記録するだけで返る。

```php
// vendor/laravel/framework/.../BusFake.php
public function dispatchSync($command, $handler = null)
{
    if ($this->shouldFakeJob($command)) {
        $this->commandsSync[get_class($command)][] = ...; // 実行されない！
    } else {
        return $this->dispatcher->dispatchSync($command, $handler);
    }
}
```

### 症状

```
// テストが Queue::fake() 環境で以下を呼ぶ
SomeJob::dispatchSync($id);

// → Job::handle() が呼ばれない
// → ジョブ内のアサーションが通らない
// → ログも出ない
// → 見た目には何も起きない
```

### 対処法

**① `->handle()` を直接呼び出す（推奨）**

```php
// Queue::fake() 環境でジョブのロジックを直接検証
(new SomeJob($id))->handle();
```

**② `Bus::fake()->except([SomeJob::class])` で特定Jobを除外する**

```php
Bus::fake()->except([SomeJob::class]);
SomeJob::dispatchSync($id);  // このJobだけ実際に実行される
```

**③ `$fakeQueue = false` にして setUp() で必要なfakeだけ設定する**

```php
protected bool $fakeQueue = false;

public function setUp(): void
{
    parent::setUp();
    // SomeJob 以外の偽装のみ設定
}
```

### delay() 付き dispatch も fake される

`Queue::fake()` 環境では `->delay()` 付きの dispatch も実際には実行されない。
`LedgerDefineObserver` のように `->delay(now()->addSeconds(5))` を使う場合も同様。

```php
// Observer 内のコード
SomeJob::dispatch($id)->delay(now()->addSeconds(5));
// Queue::fake() 環境 → キューに積まれるだけ。QUEUE_CONNECTION=sync でも実行されない

// テストでの正しい対処
(new SomeJob($id))->handle();  // 直接呼び出し
```

---

## 7b. phpunit.xml で RAG_ENABLED=false を設定する

### 問題

`LedgerObserver::created()` は `config('rag.enabled', false)` で保護されているが、
`.env.example` に `RAG_ENABLED=true` が設定されていると、`phpunit.xml` で明示指定がない限り
`Queue::fake()` を `false` にしたテストで Embedding コンテナへの接続試行が発生する。

### 解決策

`phpunit.xml` に `RAG_ENABLED=false` を追加してテスト環境のデフォルトを無効化する：

```xml
<!-- phpunit.xml -->
<php>
    <!-- 既存の設定 -->
    <env name="QUEUE_CONNECTION" value="sync"/>
    <!-- RAGジョブをテスト環境でデフォルト無効化 -->
    <env name="RAG_ENABLED" value="false"/>
</php>
```

### RAG 機能が必要なテストでの対処

RAGジョブ・Observer の dispatch を検証するテストは `setUp()` で明示的に有効化する：

```php
protected function setUp(): void
{
    parent::setUp();
    config(['rag.enabled' => true]);  // このテストクラスでのみ有効化
}
```

また `ProcessLedgerForRagJob::dispatchSync()` を直接呼ぶ場合も、
Job 内部で `if (! config('rag.enabled', false)) { return; }` があるため
呼び出し前に `config(['rag.enabled' => true])` が必要：

```php
config(['rag.enabled' => true]);
ProcessLedgerForRagJob::dispatchSync($ledger->id);
```

### 対象ファイル（RAG_ENABLED=false の影響を受けてオプトインが必要なもの）

| ファイル | 対処 |
|---|---|
| `LedgerObserverTest` | `setUp()` で `config(['rag.enabled' => true])` |
| `ProcessLedgerForRagJobTest` | `setUp()` or 各テストで `config(['rag.enabled' => true])` |
| `RagSearchServiceTest` | `setUp()` で `config(['rag.enabled' => true])` （既存） |
| `VlmRagIntegrationTest` | テスト内で `Config::set('rag.enabled', true)` （既存） |
| `SearchLedgersToolSemanticSearchTest` | `dispatchSync` 前に `config(['rag.enabled' => true])` |

---

## 8. キュー関連機能の4層テスト担保マップ

「Queue::fake() で省略した処理」が別のテストで担保されていることを示す構造：

```
Queue::fake() で分離した処理        → 担保するテスト
─────────────────────────────────────────────────────
dispatch の発火タイミング          → LedgerObserverTest ($fakeQueue=false)
  ├ 作成時に dispatch されること         Queue::assertPushed で検証
  ├ content 更新時に dispatch されること
  ├ 無関係フィールド更新では dispatch されないこと
  └ 台帳削除時に chunks が削除されること

RAGジョブ本体のロジック           → ProcessLedgerForRagJobTest ($fakeQueue=false)
  ├ チャンク生成                         ->handle() で直接呼び出し
  ├ 差分更新（変更チャンクのみ）
  └ VLMマークダウンが空のときのフォールバック

添付ファイル処理のジョブ連鎖       → ProcessAttachedFileTest / VectorizeAttachedFileTest
  ├ サムネイルジョブの dispatch          Bus::fake() で検証
  ├ VLM/OCR 対象ファイルの並列ジョブ
  └ Tika→OCR→VLM のアップグレード判定

Embedding の実際の呼び出し        → RagSearchServiceTest / RagPerformanceTest
  └ 実際の Embedding コンテナに接続       #[Group('external')]（ローカルのみ）
```

**「責務外として除外した処理は別テストで保護されているか」を必ず確認すること。**

詳細は [docs/development/testing/03-external-dependency-isolation.md](../../docs/development/testing/03-external-dependency-isolation.md) を参照。

---

## 9. チェックリスト（テスト変更・新規作成時）

テストを変更・新規作成した後は以下を確認すること：

- [ ] `Ledger::factory()->create()` を呼ぶ場合、`$fakeQueue = true`（デフォルト）のままか確認
- [ ] `Bus::fake()` を使う場合は `$fakeQueue = false` を追加したか
- [ ] `Queue::assertPushed()` で dispatch を検証する場合は `$fakeQueue = false` を追加したか
- [ ] 実コンテナ（VLM/LDAP/OCR）への接続が必須なテストに `#[Group('external')]` を付与したか
- [ ] `DatabaseMigrations` トレイトを使う場合に `#[Group('database-migrations')]` を付与したか
- [ ] Observer や Service が外部サービスを `dispatch()` 経由で呼んでいるか（直接同期呼び出しになっていないか）
- [ ] 「Queue::fake() で省略した外部処理」が別テストで担保されているか確認したか
- [ ] `dispatchSync()` を呼ぶテストで `Queue::fake()` が有効な場合、`(new Job())->handle()` に書き換えたか（§7a 参照）
- [ ] RAG 機能を使うテストで `config(['rag.enabled' => true])` を呼び出しているか（§7b 参照）
- [ ] ローカルで `./vendor/bin/sail pest --filter="テストクラス名"` を実行して通過することを確認したか

