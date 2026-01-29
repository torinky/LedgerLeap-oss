{{-- Skeleton UI (Alpine controlled) --}}
<div x-show="isLoading"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="flex flex-col flex-1 h-full bg-base-100"
     x-cloak>
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
        <div class="absolute inset-0 z-50 flex flex-col items-center justify-center bg-transparent backdrop-blur-[1px]">
            <span class="loading loading-spinner loading-lg text-primary drop-shadow-2xl"></span>
            <span class="mt-4 text-xs font-black tracking-widest text-primary uppercase animate-pulse">
                {{ __('ledger.file_inspector.loading') }}
            </span>
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
            <div class="p-6 space-y-8 flex-1 overflow-hidden">
                <div class="space-y-4">
                    <x-element.skeleton-stats items="1" class="lg:grid-cols-1 md:grid-cols-1" />
                    <x-element.skeleton-table rows="6" cols="2" />
                </div>

                <div class="pt-4 border-t border-base-200">
                    <x-element.skeleton-list items="3" />
                </div>
            </div>
        </div>
    </div>
</div>
