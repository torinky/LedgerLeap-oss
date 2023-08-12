<!-- textarea.blade.php -->
<!--
    テキストエリアフォームの幅と高さを動的に調整するためのLivewireコンポーネントのビューファイルです。
    $columnDefine->idに基づいて動的なフォームを作成します。
    フォームの入力内容はcontent[$columnDefine->id]というLivewireのプロパティで管理されます。
-->
<textarea
    wire:model.lazy="content.{{ $columnDefine->id }}"
    id="content[{{$columnDefine->id}}]"
    class="adjustWidth adjustHeight input input-bordered"
    name="content[{{$columnDefine->id}}]"
>
{{ $this->content[$columnDefine->id] ?? '' }}
</textarea>
@error('content.' . $columnDefine->id) <span class="error">{{ $message }}</span> @enderror

@once
    @push('scripts')
        <script>
            // ドキュメントがロードされたときに実行する関数
            document.addEventListener('livewire:load', function () {
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
                Livewire.hook('message.processed', () => {
                    adjustAllInputSizes();
                });
            });
        </script>
    @endpush
@endonce
