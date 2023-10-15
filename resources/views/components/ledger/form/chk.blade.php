<div class="flex flex-row">
@foreach ($columnDefine->options as $oKey => $option)
        <div class="basis-1/12 space-x-5">
        {{-- 各オプションのラベルとチェックボックスを表示する --}}
        <label for="content[{{$columnDefine->id}}][{{$oKey}}]" class="label cursor-pointer space-x-2">
            <input type="checkbox"
                   wire:model.live="content.{{$columnDefine->id}}.{{$oKey}}" {{-- Livewireの双方向データバインディングを使用 --}}
                   id="content[{{$columnDefine->id}}][{{$oKey}}]"
                   name="content[{{$columnDefine->id}}][{{$oKey}}]" value="{{$option}}"
                   @php( (isset($this->content) && is_array($this->content[$columnDefine->id]) && in_array($option, $this->content[$columnDefine->id]??[]) ) ? 'checked="checked"' : '') {{-- チェック済みの場合はチェックボックスを選択状態にする --}}
                   class="input-bordered checkbox @if($columnDefine->required) input-accent @endif"
            />
            <span class="label-text">{{$option}}</span> {{-- オプションの名前を表示 --}}
        </label>
    </div>
@endforeach
</div>
