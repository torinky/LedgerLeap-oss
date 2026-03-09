# #[Computed] Property Test Patterns

## Full example — WorkflowStatusCard

```php
#[CoversClass(WorkflowStatusCard::class)]
class WorkflowStatusCardTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    public function test_workflow_history_returns_collection(): void
    {
        // ✅ Model in final state BEFORE Livewire::test()
        $ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create(['workflow_enabled' => true]);

        $ledger = Ledger::with(['define.folder', 'latestDiff'])
            ->find(Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id])->id);

        // ✅ instance() triggers the Computed method
        $instance = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $ledger])
            ->instance();

        $history = $instance->workflowHistory();
        $this->assertInstanceOf(Collection::class, $history);
    }
}
```

## Cache trap

```php
// ❌ Too late — render() already cached workflow_enabled=false
$instance = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $ledger])->instance();
$this->ledgerDefine->update(['workflow_enabled' => true]);  // no effect
$result = $instance->workflowHistory();  // returns empty (cached)

// ✅ Correct — create with correct state from the start
$define = LedgerDefine::factory()->create(['workflow_enabled' => true]);
$ledger = Ledger::with(['define.folder', 'latestDiff'])
    ->find(Ledger::factory()->create(['ledger_define_id' => $define->id])->id);
$instance = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $ledger])->instance();
$result = $instance->workflowHistory();  // correct ✓
```

## #[Reactive] — test from parent

```php
// ✅ Drive through parent
Livewire::test(Show::class, ['ledgerId' => $ledger->id])
    ->set('displayLevel', 2)
    ->assertSeeHtml('some-expected-html')
    ->assertDispatched('displayLevelUpdated');

// ❌ Avoid: child in isolation with reactive props is fragile
Livewire::test(ChildComponent::class, ['displayLevel' => 2]);
```

## MaryUI toast assertion

```php
Livewire::test(MyComponent::class)
    ->call('saveData')
    ->assertDispatched('mary-toast', ['type' => 'success', 'title' => '保存完了']);
```

## Reference files

- `tests/Feature/Livewire/Ledger/RecordsTableLedgerDefineSortTest.php`
- `docs/development/testing/05-livewire.md`
