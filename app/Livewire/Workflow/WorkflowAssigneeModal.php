<?php

namespace App\Livewire\Workflow;

//use LivewireUI\Modal\ModalComponent; // LivewireUI\Modal を使用する場合
// use Livewire\Component; // 標準的な Livewire Component として作成する場合
use Livewire\Attributes\On;
use Livewire\Component;
use App\Livewire\Traits\InitializesTenantContext;

// 標準的な Livewire Component として作成する場合
class WorkflowAssigneeModal extends Component
{
    use InitializesTenantContext;

    public bool $showModal = false; // モーダル表示状態

    // 親から渡されるパラメータ
    public int $ledgerDefineId;
    public int $folderId;
    public string $roleType;
    public ?int $ledgerId = null;

    // WorkflowAssigneeSelect とバインドするプロパティ
    public ?int $selectedUserId = null;

    /**
     * モーダルを開くイベントを受け取るリスナー
     * (親コンポーネントから $dispatch('open-assignee-modal', ...) で呼び出す想定)
     */
    #[On('open-assignee-modal')]
    public function openModal(int $ledgerDefineId, int $folderId, string $roleType, ?int $ledgerId = null, ?int $initialUserId = null): void
    {
        $this->ledgerDefineId = $ledgerDefineId;
        $this->folderId = $folderId;
        $this->roleType = $roleType;
        $this->ledgerId = $ledgerId;
        $this->selectedUserId = $initialUserId; // 初期選択ID
        $this->resetValidation(); // バリデーションエラーをリセット
        $this->showModal = true; // モーダルを表示
    }

    /**
     * 「担当者を選択」ボタンのアクション
     */
    public function selectAssignee(): void
    {
        // 簡単なバリデーション
        $this->validate(['selectedUserId' => 'required|integer|exists:users,id']);

        // 親コンポーネントにイベントを発行
        $this->dispatch('assignee-selected', userId: $this->selectedUserId, roleType: $this->roleType);

        // モーダルを閉じる
        $this->closeModal();
    }

    /**
     * モーダルを閉じる共通処理
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->selectedUserId = null; // 選択をリセット
    }

    public function render()
    {
        // モーダル表示状態に基づいてビューをレンダリング
        return view('livewire.workflow.workflow-assignee-modal');
    }
}

/*
// --- LivewireUI\Modal を使用する場合の例 ---
use LivewireUI\Modal\ModalComponent;

class WorkflowAssigneeModal extends ModalComponent
{
    public int $ledgerDefineId;
    public int $folderId;
    public string $roleType;
    public ?int $ledgerId = null;
    public ?int $initialUserId = null; // 初期選択用

    public ?int $selectedUserId = null; // WorkflowAssigneeSelect とバインド

    public function mount(int $ledgerDefineId, int $folderId, string $roleType, ?int $ledgerId = null, ?int $initialUserId = null): void
    {
        $this->ledgerDefineId = $ledgerDefineId;
        $this->folderId = $folderId;
        $this->roleType = $roleType;
        $this->ledgerId = $ledgerId;
        $this->selectedUserId = $initialUserId; // mount で初期値をセット
    }

    public function selectAssignee(): void
    {
        $this->validate(['selectedUserId' => 'required|integer|exists:users,id']);
        // 親コンポーネントにイベントを発行 (LivewireUI Modal の機能)
        $this->dispatch('assigneeSelected', userId: $this->selectedUserId, roleType: $this->roleType)->to(Show::class); // 例: Show コンポーネントに送る
        $this->closeModal(); // モーダルを閉じる
    }

    public static function modalMaxWidth(): string
    {
        return '2xl'; // モーダルの幅を調整
    }

    public function render()
    {
        return view('livewire.workflow.workflow-assignee-modal');
    }
}
*/