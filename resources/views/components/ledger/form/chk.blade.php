@foreach ($columnDefine->options as $option)
    <div class="form-control mr-5">
        {{-- 各オプションのラベルとチェックボックスを表示する --}}
        <label for="content[{{$columnDefine->id}}][{{$option}}]" class="label cursor-pointer space-x-2">
            <input type="checkbox"
                   wire:model="content.{{$columnDefine->id}}.{{$option}}" {{-- Livewireの双方向データバインディングを使用 --}}
                   id="content[{{$columnDefine->id}}][{{$option}}]"
                   name="content[{{$columnDefine->id}}][{{$option}}]" value="{{$option}}"
                   @php( (isset($this->content) && is_array($this->content[$columnDefine->id]) && in_array($option, $this->content[$columnDefine->id]??[]) ) ? 'checked="checked"' : '') {{-- チェック済みの場合はチェックボックスを選択状態にする --}}
                   class="input-bordered checkbox"
            />
            <span class="label-text">{{$option}}</span> {{-- オプションの名前を表示 --}}
        </label>
    </div>
@endforeach
