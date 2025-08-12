<div>
    @php
        use App\Enums\WorkflowStatus;
    @endphp
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
            @elseif($ledgerRecord->status === \App\Enums\WorkflowStatus::PENDING_INSPECTION
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
            @elseif($ledgerRecord->status === \App\Enums\WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver_id === Auth::id() && !$this->ledgerRecord->hasAnyRequiredInspectionBeenDoneForCurrentContent())
                {{-- 承認担当者だが、いずれの必須点検も完了していない場合 --}}
                <div class="tooltip"
                     data-tip="{{ __('ledger.workflow.tooltip.approve_requires_any_prior_inspection') }}"> {{-- 新翻訳キー --}}
                    <x-mary-button label="{{ __('ledger.workflow.approve') }}" icon="o-check-circle"
                                   class="join-item btn-primary btn-sm md:btn-md" disabled/>
                </div>
            @elseif($ledgerRecord->status === \App\Enums\WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver_id === Auth::id())
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
        @if($ledgerRecord->status === \App\Enums\WorkflowStatus::PENDING_INSPECTION && $ledgerRecord->latestDiff?->inspector)
            ({{ __('ledger.workflow.inspector') }}: {{ $ledgerRecord->latestDiff->inspector->name }})
        @elseif($ledgerRecord->status === \App\Enums\WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver)
            ({{ __('ledger.workflow.approver') }}: {{ $ledgerRecord->latestDiff->approver->name }})
        @endif
    </div>
</div>