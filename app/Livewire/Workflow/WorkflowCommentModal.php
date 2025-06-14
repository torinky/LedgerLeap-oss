<?php

namespace App\Livewire\Workflow;

use App\Livewire\Ledger\CreateColumn;
use App\Livewire\Ledger\ModifyColumn;
use App\Livewire\Ledger\Show;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class WorkflowCommentModal extends Component
{
    use Toast;

    public bool $showCommentModal = false;
    public string $modalTitle = '';
    public string $actionButtonLabel = '';
    public string $actionButtonClass = 'btn-primary';
    public string $actionType = ''; // 親に渡すアクション識別子
    public ?int $targetLedgerId = null;

    public string $comment = '';
    public string $text='';

    #[On('open-workflow-comment-modal')]
    public function open(string $title, string $actionLabel, string $actionClass, string $actionType, int $ledgerId, ?string $initialComment = '', ?string $text = ''): void
    {
        $this->modalTitle = $title;
        $this->actionButtonLabel = $actionLabel;
        $this->actionButtonClass = $actionClass;
        $this->actionType = $actionType;
        $this->targetLedgerId = $ledgerId;
        $this->comment = $initialComment ?? '';
        $this->resetValidation('comment');
        $this->showCommentModal = true;
        $this->text = $text;
//        dd($this->modalTitle,$this->actionButtonLabel,$this->actionButtonClass,$this->actionType,$this->targetLedgerId,$this->comment);
        $this->render();
    }

    public function executeAction(): void
    {
        // コメントを必須にする場合はバリデーション
        if ($this->actionType === 'return_to_draft') { // 例: 差し戻し時はコメント必須
            $this->validate(['comment' => 'required|string|max:1000']);
        } else {
            $this->validate(['comment' => 'nullable|string|max:1000']);
        }

        // 親コンポーネントにイベントを発行
        $this->dispatch('workflow-action-with-comment',
            actionType: $this->actionType,
            ledgerId: $this->targetLedgerId,
            comment: $this->comment
        )
//            コンポーネントを絞るとModifyColumnに向けたイベントが発火しないのでグローバルにする
//            ->to(Show::class)->to(ModifyColumn::class)->to(CreateColumn::class)
        ; // 対象コンポーネントを指定 (必要なら)
//dd($this->modalTitle,$this->actionButtonLabel,$this->actionButtonClass,$this->actionType,$this->targetLedgerId, $this->comment);
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->showCommentModal = false;
        $this->comment = '';
        $this->actionType = '';
        $this->targetLedgerId = null;
    }

    public function render()
    {
        return view('livewire.workflow.workflow-comment-modal');
    }
}