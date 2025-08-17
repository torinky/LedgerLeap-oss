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
                        @if(collect($columnsInGroup)->contains(fn($col) => (is_array($col) ? ($col['required'] ?? false) : ($col->required ?? false))))
                            <div class="tooltip tooltip-right mr-2"
                                 data-tip="{{ __('ledger.form.required_group_indicator') }}">
                                <x-mary-icon name="o-check-circle" class="w-6 h-6 text-error"/>
                            </div>
                        @endif
                        {{ $groupName }}
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
                                    <div class="flex items-center">
                                        @if($columnDefine->required)
                                            <div class="tooltip tooltip-top"
                                                 data-tip="{{ __('ledger.column.required') }}">
                                                <x-mary-icon name="o-check-circle" class="w-5 h-5 text-error mr-1"/>
                                            </div>
                                        @endif
                                        <span>{{ $columnDefine->name }}</span>
                                        @if($hasChangedColumns && $status !== 'unchanged')
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
                                    <td class="w-1/3 lg:w-3/8 break-words pt-2 @if($status === 'deleted') align-middle @else align-top @endif">
                                        {{-- New Value --}}
                                        @if($status === 'deleted')
                                            <div class="flex w-full justify-center">
                                                <x-mary-icon name="o-trash" label="{{ __('ledger.diff.deleted') }}"
                                                             class="w-5 h-5 text-success-content/50"/>
                                            </div>
                                        @else
                                            {!! ColumnHtml::setAttachmentCollection($allAttachments)
                                                ->setAttachmentContents($ledgerRecord->content_attached[$columnDefine->id] ?? [])
                                                ->show($columnDefine, $currentValue, $canView, [], '', false, $ledgerRecord, $highlight) !!}
                                        @endif
                                    </td>
                                    <td class="w-1/3 lg:w-3/8 break-words pt-2 @if($status !== 'added') align-middle @else align-top @endif">
                                        {{-- Old Value --}}
                                        @if($status === 'added')
                                            <div class="flex w-full justify-center">
                                                <x-mary-icon name="o-cube" label="{{ __('ledger.diff.not_exist') }}"
                                                             class="w-5 h-5 text-success-content/50"/>
                                            </div>
                                        @else
                                            {!! ColumnHtml::setAttachmentCollection($allAttachments)
                                                ->show($columnDefine, $oldValue, $canView, [], '', false, $ledgerRecord, $highlight) !!}
                                        @endif
                                    </td>
                                @else
                                    {{-- Normal View --}}
                                    <td class="break-words align-top pt-2" colspan="2">

                                        {!! ColumnHtml::setAttachmentCollection($allAttachments)
                                            ->setAttachmentContents($ledgerRecord->content_attached[$columnDefine->id] ?? [])
                                            ->show($columnDefine, $currentValue, $canView, [], '', false, $ledgerRecord, $highlight) !!}
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
