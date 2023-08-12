@foreach ($columnDefine->options as $oKey => $option)
    <div class="form-control mr-5">
        {{-- 各オプションのラベルとチェックボックスを表示する --}}
        <label for="content[{{$columnDefine->id}}][{{$oKey}}]" class="label cursor-pointer space-x-2">
            <input type="checkbox"
                   wire:model="content.{{$columnDefine->id}}.{{$oKey}}" {{-- Livewireの双方向データバインディングを使用 --}}
                   id="content[{{$columnDefine->id}}][{{$oKey}}]"
                   name="content[{{$columnDefine->id}}][{{$oKey}}]" value="{{$option}}"
                   @php( (isset($this->content) && is_array($this->content[$columnDefine->id]) && in_array($option, $this->content[$columnDefine->id]??[]) ) ? 'checked="checked"' : '') {{-- チェック済みの場合はチェックボックスを選択状態にする --}}
                   class="input-bordered checkbox"
            />
            <span class="label-text">{{$option}}</span> {{-- オプションの名前を表示 --}}
        </label>
    </div>
@endforeach
@error('content.' . $columnDefine->id) <span class="error">{{ $message }}</span> @enderror
