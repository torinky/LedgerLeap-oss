@props(['items' => 6, 'cols' => 'grid-cols-2 md:grid-cols-4 lg:grid-cols-6'])

<div {{ $attributes->merge(['class' => "grid $cols gap-4 w-full animate-pulse"]) }}>
    @foreach (range(1, $items) as $i)
        <div class="h-28 bg-base-200/50 rounded-2xl border border-base-300/30 flex flex-col items-center justify-center p-4 space-y-3">
            <div class="h-10 w-10 bg-base-300 rounded-xl"></div>
            <div class="h-3 bg-base-300 rounded w-2/3"></div>
        </div>
    @endforeach
</div>
