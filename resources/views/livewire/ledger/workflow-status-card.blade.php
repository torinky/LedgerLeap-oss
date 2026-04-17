<div>
@php
    use App\Enums\WorkflowStatus;
@endphp

<div class="mb-4 bg-base-100 p-3 md:p-4 rounded-xl border border-base-300 shadow-sm flex flex-col xl:flex-row xl:items-center justify-between gap-4">
    
    {{-- 左側: ステータスバッジ、担当者、必須ロール進捗 --}}
    <div class="flex flex-col md:flex-row md:items-center gap-4 md:gap-6 flex-1 min-w-0">
        
        {{-- ステータスバッジ --}}
        <div class="shrink-0 flex items-center">
            <x-mary-badge :value="$ledgerRecord->status->label()"
                          class="{{ $ledgerRecord->status->colorClass() }} font-bold text-sm shadow-sm px-4 py-2" />
        </div>

        <div class="flex flex-col gap-2 min-w-0 flex-1">
            {{-- 担当者表示 --}}
            @if(
                ($ledgerRecord->status === WorkflowStatus::PENDING_INSPECTION && $ledgerRecord->latestDiff?->inspector) ||
                ($ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver) ||
                ($ledgerRecord->status === WorkflowStatus::APPROVED && $ledgerRecord->latestDiff?->approver)
            )
            <div class="text-sm font-medium text-base-content/80 flex items-center flex-wrap gap-2">
                @if($ledgerRecord->status === WorkflowStatus::PENDING_INSPECTION && $ledgerRecord->latestDiff?->inspector)
                    <span class="flex items-center gap-1.5"><x-mary-icon name="o-user" class="w-4 h-4 text-base-content/50" />{{ __('ledger.workflow.inspector') }}: {{ $ledgerRecord->latestDiff->inspector->name }}</span>
                @elseif($ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver)
                    <span class="flex items-center gap-1.5"><x-mary-icon name="o-user" class="w-4 h-4 text-base-content/50" />{{ __('ledger.workflow.approver') }}: {{ $ledgerRecord->latestDiff->approver->name }}</span>
                @elseif($ledgerRecord->status === WorkflowStatus::APPROVED && $ledgerRecord->latestDiff?->approver)
                    <span class="flex items-center gap-1.5"><x-mary-icon name="o-user" class="w-4 h-4 text-base-content/50" />{{ __('ledger.workflow.approved_by') }}: {{ $ledgerRecord->latestDiff->approver->name }} <span class="text-xs text-base-content/50 font-normal ml-1">({{ $ledgerRecord->latestDiff->approved_at?->isoFormat('YYYY/MM/DD HH:mm') }})</span></span>
                @endif
            </div>
            @endif

            {{-- 必須ロール進捗表示エリア --}}
            @if(!empty($requiredRolesProgress))
                <div class="flex flex-wrap items-center gap-4">
                    {{-- 点検進捗 --}}
                    @if($requiredRolesProgress['inspection']['total_count'] > 0)
                        <div class="flex items-center gap-2 tooltip" style="width: 200px;">
                            <div class="tooltip-content p-2 space-y-2 text-left w-max">
                                <div><span class="font-bold">{{ __('ledger.workflow.inspection_completed') }}:</span>
                                @foreach($requiredRolesProgress['inspection']['completed_roles'] as $role)
                                    <x-mary-badge :value="$role->name" class="badge-success badge-sm"/>
                                @endforeach
                                @if($requiredRolesProgress['inspection']['completed_roles']->isEmpty())
                                    <span class="text-xs">{{ __('ledger.none') }}</span>
                                @endif
                                </div>
                                <div class="mt-1"><span class="font-bold">{{ __('ledger.workflow.inspection_pending') }}:</span>
                                @foreach($requiredRolesProgress['inspection']['pending_roles'] as $role)
                                    <x-mary-badge :value="$role->name" class="badge-warning badge-sm"/>
                                @endforeach
                                @if($requiredRolesProgress['inspection']['pending_roles']->isEmpty())
                                    <span class="text-xs">{{ __('ledger.none') }}</span>
                                @endif
                                </div>
                            </div>
                            <span class="text-xs font-medium text-base-content/60 shrink-0">{{ __('ledger.workflow.required_inspector_roles') }}:</span>
                            <progress class="progress progress-warning flex-1 bg-base-200"
                                      value="{{ $requiredRolesProgress['inspection']['completed_count'] }}"
                                      max="{{ $requiredRolesProgress['inspection']['total_count'] }}"
                            ></progress>
                            <span class="text-xs font-bold text-base-content/70 shrink-0">{{ $requiredRolesProgress['inspection']['completed_count'] }}/{{ $requiredRolesProgress['inspection']['total_count'] }}</span>
                            @if ($requiredRolesProgress['inspection']['is_all_completed'])
                                <x-mary-icon name="o-check-circle" class="w-4 h-4 text-success shrink-0"/>
                            @else
                                <x-mary-icon name="o-ellipsis-horizontal-circle" class="w-4 h-4 text-warning shrink-0"/>
                            @endif
                        </div>
                    @endif

                    {{-- 承認進捗 --}}
                    @if($requiredRolesProgress['approval']['total_count'] > 0)
                        <div class="flex items-center gap-2 tooltip" style="width: 200px;">
                            <div class="tooltip-content p-2 space-y-2 text-left w-max">
                                <div><span class="font-bold">{{ __('ledger.workflow.approval_completed') }}:</span>
                                @foreach($requiredRolesProgress['approval']['completed_roles'] as $role)
                                    <x-mary-badge :value="$role->name" class="badge-success badge-sm"/>
                                @endforeach
                                @if($requiredRolesProgress['approval']['completed_roles']->isEmpty())
                                    <span class="text-xs">{{ __('ledger.none') }}</span>
                                @endif
                                </div>
                                <div class="mt-1"><span class="font-bold">{{ __('ledger.workflow.approval_pending') }}:</span>
                                @foreach($requiredRolesProgress['approval']['pending_roles'] as $role)
                                    <x-mary-badge :value="$role->name" class="badge-warning badge-sm"/>
                                @endforeach
                                @if($requiredRolesProgress['approval']['pending_roles']->isEmpty())
                                    <span class="text-xs">{{ __('ledger.none') }}</span>
                                @endif
                                </div>
                            </div>
                            <span class="text-xs font-medium text-base-content/60 shrink-0">{{ __('ledger.workflow.required_approver_roles') }}:</span>
                            <progress class="progress {{ $requiredRolesProgress['approval']['is_all_completed'] && $requiredRolesProgress['inspection']['is_all_completed'] && $ledgerRecord->status === WorkflowStatus::APPROVED ? 'progress-success' : 'progress-info' }} flex-1 bg-base-200"
                                      value="{{ $requiredRolesProgress['approval']['completed_count'] }}"
                                      max="{{ $requiredRolesProgress['approval']['total_count'] }}"
                            ></progress>
                            <span class="text-xs font-bold text-base-content/70 shrink-0">{{ $requiredRolesProgress['approval']['completed_count'] }}/{{ $requiredRolesProgress['approval']['total_count'] }}</span>
                            @if ($requiredRolesProgress['approval']['is_all_completed'])
                                <x-mary-icon name="o-check-circle" class="w-4 h-4 text-success shrink-0"/>
                            @else
                                <x-mary-icon name="o-ellipsis-horizontal-circle" class="w-4 h-4 text-warning shrink-0"/>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- 承認済みで必須ロール未完了の場合の警告 --}}
                @if($ledgerRecord->status === WorkflowStatus::APPROVED && (!$requiredRolesProgress['inspection']['is_all_completed'] || !$requiredRolesProgress['approval']['is_all_completed']))
                    <div class="mt-1 text-xs text-error flex items-center font-bold">
                        <x-mary-icon name="o-exclamation-triangle" class="w-4 h-4 mr-1 shrink-0"/>
                        {{ __('ledger.workflow.required_roles_not_completed') }}
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- 右側: アクションボタン --}}
    <div class="flex flex-wrap items-center justify-end gap-2 shrink-0 border-t xl:border-t-0 xl:border-l border-base-200 pt-3 xl:pt-0 xl:pl-4 mt-2 xl:mt-0">
        
        @if($this->canRequestApproval())
            <x-mary-button label="{{ __('ledger.workflow.request_approval_short') }}"
                           icon="o-check-badge"
                           class="btn-sm btn-success shadow-sm"
                           wire:click="openApproverSelectModal"
                           spinner="openApproverSelectModal"/>
        @elseif(
            $ledgerRecord->status === WorkflowStatus::PENDING_INSPECTION
            && $ledgerRecord->latestDiff?->inspector_id === Auth::id()
            && !$this->ledgerRecord->canProceedToApprovalStep()
        )
            <div class="tooltip tooltip-left" data-tip="{{ __('ledger.workflow.error_inspection_not_completed') }}">
                <x-mary-button label="{{ __('ledger.workflow.request_approval_short') }}"
                               icon="o-check-badge" class="btn-sm btn-success shadow-sm"
                               disabled/>
            </div>
        @endif

        @if($this->canApprove())
            <x-mary-button label="{{ __('ledger.workflow.approve') }}"
                           icon="o-check-circle"
                           class="btn-sm btn-success shadow-sm" wire:click="approveTask"
                           spinner/>
        @elseif($ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver_id === Auth::id())
            <div class="tooltip tooltip-left" data-tip="{{ __('ledger.workflow.error_approval_not_completed') }}">
                <x-mary-button label="{{ __('ledger.workflow.approve') }}"
                               icon="o-check-circle"
                               class="btn-sm btn-success shadow-sm" disabled/>
            </div>
        @endif

        @if($this->canReturnToDraft())
            <x-mary-button label="{{ __('ledger.workflow.return_to_draft_short') }}"
                           icon="o-arrow-uturn-left"
                           class="btn-sm btn-warning shadow-sm" wire:click="openReturnToDraftModal"
                           spinner="openReturnToDraftModal"/>
        @endif
    </div>

</div>
</div>