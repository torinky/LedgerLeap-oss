<div>
    @php
        use App\Enums\WorkflowStatus;
    @endphp
    <x-ledger.sticky-action-bar>
        <x-slot:left>
            <div class="flex flex-wrap items-center justify-center gap-3">
                {{-- 閉じるボタンとリストに戻るボタン --}}
                <div class="flex gap-4 items-center justify-center">
                    @php
                        $ledgerListUrl = route('ledgersByDefineId', ['tenant' => tenant('id'), 'defineId' => $ledgerRecord->define->id]);
                    @endphp
                    <x-ledger.close-window-button/>
                    <x-mary-button
                            label="{{ __('ledger.back_to_list') }}"
                            icon="o-list-bullet"
                            class="btn-sm btn-outline"
                            onclick="window.open('{{ $ledgerListUrl }}', 'ledger-list');"
                    />
                </div>
                {{-- 複製ボタン --}}
                @php $canCreate = auth()->user()->can('create', [App\Models\Ledger::class, $ledgerRecord->define]); @endphp
                @if($canCreate)
                    <a href="{{ route('ledger.duplicate', ['tenant' => tenant('id'), 'ledgerId'=>$ledgerRecord->id]) }}"
                       class="btn btn-outline btn-sm md:btn-md"
                       target="_blank"
                    >
                        <i class="fa-solid fa-copy mr-2"></i>{{ __('ledger.duplicate_from_this') }}
                    </a>
                @else
                    <div class="tooltip" data-tip="{{ __('ledger.no_create_permission') }}">
                        <button class="btn btn-outline btn-sm md:btn-md" disabled>
                            <i class="fa-solid fa-copy mr-2"></i>{{ __('ledger.duplicate_from_this') }}
                        </button>
                    </div>
                @endif

                {{-- 変更履歴ボタン --}}
                @if($ledgerRecord->ledgerDiff()->where(DB::raw('content'), '!=', '')->count() > 0)
                    <div>
                        <a href="{{ route('ledgerDiff.show', ['tenant' => tenant('id'), 'ledgerId'=>$ledgerRecord->id]) }}"
                           class="btn btn-outline btn-info btn-wide"
                        ><i class="fa-solid fa-clock-rotate-left mr-2"></i>{{ __('ledger.view_history') }}
                            @if($ledgerRecord->version-1>0)
                                <div class="badge badge-sm badge-info tooltip"
                                     data-tip="{{ __('ledger.reviseCount') }}"> {{ $ledgerRecord->version-1 }}
                                </div>
                            @endif
                        </a>
                    </div>
                @endif
            </div>
        </x-slot:left>

        <x-slot:right>
            <div class="flex flex-wrap items-center justify-center gap-3">
                {{-- 編集ボタン --}}
                @php $canUpdate = auth()->user()->can('ledgerUpdate', $ledgerRecord->define); @endphp
                @if($canUpdate && !$ledgerRecord->isLocked())
                    <a href="{{ route('ledger.edit', ['tenant' => tenant('id'), 'ledgerId'=>$ledgerRecord->id]) }}"
                       class="btn btn-primary btn-lg px-8"
                    ><i class="fa-solid fa-pencil mr-2"></i>{{ __('ledger.edit') }}</a>
                @else
                    <div class="tooltip"
                         data-tip="{{ $ledgerRecord->isLocked() ? __('ledger.workflow.record_locked') : __('ledger.no_edit_permission') }}">
                        <button class="btn btn-primary btn-lg" disabled><i
                                    class="fa-solid fa-pencil mr-2"></i>{{ __('ledger.edit') }}</button>
                    </div>
                @endif

                {{-- ワークフローアクションボタン --}}
                {{-- 点検完了（承認申請）ボタン --}}
                @if($this->canRequestApproval())
                    <x-mary-button label="{{ __('ledger.workflow.request_approval_short') }}"
                                   icon="o-check-badge" class="btn-success btn-sm md:btn-md"
                                   wire:click="openApproverSelectModal"
                                   spinner="openApproverSelectModal"/>
                @elseif($ledgerRecord->status === \App\Enums\WorkflowStatus::PENDING_INSPECTION
                    && $ledgerRecord->latestDiff?->inspector_id === Auth::id()
                    && !$this->ledgerRecord->canProceedToApprovalStep())
                    <div class="tooltip"
                         data-tip="{{ __('ledger.workflow.error_inspection_not_completed') }}">
                        <x-mary-button label="{{ __('ledger.workflow.request_approval_short') }}"
                                       icon="o-check-badge" class="btn-success btn-sm md:btn-md"
                                       disabled/>
                    </div>
                @endif

                {{-- 承認ボタン --}}
                @if($this->canApprove())
                    <x-mary-button label="{{ __('ledger.workflow.approve') }}" icon="o-check-circle"
                                   class="btn-success btn-sm md:btn-md"
                                   wire:click="approveTask"
                                   spinner/>
                @elseif($ledgerRecord->status === \App\Enums\WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver_id === Auth::id() && !$this->ledgerRecord->hasAnyRequiredInspectionBeenDoneForCurrentContent())
                    <div class="tooltip"
                         data-tip="{{ __('ledger.workflow.tooltip.approve_requires_any_prior_inspection') }}">
                        <x-mary-button label="{{ __('ledger.workflow.approve') }}" icon="o-check-circle"
                                       class="btn-primary btn-sm md:btn-md" disabled/>
                    </div>
                @elseif($ledgerRecord->status === \App\Enums\WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver_id === Auth::id())
                    {{-- 承認担当者だが、他の理由で承認できない場合 --}}
                @endif

                @if($this->canReturnToDraft())
                    <x-mary-button label="{{ __('ledger.workflow.return_to_draft_short') }}"
                                   icon="o-arrow-uturn-left" class="btn-warning"
                                   wire:click="openReturnToDraftModal"
                                   spinner="openReturnToDraftModal"/>
                @endif
            </div>
        </x-slot:right>

        <x-slot:footer>
            <div class="flex items-center space-x-4">
                    <span class="tooltip" data-tip="{{__('ledger.diff.current_version')}}">
                        <x-mary-badge :value="'Ver.' . $ledgerRecord->version"
                                      class="badge-primary badge-sm font-bold"/>
                    </span>
                {{-- 現在のステータス表示 (バッジ化 + ツールチップ) --}}
                @if ($ledgerRecord?->status)
                    @php
                        $status = $ledgerRecord->status;
                        $statusIcon = match($status) {
                            \App\Enums\WorkflowStatus::DRAFT => 'o-pencil-square',
                            \App\Enums\WorkflowStatus::PENDING_INSPECTION => 'o-magnifying-glass',
                            \App\Enums\WorkflowStatus::PENDING_APPROVAL => 'o-clock',
                            \App\Enums\WorkflowStatus::APPROVED => 'o-check-badge',
                            default => 'o-document-text',
                        };
                    @endphp
                    <div class="tooltip tooltip-top" data-tip="{{ __('ledger.workflow.current_status') }}">
                        <x-mary-badge
                                :value="$status->label()"
                                :icon="$statusIcon"
                                class="badge-sm {{ $status->colorClass() }} font-bold shadow-sm"/>
                    </div>
                @else
                    <div class="tooltip tooltip-top" data-tip="{{ __('ledger.workflow.current_status') }}">
                        <x-mary-badge
                                :value="__('ledger.workflow.status.draft')"
                                icon="o-pencil-square"
                                class="badge-sm badge-ghost font-bold shadow-sm"/>
                    </div>
                @endif
            </div>
        </x-slot:footer>
    </x-ledger.sticky-action-bar>
</div>
