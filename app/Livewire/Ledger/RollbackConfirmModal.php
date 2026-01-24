<?php

namespace App\Livewire\Ledger;

use App\Exceptions\Workflow\WorkflowConditionException;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Services\Ledger\RollbackService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class RollbackConfirmModal extends Component
{
    use Toast;

    public bool $showModal = false;

    public ?int $ledgerId = null;

    public ?int $targetDiffId = null;

    public ?int $expectedVersion = null;

    public ?string $comments = '';

    public bool $understandRisks = false;

    public int $step = 1;

    public ?Ledger $ledger = null;

    public ?LedgerDiff $targetDiff = null;

    protected RollbackService $rollbackService;

    public function boot(RollbackService $rollbackService): void
    {
        $this->rollbackService = $rollbackService;
    }

    #[On('ledger.rollback.open-modal')]
    public function openModal(int $ledgerId, int $targetDiffId, int $expectedVersion): void
    {
        $this->reset(['comments', 'understandRisks', 'step']);
        $this->ledgerId = $ledgerId;
        $this->targetDiffId = $targetDiffId;
        $this->expectedVersion = $expectedVersion;

        $this->ledger = Ledger::findOrFail($ledgerId);
        $this->targetDiff = LedgerDiff::findOrFail($targetDiffId);

        // デフォルトコメントの設定（ユーザー要望により空にする）
        $this->comments = '';

        // 実行可能性の事前チェック
        try {
            if (! $this->rollbackService->canExecute(auth()->user(), $this->ledger)) {
                $this->error(__('ledger.errors.no_permission_to_claim'));

                return;
            }
        } catch (WorkflowConditionException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->showModal = true;
    }

    public function nextStep(): void
    {
        $this->validate([
            'comments' => 'required|min:5|max:500',
        ]);
        $this->step = 2;
    }

    public function previousStep(): void
    {
        $this->step = 1;
    }

    public function executeRollback(): void
    {
        if (! $this->understandRisks) {
            return;
        }

        $this->validate([
            'comments' => 'required|min:5|max:500',
        ]);

        try {
            // システム情報を追記（表示用デリミタ付き）
            $systemInfo = __('ledger.rollback.source_info', ['version' => $this->targetDiff->version]);
            $finalComments = $this->comments . "\n--- system-info ---\n" . $systemInfo;

            $this->rollbackService->execute(
                $this->ledger,
                $this->targetDiff,
                auth()->user(),
                $finalComments,
                $this->expectedVersion
            );

            $this->success(__('ledger.rollback.success_message'));
            $this->showModal = false;

            // 完了イベントを発火（Showコンポーネント等が捕捉して詳細タブへ遷移させる）
            $this->dispatch('ledger.rollback.completed', 
                ledgerId: $this->ledgerId,
                targetDiffId: $this->targetDiffId
            );

        } catch (WorkflowConditionException $e) {
            $this->error($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Rollback failed: '.$e->getMessage());
            $this->error(__('ledger.error.operation_failed'));
        }
    }

    public function render()
    {
        return view('livewire.ledger.rollback-confirm-modal');
    }
}
