<div>
    @php
        use App\Enums\WorkflowStatus;
    @endphp
    <div class="p-4 md:p-8 bg-base-100 rounded-b-xl"> {{-- パディング調整 --}}

        {{-- タブ UI の導入 --}}
        <x-mary-tabs wire:model="selectedTab" class="mb-10"> {{-- 下にマージン追加 --}}

            {{-- 基本情報タブ --}}
            <x-mary-tab name="details" label="{{ __('ledger.tab.details') }}" icon="o-document-text"
                        class="shadow-md"
            >

                <div class="p-8 bg-base-100 rounded-b-xl grid grid-cols-1 gap-10">

                    @if($ledgerRecord->define->workflow_enabled)
                        <x-mary-card>
                            <div class="flex justify-between items-center ">
                                <div>
                                    <h3 class="text-lg font-semibold mb-1">{{ __('ledger.workflow.current_status') }}</h3>
                                    <x-mary-badge :value="$ledgerRecord->status->label()"
                                                  class="{{ $ledgerRecord->status->colorClass() }}"/>
                                    {{-- 担当者表示--}}

                                    @if($ledgerRecord->status === WorkflowStatus::PENDING_INSPECTION && $ledgerRecord->latestDiff?->inspector)
                                        <span class="text-sm ml-2">({{ __('ledger.workflow.inspector') }}: {{ $ledgerRecord->latestDiff->inspector->name }})</span>
                                    @elseif($ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver)
                                        <span class="text-sm ml-2">({{ __('ledger.workflow.approver') }}: {{ $ledgerRecord->latestDiff->approver->name }})</span>
                                    @elseif($ledgerRecord->status === WorkflowStatus::APPROVED && $ledgerRecord->latestDiff?->approver)
                                        <span class="text-sm ml-2">({{ __('ledger.workflow.approved_by') }}: {{ $ledgerRecord->latestDiff->approver->name }} at {{ $ledgerRecord->latestDiff->approved_at?->isoFormat('YYYY/MM/DD HH:mm') }})</span>
                                    @endif
                                </div>
                                {{-- アクションボタン--}}

                                <div class="flex gap-2 items-center">
                                    @if($this->canRequestApproval())
                                        <x-mary-button label="{{ __('ledger.workflow.request_approval_short') }}"
                                                       icon="o-check-badge"
                                                       class="btn-lg btn-success"
                                                       {{-- モーダルを開くメソッド呼び出し --}}
                                                       wire:click="openApproverSelectModal"
                                                       spinner="openApproverSelectModal"/>
                                    @endif
                                    @if($this->canApprove())
                                        <x-mary-button label="{{ __('ledger.workflow.approve') }}" icon="o-check-circle"
                                                       class="btn-lg btn-primary" wire:click="approveTask" spinner/>
                                    @endif
                                    @if($this->canReturnToDraft())
                                        <x-mary-button
                                                label="{{ __('ledger.workflow.return_to_draft_short') }}"
                                                icon="o-arrow-uturn-left"
                                                class="btn-sm btn-warning" wire:click="openReturnToDraftModal"
                                                spinner="openReturnToDraftModal"/>
                                    @endif
                                </div>
                            </div>
                        </x-mary-card>
                    @endif

                    {{-- カラムごとの差分表示 --}}
                    @if(!empty($contentChanges) && auth()->user()->can('view', $ledgerRecord))

                        <div class="border border-base-300 rounded-lg">
                            @if($comparisonTargetDiff)
                                <x-mary-toggle wire:model.live="hasChangedColumns" label="{{ __('ledger.show_diff') }}"
                                />
                            @endif
                            <table class="table table-compact w-full">
                                @if($hasChangedColumns)
                                    <thead>
                                        <tr>
                                            <th class="w-1/3 lg:w-1/4 break-words align-top pt-2">
                                                {{ __('ledger.column.title') }}
                                            </th>
                                            <th>
                                                {{ __('ledger.after_change') }}
                                            </th>
                                            <th>
                                                {{ __('ledger.before_change') }}
                                            </th>
                                        </tr>
                                    </thead>
                                @endif
                                <tbody>

                                @foreach($contentChanges as $columnId => $change)
                                    {{--                                        @dd($change)--}}
                                    <tr class="{{ $change['changed'] ? 'bg-warning/10 ' : '' }} hover:bg-base-300">
                                        <th class="w-1/3 lg:w-1/4 break-words align-top pt-2">
                                            {{ $change['column_name'] }}
                                            @if($change['changed'])
                                                <span class="badge badge-xs badge-warning ml-1">{{ __('ledger.changed') }}</span>
                                            @endif
                                        </th>
                                        <td class="break-words align-top pt-2">
                                            <div class="text-sm">
                                                @if($change['column_define_current'])
                                                    {{ ColumnHtml::setAttachmentContents($change['current_attachments'] ?? [])
                                                                  ->show($change['column_define_current'], $change['current_value']??'', $canView, [], '', false, $searchKeywords ?? []) }} {{-- keywords渡しも追加 --}}
                                                @else
                                                    <span class="text-error">{{ __('定義不明') }}</span> {{-- 現在の定義がない (削除されたカラム) --}}
                                                @endif
                                            </div>
                                        </td>
                                        @if($change['changed'] && $hasChangedColumns)
                                            <td class="break-words align-top pt-2">
                                                <div class="text-xs text-base-content/60 mb-0.5">{{ __('ledger.before_change_colon') }}</div>
                                                <div class="text-sm opacity-70 mb-2">
                                                    @if($change['column_define_old'])
                                                        {{ ColumnHtml::setAttachmentContents($change['old_attachments'] ?? [])
                                                                      ->show($change['column_define_old'], $change['old_value'], $canView) }}
                                                    @else
                                                        <span class="text-gray-400">---</span> {{-- 古い定義がない --}}
                                                    @endif
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        {{-- 差分情報がない場合、またはワークフロー非適用の場合など (通常の詳細表示) --}}
                        {{--                            <x-ledger.detail.table :ledgerRecord="$ledgerRecord" :canView="true" />--}}
                        <x-ledger.detail.table
                                :ledgerRecord="$ledgerRecord"
                                :canView="auth()->user()->can('view', $ledgerRecord)"
                        />
                    @endif

                    <div class="container mx-auto mt-4 items-center text-sm text-gray-500 flex justify-end">
                        <i class="fa-solid fa-user mr-2"></i>{{$ledgerRecord->modifier->name}}
                        <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('ledger.named.updated_at').$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}</span>
                        <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('ledger.named.created_at').$ledgerRecord->created_at->format('Y-m-d H:i:s')}}</span>
                    </div>

                </div>
            </x-mary-tab>


            @php
                $historyTabTitle = $ledgerRecord->define->workflow_enabled ? __('ledger.tab.workflow_history') : __('ledger.history_title');
            @endphp
            {{-- ワークフロー履歴タブ --}}
            <x-mary-tab name="history"
                        class="shadow-md"
                        label="{{ $historyTabTitle }}" icon="o-list-bullet">
                <x-mary-card>
                    <div class="overflow-x-auto">
                        <table class="table table-sm w-full  table-zebra">
                            <thead>
                            <tr>
                                <th>{{ __('ledger.workflow.history_datetime') }}</th>
                                <th>{{ __('ledger.workflow.history_user') }}</th>
                                <th>{{ __('ledger.workflow.history_action') }}</th>
                                <th>{{ __('ledger.workflow.history_detail') }}</th>
                                <th class="text-center">{{-- データリンク列 --}}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($workflowHistory as $diff)
                                <tr wire:key="history-{{ $diff->id }}" class=" hover:bg-base-300">
                                    {{-- 日時 --}}
                                    <td>{{ $diff->created_at->isoFormat('YYYY/MM/DD HH:mm:ss') }}</td>
                                    {{-- 操作者 --}}
                                    <td>{{ $diff->modifier->name ?? 'N/A' }}</td>
                                    {{-- アクション/ステータス --}}
                                    <td>
                                        @if ($diff->status !== \App\Enums\WorkflowStatus::NONE)
                                            {{-- ワークフロー有効時のステータスバッジ --}}
                                            <x-mary-badge :value="$diff->status->label()"
                                                          class="badge-sm {{ $diff->status->colorClass() }}"/>
                                            {{-- アクション名をより具体的に表示するヘルパー関数やロジックをここに追加しても良い --}}
                                            {{-- 例: getWorkflowActionDescription($diff) --}}
                                        @else
                                            {{-- ワークフロー無効時の表示 (例: "変更") --}}
                                            <span class="text-xs">{{ __('ledger.workflow.history_action_modified') }}</span> {{-- 新しい翻訳キー --}}
                                        @endif
                                    </td>
                                    {{-- 詳細 (担当者、コメント、データリンク) --}}
                                    <td>
                                        @if ($diff->status !== \App\Enums\WorkflowStatus::NONE)
                                            {{-- ワークフロー有効時の担当者・コメント表示 --}}
                                            @if ($diff->status === WorkflowStatus::PENDING_INSPECTION && $diff->inspector)
                                                <span class="text-xs">{{ __('ledger.workflow.next_inspector') }}: {{ $diff->inspector->name }}</span>
                                            @elseif ($diff->status === WorkflowStatus::PENDING_APPROVAL && $diff->approver)
                                                <span class="text-xs">{{ __('ledger.workflow.next_approver') }}: {{ $diff->approver->name }}</span>
                                            @elseif ($diff->status === WorkflowStatus::APPROVED && $diff->approver)
                                                <span class="text-xs">{{ __('ledger.workflow.approved_by') }}: {{ $diff->approver->name }}</span>
                                            @endif
                                            @if ($diff->comments)
                                                <div class="text-xs mt-1 p-1 bg-base-200 rounded"
                                                     title="{{ __('ledger.workflow.comments') }}">{!! nl2br(e($diff->comments)) !!}</div>
                                            @endif
                                        @else
                                            {{-- ワークフロー無効時は担当者・コメント欄は空 or 非表示 --}}
                                            <span class="text-xs">{{ __('ledger.workflow.workflow_inactive_at_this_point') }}</span>
                                        @endif
                                    </td>
                                    {{-- データ内容がある Diff へのリンク --}}
                                    <td class="text-center">
                                        @if ($diff->content)
                                            {{-- content が空でない場合 --}}
                                            <a href="{{ route('ledgerDiff.show', ['ledgerId' => $ledgerRecord->id, 'diffId' => $diff->id]) }}"
                                               class="btn btn-square tooltip"
                                               target="_blank"
                                               data-tip="{{ __('ledger.view_content_at_this_point') }}">
                                                <i class="far fa-eye"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4"
                                        class="text-center py-4">{{ __('ledger.workflow.no_history') }}</td> {{-- 新しい翻訳キー --}}
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-mary-card>
            </x-mary-tab>
        </x-mary-tabs>


        {{-- フッターパネル (アクションボタン集約) --}}
        <div class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3 z-20">
            <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity "> {{-- 透明度調整 --}}
                <div class="card-body p-4">
                    <div class="flex flex-wrap items-center justify-center gap-4">

                        {{-- 編集ボタン --}}
                        @php $canUpdate = auth()->user()->can('ledgerUpdate', $ledgerRecord->define); @endphp
                        @if($canUpdate && !$ledgerRecord->isLocked())
                            <a href="{{ route('ledger.edit', ['ledgerId'=>$ledgerRecord->id]) }}"
                               class="btn btn-primary btn-xl btn-wide"
                            ><i class="fa-solid fa-pencil mr-2"></i>{{__('ledger.edit')}}</a>
                        @else
                            <div class="tooltip"
                                 data-tip="{{ $ledgerRecord->isLocked() ? __('ledger.workflow.record_locked') : __('ledger.no_edit_permission') }}">
                                <button class="btn btn-primary btn-xl btn-wide" disabled><i
                                            class="fa-solid fa-pencil mr-2"></i>{{__('ledger.edit')}}</button>
                            </div>
                        @endif

                        {{-- 変更履歴ボタン --}}
                        @if($ledgerRecord->ledgerDiff()->where(DB::raw('content'), '!=', '')->count() > 0)
                            {{-- 変更履歴がある場合のみ --}}
                            <a href="{{ route('ledgerDiff.show', ['ledgerId'=>$ledgerRecord->id]) }}"
                               class="btn btn-outline btn-info btn-wide"
                            ><i class="fa-solid fa-clock-rotate-left mr-2"></i>{{__('ledger.view_history')}}
                                @if($ledgerRecord->version-1>0)
                                    <div class="badge badge-sm badge-info tooltip"
                                         data-tip="{{ __('ledger.reviseCount') }}"> {{ $ledgerRecord->version-1 }}
                                    </div>
                                @endif
                            </a>
                        @endif

                        {{-- ワークフローアクションボタン --}}
                        @if($this->canRequestApproval())
                            <x-mary-button label="{{ __('ledger.workflow.request_approval_short') }}"
                                           icon="o-check-badge"
                                           class="btn-lg btn-success"
                                           {{-- モーダルを開くメソッド呼び出し --}}
                                           wire:click="openApproverSelectModal"
                                           spinner="openApproverSelectModal"/>
                        @endif
                        @if($this->canApprove())
                            <x-mary-button label="{{ __('ledger.workflow.approve') }}" icon="o-check-circle"
                                           class="btn-lg btn-primary" wire:click="approveTask" spinner/>
                        @endif
                        @if($this->canReturnToDraft())
                            <x-mary-button label="{{ __('ledger.workflow.return_to_draft_short') }}"
                                           icon="o-arrow-uturn-left" class="btn-warning btn-sm md:btn-md"
                                           wire:click="openReturnToDraftModal"
                                           spinner="openReturnToDraftModal"/>
                        @endif

                        {{-- 閉じるボタン --}}
                        <x-ledger.close-window-button/>

                    </div>
                    {{-- 現在のステータス表示 --}}
                    <div class="text-center text-xs text-base-content/70 mt-2">
                        {{ __('ledger.workflow.current_status') }} :
                        <x-mary-badge :value="$ledgerRecord->status->label()"
                                      class="badge-xs {{ $ledgerRecord->status->colorClass() }}"/>
                        @if($ledgerRecord->status === WorkflowStatus::PENDING_INSPECTION && $ledgerRecord->latestDiff?->inspector)
                            ({{ __('ledger.workflow.inspector') }}: {{ $ledgerRecord->latestDiff->inspector->name }})
                        @elseif($ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver)
                            ({{ __('ledger.workflow.approver') }}: {{ $ledgerRecord->latestDiff->approver->name }})
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- 担当者選択モーダルコンポーネント呼び出し --}}
        @livewire('workflow.workflow-assignee-modal', key('assignee-modal-show'))

        {{-- コメント入力モーダル (新規追加) --}}
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
        <x-mary-button label="{{ __('Cancel') }}" @click="$wire.returnToDraftModal = false"/>
        <x-mary-button label="{{ __('ledger.workflow.return_to_draft') }}" class="btn-warning"
                   wire:click="returnTaskToDraft" spinner/>
        </x-slot:actions>
        </x-mary-modal>
        --}}

    </div>
</div>

