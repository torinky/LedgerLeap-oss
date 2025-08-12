<div>
@php
    use App\Enums\WorkflowStatus;
@endphp

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
</div>