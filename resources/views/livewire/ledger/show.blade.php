<div>
    <x-ledger.detail.table
        :ledgerRecord="$ledgerRecord"
        :canView="auth()->user()->can('view', $ledgerRecord)"
    />
    <div class="container mx-auto mt-4 items-center text-sm text-gray-500 flex justify-end">
        <i class="fa-solid fa-user mr-2"></i>{{$ledgerRecord->modifier->name}}
        <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('ledger.named.updated_at').$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}</span>
        <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('ledger.named.created_at').$ledgerRecord->created_at->format('Y-m-d H:i:s')}}</span>
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
    </div>
</div>
