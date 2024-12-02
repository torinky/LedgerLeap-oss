@props([
    'class'=>'adjustHeight input-primary w-full block',
    'icon'=>'o-chat-bubble-oval-left-ellipsis',
    'columnDefine'=>[]
    ])
@php
    if($columnDefine->required){
        $icon='c-check-circle';
        $class="adjustHeight input-accent w-full block";
    }
@endphp


{{--
<div class="flex items-center space-x-2 w-full">

    @if($columnDefine->required)
        <i class="fas fa-check-circle text-neutral/50"></i>
    @endif
    <div class="flex-1">
--}}
        <x-mary-textarea
            label="{{$columnDefine->name}}"
            id="content[{{$columnDefine->id}}]"
            name="content[{{$columnDefine->id}}]"
            icon="{{$icon}}"
            wire:model.blur="content.{{$columnDefine->id}}"
            placeholder="{{$columnDefine->name}}"
            {{--        hint="Max 1000 chars"--}}
            rows="3"
            :required="$columnDefine->required"
            :class="$class"
            :hint="$columnDefine->hint"
        />
{{--
    </div>
</div>
--}}


@once
    @push('scripts')
        <script>
            // ドキュメントがロードされたときに実行する関数
            document.addEventListener('livewire:initialized', function () {
                // 幅と高さを調整するためのテキストエリア要素を取得
                const textareas = document.querySelectorAll('textarea.adjustHeight');

                // テキストエリアの幅と高さを調整する関数
                function adjustInputSize(input) {
                    // テキストエリアの内容をクローンして幅を計算する
                    const clone = input.cloneNode();
                    clone.style.overflowY = 'hidden'; // クローンでのスクロールバー表示を無効に
                    clone.style.height = 'auto'; // クローンの高さを自動調整
                    clone.style.whiteSpace = 'pre'; // クローンで改行を考慮

                    // クローンを一時的に画面外に配置
                    clone.style.position = 'absolute';
                    clone.style.left = '-9999px';
                    clone.style.top = '-9999px';
                    document.body.appendChild(clone);

                    // 個別にadjustWidthが指定されている場合は幅を調整
                    if (input.classList.contains('adjustWidth')) {
                        const width = clone.scrollWidth; // テキストの幅に余裕を持たせるために25pxを加えます
                        input.style.width = width + 'px';
                    }
                    // クローンの高さを取得
                    const height = clone.scrollHeight;

                    // クローンを削除
                    document.body.removeChild(clone);

                    // テキストエリアの高さを設定
                    input.style.height = height + 'px';

                }

                // 各テキストエリアに対してイベントリスナーを設定
                textareas.forEach((textarea) => {
                    textarea.addEventListener('input', function (event) {
                        adjustInputSize(textarea);
                    });
                });

                // ページがロードされた際にすべてのテキストエリアの高さを調整
                function adjustAllInputHeights() {
                    textareas.forEach((textarea) => {
                        adjustInputSize(textarea);
                    });
                }

                // ページがロードされた際にすべてのテキストエリアの高さを初期化
                adjustAllInputHeights();

                // Livewireメッセージが処理された際にすべてのテキストエリアの高さを更新
                Livewire.hook('morph.updated', ({el, component}) => {
                    adjustAllInputHeights();
                })
            });
        </script>
    @endpush
@endonce
