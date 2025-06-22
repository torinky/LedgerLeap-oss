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
                    <x-mary-card title="{{ __('ledger.workflow.current_status') }}"
                                 shadow separator
                    >
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center"> {{-- 表示調整用に grid に変更 --}}
                            {{-- 左側: ステータスと担当者 --}}
                            <div class="flex items-center w-full justify-center">
                                <x-mary-badge :value="$ledgerRecord->status->label()"
                                              class="{{ $ledgerRecord->status->colorClass() }} text-lg p-2"/>

                            </div>

                            <div>
                                {{-- 担当者表示 --}}
                                @if($ledgerRecord->status === WorkflowStatus::PENDING_INSPECTION && $ledgerRecord->latestDiff?->inspector)
                                    <span class=" text-sm ml-2
                                ">({{ __('ledger.workflow.inspector') }}
                                : {{ $ledgerRecord->latestDiff->inspector->name }})</span>
                                @elseif($ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver)
                                    <span class="text-sm ml-2">({{ __('ledger.workflow.approver') }}: {{ $ledgerRecord->latestDiff->approver->name }})</span>
                                @elseif($ledgerRecord->status === WorkflowStatus::APPROVED && $ledgerRecord->latestDiff?->approver)
                                    <span class="text-sm ml-2">({{ __('ledger.workflow.approved_by') }}: {{ $ledgerRecord->latestDiff->approver->name }} at {{ $ledgerRecord->latestDiff->approved_at?->isoFormat('YYYY/MM/DD HH:mm') }})</span>
                                @endif

                                {{-- ★★★ 必須ロール進捗表示エリア ★★★ --}}
                                @if(!empty($requiredRolesProgress))
                                    <div class="mt-3 space-y-1">
                                        {{-- 点検進捗 --}}
                                        @if($requiredRolesProgress['inspection']['total_count'] > 0)
                                            <div class="text-xs font-medium text-gray-600 dark:text-gray-400 tooltip w-full">
                                                <div class="tooltip-content p-2 space-y-2">
                                                    {{ __('ledger.workflow.inspection_completed') }} :
                                                    @foreach($requiredRolesProgress['inspection']['completed_roles'] as $role)
                                                        <x-mary-badge :value="$role->name"
                                                                      class="badge-success badge-sm"/>
                                                    @endforeach
                                                    @if($requiredRolesProgress['inspection']['completed_roles']->isEmpty())
                                                        {{ __('ledger.none') }}
                                                    @endif
                                                    <br/>
                                                    {{ __('ledger.workflow.inspection_pending') }} :
                                                    @foreach($requiredRolesProgress['inspection']['pending_roles'] as $role)
                                                        <x-mary-badge :value="$role->name"
                                                                      class="badge-warning badge-sm"/>
                                                    @endforeach
                                                    @if($requiredRolesProgress['inspection']['pending_roles']->isEmpty())
                                                        {{ __('ledger.none') }}
                                                    @endif

                                                </div>
                                                {{ __('ledger.workflow.required_inspector_roles') }}
                                                : {{ $requiredRolesProgress['inspection']['completed_count'] }}
                                                / {{ $requiredRolesProgress['inspection']['total_count'] }}
                                                @if ($requiredRolesProgress['inspection']['is_all_completed'])
                                                    <x-mary-icon name="o-check-circle"
                                                                 class="w-4 h-4 text-success inline-block ml-1"/>
                                                @else
                                                    <x-mary-icon name="o-ellipsis-horizontal-circle"
                                                                 class="w-4 h-4 text-warning inline-block ml-1"/>
                                                @endif
                                                <progress class="progress progress-warning w-full h-2 "
                                                          value="{{ $requiredRolesProgress['inspection']['completed_count'] }}"
                                                          max="{{ $requiredRolesProgress['inspection']['total_count'] }}"
                                                >
                                                </progress>
                                            </div>
                                        @endif

                                        {{-- 承認進捗 --}}
                                        @if($requiredRolesProgress['approval']['total_count'] > 0)
                                            <div class="text-xs font-medium text-gray-600 dark:text-gray-400 mt-2  tooltip w-full">
                                                <div class="tooltip-content p-2 space-y-2">
                                                    {{ __('ledger.workflow.approval_completed') }} :
                                                    @foreach($requiredRolesProgress['approval']['completed_roles'] as $role)
                                                        <x-mary-badge :value="$role->name"
                                                                      class="badge-success badge-sm"/>
                                                    @endforeach
                                                    @if($requiredRolesProgress['approval']['completed_roles']->isEmpty())
                                                        {{ __('ledger.none') }}
                                                    @endif
                                                    <br/>
                                                    {{ __('ledger.workflow.approval_pending') }} :
                                                    @foreach($requiredRolesProgress['approval']['pending_roles'] as $role)
                                                        <x-mary-badge :value="$role->name"
                                                                      class="badge-warning badge-sm"/>
                                                    @endforeach
                                                    @if($requiredRolesProgress['approval']['pending_roles']->isEmpty())
                                                        {{ __('ledger.none') }}
                                                    @endif

                                                </div>
                                                {{ __('ledger.workflow.required_approver_roles') }}
                                                : {{ $requiredRolesProgress['approval']['completed_count'] }}
                                                / {{ $requiredRolesProgress['approval']['total_count'] }}
                                                @if ($requiredRolesProgress['approval']['is_all_completed'])
                                                    <x-mary-icon name="o-check-circle"
                                                                 class="w-4 h-4 text-success inline-block ml-1"/>
                                                @else
                                                    <x-mary-icon name="o-ellipsis-horizontal-circle"
                                                                 class="w-4 h-4 text-warning inline-block ml-1"/>
                                                @endif
                                                <progress
                                                        class="progress {{ $requiredRolesProgress['approval']['is_all_completed'] && $requiredRolesProgress['inspection']['is_all_completed'] && $ledgerRecord->status === WorkflowStatus::APPROVED ? 'progress-success' : 'progress-info' }} w-full h-2 "
                                                        value="{{ $requiredRolesProgress['approval']['completed_count'] }}"
                                                        max="{{ $requiredRolesProgress['approval']['total_count'] }}"
                                                >
                                                </progress>
                                            </div>
                                        @endif
                                    </div>
                                    {{-- 承認済みで必須ロール未完了の場合の警告 --}}
                                    @if($ledgerRecord->status === WorkflowStatus::APPROVED && (!$requiredRolesProgress['inspection']['is_all_completed'] || !$requiredRolesProgress['approval']['is_all_completed']))
                                        <div class="mt-2 text-xs text-error flex items-center">
                                            <x-mary-icon name="o-exclamation-triangle" class="w-4 h-4 mr-1"/>
                                            {{ __('ledger.workflow.required_roles_not_completed') }}
                                        </div>
                                    @endif
                                @endif

                            </div>

                            {{-- アクションボタン--}}

                            <div class="join flex flex-wrap items-center justify-end w-full">

                                @if($this->canRequestApproval())
                                    <x-mary-button label="{{ __('ledger.workflow.request_approval_short') }}"
                                                   icon="o-check-badge"
                                                   class="join-item btn-wide btn-success"
                                                   {{-- モーダルを開くメソッド呼び出し --}}
                                                   wire:click="openApproverSelectModal"
                                                   spinner="openApproverSelectModal"/>
                                @elseif(
                                    $ledgerRecord->status === WorkflowStatus::PENDING_INSPECTION
                                    && $ledgerRecord->latestDiff?->inspector_id === Auth::id()
                                    && !$this->ledgerRecord->canProceedToApprovalStep()
                                )
                                    {{-- 点検者だが、必須点検が完了していない場合 --}}
                                    <div class="tooltip"
                                         data-tip="{{ __('ledger.workflow.error_inspection_not_completed') }}">
                                        <x-mary-button
                                                label="{{ __('ledger.workflow.request_approval_short') }}"
                                                icon="o-check-badge" class="join-item btn-wide btn-success"
                                                disabled/>
                                    </div>
                                @endif
                                @if($this->canApprove())
                                    <x-mary-button label="{{ __('ledger.workflow.approve') }}"
                                                   icon="o-check-circle"
                                                   class="join-item btn-success" wire:click="approveTask"
                                                   spinner/>
                                @elseif($ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver_id === Auth::id())
                                    {{-- 承認者だが、必須点検または必須承認が完了していない場合 --}}
                                    <div class="tooltip"
                                         data-tip="{{ __('ledger.workflow.error_approval_not_completed') }}">
                                        <x-mary-button label="{{ __('ledger.workflow.approve') }}"
                                                       icon="o-check-circle"
                                                       class="join-item btn-wide btn-success" disabled/>
                                    </div>
                                @endif
                                @if($this->canReturnToDraft())
                                    <x-mary-button
                                            label="{{ __('ledger.workflow.return_to_draft_short') }}"
                                            icon="o-arrow-uturn-left"
                                            class="join-item btn-warning" wire:click="openReturnToDraftModal"
                                            spinner="openReturnToDraftModal"/>
                                @endif
                            </div>

                        </div>
                    </x-mary-card>
                @endif

                <x-mary-card title="{{ __('ledger.details') }}" shadow separator
                             icon="o-document-text"
                >

                    {{-- カラムごとの差分表示 --}}
                    @if($hasChangedColumns)
                        @if($hasChangedColumns)
                            <x-mary-toggle wire:model.live="showChanges" label="{{ __('ledger.show_diff') }}"
                                           class="m-3"
                            />
                        @endif
                        <table class="table table-compact w-full">
                            @if($showChanges)
                                <thead>
                                <tr>
                                    <th class="w-1/3 lg:w-1/4 break-words align-top pt-2">
                                        {{ __('ledger.column.title') }}
                                    </th>
                                    <th>
                                        {{ __('ledger.after_change') }}
                                        <span class="badge badge-xs badge-warning ml-1 tooltip"
                                              data-tip="{{ __('ledger.version') }}">Ver. {{ $ledgerRecord->version }} </span>
                                    </th>
                                    <th>
                                        {{ __('ledger.before_change') }}
                                        <span class="badge badge-xs badge-warning ml-1 tooltip"
                                              data-tip="{{ __('ledger.version') }}">Ver. {{ $comparisonTargetDiff->version }} </span>
                                    </th>
                                </tr>
                                </thead>
                            @endif
                            <tbody>

                            @foreach($contentChanges as $columnId => $change)
                                <tr class="{{ $change['changed'] ? 'bg-warning/10 ' : '' }} hover:bg-base-300">
                                    <th class="w-1/3 lg:w-1/4 break-words align-top pt-2">
                                        {{ $change['column_name'] }}
                                        @if($change['changed'])
                                            <span class="badge badge-xs badge-warning ml-1">{{ __('ledger.changed') }}</span>
                                        @endif
                                    </th>
                                    <td class="break-words align-top pt-2">
                                        <div class="text-sm">
                                            @if (!$canView)
                                                <x-ledger.not-authorized-message/>
                                            @elseif (empty($change['current_value']))
                                                <x-ledger.empty-message/>
                                            @elseif($change['column_define_current'])
                                                {{ ColumnHtml::setAttachmentContents($change['current_attachments'] ?? [])
                                                              ->show($change['column_define_current'], $change['current_value'], $canView, [], '', false, $searchKeywords ?? []) }} {{-- keywords渡しも追加 --}}
                                            @else
                                                <span class="text-error">{{ __('ledger.no_definition') }}</span> {{-- 現在の定義がない (削除されたカラム) --}}
                                            @endif
                                        </div>
                                    </td>
                                    @if($showChanges)
                                        <td class="break-words align-top pt-2">
                                            <div class="text-sm opacity-70 mb-2">
                                                @if (!$canView)
                                                    <x-ledger.not-authorized-message/>
                                                @elseif (empty($change['old_value']))
                                                    <x-ledger.empty-message/>
                                                @elseif($change['column_define_old'])
                                                    {{ ColumnHtml::setAttachmentContents($change['old_attachments'] ?? [])
                                                                  ->show($change['column_define_old'], $change['old_value'], $canView) }}
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
                    @else
                        {{-- 差分情報がない場合、またはワークフロー非適用の場合など (通常の詳細表示) --}}
                        <x-ledger.detail.table
                                :ledgerRecord="$ledgerRecord"
                                :canView="$canView"
                        />
                    @endif

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
                <x-mary-card>
                    <x-mary-table class="table-sm w-full table-zebra overflow-x-auto"
                                  :headers="[
                            ['key' => 'created_at', 'label' => __('ledger.workflow.history_datetime')],
                            ['key' => 'modifier_name', 'label' => __('ledger.workflow.history_user')],
                            ['key' => 'status', 'label' => __('ledger.workflow.history_action')],
                            ['key' => 'detail', 'label' => __('ledger.workflow.history_detail')],
                            ['key' => 'actions', 'label' => '', 'class' => 'text-center'],
                        ]"
                                  :rows="$workflowHistory"
                                  wire:key="workflow-history-table"
                    >
                        @scope('cell_created_at', $diff)
                        {{ $diff->created_at->isoFormat('YYYY/MM/DD HH:mm:ss') }}
                        @endscope

                        @scope('cell_modifier_name', $diff)
                        {{ $diff->modifier->name ?? 'N/A' }}
                        @endscope

                        @scope('cell_status', $diff)
                        @if ($diff->status !== WorkflowStatus::NONE)
                            <x-mary-badge :value="$diff->status->label()"
                                          class="badge-sm {{ $diff->status->colorClass() }}"/>
                        @else
                            <span class="text-xs">{{ __('ledger.workflow.history_action_modified') }}</span>
                        @endif
                        @endscope

                        @scope('cell_detail', $diff)
                        @if ($diff->status !== WorkflowStatus::NONE)
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
                            <span class="text-xs">{{ __('ledger.workflow.workflow_inactive_at_this_point') }}</span>
                        @endif
                        @endscope

                        @scope('cell_actions', $diff)
                        @if ($diff->content)
                            <a href="{{ route('ledgerDiff.show', ['ledgerId' => $diff->ledger_id, 'diffId' => $diff->id]) }}"
                               class="btn btn-square tooltip"
                               target="_blank"
                               data-tip="{{ __('ledger.view_content_at_this_point') }}">
                                <i class="far fa-eye"></i>
                            </a>
                        @endif
                        @endscope

                        <x-slot:empty>
                            <x-mary-icon name="o-cube" label="{{ __('ledger.workflow.no_history') }}"/>
                        </x-slot:empty>
                    </x-mary-table>
                </x-mary-card>
            </x-mary-tab>

            {{-- ★★★ 総合活動履歴タブ ★★★ --}}
            <x-mary-tab name="activity" label="{{ __('ledger.tab.activity_history') }}" icon="o-clock"
                        class="shadow-md">
                    @livewire('common.activity-history-display', [
                    'resourceId' => $ledgerRecord->id,
                    'resourceType' => 'Ledger',
                    'includeRelatedResources' => true,
                    'hiddenColumns' => ['subject']
                    ], key('activity-history-'.$ledgerRecord->id))

            </x-mary-tab>

            {{-- ★★★ アクセスと権限タブ ★★★ --}}
            <x-mary-tab name="permissions" label="{{ __('ledger.tab.access_and_permissions') }}" icon="o-shield-check"
                        class="shadow-md">
                    @livewire('common.permission-display', [
                    'resourceId' => $ledgerRecord->id,
                    'resourceType' => 'Ledger'
                    ], key('permission-display-'.$ledgerRecord->id))
            </x-mary-tab>

        </x-mary-tabs>

        {{-- フッターパネル (アクションボタン集約) --}}
        <div class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3 z-20">
            <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity "> {{-- 透明度調整 --}}
                <div class="card-body p-4">
                    <div class="flex flex-wrap items-center justify-center gap-4">
                        <div class="join flex flex-wrap items-center justify-center w-full">

                            {{-- 編集ボタン --}}
                            @php $canUpdate = auth()->user()->can('ledgerUpdate', $ledgerRecord->define); @endphp
                            @if($canUpdate && !$ledgerRecord->isLocked())
                                <a href="{{ route('ledger.edit', ['ledgerId'=>$ledgerRecord->id]) }}"
                                   class="join-item btn btn-primary btn-wide"
                                ><i class="fa-solid fa-pencil mr-2"></i>{{__('ledger.edit')}}</a>
                            @else
                                <div class="tooltip"
                                     data-tip="{{ $ledgerRecord->isLocked() ? __('ledger.workflow.record_locked') : __('ledger.no_edit_permission') }}">
                                    <button class="join-item btn btn-primary btn-wide" disabled><i
                                                class="fa-solid fa-pencil mr-2"></i>{{__('ledger.edit')}}</button>
                                </div>
                            @endif
                            {{-- ワークフローアクションボタン --}}
                            {{-- 点検完了（承認申請）ボタン --}}
                            @if($this->canRequestApproval())
                                <x-mary-button label="{{ __('ledger.workflow.request_approval_short') }}"
                                               icon="o-check-badge" class="join-item btn-success btn-sm md:btn-md"
                                               wire:click="openApproverSelectModal" {{-- 担当者選択モーダルを開く --}}
                                               spinner="openApproverSelectModal"/>
                            @elseif($ledgerRecord->status === WorkflowStatus::PENDING_INSPECTION
                                && $ledgerRecord->latestDiff?->inspector_id === Auth::id()
                                 && !$this->ledgerRecord->canProceedToApprovalStep())
                                {{-- 点検者だが、必須点検が完了していない場合 --}}
                                <div class="tooltip"
                                     data-tip="{{ __('ledger.workflow.error_inspection_not_completed') }}"> {{-- 新翻訳キー --}}
                                    <x-mary-button label="{{ __('ledger.workflow.request_approval_short') }}"
                                                   icon="o-check-badge" class="join-item btn-success btn-sm md:btn-md"
                                                   disabled/>
                                </div>
                            @endif

                            {{-- 承認ボタン --}}
                            @if($this->canApprove())
                                <x-mary-button label="{{ __('ledger.workflow.approve') }}" icon="o-check-circle"
                                               class="join-item btn-success btn-sm md:btn-md" {{-- 色をprimaryに変更（任意） --}}
                                               wire:click="approveTask" {{-- コメントモーダルを開く approveTask を呼び出す --}}
                                               spinner/>
                            @elseif($ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver_id === Auth::id() && !$this->ledgerRecord->hasAnyRequiredInspectionBeenDoneForCurrentContent())
                                {{-- 承認担当者だが、いずれの必須点検も完了していない場合 --}}
                                <div class="tooltip"
                                     data-tip="{{ __('ledger.workflow.tooltip.approve_requires_any_prior_inspection') }}"> {{-- 新翻訳キー --}}
                                    <x-mary-button label="{{ __('ledger.workflow.approve') }}" icon="o-check-circle"
                                                   class="join-item btn-primary btn-sm md:btn-md" disabled/>
                                </div>
                            @elseif($ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver_id === Auth::id())
                                {{-- 承認担当者だが、他の理由で承認できない場合（例：必須承認が残っているが、UI上ではボタンは押せる状態にしておき、Service側で最終判定する。またはここで canBeFinallyApproved で厳密に制御する） --}}
                                {{-- 現状の canApprove は hasAnyRequiredInspectionBeenDoneForCurrentContent のみ見ている --}}
                                {{-- もし、最終承認でない場合にボタンを非表示/非活性にしたいなら、canApprove のロジックを canBeFinallyApproved に近づける必要がある --}}
                                {{-- ここでは、押せるが最終承認にならない場合は、中間承認として次の担当者選択に移る想定 --}}
                            @endif

                            @if($this->canReturnToDraft())
                                <x-mary-button label="{{ __('ledger.workflow.return_to_draft_short') }}"
                                               icon="o-arrow-uturn-left" class=" join-item btn-warning "
                                               wire:click="openReturnToDraftModal"
                                               spinner="openReturnToDraftModal"/>
                            @endif
                        </div>

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
        <x-mary-button label="{{ __('Cancel') }}" @click="$wire.returnToDraftModal = false"/>
        <x-mary-button label="{{ __('ledger.workflow.return_to_draft') }}" class="btn-warning"
                   wire:click="returnTaskToDraft" spinner/>
        </x-slot:actions>
        </x-mary-modal>
        --}}

    </div>
</div>
