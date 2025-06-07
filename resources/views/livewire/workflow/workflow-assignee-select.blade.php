<div class="w-full">
<x-mary-choices
        class="w-1/3"
        label="{{ $roleType === 'inspector' ? __('ledger.workflow.next_inspector') : __('ledger.workflow.next_approver') }}"
        :options="$options" {{-- Collection を直接渡す --}}
        option-value="id"     {{-- オプションの実際の値のキー --}}
        wire:model.live="selectedUserId"
        placeholder="{{ __('ledger.workflow.assignee_search_placeholder') }}"
        searchable
        single
        clearable
        search-function="searchAssignees" {{-- 呼び出す Livewire メソッド名を指定 --}}
        debounce="300ms" {{-- 任意: 検索実行までの待機時間 --}}
        min-chars="1" {{-- 任意: 検索開始に必要な最小文字数 --}}
        no-result-text="{{ $roleType === 'inspector' ? __('ledger.workflow.no_inspectors') : __('ledger.workflow.no_approvers_found') }}"        wire:key="assignee-select-{{ $roleType }}-{{  $ledgerDefineId }}"
        icon="o-users"
        class="w-full" {{-- 幅を広げる --}}
        height="max-h-72" {{-- 高さを調整 --}}

>
    @scope('item', $user) {{-- $user は User モデルのインスタンス --}}
    <x-mary-list-item :item="$user" value="id" no-hover no-separator class="py-2">
        {{-- User名と理由アイコン --}}
        <x-slot:value>
            <div class="flex items-center">
                <span class="font-semibold mr-2">{{ $user->name }}</span>
                @if(!empty($user->custom_reason_presentations))
                    <span class="flex items-center space-x-1 text-xs">
                                @foreach($user->custom_reason_presentations as $presentation)
                            @if($presentation['icon'])
                                <span class="tooltip" data-tip="{{ __($presentation['tooltip_key']) }}">
                                            <x-mary-icon :name="$presentation['icon']" class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                        </span>
                            @endif
                        @endforeach
                            </span>
                @endif
            </div>
        </x-slot:value>

        {{-- 所属組織とロール --}}
        <x-slot:sub-value>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        @if($user->custom_organization_name)
                            {{ $user->custom_organization_name }}
                        @endif
                        @if($user->custom_organization_name && $user->custom_roles_string)
                            <span class="mx-1">/</span>
                        @endif
                        @if($user->custom_roles_string)
                            {{ $user->custom_roles_string }}
                        @endif
                    </span>
        </x-slot:sub-value>
    </x-mary-list-item>
    @endscope

    @scope('selection', $user) {{-- 選択された項目の表示 --}}
    @if($user)
        {{ $user->name }}
        @if(!empty($user->custom_reason_presentations))
            <span class="ml-1 text-xs">
                        @foreach($user->custom_reason_presentations as $presentation)
                    @if($presentation['icon'])
                        <x-mary-icon :name="$presentation['icon']" class="w-3 h-3 text-gray-400 inline-block" />
                    @endif
                @endforeach
                    </span>
        @endif
        @if($user->custom_organization_name || $user->custom_roles_string)
            <span class="text-xs text-gray-500 ml-1">
                        [{{ trim(($user->custom_organization_name ?? '') . ' / ' . ($user->custom_roles_string ?? ''), ' / ') }}]
                    </span>
        @endif
    @else
        @php
            $selectedOptionFromCollection = $options->firstWhere('id', $selectedUserId);
        @endphp
        {{ $selectedOptionFromCollection?->name ?? __('ledger.workflow.assignee_select_placeholder') }}
    @endif
    @endscope
</x-mary-choices>
   {{-- エラー表示 --}}
    @error('selectedUserId') <span class="text-xs text-error mt-1">{{ $message }}</span> @enderror
</div>