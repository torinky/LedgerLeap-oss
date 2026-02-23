@php
    $currentPage = $paginator->currentPage();
    $lastPage    = $paginator->lastPage();
    $perPage     = $paginator->perPage();
    $total       = $paginator->total();
    $from        = $paginator->firstItem() ?? 0;
    $to          = $paginator->lastItem()  ?? 0;
    $pageName    = $paginator->getPageName();
    $position    = $position ?? 'default';
@endphp

<div class="grid justify-items-center">
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="pagination">
            <div class="join">
                @if (!$paginator->onFirstPage())
                    <button wire:key="topPage-{{ $position }}-page{{ $currentPage }}"
                            wire:click="gotoPage(1, '{{ $pageName }}')"
                            class="join-item btn">
                        <i class="fa-solid fa-backward-step"></i>
                    </button>
                    <button wire:key="backPage-{{ $position }}-page{{ $currentPage }}"
                            wire:click="previousPage('{{ $pageName }}')"
                            class="join-item btn">
                        <i class="fa-solid fa-play fa-flip-horizontal px-3"></i>
                    </button>
                @endif

                @php
                    $startPage = max($currentPage - 3, 1);
                    $endPage   = $currentPage;
                @endphp

                @for ($i = $startPage; $i < $endPage; $i++)
                    <button wire:key="page-{{ $i }}-{{ $position }}"
                            wire:click="gotoPage({{ $i }}, '{{ $pageName }}')"
                            class="join-item btn">{{ $i }}</button>
                @endfor

                <button class="join-item btn btn-active">
                    {{ $currentPage }} / {{ $lastPage }}
                </button>

                @if ($paginator->hasMorePages())
                    @php
                        $startPage = $currentPage + 1;
                        $endPage   = min($currentPage + 4, $lastPage);
                    @endphp

                    @for ($i = $startPage; $i <= $endPage; $i++)
                        <button wire:key="page-{{ $i }}-{{ $position }}"
                                wire:click="gotoPage({{ $i }}, '{{ $pageName }}')"
                                class="join-item btn">{{ $i }}</button>
                    @endfor

                    <button wire:key="nextPage-{{ $position }}-page{{ $currentPage }}"
                            wire:click="nextPage('{{ $pageName }}')"
                            class="join-item btn">
                        <i class="fa-solid fa-play px-3"></i>
                    </button>
                    <button wire:key="lastPage-{{ $position }}-page{{ $currentPage }}"
                            wire:click="gotoPage({{ $lastPage }}, '{{ $pageName }}')"
                            class="join-item btn">
                        <i class="fa-solid fa-forward-step"></i>
                    </button>
                @endif
            </div>
        </nav>
    @endif

    <div role="record count" aria-label="record count">
        {{ $from }} 〜 {{ $to }} / {{ $total }} {{ __('ledger.records') }}
    </div>
</div>

