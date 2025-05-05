<div>
    <x-mary-select
            label="{{ $roleType === 'inspector' ? __('ledger.workflow.next_inspector') : __('ledger.workflow.next_approver') }}"
            :options="$selectOptions" {{-- 整形済みのオプション配列を使用 --}}
            wire:model.live="selectedUserId" {{-- Modelable で親と同期 --}}
            placeholder="{{ __('担当者を選択または検索...') }}"
            searchable {{-- 検索可能に --}}
            wire:key="assignee-select-{{ $roleType }}" {{-- 一意なキー --}}
            wire:input.debounce.300ms="searchQuery = $event.target.value" {{-- 検索用 --}}
    />
    @error('selectedUserId') <span class="text-xs text-error mt-1">{{ $message }}</span> @enderror
</div>