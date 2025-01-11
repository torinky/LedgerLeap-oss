@props([
    'ledgerRecord'=>[],
    'canView'=>false,
])

<div class="container mx-auto">
    {{--        @dd($ledgerRecord)--}}
    @if($ledgerRecord && $ledgerRecord->content && $ledgerRecord->define)
        <div class="card bg-base-100 shadow-xl mt-10">
            {{--
                        <h2 class="card-title">
                        {{$ledgerRecord->define->title}}
                        </h2>
            --}}
            <div class="card-body">
                <table class="table table-zebra table-compact table-hover table-fixed w-full">
            <tbody>
            @foreach($ledgerRecord->define->column_define as $cKey => $columnDefine)
                <tr class="hover">
                    <th class="w-1/3 lg:w-1/4 break-words">
                        {{$columnDefine->name}}
                    </th>
                    <td class="break-words">
                        {{ ColumnHtml::setAttachmentContents($ledgerRecord->content_attached[$columnDefine->id]??[])
                            ->show($columnDefine,$ledgerRecord->content[$columnDefine->id]??'',$canView) }}

                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
            </div>
        </div>
    @endif
</div>
