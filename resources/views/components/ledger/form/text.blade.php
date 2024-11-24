<!-- text.blade.php -->
<!--
    テキスト入力フォームの幅を動的に調整するためのLivewireコンポーネントのビューファイルです。
    $columnDefine->idに基づいて動的なフォームを作成します。
    フォームの入力内容はcontent[$columnDefine->id]というLivewireのプロパティで管理されます。
-->
@props([
    'class'=>'input-primary',
    'icon'=>'o-chat-bubble-oval-left-ellipsis',
    'columnDefine'=>[]
    ])
@php
    if($columnDefine->required){
        $icon='c-check-circle';
        $class="input-accent";
    }

@endphp

<x-mary-input
    label="{{$columnDefine->name}}"
    name="content[{{$columnDefine->id}}]"
    placeholder="{{$columnDefine->name}}"
    icon="{{$icon}}"
    {{--    hint="{{$columnDefine->name}}"--}}
    wire:model="content.{{$columnDefine->id}}"
    clearable
    :class="$class"
    :required="$columnDefine->required"
/>

{{--
<label class="form-control w-full max-w-xs">

    <div class="label">
        <span class="label-text">
            @if($columnDefine->required)
                <i class="fas fa-check-circle text-accent"></i>
            @endif
            {{$columnDefine->name}}
        </span>
    </div>
    <input
        wire:model.blur="content.{{ $columnDefine->id }}"
        id="content[{{$columnDefine->id}}]"
        class="adjustWidth input input-bordered @if($columnDefine->required) input-accent @endif"
        name="content[{{$columnDefine->id}}]"
        value="{{ $this->content[$columnDefine->id] ?? '' }}"

    >
    @error('content.' . $columnDefine->id)
    <label class="label">
        <span class="label-text-alt text-red-500 text-xs space-x-2">
            <i class="fas fa-times-circle"></i>
            <span class="error">{{ $message }}</span>
        </span>
    </label>
    @enderror

</label>
--}}

{{--@once
    @push('scripts')
        <script>
            document.addEventListener('livewire:init', function () {
                // 動的なフォームの幅を調整するために.input.adjustWidthクラスを持つ全ての入力要素を取得します
                const inputs = document.querySelectorAll('input.adjustWidth');

                // 入力要素の幅を計算する関数
                function getWidthOfInput(input) {
                    const temp = document.createElement('span');
                    temp.textContent = input.value || input.placeholder;
                    temp.style.position = 'absolute';
                    temp.style.visibility = 'hidden';
                    document.body.appendChild(temp);
                    const width = temp.offsetWidth + 50; // テキストの幅に余裕を持たせるために50pxを加えます
                    document.body.removeChild(temp);
                    return width;
                }

                // 全ての入力要素の幅を調整する関数
                function adjustInputWidth() {
                    inputs.forEach((input) => {
                        const width = getWidthOfInput(input);
                        input.style.width = width + 'px';
                    });
                }

                // 入力内容が変更された際に呼び出される関数
                window.adjustWidth = function (event) {
                    const input = event.target;
                    const width = getWidthOfInput(input);
                    input.style.width = width + 'px';
                    // console.log(input.style.width);
                };

                // ページがロードされた時に入力フォームの幅を初期化
                adjustInputWidth();

                // 入力フォームの幅を調整するイベントリスナーはinputs要素に対してのみ設定
                inputs.forEach((input) => {
                    input.addEventListener('input', function (event) {
                        adjustInputWidth(input);
                    });
                });

                // Livewireメッセージが処理された際に入力フォームの幅を更新
                Livewire.hook('message.processed', () => {
                    adjustInputWidth();
                });
            });
        </script>
    @endpush
@endonce--}}
