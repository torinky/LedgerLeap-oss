{{-- 標準 Livewire + DaisyUI モーダルの例 --}}
<div>
    {{-- モーダル本体 (表示状態は $showModal で制御) --}}
    <input type="checkbox" id="assignee-modal-toggle" class="modal-toggle" @if($showModal) checked @endif />
    <div class="modal {{ $showModal ? 'modal-open' : '' }}" role="dialog">
        <div class="modal-box">
            <h3 class="font-bold text-lg">
                {{-- roleType に応じてタイトルを変更 --}}
                @if ($roleType === 'inspector')
                    {{ __('ledger.workflow.select_next_inspector') }}
                @elseif ($roleType === 'approver')
                    {{ __('ledger.workflow.select_next_approver') }}
                @else
                    {{ __('ledger.workflow.select_next_assignee') }}
                @endif
            </h3>

            {{-- スペース確保 --}}
            <div class="pt-2 pb-36">
                {{-- WorkflowAssigneeSelect コンポーネントを呼び出し --}}
                {{-- mount で渡されたパラメータを使用 --}}
                @if($showModal)
                    {{-- モーダル表示時のみレンダリング（初期化のため） --}}
                    @livewire('workflow.workflow-assignee-select', [
                    'ledgerDefineId' => $ledgerDefineId,
                    'folderId' => $folderId,
                    'roleType' => $roleType,
                    'ledgerId' => $ledgerId,
                    // wire:model でこのモーダルコンポーネントの $selectedUserId とバインド
                    'wire:model.live' => 'selectedUserId',
                    'initialUserId' => $selectedUserId, // モーダルの $selectedUserId を初期値として渡す
                    ], key('modal-assignee-select-' . $roleType . '-' . $ledgerId ?? 'new'))
                    @error('selectedUserId')
                    <div class="text-xs text-error mt-1">{{ $message }}</div> @enderror
                @endif
            </div>

            <div class="modal-action">
                {{-- キャンセルボタン --}}
                <x-mary-button label="{{ __('Cancel') }}" wire:click="closeModal" class="btn "
                               icon="o-x-circle"
                />
                {{-- 選択ボタン --}}
                <x-mary-button label="{{ __('ledger.workflow.select_assignee') }}" class="btn-primary"
                               wire:click="selectAssignee" spinner="selectAssignee" :disabled="!$selectedUserId"
                               icon="o-paper-airplane"
                />
            </div>
        </div>
        {{-- モーダル外クリックで閉じるためのラベル (オプション) --}}
        <label class="modal-backdrop" wire:click="closeModal" for="assignee-modal-toggle">Close</label>
    </div>
</div>

{{-- --- LivewireUI\Modal を使用する場合の例 ---
<div>
    <div class="p-4">
        <h3 class="font-bold text-lg">
            @if ($roleType === 'inspector') {{ __('ledger.workflow.select_next_inspector') }} @else {{ __('ledger.workflow.select_next_approver') }} @endif
        </h3>

        <div class="py-4">
             @livewire('workflow.workflow-assignee-select', [
                 'ledgerDefineId' => $ledgerDefineId,
                 'folderId' => $folderId,
                 'roleType' => $roleType,
                 'ledgerId' => $ledgerId,
                 'wire:model' => 'selectedUserId', // selectedUserId をバインド
                 'initialUserId' => $initialUserId // mount で渡された初期値を使う
             ], key('modal-assignee-select-' . $roleType . '-' . $ledgerId ?? 'new'))
             @error('selectedUserId') <div class="text-xs text-error mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="flex justify-end space-x-2">
             <x-mary-button label="{{ __('Cancel') }}" wire:click="$dispatch('closeModal')" class="btn-ghost" />
             <x-mary-button label="{{ __('担当者を選択') }}" class="btn-primary" wire:click="selectAssignee" spinner="selectAssignee" :disabled="!$selectedUserId" />
        </div>
    </div>
</div>
--}}