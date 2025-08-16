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

    @if($groupedColumns)
        @foreach($groupedColumns as $groupName => $columnsInGroup)
            <div wire:key="group-{{ $groupName }}-{{ $loop->index }}" class="collapse collapse-plus bg-base-200 mb-4"
                 @if(!($collapsedStates[$groupName] ?? false)) open @endif >
                <div class="collapse-title text-xl font-medium" wire:click.prevent="toggleGroup('{{ $groupName }}')">
                    <h3 class="text-lg font-bold flex items-center">
                        {{ $groupName }}
                        @if(collect($columnsInGroup)->contains(fn($col) => (is_array($col) ? ($col['required'] ?? false) : ($col->required ?? false))))
                            <span class="ml-2 text-error text-sm">{{ __('ledger.form.required_group_indicator') }}</span>
                        @endif
                    </h3>
                </div>
                <div class="collapse-content">
                    <table class="table table-zebra table-compact table-hover table-fixed w-full">
                        <tbody>
                        @foreach($columnsInGroup as $columnInGroup)
                            @php
                                $columnId = data_get($columnInGroup, 'id');
                                $change = $contentChanges[$columnId] ?? null;
                                if (!$change) continue;

                                $columnDefine = new \App\Models\ColumnDefine((object)$change['column_define']);
                                $status = $change['status'];
                                $currentValue = $change['current_value'];
                                $oldValue = $change['old_value'];

                                $rowClass = 'hover:bg-base-300';
                                if ($showChanges) {
                                    if ($status === 'modified') $rowClass .= ' bg-warning/20 text-warning-content';
                                    if ($status === 'added') $rowClass .= ' bg-success/20 text-success-content';
                                    if ($status === 'deleted') $rowClass .= ' bg-error/20 text-error-content';
                                }
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <th class="w-1/3 lg:w-1/4 break-words align-top pt-2">
                                    {{ $columnDefine->name }}
                                    @if($hasChangedColumns && $status !== 'unchanged')
                                        <span class="badge badge-xs badge-warning ml-1">{{ __('ledger.changed') }}</span>
                                    @endif
                                </th>

                                @if($showChanges)
                                    {{-- Diff View --}}
                                    <td class="w-1/3 lg:w-3/8 break-words align-top pt-2">
                                        {{-- New Value --}}
                                        @if($status === 'deleted')
                                            <div class="p-2 italic text-gray-400 line-through">({{ __('ledger.diff.deleted') }})</div>
                                        @else
                                            {!! ColumnHtml::show($columnDefine, $currentValue, $canView, [], '', false, $ledgerRecord, $highlight, $currentLedgerAttachments) !!}
                                        @endif
                                    </td>
                                    <td class="w-1/3 lg:w-3/8 break-words align-top pt-2">
                                        {{-- Old Value --}}
                                        @if($status === 'added')
                                            <div class="p-2 italic text-gray-400">({{ __('ledger.diff.not_exist') }})</div>
                                        @else
                                            {!! ColumnHtml::show($columnDefine, $oldValue, $canView, [], '', false, $ledgerRecord, $highlight, $oldAttachments) !!}
                                        @endif
                                    </td>
                                @else
                                    {{-- Normal View --}}
                                    <td class="break-words align-top pt-2" colspan="2">
                                        {!! ColumnHtml::show($columnDefine, $currentValue, $canView, [], '', false, $ledgerRecord, $highlight, $currentLedgerAttachments) !!}
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
