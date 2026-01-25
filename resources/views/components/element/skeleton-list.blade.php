@props(['items' => 5])

<div {{ $attributes->merge(['class' => 'w-full space-y-4 shimmer']) }}>
    @foreach (range(1, $items) as $i)
        <div class="flex items-center space-x-4 p-4 bg-base-100 rounded-xl border border-base-200 shadow-sm">
            <div class="h-12 w-12 rounded-lg bg-base-300"></div>
            <div class="flex-1 space-y-3">
                <div class="h-4 bg-base-300 rounded-lg w-1/2"></div>
                <div class="h-3 bg-base-200 rounded-lg w-1/4"></div>
            </div>
            <div class="h-8 w-8 bg-base-200 rounded-full"></div>
        </div>
    @endforeach
</div>
