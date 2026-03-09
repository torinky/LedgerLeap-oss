# Test Data Patterns — Ledger content

## Rule: Always fill indices 0..maxColumnId

```php
// ❌ Wrong — gaps cause index shift
$ledger = Ledger::factory()->create([
    'content' => [
        0 => 'Title',
        7 => ['tag1', 'tag2'],  // indices 1-6 missing
    ],
]);
// DB stores: ["Title", ["tag1","tag2"]]
// $ledger->content[7] => NULL  ← shifted to index 1

// ✅ Correct — fill every index up to maxId
$ledger = Ledger::factory()->create([
    'content' => [
        0 => 'Title',
        1 => '', 2 => '', 3 => '', 4 => [], 5 => '', 6 => '',
        7 => ['tag1', 'tag2'],
    ],
]);
// $ledger->content[7] => ['tag1','tag2']  ✓
```

## content_attached structure

```php
// ✅ Correct — index 0 sentinel required
$ledger = Ledger::factory()->create([
    'content_attached' => [
        0 => [],   // required sentinel
        1 => [
            'test.pdf' => [
                'meta' => ['content' => 'OCR extracted text'],
            ],
        ],
    ],
]);

// Access
$text = $ledger->content_attached[1]['test.pdf']['meta']['content'] ?? null;
```

## Helper pattern for normalized creation

```php
protected function createLedgerWithContent(LedgerDefine $define, array $content): Ledger
{
    $normalized = $define->normalizeByColumnDefine($content);
    return Ledger::factory()->create([
        'ledger_define_id' => $define->id,
        'content' => $normalized,
    ]);
}
```

## latest_diff_id — explicit assignment required

```php
// ❌ Wrong — latest_diff_id stays null
$ledger = Ledger::factory()->create(['status' => WorkflowStatus::PENDING_INSPECTION]);
$diff = LedgerDiff::factory()->create(['ledger_id' => $ledger->id]);
// $ledger->latestDiff => NULL

// ✅ Correct
$ledger = Ledger::factory()->create(['status' => WorkflowStatus::PENDING_INSPECTION]);
$diff = LedgerDiff::factory()->create([
    'ledger_id'    => $ledger->id,
    'inspector_id' => $this->inspector->id,
    'status'       => WorkflowStatus::PENDING_INSPECTION,
]);
$ledger->update(['latest_diff_id' => $diff->id]);
$ledger = $ledger->fresh();  // reload to reflect latest_diff_id

// $ledger->latestDiff->id === $diff->id  ✓
```

## Livewire flow (no manual factory needed)

```php
// Livewire test — normalizeByColumnDefine() is called automatically
Livewire::test(CreateColumn::class, ['ledgerDefineId' => $define->id])
    ->set('content.1', 'test value')
    ->call('saveDraft');
```

## Debug: inspect raw DB JSON

```php
$ledger = Ledger::find($ledgerId);
dd([
    'content'      => $ledger->content,
    'content_keys' => array_keys($ledger->content),
    'db_raw'       => $ledger->getAttributes()['content'],
]);
```

