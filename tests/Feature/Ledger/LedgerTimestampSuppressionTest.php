<?php

namespace Tests\Feature\Ledger;

use App\Console\Commands\CalculateScores;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Services\Scoring\ActivityScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class LedgerTimestampSuppressionTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        Storage::fake('public');
        Bus::fake();

        // 共通のスコア設定
        config([
            'ledgerleap.scoring.activity.windows' => [
                ['days' => 7, 'multiplier' => 10],
            ],
            'ledgerleap.scoring.weights' => [
                'activity' => 1.0,
                'freshness' => 0.0,
                'importance' => 0.0,
                'relevance' => 0.0,
                'popularity' => 0.0,
            ],
        ]);
    }

    #[Test]
    public function it_does_not_update_ledger_timestamp_when_calculating_scores_via_command(): void
    {
        // Arrange
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'activity_score' => 0,
            'updated_at' => now()->subDay(), // 過去のタイムスタンプ
        ]);
        
        $originalUpdatedAt = $ledger->updated_at->toDateTimeString();

        // アクティビティを作成してスコアが確実に変化するようにする
        activity()->performedOn($ledger)->log('updated');

        // Act
        $this->artisan('scoring:calculate')->assertExitCode(0);

        // Assert
        $ledger->refresh();
        $this->assertGreaterThan(0, $ledger->activity_score);
        $this->assertEquals($originalUpdatedAt, $ledger->updated_at->toDateTimeString(), 'updated_at was modified by scoring:calculate command');
    }

    #[Test]
    public function it_does_not_update_ledger_timestamp_when_calculating_scores_via_service(): void
    {
        // Arrange
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'activity_score' => 0,
            'updated_at' => now()->subDay(),
        ]);
        
        $originalUpdatedAt = $ledger->updated_at->toDateTimeString();
        activity()->performedOn($ledger)->log('updated');

        // Act
        app(ActivityScoreService::class)->updateAllLedgers();

        // Assert
        $ledger->refresh();
        $this->assertGreaterThan(0, $ledger->activity_score);
        $this->assertEquals($originalUpdatedAt, $ledger->updated_at->toDateTimeString(), 'updated_at was modified by ActivityScoreService');
    }

    #[Test]
    public function it_does_not_update_ledger_timestamp_during_finalize_attached_file_processing(): void
    {
        // Arrange
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = \App\Models\User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
            'updated_at' => now()->subDay(),
        ]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledger->ledger_define_id,
            'tenant_id' => $ledger->tenant_id,
            'column_id' => 0,
            'filename' => 'test.txt',
            'hashedbasename' => 'test.txt',
            'status' => \App\Enums\AttachedFileStatus::UPLOADED,
            'tika_processed_at' => now()->subMinute(),
            'processing_finalized_at' => null,
            'vlm_markdown' => 'VLM Content',
            'vlm_processed_at' => now()->subMinute(),
            'ocr_processed_at' => now()->subMinute(),
            'path' => \App\Helpers\AttachedFilePathHelper::getAttachmentPath($ledger->ledger_define_id, 'test.txt'),
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        // content_attached に古いデータを入れる（VLM Contentに更新されることでdirtyになるようにする）
        $ledger->content_attached = [
            0 => [
                'test.txt' => [
                    'meta' => ['content' => 'Old content', 'source' => 'tika']
                ]
            ]
        ];
        $ledger->timestamps = false;
        $ledger->save();
        $originalUpdatedAt = $ledger->fresh()->updated_at->toDateTimeString();

        // Act
        $this->artisan('ledger:finalize-processing')->assertExitCode(0);

        // Assert
        $ledger->refresh();
        $this->assertEquals('VLM Content', $ledger->content_attached[0]['test.txt']['meta']['content']);
        $this->assertEquals($originalUpdatedAt, \Illuminate\Support\Carbon::parse($ledger->refresh()->updated_at)->format('Y-m-d H:i:s'), 'updated_at was modified by ledger:finalize-processing command');
    }

    #[Test]
    public function it_does_not_update_ledger_timestamp_during_process_attached_file_job(): void
    {
        // Arrange
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = \App\Models\User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
            'updated_at' => now()->subDay(),
        ]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledger->ledger_define_id,
            'tenant_id' => $ledger->tenant_id,
            'column_id' => 0,
            'mime' => 'text/plain',
            'status' => \App\Enums\AttachedFileStatus::UPLOADED,
            'filename' => 'test.txt',
            'hashedbasename' => 'test.txt',
            'path' => \App\Helpers\AttachedFilePathHelper::getAttachmentPath($ledger->ledger_define_id, 'test.txt'),
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        // 物理ファイルを配置（ProcessAttachedFileがdisk->path()を取得してTikaに渡すため）
        Storage::disk('public')->put($file->path, 'stub content');

        // Tikaクライアントをモック
        $tikaClientMock = $this->mock(\Vaites\ApacheTika\Client::class, function ($mock) {
            $mock->shouldReceive('isAlive')->andReturn(true);
            $mock->shouldReceive('getText')->andReturn('Extracted Tika Content');
            $metadataMock = \Mockery::mock(\Vaites\ApacheTika\Metadata\MetadataInterface::class, \IteratorAggregate::class);
            $metadataMock->shouldReceive('get')->with('mime')->andReturn('text/plain');
            $metadataMock->shouldReceive('getIterator')->andReturn(new \ArrayIterator(['mime' => 'text/plain']));
            $mock->shouldReceive('getMetadata')->andReturn($metadataMock);
            $mock->shouldReceive('setTimeout');
        });
        $this->app->instance(\Vaites\ApacheTika\Client::class, $tikaClientMock);

        $originalUpdatedAt = $ledger->updated_at->toDateTimeString();

        // Act
        $job = new ProcessAttachedFile($file);
        $job->handle();

        // Assert
        $ledger->refresh();
        $this->assertStringContainsString('Extracted Tika Content', json_encode($ledger->content_attached));
        $this->assertEquals($originalUpdatedAt, $ledger->updated_at->toDateTimeString(), 'updated_at was modified by ProcessAttachedFile job');
    }
}
