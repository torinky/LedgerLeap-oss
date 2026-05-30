<?php

namespace App\Jobs\Ledger;

use App\Models\AttachedFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryVlmProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public AttachedFile $attachedFile;

    /**
     * Create a new job instance.
     */
    public function __construct(AttachedFile $attachedFile)
    {
        $this->attachedFile = $attachedFile;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        tenancy()->initialize($this->attachedFile->tenant_id);

        Log::info('[VLM-RETRY] Resetting VLM status for file: '.$this->attachedFile->id);

        // VLM関連フィールドをリセット
        $this->attachedFile->update([
            'vlm_processed_at' => null,
            'vlm_failed_at' => null,
            'vlm_confidence' => null,
            'vlm_markdown' => null,
            'vlm_structured_data' => null,
        ]);

        // VLM処理ジョブをディスパッチ
        ProcessVlmExtraction::dispatch($this->attachedFile)
            ->onQueue('vlm');

        Log::info('[VLM-RETRY] Dispatched ProcessVlmExtraction for file: '.$this->attachedFile->id);
    }
}
