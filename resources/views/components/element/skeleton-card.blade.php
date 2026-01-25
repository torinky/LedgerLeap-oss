<div {{ $attributes->merge(['class' => 'card bg-base-100 shadow-sm shimmer overflow-hidden rounded-xl border border-base-200']) }}>
    <div class="card-body p-6 space-y-6">
        <div class="flex items-center space-x-4">
            <div class="h-14 w-14 rounded-2xl bg-base-300"></div>
            <div class="flex-1 space-y-3 py-1">
                <div class="h-4 bg-base-300 rounded-lg w-3/4"></div>
                <div class="h-3 bg-base-300 rounded-lg w-1/2"></div>
            </div>
        </div>
        <div class="space-y-4">
            <div class="h-2.5 bg-base-300 rounded-full w-full"></div>
            <div class="h-2.5 bg-base-300 rounded-full w-5/6"></div>
            <div class="h-2.5 bg-base-300 rounded-full w-2/3"></div>
        </div>
        <div class="pt-4 flex justify-between items-center">
            <div class="h-8 w-24 bg-base-300 rounded-lg"></div>
            <div class="h-6 w-16 bg-base-300 rounded-lg"></div>
        </div>
    </div>
</div>
