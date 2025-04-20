<div>
    @php use App\Enums\WorkflowStatus; @endphp

    <div class="p-8 bg-base-100 rounded-b-xl grid grid-cols-1 gap-5">

        <x-mary-card>
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold mb-1">{{ __('ledger.workflow.current_status') }}</h3>
                    <x-mary-badge :value="$ledgerRecord->status->label()"
                                  class="{{ $ledgerRecord->status->colorClass() }}"/>
                    {{-- 担当者表示 --}}
                    @if($ledgerRecord->status === WorkflowStatus::PENDING_INSPECTION && $ledgerRecord->latestDiff?->inspector)
                        <span class="text-sm ml-2">({{ __('ledger.workflow.inspector') }}: {{ $ledgerRecord->latestDiff->inspector->name }})</span>
                    @elseif($ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver)
                        <span class="text-sm ml-2">({{ __('ledger.workflow.approver') }}: {{ $ledgerRecord->latestDiff->approver->name }})</span>
                    @elseif($ledgerRecord->status === WorkflowStatus::APPROVED && $ledgerRecord->latestDiff?->approver)
                        <span class="text-sm ml-2">({{ __('ledger.workflow.approved_by') }}: {{ $ledgerRecord->latestDiff->approver->name }} at {{ $ledgerRecord->approved_at?->isoFormat('YYYY/MM/DD HH:mm') }})</span>
                    @endif
                </div>
                {{-- アクションボタン --}}
                <div class="flex gap-2">
                    @if($this->canRequestApproval())
                        <x-mary-button label="{{ __('ledger.workflow.request_approval_short') }}" icon="o-check-badge"
                                       class="btn-sm btn-success" wire:click="openApprovalRequestModal" spinner/>
                    @endif
                    @if($this->canApprove())
                        <x-mary-button label="{{ __('ledger.workflow.approve') }}" icon="o-check-circle"
                                       class="btn-sm btn-primary" wire:click="approveTask" spinner/>
                    @endif
                    @if($this->canReturnToDraft())
                        <x-mary-button label="{{ __('ledger.workflow.return_to_draft_short') }}"
                                       icon="o-arrow-uturn-left"
                                       class="btn-sm btn-warning" wire:click="openReturnToDraftModal" spinner/>
                    @endif
                </div>
            </div>
        </x-mary-card>

        <x-ledger.detail.table
                :ledgerRecord="$ledgerRecord"
                :canView="auth()->user()->can('view', $ledgerRecord)"
        />
        <div class="container mx-auto mt-4 items-center text-sm text-gray-500 flex justify-end">
            <i class="fa-solid fa-user mr-2"></i>{{$ledgerRecord->modifier->name}}
            <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('ledger.named.updated_at').$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}</span>
            <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('ledger.named.created_at').$ledgerRecord->created_at->format('Y-m-d H:i:s')}}</span>
        </div>
        {{-- 変更履歴へのリンク --}}
        <div class="mt-4 text-center">
            @if($ledgerRecord->ledgerDiff()->where(DB::raw('content'), '!=', '')->count() > 0)
                {{-- content がある Diff が存在する場合のみ --}}
                <a href="{{ route('ledgerDiff.show', ['ledgerId'=>$ledgerRecord->id]) }}"
                   class="btn btn-outline btn-info ml-5"
                ><i class="fa-solid fa-clock-rotate-left mr-2"></i>{{__('ledger.view_history')}} {{-- 翻訳キー変更 --}}
                </a>
            @endif
        </div>

        <div
                class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
            <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                <div class="card-body flex flex-row justify-center items-center">
                    <div class="card-actions justify-center place-items-center">
                        <a href="{{ route('ledger.edit', ['ledgerId'=>$ledgerRecord->id]) }}"
                           class="btn btn-primary btn-lg btn-wide"
                        ><i class="fa-solid fa-pencil mr-2"></i>{{__('ledger.edit')}}</a>

                        @if($ledgerRecord->ledger_diff_count>0)
                            <a href="{{ route('ledgerDiff.show', ['ledgerId'=>$ledgerRecord->id]) }}"
                               class="btn btn-outline btn-info ml-5"
                            ><i class="fa-solid fa-clock-rotate-left mr-2"></i>{{__('ledger.modifies')}}
                                <div class="badge badge-info badge-outline">{{$ledgerRecord->ledger_diff_count}}</div>
                            </a>
                        @endif

                        {{--
                                            <a href="#" class="btn btn-outline btn-info ml-5" onclick="window.close();"><i
                                                    class="fa-solid fa-close mr-2"></i>{{__('ledger.close_window')}}</a>
                        --}}
                        <x-ledger.close-window-button
                                :closeWindowMessage="__('ledger.close_view_window_message')"
                                :cancel="__('ledger.cancel')"
                        />
                    </div>
                </div>
            </div>
            {{-- ワークフロー履歴表示エリア (ステップ4.1で実装) --}}
            {{-- <x-mary-card title="ワークフロー履歴" class="mt-6"> --}}
            {{--    @foreach($ledgerRecord->activities()->latest()->get() as $activity) --}}
            {{--        <div>{{ $activity->created_at }} - {{ $activity->causer->name }} - {{ $activity->description }} - {{ $activity->properties->get('comments') }}</div> --}}
            {{--    @endforeach --}}
            {{-- </x-mary-card> --}}


            {{-- 承認者選択モーダル --}}
            <x-mary-modal wire:model="approvalRequestModal" title="{{ __('ledger.workflow.select_next_approver') }}">
                <x-mary-select label="{{ __('ledger.workflow.next_approver') }}" :options="$approverOptions"
                               wire:model="selectedApproverId"/>
                <x-slot:actions>
                    <x-mary-button label="{{ __('Cancel') }}" @click="$wire.approvalRequestModal = false"/>
                    <x-mary-button label="{{ __('ledger.workflow.request_approval') }}" class="btn-primary"
                                   wire:click="requestApproval" spinner/>
                </x-slot:actions>
            </x-mary-modal>

            {{-- 戻し理由入力モーダル --}}
            <x-mary-modal wire:model="returnToDraftModal" title="{{ __('ledger.workflow.return_to_draft_reason') }}">
                <x-mary-textarea label="{{ __('ledger.workflow.comments') }}" wire:model="returnComment"
                                 placeholder="{{ __('ledger.workflow.return_reason_placeholder') }}"
                                 hint="{{ __('ledger.workflow.optional_comment') }}" rows="3"/>
                <x-slot:actions>
                    <x-mary-button label="{{ __('Cancel') }}" @click="$wire.returnToDraftModal = false"/>
                    <x-mary-button label="{{ __('ledger.workflow.return_to_draft') }}" class="btn-warning"
                                   wire:click="returnTaskToDraft" spinner/>
                </x-slot:actions>
            </x-mary-modal>

        </div>
    </div>
</div>
