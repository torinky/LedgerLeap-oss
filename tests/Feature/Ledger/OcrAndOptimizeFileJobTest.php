<?php

namespace Tests\Feature\Ledger;

use App\Enums\AttachedFileStatus;
use App\Jobs\Ledger\OcrAndOptimizeFile;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OcrAndOptimizeFileJobTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['id' => 'test-tenant']);
        tenancy()->initialize($this->tenant);
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_successfully_processes_a_pdf_file_and_updates_status()
    {
        // Arrange: Prepare the test environment and data
        Storage::fake('public');
        Bus::fake();

        // 1. Prepare the file in storage
        $fixturePath = base_path('tests/fixtures/files/test.pdf');
        $this->assertFileExists($fixturePath, "Test fixture file is missing.");
        $uploadedFile = new UploadedFile($fixturePath, 'test.pdf', 'application/pdf', null, true);
        
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->for($folder)->create();
        $ledger = Ledger::factory()->for($ledgerDefine, 'define')->create();

        $path = $uploadedFile->storeAs("tenants/{$this->tenant->id}/Ledger/Attachments/{$ledgerDefine->id}", $uploadedFile->hashName(), 'public');

        $attachedFile = AttachedFile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_define_id' => $ledgerDefine->id,
            'ledger_id' => $ledger->id,
            'path' => $path,
            'hashedbasename' => basename($path),
            'filename' => 'test.pdf',
            'mime' => 'application/pdf',
            'size' => $uploadedFile->getSize(),
            'status' => AttachedFileStatus::PENDING_OCR,
        ]);

        // 2. Mock the Process facade for a successful OCR
        Process::fake([
            '*' => function ($process) {
                $command = $process->command;
                $outputPath = $command[count($command) - 1];
                $storagePath = str_replace('/var/www/html/storage/app/public/', '', $outputPath);
                Storage::disk('public')->put($storagePath, 'dummy content');

                return Process::result(
                    output: 'OCR successful',
                    errorOutput: '',
                    exitCode: 0
                );
            },
        ]);

        // Act: Dispatch and handle the job
        $job = new OcrAndOptimizeFile($attachedFile);
        $job->handle();

        // Assert: Verify the outcomes
        $attachedFile->refresh();

        // Assert that the process was called
        Process::assertRan(function ($process) {
            return is_array($process->command) && str_contains(implode(' ', $process->command), 'ocrmypdf');
        });

        // Assert file status is updated
        $this->assertEquals(AttachedFileStatus::PENDING_INITIAL_PROCESSING, $attachedFile->status);
        $this->assertTrue($attachedFile->optimized);

        // Assert original file was moved
        $this->assertNotNull($attachedFile->original_file_path);
        Storage::disk('public')->assertExists($attachedFile->original_file_path);
        
        // Assert new file was created (path should be the same, but content is new)
        $this->assertEquals($path, $attachedFile->path);
        Storage::disk('public')->assertExists($attachedFile->path);
    }
    
    /** @test */
    public function it_handles_ocr_failure_and_updates_status()
    {
        // Arrange
        Storage::fake('public');
        Bus::fake();

        $fixturePath = base_path('tests/fixtures/files/test.pdf');
        $uploadedFile = new UploadedFile($fixturePath, 'test.pdf', 'application/pdf', null, true);

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->for($folder)->create();
        $path = $uploadedFile->storeAs("tenants/{$this->tenant->id}/Ledger/Attachments/{$ledgerDefine->id}", $uploadedFile->hashName(), 'public');

        $attachedFile = AttachedFile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_define_id' => $ledgerDefine->id,
            'path' => $path,
            'status' => AttachedFileStatus::PENDING_OCR,
        ]);

        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'InputFileError',
                exitCode: 2
            ),
        ]);

        // Act
        $job = new OcrAndOptimizeFile($attachedFile);
        $job->handle();

        // Assert
        $attachedFile->refresh();
        Process::assertRan(function ($process) {
            return is_array($process->command) && $process->command[0] === 'docker' && str_contains($process->command[2], 'ocrmypdf');
        });
        $this->assertEquals(AttachedFileStatus::OCR_FAILED, $attachedFile->status);
    }
}
