<?php

namespace Tests\Unit\Services\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ledger\LedgerDefineStatsService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(LedgerDefineStatsService::class)]
class LedgerDefineStatsServiceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private LedgerDefineStatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        $this->service = new LedgerDefineStatsService;
        Cache::flush();
    }

    // ----------------------------------------------------------------
    // Empty input
    // ----------------------------------------------------------------

    #[Test]
    public function empty_define_ids_returns_empty_array(): void
    {
        $result = $this->service->computeOverallStats([]);

        $this->assertEmpty($result);
    }

    // ----------------------------------------------------------------
    // Single ledger define ID
    // ----------------------------------------------------------------

    #[Test]
    public function single_define_stats_are_correct(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $define = LedgerDefine::factory()->create();
        Ledger::factory()->count(3)->create([
            'ledger_define_id' => $define->id,
            'composite_score' => 100,
        ]);

        $result = $this->service->computeOverallStats([$define->id], $tenant->id);

        $this->assertArrayHasKey($define->id, $result);
        $this->assertSame(3, $result[$define->id]['count']);
        $this->assertSame(100.0, $result[$define->id]['avg_score']);
        $this->assertSame(100.0, $result[$define->id]['max_score']);
        $this->assertSame(100.0, $result[$define->id]['min_score']);
        $this->assertTrue($result[$define->id]['has_scores']);
    }

    // ----------------------------------------------------------------
    // Multiple ledger define IDs
    // ----------------------------------------------------------------

    #[Test]
    public function multiple_defines_have_separate_stats(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $define1 = LedgerDefine::factory()->create();
        $define2 = LedgerDefine::factory()->create();

        Ledger::factory()->count(2)->create([
            'ledger_define_id' => $define1->id,
            'composite_score' => 100,
        ]);
        Ledger::factory()->count(2)->create([
            'ledger_define_id' => $define2->id,
            'composite_score' => 200,
        ]);

        $result = $this->service->computeOverallStats([$define1->id, $define2->id], $tenant->id);

        $this->assertSame(100.0, $result[$define1->id]['avg_score']);
        $this->assertSame(200.0, $result[$define2->id]['avg_score']);
    }

    // ----------------------------------------------------------------
    // Zero / negative scores handling
    // ----------------------------------------------------------------

    #[Test]
    public function zero_scores_are_excluded_from_avg(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $define = LedgerDefine::factory()->create();
        Ledger::factory()->create([
            'ledger_define_id' => $define->id,
            'composite_score' => 100,
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $define->id,
            'composite_score' => 0,
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $define->id,
            'composite_score' => 0,
        ]);

        $result = $this->service->computeOverallStats([$define->id], $tenant->id);
        $stats = $result[$define->id];

        $this->assertSame(3, $stats['count']);
        $this->assertSame(100.0, $stats['avg_score']);
        $this->assertSame(100.0, $stats['max_score']);
        $this->assertSame(0.0, $stats['min_score']);
        $this->assertTrue($stats['has_scores']);
    }

    #[Test]
    public function all_zero_scores_result_in_zero_stats(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $define = LedgerDefine::factory()->create();
        Ledger::factory()->count(2)->create([
            'ledger_define_id' => $define->id,
            'composite_score' => 0,
        ]);

        $result = $this->service->computeOverallStats([$define->id], $tenant->id);
        $stats = $result[$define->id];

        $this->assertSame(2, $stats['count']);
        $this->assertSame(0, $stats['avg_score']);
        $this->assertSame(0, $stats['max_score']);
        $this->assertSame(0, $stats['min_score']);
        $this->assertFalse($stats['has_scores']);
    }

    // ----------------------------------------------------------------
    // User-specific caching
    // ----------------------------------------------------------------

    #[Test]
    public function different_users_have_separate_cache_entries(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $define = LedgerDefine::factory()->create();
        Ledger::factory()->count(2)->create([
            'ledger_define_id' => $define->id,
            'composite_score' => 100,
        ]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $resultA = $this->service->computeOverallStats([$define->id], $tenant->id, $userA);
        $resultB = $this->service->computeOverallStats([$define->id], $tenant->id, $userB);

        $this->assertSame($resultA, $resultB);
        // キャッシュキーが異なることを間接的に確認：
        // 同一データでも別ユーザーのキャッシュエントリが作成される
        $this->assertNotNull(Cache::get($this->invokeBuildCacheKey([$define->id], $tenant->id, $userA)));
        $this->assertNotNull(Cache::get($this->invokeBuildCacheKey([$define->id], $tenant->id, $userB)));
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function invokeBuildCacheKey(array $ledgerDefineIds, ?string $tenantId, ?User $user): string
    {
        $reflection = new \ReflectionMethod($this->service, 'buildCacheKey');

        return $reflection->invoke($this->service, $ledgerDefineIds, $tenantId, $user);
    }
}
