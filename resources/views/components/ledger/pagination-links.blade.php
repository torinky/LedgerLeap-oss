<?php
    $currentPage = $paginator->currentPage();
    $startCount  = $paginator->firstItem() ?? 1;
    $lastPage    = $this->lastPage();
    $position    = $position ?? 'default';
?>

<div class="flex flex-col gap-3 rounded-box border border-base-300/70 bg-base-200/40 px-3 py-2 shadow-sm sm:flex-row sm:items-center sm:justify-between">
    <div aria-label="record count" class="text-sm font-medium text-base-content/75">
        <?php if ($this->totalRecords > 0): ?>
            {{ $startCount }} 〜 {{ min($startCount + $paginator->perPage() - 1, $this->totalRecords) }} / {{ $this->totalRecords }} {{ __('ledger.records') }}
        <?php else: ?>
            0 / 0 {{ __('ledger.records') }}
        <?php endif; ?>
    </div>

    <?php if ($paginator->hasPages()): ?>
        <nav role="navigation" aria-label="pagination" class="w-full sm:w-auto">
            <div class="join flex flex-wrap justify-center sm:justify-end">
                <?php if (! $paginator->onFirstPage()): ?>
                    <button wire:key="topPage-{{ $position }}-page{{ $currentPage }}"
                            wire:click="gotoPage(1,'{{ $paginator->getPageName() }}')" class="join-item btn">
                        <i class="fa-solid fa-backward-step"></i>
                    </button>
                    <button wire:key="backPage-{{ $position }}-page{{ $currentPage }}"
                            wire:click="previousPage('{{ $paginator->getPageName() }}')" class="join-item btn">
                        <i class="fa-solid fa-play fa-flip-horizontal px-3"></i>
                    </button>
                <?php endif; ?>

                <?php
                    $startPage = max($currentPage - 3, 1);
                    $endPage = $currentPage;
                ?>

                <?php for ($i = $startPage; $i < $endPage; $i++): ?>
                    <button wire:key="page-{{ $i }}-{{ $position }}"
                            wire:click="gotoPage({{ $i }},'{{ $paginator->getPageName() }}')"
                            class="join-item btn">{{ $i }}</button>
                <?php endfor; ?>

                <button class="join-item btn btn-active">
                    {{ $currentPage }} / {{ $lastPage }}
                </button>

                <?php if ($paginator->hasMorePages()): ?>
                    <?php
                        $startPage = $currentPage + 1;
                        $endPage = min($currentPage + 4, $lastPage);
                    ?>

                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <button wire:key="page-{{ $i }}-{{ $position }}"
                                wire:click="gotoPage({{ $i }},'{{ $paginator->getPageName() }}')"
                                class="join-item btn">{{ $i }}</button>
                    <?php endfor; ?>

                    <button wire:key="nextPage-{{ $position }}-page{{ $currentPage }}"
                            wire:click="nextPage('{{ $paginator->getPageName() }}')" class="join-item btn">
                        <i class="fa-solid fa-play px-3"></i>
                    </button>
                    <button wire:key="lastPage-{{ $position }}-page{{ $currentPage }}"
                            wire:click="gotoPage({{ $lastPage }},'{{ $paginator->getPageName() }}')"
                            class="join-item btn">
                        <i class="fa-solid fa-forward-step"></i>
                    </button>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>

</div>
