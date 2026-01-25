@props(['items' => 1])

<div {{ $attributes->merge(['class' => 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 w-full animate-pulse']) }}>
    @foreach (range(1, $items) as $i)
        <div class="bg-base-100 p-6 rounded-2xl border border-base-200 shadow-sm flex items-center justify-between">
            <div class="space-y-3 flex-1">
                <div class="h-4 bg-base-300 rounded-lg w-1/3"></div>
                <div class="h-3 bg-base-200 rounded-lg w-2/3"></div>
            </div>
            <div class="h-12 w-12 bg-base-300 rounded-2xl ml-4"></div>
        </div>
    @endforeach
</div>
