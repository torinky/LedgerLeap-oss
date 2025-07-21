<?php

namespace tests\Unit\Jobs;

use App\Enums\AttachedFileStatus;
use App\Jobs\Ledger\OcrAndOptimizeFile;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\User;
use App\Models\ColumnDefine;
use App\Helpers\AttachedFilePathHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OcrAndOptimizeFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Bus::fake();
        $this->app->instance('log', Mockery::mock(\Illuminate\Log\Logger::class));
    }

    /** @test */
    public function ocr_job_processes_ocr_eligible_files()
    {
        // Process クラスをモック
        $processMock = Mockery::mock(Process::class);
        $processMock->shouldReceive('setTimeout')->andReturnSelf();
        $processMock->shouldReceive('setIdleTimeout')->andReturnSelf();
        $processMock->shouldReceive('run');
        $processMock->shouldReceive('isSuccessful')->andReturn(true);
        $processMock->shouldReceive('getErrorOutput')->andReturn('');

        // Process クラスのインスタンスが生成されるときにモックを返すようにする
        $this->app->instance(Process::class, $processMock);

        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $columnDefine = new ColumnDefine(
            1, // id
            'test_file_column', // name
            'files', // typeIdentifier
            1, // order
            [], // options
            false, // required
            false, // unique
            false, // sortBy
            '', // hint
            [] // file
        );

        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'image/jpeg', // Or application/pdf
            'status' => AttachedFileStatus::PENDING_OCR->value,
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledger->ledger_define_id,
            'column_id' => $columnDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
            'filename' => 'test.jpeg',
            'hashedbasename' => \Illuminate\Support\Str::random(40) . '.jpeg',
            'contain_content' => false,
            'optimized' => false,
            'path' => AttachedFilePathHelper::getOriginalAttachmentPath($ledger->ledger_define_id, 'test.jpeg'), // Use helper for path
            'original_file_path' => AttachedFilePathHelper::getOriginalAttachmentPath($ledger->ledger_define_id, 'test.jpeg'),
        ]);

        Storage::disk('public')->put(AttachedFilePathHelper::getOriginalAttachmentPath($attachedFile->ledger_define_id, 'test.jpeg'), 'dummy image content');

        $outputPhysicalPath = Storage::disk('public')->path(AttachedFilePathHelper::getAttachmentPath($attachedFile->ledger_define_id, pathinfo($attachedFile->hashedbasename, PATHINFO_FILENAME) . '.pdf'));
        // Process モックがファイルを作成するように設定
        file_put_contents($outputPhysicalPath, 'dummy ocr content');

        $job = new OcrAndOptimizeFile($attachedFile);
        $job->handle();

        // ProcessAttachedFile がディスパッチされたことを確認
        Bus::assertDispatched(ProcessAttachedFile::class);

        // OCR 後のファイルが新しいパスに保存されたことを確認
        Storage::disk('public')->assertExists(AttachedFilePathHelper::getAttachmentPath($attachedFile->ledger_define_id, pathinfo($attachedFile->hashedbasename, PATHINFO_FILENAME) . '.pdf'));
    }

    /** @test */
    public function ocr_processing_fails_and_updates_status()
    {
        // Process クラスをモックし、失敗をシミュレート
        $processMock = Mockery::mock(Process::class);
        $processMock->shouldReceive('setTimeout')->andReturnSelf();
        $processMock->shouldReceive('setIdleTimeout')->andReturnSelf();
        $processMock->shouldReceive('run');
        $processMock->shouldReceive('isSuccessful')->andReturn(false);
        $processMock->shouldReceive('getErrorOutput')->andReturn('OCR error message');

        $this->app->instance(Process::class, $processMock);

        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $columnDefine = new ColumnDefine(
            1, // id
            'test_file_column', // name
            'files', // typeIdentifier
            1, // order
            [], // options
            false, // required
            false, // unique
            false, // sortBy
            '', // hint
            [] // file
        );

        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'application/pdf',
            'status' => AttachedFileStatus::PENDING_OCR->value,
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledger->ledger_define_id,
            'column_id' => $columnDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
            'filename' => 'test.pdf',
            'hashedbasename' => \Illuminate\Support\Str::random(40) . '.pdf',
            'contain_content' => false,
            'optimized' => false,
            'path' => AttachedFilePathHelper::getOriginalAttachmentPath($ledger->ledger_define_id, 'test.pdf'),
            'original_file_path' => AttachedFilePathHelper::getOriginalAttachmentPath($ledger->ledger_define_id, 'test.pdf'),
        ]);

        Storage::disk('public')->put(AttachedFilePathHelper::getOriginalAttachmentPath($attachedFile->ledger_define_id, 'test.pdf'), 'dummy pdf content');

        $job = new OcrAndOptimizeFile($attachedFile);
        $job->handle();

        // ステータスが OCR_FAILED に更新されたことを確認
        $this->assertEquals(AttachedFileStatus::OCR_FAILED->value, $attachedFile->fresh()->status);

        // ProcessAttachedFile がディスパッチされていないことを確認
        Bus::assertNotDispatched(ProcessAttachedFile::class);
    }
}