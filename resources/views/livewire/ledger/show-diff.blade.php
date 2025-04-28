<div>
    {{-- スライダー表示 (変更なし、ただし value を $offset に) --}}
    <div class="container mx-auto prose lg:prose-xl">
        <input wire:change="changeOffset($event.target.value)"
               type="range" min="0" max="{{ $ledgerDiffCount > 0 ? $ledgerDiffCount -1 : 0 }}" value="{{ $offset }}"
               class="range w-full flex justify-between" step="1"/>
        <div class="w-full flex justify-between text-xs px-2">
            @for($i = 0; $i < $ledgerDiffCount; $i++)
                {{-- 0 から Count-1 まで --}}
                <span>|</span>
            @endfor
        </div>
        <p class="text-center text-sm">
            {{--            id: {{ $currentDiffRecord?->id ?? 'N/A' }}--}}
            Ver: {{ $currentDiffRecord?->version }}
        {{--            ({{ $ledgerDiffCount - $offset }} / {{ $ledgerDiffCount }})</p>--}}
        {{-- バージョン表示例 --}}
    </div>

    {{--  ワークフロー情報表示エリア  --}}
    @if ($currentDiffRecord && $currentDiffRecord->status !== \App\Enums\WorkflowStatus::NONE)
        <x-mary-card title="{{ __('ledger.workflow.history_detail') }}" class="mb-6" shadow="sm">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <div class="font-semibold">{{ __('ledger.workflow.status.label') }}</div>
                    <x-mary-badge :value="$currentDiffRecord->status->label()"
                                  class="badge-sm {{ $currentDiffRecord->status->colorClass() }}"/>
                </div>
                <div>
                    <div class="font-semibold">{{ __('ledger.workflow.history_user') }}</div>
                    <div>{{ $currentDiffRecord->modifier->name ?? 'N/A' }}</div>
                </div>
                <div>
                    <div class="font-semibold">{{ __('ledger.workflow.history_datetime') }}</div>
                    <div>{{ $currentDiffRecord->created_at->isoFormat('YYYY/MM/DD HH:mm:ss') }}</div>
                </div>
                @if ($currentDiffRecord->inspector)
                    <div>
                        <div class="font-semibold">{{ __('ledger.workflow.inspector') }}</div>
                        <div>{{ $currentDiffRecord->inspector->name }}</div>
                    </div>
                @endif
                @if ($currentDiffRecord->approver)
                    <div>
                        <div class="font-semibold">{{ __('ledger.workflow.approver') }}</div>
                        <div>{{ $currentDiffRecord->approver->name }}</div>
                    </div>
                @endif
                @if ($currentDiffRecord->comments)
                    <div class="col-span-2 md:col-span-4"> {{-- コメントは幅広く --}}
                        <div class="font-semibold">{{ __('ledger.workflow.comments') }}</div>
                        <div class="whitespace-pre-wrap bg-base-200 p-2 rounded text-xs">{{ $currentDiffRecord->comments }}</div>
                    </div>
                @endif
                {{-- 他の関連日時なども必要なら追加 --}}
            </div>
        </x-mary-card>
    @elseif ($currentDiffRecord)
        {{-- status が NONE の場合の代替表示 (任意) --}}
        <div class="text-sm text-base-content/70 mb-6 italic text-center">
            {{ __('ledger.workflow.workflow_inactive_at_this_point') }}
            ({{ __('ledger.workflow.history_user') }}: {{ $currentDiffRecord->modifier->name ?? 'N/A' }}
            , {{ $currentDiffRecord->created_at->isoFormat('YYYY/MM/DD HH:mm:ss') }})
        </div>
    @endif

    @if($currentDiffRecord && !empty($currentDiffRecord->content) && $currentDiffRecord->content != '[]' && $currentDiffRecord->content != '{}')
        {{--
                <x-ledger.detail.table
                        :ledgerRecord="$currentDiffRecord" --}}
        {{-- Diff レコードを渡す --}}{{--

                        :columnDefine="$currentDiffRecord->column_define" --}}
        {{-- Diff の定義を渡す --}}{{--

                        :canView="auth()->user()->can('view', $ledgerRecord)"
                />
        --}}
    @else
        {{-- content が記録されていない Diff の場合にメッセージ表示 --}}

        <div class="alert alert-info max-w-md mx-auto"><i
                    class="fas fa-info-circle"></i>{{__('ledger.no_content_in_this_diff')}}</div>
    @endif

    @if($ledgerRecord->content)
        <x-ledger.detail.table
                :ledgerRecord="$ledgerRecord"
                :canView="auth()->user()->can('view', $ledgerRecord)"
        />
    @else
        <div class="alert alert-info"><i class="fas fa-info-circle"></i>{{__('ledger.no_change_content')}}</div>
    @endif


    <div class="container mx-auto mt-4 items-center text-sm text-gray-500 flex justify-end">
        <i class="fa-solid fa-user mr-2"></i>{{$ledgerRecord->modifier->name}}
        <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('updated at: ').$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}</span>
        <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('created at: ').$ledgerRecord->created_at->format('Y-m-d H:i:s')}}</span>
    </div>

</div>
