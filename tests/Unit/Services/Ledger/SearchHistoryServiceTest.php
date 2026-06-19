<?php

namespace Tests\Unit\Services\Ledger;

use App\Models\CustomActivity;
use App\Models\Folder;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use App\Services\Ledger\SearchHistoryService;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(SearchHistoryService::class)]
class SearchHistoryServiceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private SearchHistoryService $service;

    private User $user;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->service = new SearchHistoryService;

        $this->tenant = $this->getTenant();

        $this->user = User::factory()->create([
            'email' => 'test.'.Str::random(10).'@example.com',
        ]);
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_records_search_conditions_to_activity_log(): void
    {
        $conditions = $this->sampleConditions();

        $activity = $this->service->record($conditions, 10);

        $this->assertInstanceOf(CustomActivity::class, $activity);
        $this->assertSame('searched', $activity->event);
        $this->assertSame($this->user->id, $activity->causer_id);
        $this->assertSame($this->tenant->id, $activity->tenant_id);
        $this->assertSame($conditions, $activity->properties['conditions']);
        $this->assertSame(10, $activity->properties['result_count']);
    }

    #[Test]
    public function it_merges_duplicate_conditions_by_updating_timestamp(): void
    {
        $conditions = $this->sampleConditions();

        $first = $this->service->record($conditions, 5);
        $second = $this->service->record($conditions, 8);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CustomActivity::where('event', 'searched')->count());
        $this->assertSame(8, $second->fresh()->properties['result_count']);
    }

    #[Test]
    public function it_creates_new_record_when_conditions_differ(): void
    {
        $first = $this->service->record($this->sampleConditions(['q' => 'first']), 5);
        $second = $this->service->record($this->sampleConditions(['q' => 'second']), 5);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, CustomActivity::where('event', 'searched')->count());
    }

    #[Test]
    public function it_returns_recent_searches_for_current_user(): void
    {
        $this->service->record($this->sampleConditions(['q' => 'alpha']), 1);
        $this->service->record($this->sampleConditions(['q' => 'beta']), 2);

        $recent = $this->service->getRecent($this->user, $this->tenant->id, 5);

        $this->assertCount(2, $recent);
        $this->assertSame('beta', $recent->first()->properties['conditions']['q']);
    }

    #[Test]
    public function it_does_not_return_other_users_searches(): void
    {
        $this->service->record($this->sampleConditions(['q' => 'mine']), 1);

        $otherUser = User::factory()->create(['email' => 'other.'.Str::random(10).'@example.com']);
        $recent = $this->service->getRecent($otherUser, $this->tenant->id, 5);

        $this->assertCount(0, $recent);
    }

    #[Test]
    public function it_filters_recent_searches_by_restore_permission(): void
    {
        $folder = Folder::factory()->create();
        $allowed = $this->sampleConditions(['f' => [$folder->id], 'cf' => $folder->id]);
        $this->service->record($allowed, 1);

        $this->mock(WritableFolderRepository::class, function ($mock) {
            $mock->shouldReceive('getReadableFolderIds')->andReturn([]);
        });

        $recent = $this->service->getRecent($this->user, $this->tenant->id, 5, onlyRestorable: true);

        $this->assertCount(0, $recent);
    }

    #[Test]
    public function it_deletes_own_search_history(): void
    {
        $activity = $this->service->record($this->sampleConditions(), 1);

        $deleted = $this->service->delete($this->user, $activity->id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('activity_log', ['id' => $activity->id]);
    }

    #[Test]
    public function it_refuses_to_delete_other_users_history(): void
    {
        $activity = $this->service->record($this->sampleConditions(), 1);
        $otherUser = User::factory()->create(['email' => 'other.'.Str::random(10).'@example.com']);

        $deleted = $this->service->delete($otherUser, $activity->id);

        $this->assertFalse($deleted);
        $this->assertDatabaseHas('activity_log', ['id' => $activity->id]);
    }

    #[Test]
    public function build_label_uses_query_and_status_and_folder_names(): void
    {
        $folder = Folder::factory()->create(['title' => '営業部']);
        $conditions = [
            'q' => 'A社 契約書',
            'status' => 'DRAFT',
            'f' => [$folder->id],
        ];

        $label = $this->service->buildLabel($conditions);

        $this->assertStringContainsString('A社 契約書', $label);
        $this->assertStringContainsString('営業部', $label);
    }

    #[Test]
    public function build_label_falls_back_to_default_when_empty(): void
    {
        $this->assertSame(__('ledger.search_suggest.empty_label'), $this->service->buildLabel([]));
    }

    private function sampleConditions(array $overrides = []): array
    {
        return array_merge([
            'q' => 'test query',
            'sort' => 'composite_score',
            'dir' => 'desc',
            'status' => '',
            'filter' => [],
            'l' => [],
            'f' => [],
            'cf' => null,
            'dl' => 1,
            'pp' => 100,
            'sem' => false,
            'syn' => true,
            'tt' => true,
        ], $overrides);
    }
}
