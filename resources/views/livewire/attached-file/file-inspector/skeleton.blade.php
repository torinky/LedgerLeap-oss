{{-- Skeleton UI (Alpine controlled) --}}
<div x-show="isLoading" class="flex flex-col flex-1 h-full bg-base-100">
    {{-- Skeleton UI Header --}}
    <div class="navbar bg-base-100 border-b border-base-200 min-h-[4rem] px-4 flex-none animate-pulse">
        <div class="flex-1">
            <div class="flex flex-col gap-2">
                <div class="h-5 bg-base-300 rounded-lg w-1/2"></div>
                <div class="h-3 bg-base-200 rounded-lg w-1/3"></div>
            </div>
        </div>
        <div class="flex-none">
            <div class="w-8 h-8 bg-base-200 rounded-full"></div>
        </div>
    </div>

    {{-- Skeleton Quick Actions --}}
    <div class="bg-base-200/30 border-b border-base-200 p-3 flex gap-4 flex-none animate-pulse">
        <div class="h-9 bg-base-300 rounded-xl flex-1"></div>
        <div class="h-9 bg-base-300 rounded-xl flex-1"></div>
        <div class="h-9 bg-base-300 rounded-xl w-10"></div>
    </div>

    {{-- Skeleton Loading Overlay (Tier 1 style but Alpine controlled) --}}
    <div class="flex-1 flex flex-col min-h-0 relative">
        <div class="absolute inset-0 z-50 flex flex-col items-center justify-center bg-base-100/40 backdrop-blur-sm">
            <div class="flex flex-col items-center p-6 bg-base-100/80 rounded-2xl shadow-xl ring-1 ring-base-content/5">
                <span class="loading loading-spinner loading-lg text-primary"></span>
                <span class="mt-4 text-sm font-semibold tracking-wide text-base-content/80 animate-pulse">
                    {{ __('ledger.file_inspector.loading') }}
                </span>
            </div>
        </div>

        {{-- Skeleton Main Content pulse --}}
        <div class="flex-1 animate-pulse flex flex-col">
            {{-- Skeleton Preview Area --}}
            <div class="bg-base-200 border-b border-base-200 flex-none aspect-video"></div>

            {{-- Skeleton Tabs --}}
            <div class="flex gap-2 px-6 mt-4 border-b border-base-200">
                <div class="h-10 bg-base-200 rounded-t-xl w-24"></div>
                <div class="h-10 bg-base-200 rounded-t-xl w-24"></div>
                <div class="h-10 bg-base-300 rounded-t-xl w-24"></div>
                <div class="h-10 bg-base-200 rounded-t-xl w-24"></div>
            </div>

            {{-- Skeleton Content Body --}}
            <div class="p-6 space-y-6 flex-1 overflow-hidden">
                <div class="h-10 bg-base-200 rounded-xl w-full"></div>
                <div class="space-y-4">
                    <div class="h-4 bg-base-200 rounded-lg w-full"></div>
                    <div class="h-4 bg-base-200 rounded-lg w-11/12"></div>
                    <div class="h-4 bg-base-200 rounded-lg w-4/5"></div>
                    <div class="h-4 bg-base-200 rounded-lg w-full"></div>
                    <div class="h-4 bg-base-200 rounded-lg w-3/4"></div>
                </div>
            </div>
        </div>
    </div>
</div>
