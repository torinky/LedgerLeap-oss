@props([
    'ledgerRecord'=>null,
    'highlightKeyword'=>null,
    'canUpdate'=>false,
    'canView'=>false,
    'allAttachments' => [],
    'filteredColumnDefines' => [],
    'currentTenantId' => null,
    ])
<tr class="hover">
    <th class=" border flex-col bg-accent/20">
        <div class="tooltip tooltip-right"
             data-tip="{{__('ledger.edit')}}"
        >
            @if($canUpdate && !$ledgerRecord->isLocked())
                <a href="{{ route('ledger.edit', ['tenant' => tenant()?->id, 'ledgerId'=>$ledgerRecord->id]) }}"
                   class="btn btn-neutral opacity-70 hover:opacity-100 btn-sm my-1 btn-square"
                   target="ledgerEdit_{{$ledgerRecord->define->id}}}}"
                >
                    <i class="fas fa-pencil"></i>
                </a>
            @else
                <div class="tooltip tooltip-right"
                     data-tip="{{ $ledgerRecord->isLocked() ? __('ledger.workflow.record_locked') : __('ledger.no_edit_permission') }}">
                    <button class="btn btn-neutral opacity-70 btn-sm my-1 btn-square" disabled>
                        <i class="fas fa-pencil"></i>
                    </button>
                </div>
            @endif
        </div>


        <div class="tooltip tooltip-right"
             data-tip="{{__('ledger.show_details')}}"
        >
            <a href="{{ route('ledger.show', ['tenant' => tenant()?->id, 'ledgerId'=>$ledgerRecord->id, 'highlight' => $highlightKeyword]) }}"
               class="btn btn-outline btn-info btn-sm my-1 btn-square opacity-70 hover:opacity-100"
               target="ledgerShow_{{$ledgerRecord->define->id}}}}">
                <i class="fas fa-table-list"></i>
            </a>
        </div>

    </th>

    {{-- 複合スコア表示 --}}
    <td class="px-2 py-2 text-center border">
        @php
            $scoreClass = match(true) {
                $ledgerRecord->composite_score >= 70 => 'badge-success',  // 緑: 非常に重要
                $ledgerRecord->composite_score >= 40 => 'badge-primary',   // 青: 重要
                $ledgerRecord->composite_score >= 20 => 'badge-info',      // 水色: 注目
                $ledgerRecord->composite_score > 0 => 'badge-ghost',       // グレー: 通常
                default => ''
            };
        @endphp
        @if($ledgerRecord->composite_score > 0)
            <span class="badge badge-sm {{ $scoreClass }}">
                {{ number_format($ledgerRecord->composite_score, 1) }}
            </span>
        @else
            <span class="text-base-content/30 text-xs">-</span>
        @endif
    </td>

    @foreach($filteredColumnDefines as $cKey=>$columnDefine)
        <td class="hover:bg-accent/20 border px-4 py-2">
            @if (!$canView)
                <x-ledger.not-authorized-message/>
            @elseif (empty($ledgerRecord->content[$columnDefine->id]))
                <x-ledger.empty-message/>
            @else
                @php
                    $columnHtml = ColumnHtml::setAttachmentCollection($allAttachments->get($ledgerRecord->id, collect())->keyBy('hashedbasename'))
                                 ->setAttachmentContents($ledgerRecord->content_attached[$columnDefine->id] ?? [])
                                 ->show($columnDefine, $ledgerRecord->content[$columnDefine->id], $canView, [], '', false, $ledgerRecord, $highlightKeyword, tenant()?->id);
                    $columnHtmlString = $columnHtml->toHtml();
                @endphp
                
                <x-expandable-content 
                    :content="$columnHtmlString"
                    max-height="6rem"
                />
            @endif
        </td>
    @endforeach
    {{--                        <td class="border px-4 py-2 break-words whitespace-pre-wrap">{{$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}--}}

    {{-- ステータス表示セル --}}
    @if($ledgerRecord->define->workflow_enabled)
        <th class="border px-4 py-2 text-center">
            @if ($ledgerRecord->status)
                <x-mary-badge :value="$ledgerRecord->status->label()"
                              class="badge-sm {{ $ledgerRecord->status->colorClass() }}"/>
            @endif
        </th>
    @endif


    <td class="border px-4 py-2">{{$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}
        <span class="text-gray-500">{{JpDatetime::date('(bk)',$ledgerRecord->updated_at->timestamp)}}</span>
        <br/>( {{ $ledgerRecord->updated_at->diffForHumans() }} )
    </td>
    {{--                <td class="border px-4 py-2">{{ $ledgerRecords->created_at }}</td>--}}
</tr>
