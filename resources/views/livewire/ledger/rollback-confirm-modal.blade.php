<x-mary-modal wire:model="showModal" title="{{ __('ledger.rollback.modal_title') }}" separator>
    @if ($ledger && $targetDiff)
        <div class="space-y-4">
            {{-- ヘッダー情報 --}}
            <div class="bg-base-200/50 p-4 rounded-lg border border-base-300">
                <div class="text-xs text-base-content/60 mb-1">{{ __('ledger.rollback.target_record') }}</div>
                <div class="font-bold">Ver.{{ $targetDiff->version }}
                    ({{ $targetDiff->created_at->format('Y-m-d H:i') }})</div>
                <div class="text-sm mt-1">
                    {{ __('ledger.workflow.label.editor') }}: {{ $targetDiff->modifier?->name ?? '?' }}
                </div>
            </div>

            @if ($step === 1)
                {{-- ステップ1: 理由入力 --}}
                <div class="space-y-4">
                    <p class="text-sm">{{ __('ledger.rollback.step1_description') }}</p>
                    <x-mary-textarea label="{{ __('ledger.rollback.reason_label') }}" wire:model.live="comments"
                        placeholder="{{ __('ledger.rollback.reason_placeholder') }}"
                        hint="{{ __('ledger.rollback.reason_hint') }}" rows="4" />
                </div>
            @else
                {{-- ステップ2: 最終確認 --}}
                <div class="space-y-4">
                    <div class="alert alert-warning shadow-sm">
                        <x-mary-icon name="o-exclamation-triangle" class="w-6 h-6" />
                        <div>
                            <h3 class="font-bold">{{ __('ledger.rollback.warning_title') }}</h3>
                            <div class="text-xs">{{ __('ledger.rollback.warning_description') }}</div>
                        </div>
                    </div>

                    <div class="bg-warning/10 p-4 rounded-lg border border-warning/20">
                        <x-mary-checkbox wire:model.live="understandRisks"
                            label="{{ __('ledger.rollback.understand_risks') }}" class="checkbox-warning font-bold" />
                    </div>

                    <div class="text-sm opacity-70 italic pl-2">
                        {{ __('ledger.rollback.your_comment') }}:<br>
                        "{{ $comments }}"
                    </div>
                </div>
            @endif
        </div>
    @endif

    <x-slot:actions>
        <x-mary-button label="{{ __('ledger.cancel') }}" @click="$wire.showModal = false" />

        @if ($step === 1)
            <x-mary-button label="{{ __('ledger.next') }}" class="btn-primary" wire:click="nextStep"
                :disabled="empty($comments) || strlen($comments) < 5" />
        @else
            <x-mary-button label="{{ __('ledger.back') }}" wire:click="previousStep" />
            <x-mary-button label="{{ __('ledger.rollback.execute_button') }}" class="btn-error"
                wire:click="executeRollback" spinner="executeRollback" :disabled="!$understandRisks" />
        @endif
    </x-slot:actions>
</x-mary-modal>
