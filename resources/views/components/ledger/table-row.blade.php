@props([
    'ledgerRecord'=>null,
    'keywords'=>[],
    'canUpdate'=>false,
    'canView'=>false,
    ])
<tr class="hover">
    <th class=" border flex-col bg-accent/20">
        <div class="tooltip tooltip-right"
             data-tip="{{__('ledger.edit')}}"
        >
            @if($canUpdate)
                <a href="{{ route('ledger.edit', ['ledgerId'=>$ledgerRecord->id]) }}"
                   class="btn btn-neutral opacity-70 hover:opacity-100 btn-sm my-1 btn-square"
                   target="ledgerEdit_{{$ledgerRecord->define->id}}}}"
                >
                    <i class="fas fa-pencil"></i>
                </a>
            @else
                <div class="tooltip tooltip-right" data-tip="{{__('ledger.no_edit_permission')}}">
                    <button class="btn btn-neutral opacity-70 btn-sm my-1 btn-square" disabled>
                        <i class="fas fa-pencil"></i>
                    </button>
                </div>
            @endif
        </div>


        <div class="tooltip tooltip-right"
             data-tip="{{__('ledger.show_details')}}"
        >
            <a href="{{ route('ledger.show', ['ledgerId'=>$ledgerRecord->id]) }}"
               class="btn btn-outline btn-info btn-sm my-1 btn-square opacity-70 hover:opacity-100"
               target="ledgerShow_{{$ledgerRecord->define->id}}}}">
                <i class="fas fa-table-list"></i>
            </a>
        </div>

    </th>
    @foreach($ledgerRecord->define->column_define as $cKey=>$columnDefine)
        @isset($ledgerRecord->content[$columnDefine->id])
            {{--            <td class="hover:bg-accent/20 border px-4 py-2">{{ ColumnHtml::show($columnDefine,$ledgerRecord->content[$columnDefine->id]) }}</td>--}}
            <td class="hover:bg-accent/20 border px-4 py-2">{{ ColumnHtml::setHighlightKeywords($keywords)
              ->setAttachments($ledgerRecord->content_attached[$columnDefine->id]??[])->show($columnDefine,$ledgerRecord->content[$columnDefine->id],$canView) }}</td>
        @else
            <td class="border px-4 py-2 text-center">-</td>
        @endif
    @endforeach
    {{--                        <td class="border px-4 py-2 break-words whitespace-pre-wrap">{{$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}--}}
    <td class="border px-4 py-2">{{$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}
        <span
            class="text-gray-500">{{JpDatetime::date('(bk)',$ledgerRecord->updated_at->timestamp)}}</span>
        <br/>( {{ $ledgerRecord->updated_at->diffForHumans() }} )
    </td>
    {{--                <td class="border px-4 py-2">{{ $ledgerRecords->created_at }}</td>--}}
</tr>
