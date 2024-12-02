@props([
    'class'=>'',
    'hintClass'=> 'label-text-alt text-gray-400 ps-1 mt-2',
    ])

<div class="form-control">
    <div class="pt-0 label label-text font-semibold">
        <span>
             {{$columnDefine->name}}
            @if($columnDefine->required)
                <span class="text-error">*</span>
            @endif
        </span>
    </div>
    <input type="text" wire:model.live="content.{{$columnDefine->id}}" id="content[{{$columnDefine->id}}]"
           class="input input-bordered @if($columnDefine->required) input-accent @endif"
           name="content[{{$columnDefine->id}}]" value="{{$this->content[$columnDefine->id] ?? ''}}"/>
    @if($columnDefine->hint)
        <div class="{{ $hintClass }}" x-classes="label-text-alt text-gray-400 ps-1 mt-2">{{ $columnDefine->hint }}</div>
    @endif

</div>

