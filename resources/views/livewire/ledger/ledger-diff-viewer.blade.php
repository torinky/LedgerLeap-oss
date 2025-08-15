<div>
    @if($hasChangedColumns)
        <x-mary-toggle wire:model.live="showChanges" label="{{ __('ledger.show_diff') }}" class="m-3"/>
    @endif

    @foreach($groupedColumns as $groupName => $columnsInGroup)
        <div class="collapse collapse-plus bg-base-200 mb-4"
             wire:key="collapse-group-{{ $groupName }}-{{ $loop->index }}-{{ $ledgerRecord->id }}"
             @if(!$collapsedStates[$groupName]) open @endif
        >
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
                    @foreach($columnsInGroup as $columnDefine)
                        @php
                            $columnId = data_get($columnDefine, 'id');
                            $change = $contentChanges[$columnId] ?? null;
                        @endphp
                        <tr class=" {{ $change && $change['changed'] && $hasChangedColumns ? 'bg-warning/10 ' : '' }} hover:bg-base-300">
                            <th class="w-1/3 lg:w-1/4 break-words align-top pt-2">
                                {{ data_get($columnDefine, 'name') }}
                                @if($change && $change['changed'] && $hasChangedColumns)
                                    <span class="badge badge-xs badge-warning ml-1">{{ __('ledger.changed') }}</span>
                                @endif
                            </th>
                            <td class="break-words align-top pt-2">
                                @if (!$canView)
                                    <x-ledger.not-authorized-message />
                                @elseif (empty($ledgerRecord->content[$columnId]))
                                    <x-ledger.empty-message />
                                @else
                                    {!! ColumnHtml::setAttachmentCollection($currentLedgerAttachments->keyBy('hashedbasename'))
                                                  ->setAttachmentContents($ledgerRecord->content_attached[$columnId] ?? [])
                                                  ->show($columnDefine, $ledgerRecord->content[$columnId] ?? '', $canView, [], '', false, $ledgerRecord, $highlight) !!}
                                @endif
                            </td>
                            @if($showChanges)
                                <td class="break-words align-top pt-2">
                                    <div class="text-sm opacity-70 mb-2">
                                        @if (!$canView)
                                            <x-ledger.not-authorized-message/>
                                        @elseif (empty($change['old_value']))
                                            <x-ledger.empty-message/>
                                        @elseif($change['column_define_old'])
                                            {!! ColumnHtml::setAttachmentCollection($change['old_attachments'] ?? collect())
                                                          ->setAttachmentContents($change['old_attachment_contents'] ?? [])
                                                          ->show($change['column_define_old'], $change['old_value'], $canView) !!}
                                        @else
                                            <span class="text-ghost">---</span>
                                        @endif
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
