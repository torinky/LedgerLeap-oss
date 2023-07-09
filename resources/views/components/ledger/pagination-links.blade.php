<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination Navigation" class="flex justify-center my-5">
            <div class="join">
                @if (!$paginator->onFirstPage())
                    <button wire:click="gotoPage(1)" class="join-item btn "><i
                            class="fa-sharp fa-solid fa-backward-step"></i></button>
                    <button wire:click="previousPage" class="join-item btn"><i
                            class="fa-solid fa-play fa-flip-horizontal px-3"></i></button>
                @endif
                @if($paginator->currentPage()>2 )
                    @for($i=$paginator->currentPage()-3;$i<$paginator->currentPage();$i++)
                        @if($i>0)
                            <button wire:click="gotoPage({{ $i }})" class="join-item btn ">{{ $i }}</button>
                        @endif
                    @endfor
                @endif
                <button class="join-item btn btn-active">{{$paginator->currentPage()}}
                    / {{ $this->lastPage() }}</button>
                @if ($paginator->hasMorePages())
                    @for($i=($paginator->currentPage()+1);$i<=$this->lastPage() && ($i<$paginator->currentPage()+4);$i++)
                        <button wire:click="gotoPage({{ $i }})" class="join-item btn">{{ $i }}</button>
                    @endfor
                    <button wire:click="nextPage" class="join-item btn"><i
                            class="fa-solid fa-play px-3"></i></button>
                    <button wire:click="gotoPage({{ $this->lastPage() }})" class="join-item btn "><i
                            class="fa-sharp fa-solid fa-forward-step"></i></button>
                @endif
            </div>

        </nav>
    @endif
</div>
