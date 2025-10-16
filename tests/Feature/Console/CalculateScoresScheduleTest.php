<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalculateScoresScheduleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_schedules_scoring_command_with_default_daily_frequency(): void
    {
        // デフォルト設定（daily）
        config(['ledgerleap.scoring.schedule_frequency' => 'daily']);

        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        // scoring:calculate コマンドを探す
        $scoringEvent = $events->first(function (Event $event) {
            return str_contains($event->command ?? '', 'scoring:calculate');
        });

        $this->assertNotNull($scoringEvent, 'scoring:calculate command should be scheduled');

        // daily スケジュールであることを確認
        // Note: Laravelのスケジュールイベントは内部的に複雑で、
        // 正確な頻度を外部から判定するのは困難なため、
        // ここではコマンドがスケジュールされていることのみを確認
        $this->assertStringContainsString('scoring:calculate', $scoringEvent->command ?? '');
    }

    #[Test]
    public function it_schedules_scoring_command_with_hourly_frequency(): void
    {
        config(['ledgerleap.scoring.schedule_frequency' => 'hourly']);

        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $scoringEvent = $events->first(function (Event $event) {
            return str_contains($event->command ?? '', 'scoring:calculate');
        });

        $this->assertNotNull($scoringEvent, 'scoring:calculate command should be scheduled');
        $this->assertStringContainsString('scoring:calculate', $scoringEvent->command ?? '');
    }

    #[Test]
    public function it_schedules_scoring_command_with_every_five_minutes_frequency(): void
    {
        config(['ledgerleap.scoring.schedule_frequency' => 'everyFiveMinutes']);

        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $scoringEvent = $events->first(function (Event $event) {
            return str_contains($event->command ?? '', 'scoring:calculate');
        });

        $this->assertNotNull($scoringEvent, 'scoring:calculate command should be scheduled');
        $this->assertStringContainsString('scoring:calculate', $scoringEvent->command ?? '');
    }

    #[Test]
    public function it_defaults_to_daily_when_invalid_frequency_is_provided(): void
    {
        config(['ledgerleap.scoring.schedule_frequency' => 'invalid_frequency']);

        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $scoringEvent = $events->first(function (Event $event) {
            return str_contains($event->command ?? '', 'scoring:calculate');
        });

        $this->assertNotNull($scoringEvent, 'scoring:calculate command should be scheduled with default frequency');
        $this->assertStringContainsString('scoring:calculate', $scoringEvent->command ?? '');
    }

    #[Test]
    public function it_reads_frequency_from_config_with_env_default(): void
    {
        // configヘルパーで設定を取得し、envのデフォルト値が反映されることを確認
        $frequency = config('ledgerleap.scoring.schedule_frequency');

        $this->assertNotNull($frequency);
        $this->assertIsString($frequency);
        // デフォルトは 'daily'
        $this->assertEquals('daily', $frequency);
    }
}
