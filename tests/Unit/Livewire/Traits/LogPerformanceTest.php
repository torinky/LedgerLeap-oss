<?php

namespace Tests\Unit\Livewire\Traits;

use App\Livewire\Traits\LogPerformance;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogPerformanceTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }

    private function makePerformanceStub(): object
    {
        return new class
        {
            use LogPerformance;

            public function record(string $metric, float $duration, array $metadata = []): void
            {
                $this->logPerformance($metric, $duration, $metadata);
            }

            protected function getPerformanceContext(): array
            {
                return [
                    'tenant_id' => 'tenant-123',
                ];
            }
        };
    }

    #[Test]
    public function testMarksAlwaysOnMetricsAndWarnsWhenThresholdIsExceeded(): void
    {
        config([
            'ledgerleap.performance.enabled' => true,
            'ledgerleap.performance.log_destination' => 'log',
            'ledgerleap.performance.monitoring.thresholds_ms.ledger_records_render' => 100,
        ]);

        $performanceLogger = new class
        {
            public array $calls = [];

            public function warning(string $message, array $context = []): void
            {
                $this->calls[] = compact('message', 'context');
            }
        };

        Log::shouldReceive('info')
            ->once()
            ->with('[Performance] ledger_records_render', \Mockery::on(function (array $context): bool {
                return ($context['metric'] ?? null) === 'ledger_records_render'
                    && is_string($context['component'] ?? null)
                    && ($context['component'] ?? '') !== ''
                    && ($context['tenant_id'] ?? null) === 'tenant-123'
                    && ($context['request_id'] ?? null) === 'req-1'
                    && ($context['monitoring_tier'] ?? null) === 'always_on'
                    && ($context['threshold_ms'] ?? null) === 100.0
                    && ($context['threshold_exceeded'] ?? null) === true
                    && ($context['duration_ms'] ?? null) === 125.0;
            }));

        Log::shouldReceive('channel')
            ->once()
            ->with('performance')
            ->andReturn($performanceLogger);

        $stub = $this->makePerformanceStub();
        $stub->record('ledger_records_render', 125, ['request_id' => 'req-1']);

        $this->assertCount(1, $performanceLogger->calls);
        $this->assertSame(
            '[Performance] ledger_records_render threshold exceeded',
            $performanceLogger->calls[0]['message']
        );
        $this->assertSame(100.0, $performanceLogger->calls[0]['context']['threshold_ms']);
        $this->assertSame(25.0, $performanceLogger->calls[0]['context']['exceeded_by_ms']);
    }

    #[Test]
    public function testSkipsThresholdWarningWhenDurationIsWithinBounds(): void
    {
        config([
            'ledgerleap.performance.enabled' => true,
            'ledgerleap.performance.log_destination' => 'log',
            'ledgerleap.performance.monitoring.thresholds_ms.ledger_records_query_prep_ms' => 200,
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('[Performance] ledger_records_query_prep_ms', \Mockery::on(function (array $context): bool {
                return ($context['monitoring_tier'] ?? null) === 'always_on'
                    && ($context['threshold_ms'] ?? null) === 200.0
                    && ($context['threshold_exceeded'] ?? null) === false;
            }));
        Log::shouldNotReceive('channel');
        Log::shouldNotReceive('warning');

        $stub = $this->makePerformanceStub();
        $stub->record('ledger_records_query_prep_ms', 150);
    }

    #[Test]
    public function testTreatsAnEmptyJsonStatsFileAsAnEmptyCollection(): void
    {
        config([
            'ledgerleap.performance.enabled' => true,
            'ledgerleap.performance.log_destination' => 'both',
            'ledgerleap.performance.monitoring.thresholds_ms.ledger_records_query_paginate_ms' => 9999,
        ]);

        $statsFile = storage_path('logs/performance_stats.json');
        $existingContents = file_exists($statsFile) ? file_get_contents($statsFile) : null;
        file_put_contents($statsFile, '');

        Log::shouldReceive('info')->once();
        Log::shouldNotReceive('channel');
        Log::shouldNotReceive('warning');

        try {
            $stub = $this->makePerformanceStub();
            $stub->record('ledger_records_query_paginate_ms', 10);

            $stats = json_decode(file_get_contents($statsFile), true);
            $this->assertIsArray($stats);
            $this->assertCount(1, $stats);
            $this->assertSame('ledger_records_query_paginate_ms', $stats[0]['metric']);
        } finally {
            if ($existingContents === null) {
                @unlink($statsFile);
            } else {
                file_put_contents($statsFile, $existingContents);
            }
        }
    }
}

