<?php

namespace App\Observers;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Ledger;
use App\Services\Embedding\RuriChunkFormatter;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\DB;

class LedgerObserver
{
    /**
     * Handle the Ledger "created" event.
     */
    public function created(Ledger $ledger): void
    {
        if (config('rag.enabled', false)) {
            $this->dispatchRagJob($ledger);
        }
    }

    /**
     * Handle the Ledger "updated" event.
     */
    public function updated(Ledger $ledger): void
    {
        if (config('rag.enabled', false)) {
            if ($ledger->wasChanged(['content', 'content_attached'])) {
                $this->dispatchRagJob($ledger);
            }
        }
    }

    private function dispatchRagJob(Ledger $ledger): void
    {
        // Queue::fake()使用時はQueueFakeが使われるのでdispatchを使用
        $queueManager = app('queue');
        $isFake = $queueManager instanceof \Illuminate\Support\Testing\Fakes\QueueFake;

        if ($isFake) {
            // テスト環境でQueue::fake()使用時
            ProcessLedgerForRagJob::dispatch($ledger->id);

            return;
        }

        if (config('queue.default') === 'sync') {
            // 同期実行の場合は直接実行（tenancyコンテキストを維持）
            (new ProcessLedgerForRagJob($ledger->id))->handle(
                app(EmbeddingService::class),
                app(RuriChunkFormatter::class)
            );
        } else {
            // 非同期の場合は通常通りdispatch
            // QueueTenancyBootstrapperがtenancyを処理
            ProcessLedgerForRagJob::dispatch($ledger->id);
        }
    }

    /**
     * Handle the Ledger "deleted" event.
     */
    public function deleted(Ledger $ledger): void
    {
        if (config('rag.enabled', false)) {
            DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->delete();
        }
    }

    /**
     * Handle the Ledger "restored" event.
     */
    public function restored(Ledger $ledger): void
    {
        //
    }

    /**
     * Handle the Ledger "force deleted" event.
     */
    public function forceDeleted(Ledger $ledger): void
    {
        //
    }
}
