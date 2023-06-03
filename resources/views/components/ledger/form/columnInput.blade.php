@foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)
    <div class="flex flex-justify items-center align-middle px-3 my-5">
        <label for="content[{{$columnDefine->id}}]"
               class="basis-1/4 text-right text-gray-700 font-bold mr-5">
            {{$columnDefine->name}}
        </label>
        {{--
                <input type="hidden" name="content[{{$columnDefine->id}}]" value="">
                <input name="content[{{$columnDefine->id}}]" type="text"
                       value="{{$ledgerRecord->content[$columnDefine->id] ?? ''}}"
                       placeholder="Type here"
                       class="input input-bordered w-full"/>
        --}}

        {{ ColumnForm::show($columnDefine,
        $ledgerRecord->content[$columnDefine->id] ?? '',
        ['class'=>'input-bordered'],
        '['.$ledgerDefineRecord->id.']', empty($ledgerRecord->id) ? true: false)
         }}
    </div>
@endforeach

