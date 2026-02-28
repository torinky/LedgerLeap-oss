<?php

namespace App\Observers;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;

class LedgerObserver
{
    /**
     * Handle the Ledger "saving" event.
     */
    public function saving(Ledger $ledger): void
    {
        $ledger->default_sort_value = $ledger->generateDefaultSortValue();
    }

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
        // sync/fake/async いずれの場合も dispatch() を使用する。
        // 旧実装では QUEUE_CONNECTION=sync 時に EmbeddingService を直接同期呼び出し
        // していたが、これはテスト環境でコンテナ不在の場合に60秒タイムアウトを引き起こす。
        // dispatch() を使えば:
        //   - QUEUE_CONNECTION=sync  → Laravel が同期実行（コンテナがあれば動作）
        //   - Queue::fake()          → ジョブはキューに積まれるだけで実行されない
        //   - 非同期キュー           → QueueTenancyBootstrapper がテナントを処理
        ProcessLedgerForRagJob::dispatch($ledger->id);
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
