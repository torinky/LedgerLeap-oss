<div class="relative min-h-[400px]">
    <x-element.loading-overlay tier="2" :manual="true" message="{{ __('ledger.loading') }}" />

    <div class="space-y-6 p-4 w-full min-h-[400px]">
        {{-- ツールバースケルトン --}}
        <div class="flex items-center gap-4 p-3 bg-base-200/40 rounded-lg">
            <div class="h-8 bg-base-300 rounded-full w-32 shimmer"></div>
            <div class="h-8 bg-base-300 rounded-full w-32 shimmer"></div>
            <div class="h-5 bg-base-200 rounded w-16 shimmer ml-auto"></div>
        </div>

        {{-- 台帳グループ A スケルトン --}}
        <div class="card bg-base-100 shadow-xl border border-base-200 overflow-hidden">
            <div class="bg-primary/30 px-4 py-3">
                <div class="h-6 bg-base-300 rounded w-40 shimmer"></div>
            </div>
            <div class="card-body pt-0 px-0">
                <x-element.skeleton-table rows="4" cols="5" />
            </div>
        </div>

        {{-- 台帳グループ B スケルトン --}}
        <div class="card bg-base-100 shadow-xl border border-base-200 overflow-hidden">
            <div class="bg-primary/30 px-4 py-3">
                <div class="h-6 bg-base-300 rounded w-48 shimmer"></div>
            </div>
            <div class="card-body pt-0 px-0">
                <x-element.skeleton-table rows="3" cols="5" />
            </div>
        </div>
    </div>
</div>

