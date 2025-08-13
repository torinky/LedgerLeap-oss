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
//use Mockery;
use Illuminate\Support\Facades\Process; // ★ use を変更
use Tests\TestCase;

class OcrAndOptimizeFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Bus::fake();
        // Process::fake() は各テストメソッド内で個別に設定します
    }

    #[Test]
    public function ocr_job_processes_ocr_eligible_files()
    {
        // ★ Process ファサードをフェイク化し、成功をシミュレート
        Process::fake();

        // --- Arrange ---
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();

        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'image/jpeg',
            'status' => AttachedFileStatus::PENDING_OCR, // Enumインスタンスを直接渡す
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledger->ledger_define_id,
            'column_id' => 1,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
            'hashedbasename' => \Illuminate\Support\Str::random(40) . '.jpeg',
            'optimized' => false,
            'original_file_path' => 'originals/test.jpeg',
        ]);

        // OCR対象の元ファイルを仮想ストレージに配置
        Storage::disk('public')->put($attachedFile->original_file_path, 'dummy image content');

        // OCR後の出力ファイルを事前に作成しておく
        // ジョブ内で Storage::size() が呼ばれるため、ファイルが存在しないとエラーになるのを防ぎます
        $outputFilename = pathinfo($attachedFile->hashedbasename, PATHINFO_FILENAME) . '.pdf';
        $outputStoragePath = AttachedFilePathHelper::getAttachmentPath($attachedFile->ledger_define_id, $outputFilename);
        Storage::disk('public')->put($outputStoragePath, 'dummy ocr content');

        // --- Act ---
        $job = new OcrAndOptimizeFile($attachedFile);
        $job->handle();

        // --- Assert ---
        // ProcessAttachedFile がディスパッチされたことを確認
        Bus::assertDispatched(ProcessAttachedFile::class);

        // データベースのレコードが正しく更新されたことを確認
        $attachedFile->refresh();
        $this->assertSame(AttachedFileStatus::PENDING_INITIAL_PROCESSING, $attachedFile->status);
        $this->assertTrue($attachedFile->optimized);
        $this->assertEquals('application/pdf', $attachedFile->mime);
        $this->assertEquals($outputStoragePath, $attachedFile->path);

        // OCR後のファイルが存在することを確認
        Storage::disk('public')->assertExists($outputStoragePath);

        // (オプション) 実行されたコマンドをアサート
        Process::assertRan(function ($process) {
            // 配列をスペースで連結した文字列に変換してから、特定のコマンドが含まれているかチェックします。
            return str_contains(implode(' ', $process->command), 'ocrmypdf');
        });
    }

    #[Test]
    public function ocr_processing_fails_and_updates_status()
    {
        // ★ Process ファサードをフェイク化し、失敗をシミュレート
        Process::fake([
            '*' => Process::result(
                exitCode: 1, // 0以外のコードで失敗
                errorOutput: 'OCR error message'
            ),
        ]);

        // --- Arrange ---
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();

        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'application/pdf',
            'status' => AttachedFileStatus::PENDING_OCR,
            'original_file_path' => 'originals/test.pdf',
        ]);

        Storage::disk('public')->put($attachedFile->original_file_path, 'dummy pdf content');

        // --- Act ---
        $job = new OcrAndOptimizeFile($attachedFile);
        $job->handle();

        // --- Assert ---
        // ステータスが OCR_FAILED に更新されたことを確認
        // ★ Enum インスタンス同士で比較することで、型安全なアサーションになります
        $this->assertSame(AttachedFileStatus::OCR_FAILED, $attachedFile->fresh()->status);

        // ProcessAttachedFile がディスパッチされていないことを確認
        Bus::assertNotDispatched(ProcessAttachedFile::class);
    }
}