<?php

namespace Tests\Feature\Livewire\Common;

use App\Livewire\Common\ActivityHistoryDisplay;
use App\Models\CustomActivity;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivityHistoryDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    protected function setUp(): void
    {
        parent::setUp();
        fake()->unique(true); // Reset Faker's unique state

        // 既存のログをクリア
        CustomActivity::query()->delete();

        // 権限を作成
        Permission::create(['name' => 'viewAny']);

        // ユーザーの作成
        $this->adminUser = User::factory()->create(['email' => 'admin_'.uniqid().'@example.com']);
        $this->adminUser->givePermissionTo('viewAny');

        $this->generalUser = User::factory()->create(['email' => 'general_'.uniqid().'@example.com']);

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
    }

    #[Test]
    public function shows_permission_error_for_user_without_permission()
    {
        $this->actingAs($this->generalUser);

        Livewire::test(ActivityHistoryDisplay::class)
            ->assertViewIs('livewire.common.activity-history-display-no-permission');
    }

    #[Test]
    public function renders_successfully_for_user_with_permission()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ActivityHistoryDisplay::class)
            ->assertViewIs('livewire.common.activity-history-display')
            ->assertOk();
    }

    #[Test]
    public function displays_all_explicitly_created_activities_in_global_mode()
    {
        $this->actingAs($this->adminUser);

        $component = Livewire::test(ActivityHistoryDisplay::class);
        $allIds = $component->instance()->getActivitiesQuery()->pluck('id');

        $this->assertContains($this->activityA->id, $allIds);
        $this->assertContains($this->activityB->id, $allIds);
        $this->assertContains($this->activityC->id, $allIds);
        $this->assertContains($this->activityD->id, $allIds);
        $this->assertContains($this->activityE->id, $allIds);
    }

    #[Test]
    public function shows_activities_for_a_folder_and_its_descendants()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ActivityHistoryDisplay::class, [
            'resourceType' => 'Folder',
            'resourceId' => $this->folderA->id,
        ])->assertViewHas('activities', function ($activities) {
            $ids = $activities->pluck('id');
            $this->assertContains($this->activityA->id, $ids);
            $this->assertContains($this->activityB->id, $ids);
            $this->assertContains($this->activityC->id, $ids);
            $this->assertContains($this->activityD->id, $ids);
            $this->assertNotContains($this->activityE->id, $ids);

            return true;
        });
    }

    #[Test]
    public function shows_activities_for_a_ledger_define_and_its_records()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ActivityHistoryDisplay::class, [
            'resourceType' => 'LedgerDefine',
            'resourceId' => $this->defineC->id,
        ])->assertViewHas('activities', function ($activities) {
            $ids = $activities->pluck('id');
            $this->assertContains($this->activityC->id, $ids);
            $this->assertContains($this->activityD->id, $ids);
            $this->assertNotContains($this->activityA->id, $ids);
            $this->assertNotContains($this->activityB->id, $ids);

            return true;
        });
    }

    #[Test]
    public function shows_activities_for_a_ledger_with_related_resources()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ActivityHistoryDisplay::class, [
            'resourceType' => 'Ledger',
            'resourceId' => $this->ledgerD->id,
            'includeRelatedResources' => true,
        ])->assertViewHas('activities', function ($activities) {
            $ids = $activities->pluck('id');
            $this->assertContains($this->activityD->id, $ids);
            $this->assertContains($this->activityC->id, $ids);
            $this->assertContains($this->activityB->id, $ids);

            return true;
        });
    }

    #[Test]
    public function shows_activities_for_a_ledger_without_related_resources()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ActivityHistoryDisplay::class, [
            'resourceType' => 'Ledger',
            'resourceId' => $this->ledgerD->id,
            'includeRelatedResources' => false,
        ])->assertViewHas('activities', function ($activities) {
            $ids = $activities->pluck('id');
            $this->assertContains($this->activityD->id, $ids);
            $this->assertNotContains($this->activityC->id, $ids);
            $this->assertNotContains($this->activityB->id, $ids);

            return true;
        });
    }

    #[Test]
    public function filters_activities_by_causer()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ActivityHistoryDisplay::class)
            ->set('filterByUserId', $this->generalUser->id)
            ->assertViewHas('activities', function ($activities) {
                // generalUserによるログは2件のはず
                $this->assertTrue($activities->every(fn ($act) => $act->causer_id === $this->generalUser->id));

                return true;
            });
    }

    #[Test]
    public function filters_activities_by_event()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ActivityHistoryDisplay::class)
            ->set('filterByEvent', 'created')
            ->assertViewHas('activities', function ($activities) {
                // createdイベントを持つログのみが表示される
                $this->assertTrue($activities->every(fn ($act) => $act->event === 'created'));
                $this->assertNotContains($this->activityB->id, $activities->pluck('id')); // updated event

                return true;
            });
    }

    #[Test]
    public function filters_activities_by_date_range()
    {
        $this->actingAs($this->adminUser);

        $futureActivity = activity()->causedBy($this->adminUser)->performedOn($this->folderA)
            ->createdAt(now()->addDay())
            ->log('future_event');

        Livewire::test(ActivityHistoryDisplay::class)
            ->set('filterStartDate', now()->addDay()->toDateString())
            ->set('filterEndDate', now()->addDay()->toDateString())
            ->assertViewHas('activities', function ($activities) use ($futureActivity) {
                $this->assertContains($futureActivity->id, $activities->pluck('id'));
                $this->assertCount(1, $activities);

                return true;
            });
    }

    #[Test]
    public function resets_filters()
    {
        $this->actingAs($this->adminUser);

        $component = Livewire::test(ActivityHistoryDisplay::class)
            ->set('filterByUserId', $this->generalUser->id)
            ->call('resetFilters');

        $component->assertSet('filterByUserId', null);

        $allIds = $component->instance()->getActivitiesQuery()->pluck('id');
        $this->assertContains($this->activityA->id, $allIds);
        $this->assertContains($this->activityB->id, $allIds);
    }

    #[Test]
    public function hides_columns_as_specified()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ActivityHistoryDisplay::class, ['hiddenColumns' => ['subject']])
            ->assertViewHas('headers', function ($headers) {
                $keys = collect($headers)->pluck('key');

                return ! $keys->contains('subject');
            });
    }

    #[Test]
    public function resets_page_when_filter_is_updated()
    {
        $this->actingAs($this->adminUser);

        Livewire::withQueryParams(['page' => 2])
            ->test(ActivityHistoryDisplay::class)
            ->assertViewHas('activities', fn ($activities) => $activities->currentPage() === 2)
            ->set('filterByEvent', 'created')
            ->assertViewHas('activities', fn ($activities) => $activities->currentPage() === 1);
    }
}
