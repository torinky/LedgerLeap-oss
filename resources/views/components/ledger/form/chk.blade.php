
@props([
    'class'=>'',
    'hintClass'=> 'label-text-alt text-gray-400 ps-1 mt-2',
    'isDemo'=>false,
    'columnDefine' => null,
    ])

<div class="form-control {{$class}}">
    <label class="pt-0 label label-text font-semibold">
        <span>
            {{$columnDefine->name}}
            @if($columnDefine->required)
                <span class="text-error">*</span>
            @endif
        </span>
    </label>

    <div class="flex flex-wrap join ">
        @foreach($columnDefine->options as $oKey => $option)
            <div class="join-item items-center space-y-2">
                <input type="checkbox"
                       @if(!$isDemo)
                           wire:model.live="content.{{$columnDefine->id}}.{{$option}}"
                       @endif
                       id="content[{{$columnDefine->id}}][{{$option}}]"
                       value="{{$option}}"
                       name="content[{{$columnDefine->id}}][{{$option}}]"
                       class="hidden peer"
                    {{--                       @if($columnDefine->required) required @endif--}}
                />
                <label for="content[{{$columnDefine->id}}][{{$option}}]"
                       class="btn peer-checked:btn-primary peer-checked:bg-opacity-50">
                    <span class="label-text">{{$option}}</span>
                </label>
            </div>
        @endforeach
    </div>
    @error('content.' . $columnDefine->id)
    <label class="label">
        <span class="label-text-alt text-red-500 text-xs space-x-2">
            <i class="fas fa-times-circle"></i>
            <span class="error">{{ $message }}</span>
        </span>
    </label>
    @enderror
    @if($columnDefine->hint)
        <div class="{{ $hintClass }}" x-classes="label-text-alt text-gray-400 ps-1 mt-2">{{ $columnDefine->hint }}</div>
    @endif

</div>
