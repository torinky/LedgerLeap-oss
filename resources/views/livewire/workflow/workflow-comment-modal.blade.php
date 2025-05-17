<div>
{{--
    @if($modalTitle)
    @dd($modalTitle,$actionButtonLabel,$actionButtonClass,$actionType,$targetLedgerId,$comment)
    @endif
--}}
    <x-mary-modal wire:model="showCommentModal" :title="$modalTitle">
        <x-mary-textarea
                label="{{ __('ledger.workflow.comments') }}"
                wire:model="comment"
                placeholder="{{ __('ledger.workflow.comment_placeholder') }}"
                hint="{{ $actionType === 'return_to_draft' ? __('ledger.workflow.comment_hint_return_reason_required') : __('ledger.workflow.comment_hint_optional') }}"
                rows="4"
        />
        @error('comment') <span class="text-xs text-error mt-1">{{ $message }}</span> @enderror

        <x-slot:actions>
            <x-mary-button label="{{ __('Cancel') }}" @click="$wire.closeModal()"/> {{-- closeModal を呼び出す --}}
            <x-mary-button :label="$actionButtonLabel" :class="$actionButtonClass" wire:click="executeAction"
                           spinner="executeAction"/>
        </x-slot:actions>
    </x-mary-modal>
</div>