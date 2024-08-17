{{--<div class="flex flex-row">
@foreach ($columnDefine->options as $oKey => $option)
        <div class="basis-1/12 space-x-5">
        --}}{{-- 各オプションのラベルとチェックボックスを表示する --}}{{--
        <label for="content[{{$columnDefine->id}}][{{$oKey}}]" class="label cursor-pointer space-x-2">
            <input type="checkbox"
                   wire:model="content.{{$columnDefine->id}}" --}}{{-- Livewireの双方向データバインディングを使用 --}}{{--
                   id="content[{{$columnDefine->id}}][{{$oKey}}]"
                   name="content[{{$columnDefine->id}}][{{$option}}]"
                   value="{{$option}}"
                   class="input-bordered checkbox @if($columnDefine->required) input-accent @endif"
            />
            <span class="label-text">{{$option}}</span> --}}{{-- オプションの名前を表示 --}}{{--
        </label>
    </div>
@endforeach
</div>--}}


<div>
    <label class="label">
        <span class="label-text">
            @if($columnDefine->required)
                <i class="fas fa-check-circle text-accent"></i>
            @endif
            {{$columnDefine->name}}
        </span>
    </label>
    <div class="join">
        @foreach($columnDefine->options as $oKey => $option)
            <div class="join-item">
                <input type="checkbox"
                       id="content[{{$columnDefine->id}}][{{$option}}]"
                       value="{{$option}}"
                       wire:model="content.{{$columnDefine->id}}.{{$option}}"
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

</div>
