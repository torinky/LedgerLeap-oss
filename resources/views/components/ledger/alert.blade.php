@props([
    'message'=>'',
    'refreshParentWindow'=>false,
    'type'=>'info',
    'icon'=>'fa-circle-check',
    ])
<div class="flex min-h-[6rem] min-w-[18rem] flex-wrap items-center justify-center gap-2 overflow-x-hidden ">
    <div class="alert alert-{{$type}} shadow-lg max-w-4xl ">
        <i class="fas {{$icon}} text-lg"></i>
            <span>{{ $message }}</span>
    </div>
</div>
@if($refreshParentWindow)
    <script type="text/javascript">
        window.onunload = function () {
            reload_parent_window();
        }

        //親ウィンドウの更新処理
        function reload_parent_window() {
            //自身を開いたウィンドウが存在する場合
            if ((window.opener && !window.opener.closed)) {
                window.opener.location.reload();
                //自身がiframeの子である場合
            } else if (window != window.parent) {
                window.parent.location.reload();
            }
        }
    </script>
@endif
