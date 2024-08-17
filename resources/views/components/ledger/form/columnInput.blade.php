@foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)
    {{--
        <div class="flex flex-justify items-center align-middle px-3 my-5">
            <label for="content[{{$columnDefine->id}}]"
                   class="basis-1/4 text-right text-gray-700 font-bold mr-5">
                {{$columnDefine->name}}
            </label>

            {{ ColumnForm::show($columnDefine,
            $ledgerRecord->content[$columnDefine->id] ?? '',
            ['class'=>'input-bordered'],
            '['.$ledgerDefineRecord->id.']', empty($ledgerRecord->id) ? true: false)
             }}
        </div>
    --}}

    {{ ColumnForm::show($columnDefine,
    $ledgerRecord->content[$columnDefine->id] ?? '',
    ['class'=>'input-bordered'],
    '['.$ledgerDefineRecord->id.']', empty($ledgerRecord->id) ? true: false)
     }}

@endforeach

