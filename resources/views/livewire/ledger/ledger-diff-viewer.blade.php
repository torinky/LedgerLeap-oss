<div class="space-y-6" x-data x-init="$store.ledgerState.init({{ $ledgerRecord->id }})">
    @if ($showChanges && $hasChangedColumns)
        <div class="flex items-center justify-between px-4 py-2 bg-base-200/50 rounded-lg border border-base-300">
            <div class="flex items-center gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <span class="badge badge-outline badge-sm">{{ __('ledger.diff.comparing') }}</span>
                    <span class="font-bold text-success">Ver.{{ $currentVersion }}</span>
                    <x-mary-icon name="o-arrow-long-left" class="w-4 h-4" />
                    @if ($pastVersion)
                        <span class="font-bold text-error">Ver.{{ $pastVersion }}</span>
                    @else
                        <span class="font-bold text-success">{{ __('ledger.diff.not_exist') }}</span>
                    @endif
                </div>
            </div>

            @if ($baseMeta || $targetMeta)
                <div class="flex items-center gap-2">
                    @if ($currentVersion && $baseMeta)
                        <div class="text-xs text-base-content/70">
                            {{ $baseMeta['modifier_name'] ?? '' }} ({{ $baseMeta['updated_at'] ?? '' }})
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($displayData as $group)
            <div class="collapse collapse-arrow bg-base-100 border border-base-200 shadow-sm"
                :class="{
                    'collapse-open': !$store.ledgerState.isCollapsed('{{ $group['group_name'] }}',
                        {{ $group['is_required_group'] ? 'true' : 'false' }}),
                    'collapse-close': $store.ledgerState
                        .isCollapsed('{{ $group['group_name'] }}',
                            {{ $group['is_required_group'] ? 'true' : 'false' }})
                }"
                wire:key="group-{{ md5($group['group_name']) }}">

                <input type="checkbox" class="hidden" />

                <div class="collapse-title text-sm font-bold flex items-center justify-between cursor-pointer"
                    @click.stop="$store.ledgerState.toggle('{{ $group['group_name'] }}', {{ $group['is_required_group'] ? 'true' : 'false' }})"
                    role="button"
                    :aria-expanded="!$store.ledgerState.isCollapsed('{{ $group['group_name'] }}',
                        {{ $group['is_required_group'] ? 'true' : 'false' }})">
                    <div class="flex items-center gap-2">
                        @if ($group['is_required_group'])
                            <span class="badge badge-ghost badge-sm text-error">必須</span>
                        @endif
                        {{ $group['group_name'] }}
                    </div>
                </div>

                <div class="collapse-content overflow-x-auto p-0">
                    <table class="table table-sm w-full border-t border-base-200">
                        <thead class="bg-base-200/30">
                            <tr>
                                <th class="{{ $showChanges ? 'w-1/4' : 'w-1/3' }} min-w-[150px]">
                                    {{ __('ledger.form.column_name') }}</th>
                                <th class="{{ $showChanges ? 'w-3/8' : 'w-2/3' }} min-w-[250px]">
                                    {{ 'Ver.' . $currentVersion }}</th>
                                @if ($showChanges)
                                    <th class="w-3/8 min-w-[250px]">
                                        {{ $pastVersion ? 'Ver.' . $pastVersion : __('ledger.diff.previous_value') }}
                                    </th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-100">
                            @foreach ($group['columns'] as $column)
                                <tr class="hover:bg-base-200/20 transition-colors {{ $showChanges && $column['status'] !== 'unchanged' ? 'bg-warning/5' : '' }}"
                                    wire:key="col-{{ $column['id'] }}">
                                    <td class="align-top py-3">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="text-xs font-semibold text-base-content/80">{{ $column['name'] }}</span>
                                                @if ($column['is_required'])
                                                    <span class="text-error font-bold flex items-start -mt-1">*</span>
                                                @endif
                                            </div>
                                            @if ($column['hint'])
                                                <span
                                                    class="text-[10px] text-base-content/50 leading-tight">{{ $column['hint'] }}</span>
                                            @endif

                                            @if ($showChanges && $column['status'] !== 'unchanged')
                                                <div>
                                                    @if ($column['status'] === 'added')
                                                        <span
                                                            class="badge badge-success badge-xs">{{ __('ledger.diff.added') }}</span>
                                                    @elseif($column['status'] === 'deleted')
                                                        <span
                                                            class="badge badge-error badge-xs">{{ __('ledger.diff.deleted') }}</span>
                                                    @else
                                                        <span
                                                            class="badge badge-warning badge-xs">{{ __('ledger.diff.modified') }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- 新データ (現在) --}}
                                    <td class="align-top py-3 text-sm prose-sm max-w-none">
                                        <div
                                            class="break-words {{ $showChanges && $column['status'] !== 'unchanged' ? 'font-medium' : '' }}">
                                            {!! $column['current_value_html'] !!}
                                        </div>
                                    </td>

                                    {{-- 旧データ (比較対象) --}}
                                    @if ($showChanges)
                                        <td class="align-top py-3 text-sm prose-sm max-w-none">
                                            <div class="break-words">
                                                {!! $column['old_value_html'] !!}
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    @if (!$hasChangedColumns && $showChanges)
        <div class="flex flex-col items-center justify-center py-12 text-base-content/40">
            <x-mary-icon name="o-check-circle" class="w-12 h-12 mb-2" />
            <p>{{ __('ledger.diff.no_changes') }}</p>
        </div>
    @endif
</div>
