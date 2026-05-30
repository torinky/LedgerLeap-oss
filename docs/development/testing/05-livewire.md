# Livewire コンポーネントのテストパターン

**最終更新:** 2026-02-28
**元ドキュメント:** Testing-Best-Practices.md（2026-02-22版）より分割

---

## リアクティブプロパティの同期テスト

子コンポーネントが `#[Reactive]` プロパティを持つ場合、テストは親コンポーネントの視点から行う。

```php
// ✅ 推奨: 親子統合テスト
public function test_child_reacts_to_parent_state_change()
{
    Livewire::test(Show::class, ['ledgerId' => $ledger->id])
        ->set('displayLevel', 2)
        ->assertSeeHtml('...')
        ->assertDispatched('displayLevelUpdated');
}
```

---

## `#[Url]` プロパティの同期テスト

```php
// ✅ URL パラメータからの初期化テスト
public function test_it_initializes_from_url_parameters()
{
    Livewire::withQueryParams(['q' => '検索ワード', 'l' => [1, 2]])
        ->test(RecordsTable::class)
        ->assertSet('search', '検索ワード')
        ->assertSet('selectedLedgerDefineIds', [1, 2]);
}
```

---

## `#[Computed]` プロパティのテスト（2026-02-22 追加）

### 問題の背景

`#[Computed]` プロパティは以下の特性を持つ：
1. ビューから参照されて初めて実行される（`render()` だけでは実行されない）
2. 初回実行結果がキャッシュされる
3. `assertStatus(200)` だけではカバレッジが 0% のまま

### 解決策: `instance()` 経由でメソッドを直接呼び出す

```php
// ❌ 悪い: assertStatus(200) だけではメソッドが実行されない
Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $this->ledger])
    ->assertStatus(200);  // workflowHistory() は呼ばれていない

// ✅ 良い: instance() 経由で直接呼び出す
$instance = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $this->ledger])
    ->instance();
$history = $instance->workflowHistory();  // 直接呼び出し → カバレッジ計上
$this->assertInstanceOf(Collection::class, $history);
```

### キャッシュ問題への対処

`render()` が走った時点でキャッシュが確定する。
**テストに渡すモデルは `Livewire::test()` を呼ぶ前に正しい状態にしておく必要がある。**

```php
// ❌ 悪い: setUp()で workflow_enabled=false → render()時にキャッシュされる
$this->ledgerDefine->update(['workflow_enabled' => true]);  // 手遅れ
$instance = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $this->ledger])->instance();

// ✅ 良い: 最初から正しい状態のデータを作成して渡す
$ledgerDefineEnabled = LedgerDefine::factory()
    ->for($this->folder)
    ->create(['workflow_enabled' => true]);  // 最初から true

$ledger = Ledger::with(['define.folder', 'latestDiff'])->find(
    Ledger::factory()->create(['ledger_define_id' => $ledgerDefineEnabled->id])->id
);

$instance = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $ledger])->instance();
```

### `#[CoversClass]` アトリビュートの重要性

`#[CoversClass]` が付いていないテストは、そのクラスのカバレッジとして計上されない。

```php
// ❌ 悪い: カバレッジが計上されないことがある
class WorkflowStatusCardTest extends TestCase { }

// ✅ 良い: 必ず付ける
#[CoversClass(WorkflowStatusCard::class)]
class WorkflowStatusCardTest extends TestCase { }
```

---

## Livewire トースト通知テスト

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

---

## 親子コンポーネントのテスト（IndexManager + RecordsTable）

### 親コンポーネントのテスト対象
- 状態管理（search, selectedLedgerDefineIds, currentFolderId 等）
- URL パラメータとの同期
- イベントハンドリング

### 子コンポーネントのテスト対象
- 受け取ったプロパティに基づく表示ロジック
- データのフィルタリング・ソート・ページネーション

### 推奨パターン1: `withQueryParams()` での初期化

```php
// ✅ 良い: クエリパラメータで初期状態を設定
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

### 推奨パターン2: `wire:key` による順序検証

```php
// ✅ テキストの重複による誤検出を回避
$html = $component->html();
$posB = strpos($html, 'wire:key="ledger_record_' . $defineB->id . '"');
$posC = strpos($html, 'wire:key="ledger_record_' . $defineC->id . '"');
$this->assertLessThan($posC, $posB, 'B should appear before C');
```

### よくある落とし穴と対処法

#### 問題1: 子コンポーネントの HTML が含まれない

```php
// ❌ 悪い: 子の内容に直接依存
$component->assertSee('RecordTitle');

// ✅ 良い: 親の状態管理を検証
$component->assertSet('totalRecords', 10);

// ✅ 良い: 子コンポーネントを直接テスト
Livewire::test(RecordsTable::class, ['search' => 'term', ...])
    ->assertSee('RecordTitle');
```

#### 問題2: `wire:loading.remove.delay` の影響

```php
// ❌ 悪い: set() 後すぐに検証
$component->set('search', 'term')->assertSee('Result');  // 子が削除されている可能性

// ✅ 良い: クエリパラメータで初期化
$component = Livewire::withQueryParams(['q' => 'term'])
    ->test(IndexManager::class)
    ->assertSet('search', 'term');
```

#### 問題3: `totalRecords` が 0 のまま

`totalRecords` は子コンポーネントから `recordsUpdated` イベント経由で更新される。
Livewire テスト環境ではこのイベントが同期的に実行されないため、
`totalRecords` の代わりに `selectedLedgerDefineIds` 等の親の状態を検証すること。

### テスト設計チェックリスト

#### 親コンポーネント（IndexManager）テスト
- [ ] `withQueryParams()` で初期状態を設定しているか
- [ ] 状態管理（プロパティの更新）を検証しているか
- [ ] 子コンポーネントの表示内容に依存していないか

#### 子コンポーネント（RecordsTable）テスト
- [ ] 必要なプロパティを全て渡しているか
- [ ] 表示ロジックを個別に検証しているか

### 実装例

- `tests/Feature/Livewire/Ledger/RecordsTableLedgerDefineSortTest.php`
- `tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php`
- `tests/Feature/Livewire/Ledger/RecordsTableCompositeScoreSortTest.php`

---

## URL 同期のベストプラクティス

### `#[Url]` 属性の共有

親と子の両方で同じプロパティを `#[Url]` として定義することで、
Livewire 3 はそれらが同一の URL クエリを指していることを自動認識する。

### 明示的なパラメータ渡しの回避

Blade で `:param="$param"` のように明示的に渡すと、ページリロード時に
「親の初期値(null)」が「URL から復元された子の値」を上書きする競合が発生する。
URL と同期するプロパティは親子間で明示的に渡さず、それぞれが URL から独立して復元するように設計する。

### 初期化時のガード

```php
public function mount() {
    if (! $this->myParam) {
        $this->myParam = 'default';
    }
}
```

---

## `CannotMutateReactivePropException` の回避

子コンポーネント内で `#[Reactive]` プロパティを書き換えるとランタイムエラーが発生する。
コレクションなどを渡す際、サービス内でリレーションをロード（`loadMissing` 等）すると
変異とみなされる場合がある。`clone` や `Collection::make()` で防御的にコピーを渡す実装が
正しく機能しているか確認すること。

---

## データ整合性テスト

マルチテナント環境において `tenant_id` の欠落は致命的なセキュリティリスクを招く。

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

`LedgerDiff` のように副産物として作成されるレコードは `tenant_id` を忘れやすいため、
生成サービスのテストで `tenant_id` が継承されているかを必ず検証すること。

