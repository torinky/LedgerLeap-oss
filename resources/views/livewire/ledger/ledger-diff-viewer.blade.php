<div>
    @if($hasChangedColumns)
        <div class="flex justify-end mb-2">
            <x-mary-toggle
                    wire:model.live="showChanges"
                    label="{{__('ledger.show_diff')}}"
                    hint="{{__('ledger.show_diff_hint')}}"
                    position="left"
                    class="m-3"
            />
        </div>
    @endif

    @if($displayData)
        @foreach($displayData as $group)
            <div wire:key="group-{{ $group['group_name'] }}-{{ $loop->index }}" class="collapse collapse-plus bg-base-200 mb-4"
                 @if(!($collapsedStates[$group['group_name']] ?? false)) open @endif >
                <div class="collapse-title text-xl font-medium" wire:click.prevent="toggleGroup('{{ $group['group_name'] }}')">
                    <h3 class="text-lg font-bold flex items-center">
                        @if($group['is_required_group'])
                            <div class="tooltip tooltip-right mr-2"
                                 data-tip="{{ __('ledger.form.required_group_indicator') }}">
                                <x-mary-icon name="o-check-circle" class="w-6 h-6 text-error"/>
                            </div>
                        @endif
                        {{ $group['group_name'] }}
                    </h3>
                </div>
                <div class="collapse-content">
                    <table class="table table-zebra table-compact table-hover table-fixed w-full">
                        <thead>
                        <tr>
                            <th class="w-1/3 lg:w-1/4 break-words align-top pt-2"></th>
                            @if($showChanges)
                                <th class="w-1/3 lg:w-3/8 break-words align-top pt-2 text-center">
                                    {{ __('ledger.diff.current_version') }} Version. {{ $currentVersion }}
                                </th>
                                <th class="w-1/3 lg:w-3/8 break-words align-top pt-2 text-center">
                                    {{ __('ledger.diff.past_version') }} Version. {{ $pastVersion ?? '-' }}
                                </th>
                            @else
                                <th class="break-words align-top pt-2 text-center" colspan="2">
                                    {{ __('ledger.diff.current_version') }} Version. {{ $currentVersion }}
                                </th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($group['columns'] as $column)
                            @php
                                $rowClass = 'hover:bg-base-300';
                                if ($showChanges) {
                                    if ($column['status'] === 'modified') $rowClass .= ' bg-warning/20 text-warning-content';
                                    if ($column['status'] === 'added') $rowClass .= ' bg-success/20 text-success-content';
                                    if ($column['status'] === 'deleted') $rowClass .= ' bg-error/20 text-error-content';
                                }
                            @endphp
                            <tr class="{{ $rowClass }}" wire:key="column-{{ $column['id'] }}">
                                <th class="w-1/3 lg:w-1/4 break-words align-top pt-2">
                                    <div class="flex items-center">
                                        @if($column['is_required'])
                                            <div class="tooltip tooltip-top"
                                                 data-tip="{{ __('ledger.column.required') }}">
                                                <x-mary-icon name="o-check-circle" class="w-5 h-5 text-error mr-1"/>
                                            </div>
                                        @endif
                                        <span>{{ $column['name'] }}</span>
                                        @if($hasChangedColumns && $column['status'] !== 'unchanged')
                                            <div class="tooltip tooltip-right ml-1 cursor-pointer"
                                                 data-tip="{{ __('ledger.changed') }}">
                                                <x-mary-icon name="o-pencil-square"
                                                             class="w-5 h-5 text-error-content/50"/>
                                            </div>
                                        @endif
                                    </div>
                                </th>

                                @if($showChanges)
                                    {{-- Diff View --}}
                                    <td class="w-1/3 lg:w-3/8 break-words pt-2 align-top">
                                        {!! $column['current_value_html'] !!}
                                    </td>
                                    <td class="w-1/3 lg:w-3/8 break-words pt-2 align-top">
                                        {!! $column['old_value_html'] !!}
                                    </td>
                                @else
                                    {{-- Normal View --}}
                                    <td class="break-words align-top pt-2" colspan="2">
                                        {!! $column['current_value_html'] !!}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
</div>
