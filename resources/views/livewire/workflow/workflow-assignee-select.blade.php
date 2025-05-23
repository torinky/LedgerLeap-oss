<div class="w-full">
<x-mary-choices
        class="w-1/3"
        label="{{ $roleType === 'inspector' ? __('ledger.workflow.next_inspector') : __('ledger.workflow.next_approver') }}"
        :options="$options" {{-- Collection を直接渡す --}}
        wire:model.live="selectedUserId"
        placeholder="{{ __('ledger.workflow.assignee_search_placeholder') }}"
        searchable
        single
        clearable
        search-function="searchAssignees" {{-- 呼び出す Livewire メソッド名を指定 --}}
        debounce="300ms" {{-- 任意: 検索実行までの待機時間 --}}
        min-chars="1" {{-- 任意: 検索開始に必要な最小文字数 --}}
        no-result-text="{{ __('ledger.workflow.no_inspectors') }}" {{-- 任意: 検索結果がない場合のテキスト --}}
        wire:key="assignee-select-{{ $roleType }}-{{  $ledgerDefineId }}"
        icon="o-users"
/>
   {{-- エラー表示 --}}
    @error('selectedUserId') <span class="text-xs text-error mt-1">{{ $message }}</span> @enderror
</div>