<?php

use App\Livewire\Common\ActivityHistoryDisplay;
use App\Models\CustomActivity;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    fake()->unique(true); // Reset Faker's unique state

    // 既存のログをクリア
    CustomActivity::query()->delete();

    // 権限を作成
    Permission::create(['name' => 'viewAny']);

    // ユーザーの作成
    $this->adminUser = User::factory()->create(['email' => fake()->unique()->safeEmail()]);
    $this->adminUser->givePermissionTo('viewAny');

    $this->generalUser = User::factory()->create(['email' => fake()->unique()->safeEmail()]);

    // テストデータの階層構造を作成
    $this->folderA = Folder::factory()->create(['title' => 'Folder A']);
    $this->folderB = Folder::factory()->create(['title' => 'Folder B', 'parent_id' => $this->folderA->id]);
    $this->defineC = LedgerDefine::factory()->create(['title' => 'LedgerDefine C', 'folder_id' => $this->folderB->id]);
    $this->ledgerD = Ledger::factory()->create(['ledger_define_id' => $this->defineC->id]);
    $this->folderE = Folder::factory()->create(['title' => 'Folder E']);

    // テスト用アクティビティの記録 (後で参照できるようにプロパティに保存)
    $this->activityA = activity()->causedBy($this->adminUser)->performedOn($this->folderA)->log('created');
    $this->activityB = activity()->causedBy($this->generalUser)->performedOn($this->folderB)->log('updated');
    $this->activityC = activity()->causedBy($this->adminUser)->performedOn($this->defineC)->log('created');
    $this->activityD = activity()->causedBy($this->generalUser)->performedOn($this->ledgerD)->log('approved');
    $this->activityE = activity()->causedBy($this->adminUser)->performedOn($this->folderE)->log('created');
});

it('shows permission error for user without permission', function () {
    actingAs($this->generalUser);

    Livewire::test(ActivityHistoryDisplay::class)
        ->assertViewIs('livewire.common.activity-history-display-no-permission');
});

it('renders successfully for user with permission', function () {
    actingAs($this->adminUser);

    Livewire::test(ActivityHistoryDisplay::class)
        ->assertViewIs('livewire.common.activity-history-display')
        ->assertOk();
});

it('displays all explicitly created activities in global mode', function () {
    actingAs($this->adminUser);

    $component = Livewire::test(ActivityHistoryDisplay::class);
    $allIds = $component->instance()->getActivitiesQuery()->pluck('id');

    expect($allIds)
        ->toContain($this->activityA->id)
        ->toContain($this->activityB->id)
        ->toContain($this->activityC->id)
        ->toContain($this->activityD->id)
        ->toContain($this->activityE->id);
});

it('shows activities for a folder and its descendants', function () {
    actingAs($this->adminUser);

    Livewire::test(ActivityHistoryDisplay::class, [
        'resourceType' => 'Folder',
        'resourceId' => $this->folderA->id
    ])->assertViewHas('activities', function ($activities) {
        $ids = $activities->pluck('id');
        expect($ids)
            ->toContain($this->activityA->id) // Folder A
            ->toContain($this->activityB->id) // Folder B (descendant)
            ->toContain($this->activityC->id) // Define C (descendant)
            ->toContain($this->activityD->id) // Ledger D (descendant)
            ->not->toContain($this->activityE->id); // Folder E (sibling)
        return true;
    });
});

it('shows activities for a ledger define and its records', function () {
    actingAs($this->adminUser);

    Livewire::test(ActivityHistoryDisplay::class, [
        'resourceType' => 'LedgerDefine',
        'resourceId' => $this->defineC->id
    ])->assertViewHas('activities', function ($activities) {
        $ids = $activities->pluck('id');
        expect($ids)
            ->toContain($this->activityC->id) // Define C
            ->toContain($this->activityD->id) // Ledger D (record)
            ->not->toContain($this->activityA->id) // Folder A (parent)
            ->not->toContain($this->activityB->id); // Folder B (parent)
        return true;
    });
});

