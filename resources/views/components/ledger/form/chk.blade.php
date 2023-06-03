@foreach ($columnDefine->options as $option)
    <div class="form-control">
        <label for="content[{{$columnDefine->id}}][{{$option}}]" class="label cursor-pointer">
            <span class="label-text">{{$option}}</span>
            <input type="checkbox" wire:model="content.{{$columnDefine->id}}.{{$option}}"
                   id="content[{{$columnDefine->id}}][{{$option}}]"
                   name="content[{{$columnDefine->id}}][{{$option}}]" value="{{$option}}"
                   @php( (isset($lthis->content) && is_array($this->content[$columnDefine->id]) && in_array($option, $this->content[$columnDefine->id]??[]) ) ? 'checked="checked"' : '')
                   class="input-bordered checkbox"
            />
        </label>
    </div>
@endforeach
