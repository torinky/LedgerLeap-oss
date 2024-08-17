<select wire:model.live="content.{{$columnDefine->id}}" id="content[{{$columnDefine->id}}]"
        name="content[{{$columnDefine->id}}]"
        class="select select-bordered @if($columnDefine->required) input-accent @endif"
>
    <option disabled>Pick your choice</option>

    @foreach ($columnDefine->options as $option)
        <option value="{{$option}}" @php(($option==($this->content[$columnDefine->id]??'') )? 'selected' : '')>
            {{$option}}
        </option>
    @endforeach
</select>
