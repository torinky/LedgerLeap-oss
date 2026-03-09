# Workflow Test Setup Helpers

## createPendingLedgerWithDiff() — canonical helper

```php
private function createPendingLedgerWithDiff(
    WorkflowStatus $status = WorkflowStatus::PENDING_INSPECTION
): array {
    $define = LedgerDefine::factory()
        ->for($this->folder)
        ->create(['workflow_enabled' => true]);

    $ledger = Ledger::factory()->create([
        'ledger_define_id' => $define->id,
        'status'           => $status,
    ]);

    $diff = LedgerDiff::factory()->create([
        'ledger_id'    => $ledger->id,
        'inspector_id' => $this->inspector->id,  // must match Auth::id()
        'approver_id'  => $this->approver->id,
        'status'       => $status,
    ]);

    $ledger->update(['latest_diff_id' => $diff->id]);  // REQUIRED

    return [$ledger->fresh(), $diff];
}
```

## Setting up users with correct permissions

```php
protected function setUp(): void
{
    parent::setUp();
    tenancy()->initialize($this->tenant);

    // Inspector needs INSPECT permission on the folder
    $this->inspector = User::factory()->create();
    RoleFolderPermission::create([
        'role_id'    => $this->inspectorRole->id,
        'folder_id'  => $this->folder->id,
        'permission' => FolderPermissionType::INSPECT,
        'modifier_id'=> $this->admin->id,
    ]);
    $this->inspector->assignRole($this->inspectorRole);

    // Approver needs APPROVE permission
    $this->approver = User::factory()->create();
    RoleFolderPermission::create([
        'role_id'    => $this->approverRole->id,
        'folder_id'  => $this->folder->id,
        'permission' => FolderPermissionType::APPROVE,
        'modifier_id'=> $this->admin->id,
    ]);
    $this->approver->assignRole($this->approverRole);
}
```

## Livewire workflow component test pattern

```php
public function test_inspector_can_open_approval_modal(): void
{
    [$ledger, $diff] = $this->createPendingLedgerWithDiff();

    Livewire::actingAs($this->inspector)
        ->test(PendingList::class)
        ->call('openApproverSelectModal', $ledger->id)
        ->assertDispatched('open-modal');  // or assertSee the modal content
}

public function test_non_inspector_cannot_open_modal(): void
{
    [$ledger, $diff] = $this->createPendingLedgerWithDiff();
    $otherUser = User::factory()->create();

    Livewire::actingAs($otherUser)
        ->test(PendingList::class)
        ->call('openApproverSelectModal', $ledger->id)
        ->assertNotDispatched('open-modal');
}
```

## Rollback test pattern

```php
public function test_rollback_from_approved(): void
{
    [$ledger, $diff] = $this->createPendingLedgerWithDiff(WorkflowStatus::APPROVED);

    app(WorkflowService::class)->rollback($ledger, $this->admin);

    $this->assertEquals(WorkflowStatus::DRAFT, $ledger->fresh()->status);
}
```

## Notification assertion

```php
// WorkflowService dispatches notifications via NotificationService
// In tests, use Queue::fake() + Mail::fake() to prevent actual sending:
Queue::fake();
Mail::fake();

app(WorkflowService::class)->submitForInspection($ledger, $this->inspector->id);

Queue::assertPushed(\App\Jobs\SendWorkflowNotification::class);
```

