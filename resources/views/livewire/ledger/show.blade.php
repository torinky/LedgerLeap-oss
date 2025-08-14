<div>
    @php
        use App\Enums\WorkflowStatus;
    @endphp
    <div class="p-0 bg-base-200 rounded-b-xl sm:w-full"> {{-- パディング調整 --}}

        {{-- タブ UI の導入 --}}
        <x-mary-tabs wire:model="selectedTab" class="mb-10"> {{-- 下にマージン追加 --}}

            {{-- 基本情報タブ --}}
            <x-mary-tab name="details" label="{{ __('ledger.tab.details') }}" icon="o-document-text"
                        class="shadow-lg space-y-4"
            >
                {{--                <x-mary-header title="{{ __('ledger.tab.details') }}" icon="o-document-text"/>--}}

                @if($ledgerRecord->define->workflow_enabled)
                    <livewire:ledger.workflow-status-card :ledgerRecord="$ledgerRecord" wire:key="status-card-{{ $ledgerRecord->id }}" />
                @endif

                <x-mary-card title="{{ __('ledger.details') }}" shadow separator
                             icon="o-document-text"
                >
                    <x-slot:menu>
                        @php
                            $displayLevelOptions = [
                                ['id' => 1, 'name' => __('ledger.form.display_level_options.1')],
                                ['id' => 2, 'name' => __('ledger.form.display_level_options.2')],
                                ['id' => 3, 'name' => __('ledger.form.display_level_options.3')],
                            ];
                        @endphp
                        <x-mary-group
                                wire:model.live="displayLevel"
                                :options="$displayLevelOptions"
                                class="[&_label]:btn-ghost [&_input:checked+label]:!btn-primary"
                                option-value="id"
                                option-label="name"
                        />
                    </x-slot:menu>

                    {{-- 差分表示トグルをここに再挿入 --}}
                    @if($hasChangedColumns)
                        <x-mary-toggle wire:model.live="showChanges" label="{{ __('ledger.show_diff') }}" class="m-3"/>
                    @endif

                    {{-- 新しいグループ化構造 --}}
{{--                    @dd($groupedColumns,$collapsedStates)--}}
                    @foreach($groupedColumns as $groupName => $columnsInGroup)
                        <div class="collapse collapse-plus bg-base-200 mb-4"
                         wire:key="collapse-group-{{ $groupName }}-{{ $loop->index }}-{{ $ledgerRecord->id }}"
                         @if(!$collapsedStates[$groupName]) open @endif {{-- Livewireのプロパティで開閉を制御 --}}
                    >
                        <div class="collapse-title text-xl font-medium"
                             wire:click.prevent="toggleGroup('{{ $groupName }}')">
                            <h3 class="text-lg font-bold flex items-center">
                                {{ $groupName }}
                                {{-- 必須項目を含むグループのインジケーター --}}
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
                                        $change = $contentChanges[$columnId] ?? null; // このカラムの変更データを取得
                                    @endphp
                                    <tr class="{{ $change && $change['changed'] && $hasChangedColumns ? 'bg-warning/10 ' : '' }} hover:bg-base-300">
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
                                                        <span class="text-ghost">---</span> {{-- 古い定義がない --}}
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

                    <div class="container mx-auto mt-4 items-center text-sm text-gray-500 flex justify-end">
                        <i class="fa-solid fa-user mr-2"></i>{{$ledgerRecord->modifier->name}}
                        <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('ledger.named.updated_at').$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}</span>
                        <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('ledger.named.created_at').$ledgerRecord->created_at->format('Y-m-d H:i:s')}}</span>
                    </div>
                </x-mary-card>

            </x-mary-tab>


            @php
                $historyTabTitle = $ledgerRecord->define->workflow_enabled ? __('ledger.tab.workflow_history') : __('ledger.history_title');
            @endphp
            {{-- ワークフロー履歴タブ --}}
            <x-mary-tab name="history"
                        class="shadow-md"
                        label="{{ $historyTabTitle }}" icon="o-list-bullet">
                <livewire:ledger.workflow-history-list :ledgerRecord="$ledgerRecord" wire:key="history-list-{{ $ledgerRecord->id }}" />
            </x-mary-tab>


            {{-- ★★★ 総合活動履歴タブ ★★★ --}}
            <x-mary-tab name="activity" label="{{ __('ledger.tab.activity_history') }}" icon="o-clock"
                        class="shadow-md">
                    {{-- テスト実行時はレンダリングしない --}}
                    @if(app()->environment() !== 'testing')
                        @livewire('common.activity-history-display', [
                        'resourceId' => $ledgerRecord->id,
                        'resourceType' => 'Ledger',
                        'includeRelatedResources' => true,
                        'hiddenColumns' => ['subject']
                        ], key('activity-history-'.$ledgerRecord->id))
                    @else
                        <div id="activity-history-placeholder-for-testing"></div>
                    @endif
            </x-mary-tab>

            {{-- ★★★ アクセスと権限タブ ★★★ --}}
            <x-mary-tab name="permissions" label="{{ __('ledger.tab.access_and_permissions') }}" icon="o-shield-check"
                        class="shadow-md">
                    {{-- テスト実行時はレンダリングしない --}}
                    @if(app()->environment() !== 'testing')
                        @livewire('common.permission-display', [
                        'resourceId' => $ledgerRecord->id,
                        'resourceType' => 'Ledger'
                        ], key('permission-display-'.$ledgerRecord->id))
                    @else
                        <div id="permission-display-placeholder-for-testing"></div>
                    @endif
            </x-mary-tab>

        </x-mary-tabs>

        {{-- フッターパネル (アクションボタン集約) --}}
        <div class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3 z-20">
            <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity "> {{-- 透明度調整 --}}
                <div class="card-body p-4">
                    <livewire:ledger.workflow-action-buttons :ledgerRecord="$ledgerRecord" wire:key="action-buttons-{{ $ledgerRecord->id }}" />
                </div>
            </div>
        </div>

        {{-- 担当者選択モーダルコンポーネント呼び出し --}}
        @livewire('workflow.workflow-assignee-modal', key('assignee-modal-show'))

        {{-- コメント入力モーダル --}}
        @livewire('workflow.workflow-comment-modal', ['ledgerId' => $ledgerRecord->id],
        key('workflow-comment-modal-show'))


        {{-- 戻し理由入力モーダル --}}
        {{--
        <x-mary-modal wire:model="returnToDraftModal"
          title="{{ __('ledger.workflow.return_to_draft_reason') }}">
        <x-mary-textarea label="{{ __('ledger.workflow.comments') }}" wire:model="returnComment"
                 placeholder="{{ __('ledger.workflow.return_reason_placeholder') }}"
                 hint="{{ __('ledger.workflow.optional_comment') }}" rows="3"/>
        <x-slot:actions>
        <x-mary-button label="{{ __('actions.cancel') }}" @click="$wire.returnToDraftModal = false"/>
        <x-mary-button label="{{ __('ledger.workflow.return_to_draft') }}" class="btn-warning"
                   wire:click="returnTaskToDraft" spinner/>
        </x-slot:actions>
        </x-mary-modal>
        --}}

    </div>
</div>
