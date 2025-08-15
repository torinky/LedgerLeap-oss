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

                    {{-- 新しい LedgerDiffViewer コンポーネント --}}
                    <livewire:ledger.ledger-diff-viewer
                        :ledgerRecord="$ledgerRecord"
                        :canView="$canView"
                        :currentLedgerAttachments="$currentLedgerAttachments"
                        :highlight="$highlight"
                        :displayLevel="$displayLevel"
                        wire:key="diff-viewer-{{ $ledgerRecord->id }}"
                        lazy {{-- lazy 修飾子を追加 --}}
                    />

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
