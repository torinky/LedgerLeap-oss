<div>
    {{-- ページネーションがある場合にのみ表示 --}}
    @if ($paginator->hasPages())
        {{-- ページネーションナビゲーション --}}
        <nav role="navigation" aria-label="ページネーション" class="flex justify-center my-5">
            <div class="join">
                {{-- 現在のページが最初のページでない場合に表示 --}}
                @if (!$paginator->onFirstPage())
                    {{-- 最初のページに移動するボタン --}}
                    <button wire:click="gotoPage({{1}})" class="join-item btn"><i
                            class="fa-sharp fa-solid fa-backward-step"></i></button>
                    {{-- 前のページに移動するボタン --}}
                    <button wire:click="previousPage" class="join-item btn"><i
                            class="fa-solid fa-play fa-flip-horizontal px-3"></i></button>
                @endif

                {{-- 現在のページが2ページ目以降の場合に表示 --}}
                @if($paginator->currentPage() > 1)
                    {{-- 現在のページより前の3つのページボタンを表示 --}}
                    @for($i = $paginator->currentPage() - 3; $i < $paginator->currentPage(); $i++)
                        {{-- 1より大きいページ番号のみ表示 --}}
                        @if($i > 0)
                            <button wire:click="gotoPage({{ $i }})"
                                    class="join-item btn">{{ $i }}</button>
                        @endif
                    @endfor
                @endif

                {{-- 現在のページを表示 (アクティブなスタイルを適用) --}}
                <button class="join-item btn btn-active">
                    {{ $paginator->currentPage() }} / {{ $this->lastPage() }}
                </button>

                {{-- 次のページが存在する場合に表示 --}}
                @if ($paginator->hasMorePages())
                    {{-- 現在のページより後の3つのページボタンを表示 --}}
                    @for($i = ($paginator->currentPage() + 1); $i <= $this->lastPage() && ($i < $paginator->currentPage() + 4); $i++)
                        <button wire:click="gotoPage({{ $i }})"
                                class="join-item btn">{{ $i }}</button>
                    @endfor
                    {{-- 次のページに移動するボタン --}}
                    <button wire:click="nextPage()" class="join-item btn"><i
                            class="fa-solid fa-play px-3"></i></button>
                    {{-- 最後のページに移動するボタン --}}
                    <button wire:click="gotoPage({{ $this->lastPage() }})" class="join-item btn "><i
                            class="fa-sharp fa-solid fa-forward-step"></i></button>
                @endif
            </div>
        </nav>
    @endif
</div>
