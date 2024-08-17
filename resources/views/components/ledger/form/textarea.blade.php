<!-- textarea.blade.php -->
<!--
    テキストエリアフォームの幅と高さを動的に調整するためのLivewireコンポーネントのビューファイルです。
    $columnDefine->idに基づいて動的なフォームを作成します。
    フォームの入力内容はcontent[$columnDefine->id]というLivewireのプロパティで管理されます。
-->
<label for="content[{{$columnDefine->id}}]"
       class="form-control">
    <div class="label">
            <span class="label-text">
                @if($columnDefine->required)
                    <i class="fas fa-check-circle text-accent"></i>
                @endif
                {{$columnDefine->name}}

            </span>

    </div>
<textarea
    wire:model.blur="content.{{ $columnDefine->id }}"
    id="content[{{$columnDefine->id}}]"
    class="adjustWidth adjustHeight input input-bordered @if($columnDefine->required) input-accent @endif"
    name="content[{{$columnDefine->id}}]"
>
{{ $this->content[$columnDefine->id] ?? '' }}
</textarea>
    @error('content.' . $columnDefine->id)
    <label class="label">
        <span class="label-text-alt text-red-500 text-xs space-x-2">
            <i class="fas fa-times-circle"></i>
            <span class="error">{{ $message }}</span>
        </span>
    </label>
    @enderror
</label>

@once
    @push('scripts')
        <script>
            // ドキュメントがロードされたときに実行する関数
            document.addEventListener('livewire:initialized', function () {
                // 幅と高さを調整するためのテキストエリア要素を取得
                const textareas = document.querySelectorAll('textarea.adjustWidth.adjustHeight');

                // テキストエリアの幅と高さを調整する関数
                function adjustInputSize(input) {
                    // テキストエリアの内容をクローンして幅を計算する
                    const clone = input.cloneNode();
                    clone.style.overflowY = 'hidden'; // クローンでのスクロールバー表示を無効に
                    clone.style.height = 'auto'; // クローンの高さを自動調整
                    clone.style.width = 'auto'; // クローンの幅を自動調整
                    clone.style.whiteSpace = 'pre'; // クローンで改行を考慮

                    // クローンを一時的に画面外に配置
                    clone.style.position = 'absolute';
                    clone.style.left = '-9999px';
                    clone.style.top = '-9999px';
                    document.body.appendChild(clone);

                    // クローンの幅と高さを取得
                    const width = clone.scrollWidth + 25; // テキストの幅に余裕を持たせるために25pxを加えます
                    const height = clone.scrollHeight;

                    // クローンを削除
                    document.body.removeChild(clone);

                    // テキストエリアの幅と高さを設定
                    input.style.width = width + 'px';
                    input.style.height = height + 'px';
                }

                // 各テキストエリアに対してイベントリスナーを設定
                textareas.forEach((textarea) => {
                    textarea.addEventListener('input', function (event) {
                        adjustInputSize(textarea);
                    });
                });

                // ページがロードされた際にすべてのテキストエリアの幅と高さを調整
                function adjustAllInputSizes() {
                    textareas.forEach((textarea) => {
                        adjustInputSize(textarea);
                    });
                }

                // ページがロードされた際にすべてのテキストエリアの幅と高さを初期化
                adjustAllInputSizes();

                // Livewireメッセージが処理された際にすべてのテキストエリアの幅と高さを更新
                Livewire.hook('morph.updated', ({el, component}) => {
                    adjustAllInputSizes();
                })
            });
        </script>
    @endpush
@endonce
