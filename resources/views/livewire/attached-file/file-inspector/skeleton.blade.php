{{-- Skeleton UI (Alpine controlled) --}}
<div x-show="isLoading" class="flex flex-col flex-1 h-full">
    {{-- Skeleton UI Header --}}
    <div class="navbar bg-base-200 border-b border-base-300 min-h-[4rem] px-4 flex-none animate-pulse">
        <div class="flex-1">
            <div class="flex flex-col gap-1">
                <div class="h-5 bg-base-300 rounded w-3/4"></div>
                <div class="h-3 bg-base-300 rounded w-1/2 mt-1"></div>
            </div>
        </div>
        <div class="flex-none">
            <div class="w-8 h-8 bg-base-300 rounded-full"></div>
        </div>
    </div>

    {{-- Skeleton Quick Actions --}}
    <div class="bg-base-100 border-b border-base-300 p-3 flex gap-2 flex-none animate-pulse">
        <div class="join flex-1">
            <div class="h-8 bg-base-300 rounded-l-lg flex-1"></div>
            <div class="h-8 bg-base-300 rounded-r-lg w-10 border-l border-base-100"></div>
        </div>
        <div class="join flex-1">
            <div class="h-8 bg-base-300 rounded-l-lg flex-1"></div>
            <div class="h-8 bg-base-300 rounded-r-lg w-10 border-l border-base-100"></div>
        </div>
    </div>

    {{-- Skeleton Main Content with Spinner --}}
    <div class="flex-1 flex flex-col min-h-0 animate-pulse relative">
        {{-- Central Spinner --}}
        <div class="absolute inset-0 flex items-center justify-center bg-base-100/30 z-10">
            <div class="flex flex-col items-center gap-2">
                <span class="loading loading-spinner loading-lg text-primary"></span>
                <span class="text-xs font-bold text-base-content/60">{{ __('ledger.file_inspector.loading') }}</span>
            </div>
        </div>

        {{-- Skeleton Preview Area --}}
        <div class="bg-base-200/50 border-b border-base-300 flex-none aspect-video bg-base-300"></div>

        {{-- Skeleton Tabs --}}
        <div class="flex gap-1 px-4 mt-2 border-b border-base-300">
            <div class="h-10 bg-base-200 rounded-t-lg w-20"></div>
            <div class="h-10 bg-base-200 rounded-t-lg w-20"></div>
            <div class="h-10 bg-base-200 rounded-t-lg w-20"></div>
            <div class="h-10 bg-base-200 rounded-t-lg w-20"></div>
        </div>

        {{-- Skeleton Content Body --}}
        <div class="p-4 space-y-4 flex-1 overflow-hidden">
            <div class="h-8 bg-base-200 rounded w-full"></div>
            <div class="space-y-2">
                <div class="h-4 bg-base-200 rounded w-full"></div>
                <div class="h-4 bg-base-200 rounded w-5/6"></div>
                <div class="h-4 bg-base-200 rounded w-4/6"></div>
                <div class="h-4 bg-base-200 rounded w-full"></div>
                <div class="h-4 bg-base-200 rounded w-3/4"></div>
            </div>
        </div>
    </div>
</div>
