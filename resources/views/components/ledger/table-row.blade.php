<tr class="hover">
    <th class=" border justify-center">
        <div>
            <a href="{{ route('ledger.edit', ['ledgerId'=>$ledgerRecord->id]) }}"
               class="btn btn-outline btn-primary btn-sm my-1 w-28"
               target="ledgerEdit_{{$ledgerRecord->define->id}}}}">
                <i class="fas fa-pencil mr-1"></i>
                {{__('edit')}}</a>

        </div>

        <div>
            <a href="{{ route('ledger.show', ['ledgerId'=>$ledgerRecord->id]) }}"
               class="btn btn-outline btn-info btn-sm my-1 w-28"
               target="ledgerShow_{{$ledgerRecord->define->id}}}}">
                <i class="fas fa-table-list mr-1"></i>
                {{__('detail')}}</a>

        </div>

    </th>
    @foreach($ledgerRecord->define->column_define as $cKey=>$columnDefine)
        @isset($ledgerRecord->content[$columnDefine->id])
            {{--                                <td class="border px-4 py-2 break-words whitespace-pre-wrap">{{ ColumnHtml::show($columnDefine,$ledgerRecord->content[$columnDefine->id]) }}</td>--}}
            <td class="border px-4 py-2">{{ ColumnHtml::show($columnDefine,$ledgerRecord->content[$columnDefine->id]) }}</td>
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