it('shows activities for a ledger with related resources', function () {
    actingAs($this->adminUser);

    Livewire::test(ActivityHistoryDisplay::class, [
        'resourceType' => 'Ledger',
        'resourceId' => $this->ledgerD->id,
        'includeRelatedResources' => true
    ])->assertViewHas('activities', function ($activities) {
        $ids = $activities->pluck('id');
        expect($ids)
            ->toContain($this->activityD->id) // Ledger D
            ->toContain($this->activityC->id) // Define C (parent)
            ->toContain($this->activityB->id); // Folder B (parent)
        return true;
    });
});

it('shows activities for a ledger without related resources', function () {
    actingAs($this->adminUser);

    Livewire::test(ActivityHistoryDisplay::class, [
        'resourceType' => 'Ledger',
        'resourceId' => $this->ledgerD->id,
        'includeRelatedResources' => false
    ])->assertViewHas('activities', function ($activities) {
        $ids = $activities->pluck('id');
        expect($ids)
            ->toContain($this->activityD->id) // Ledger D
            ->not->toContain($this->activityC->id) // Define C (parent)
            ->not->toContain($this->activityB->id); // Folder B (parent)
        return true;
    });
});

it('filters activities by causer', function () {
    actingAs($this->adminUser);

    Livewire::test(ActivityHistoryDisplay::class)
        ->set('filterByUserId', $this->generalUser->id)
        ->assertViewHas('activities', function ($activities) {
            // generalUserによるログは2件のはず
            expect($activities->every(fn($act) => $act->causer_id === $this->generalUser->id))->toBeTrue();
            return true;
        });
});

it('filters activities by event', function () {
    actingAs($this->adminUser);

    Livewire::test(ActivityHistoryDisplay::class)
        ->set('filterByEvent', 'created')
        ->assertViewHas('activities', function ($activities) {
            // createdイベントを持つログのみが表示される
            expect($activities->every(fn($act) => $act->event === 'created'))->toBeTrue()
                ->and($activities->pluck('id'))->not->toContain($this->activityB->id); // updated event
            return true;
        });
});

it('filters activities by date range', function () {
    actingAs($this->adminUser);

    $futureActivity = activity()->causedBy($this->adminUser)->performedOn($this->folderA)
        ->createdAt(now()->addDay())
        ->log('future_event');

    Livewire::test(ActivityHistoryDisplay::class)
        ->set('filterStartDate', now()->addDay()->toDateString())
        ->set('filterEndDate', now()->addDay()->toDateString())
        ->assertViewHas('activities', function ($activities) use ($futureActivity) {
            expect($activities->pluck('id'))->toContain($futureActivity->id)
                ->and($activities)->toHaveCount(1);
            return true;
        });
});

it('resets filters', function () {
    actingAs($this->adminUser);

    $component = Livewire::test(ActivityHistoryDisplay::class)
        ->set('filterByUserId', $this->generalUser->id)
        ->call('resetFilters');

    $component->assertSet('filterByUserId', null);

    $allIds = $component->instance()->getActivitiesQuery()->pluck('id');
    expect($allIds)
        ->toContain($this->activityA->id)
        ->toContain($this->activityB->id);
});

it('hides columns as specified', function () {
    actingAs($this->adminUser);

    Livewire::test(ActivityHistoryDisplay::class, ['hiddenColumns' => ['subject']])
        ->assertViewHas('headers', function ($headers) {
            $keys = collect($headers)->pluck('key');
            return !$keys->contains('subject');
        });
});

it('resets page when filter is updated', function () {
    actingAs($this->adminUser);

    Livewire::withQueryParams(['page' => 2])
        ->test(ActivityHistoryDisplay::class)
        ->assertViewHas('activities', fn ($activities) => $activities->currentPage() === 2)
        ->set('filterByEvent', 'created')
        ->assertViewHas('activities', fn ($activities) => $activities->currentPage() === 1);
});