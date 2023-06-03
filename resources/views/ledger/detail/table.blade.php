<div class="container mx-auto prose lg:prose-xl">
    {{--        @dd($ledgerRecord)--}}
    @if($ledgerRecord && $ledgerRecord->content && $ledgerRecord->define)
        <h3 class="">
            {{$ledgerRecord->define->title}}
        </h3>
        <table class="table table-zebra table-compact  table-fixed w-full">
            <tbody>
            @foreach($ledgerRecord->define->column_define as $cKey => $columnDefine)
                <tr>
                    <th class="w-1/6 break-words">
                        {{$columnDefine->name}}
                    </th>
                    <td class="break-words">
                        {{ ColumnHtml::show($columnDefine,$ledgerRecord->content[$columnDefine->id]??'') }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
