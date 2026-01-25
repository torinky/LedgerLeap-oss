@props(['items' => 6, 'cols' => 'sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-5 2xl:grid-cols-7 3xl:grid-cols-8 4xl:grid-cols-10'])

<div {{ $attributes->merge(['class' => "grid $cols gap-4 w-full shimmer"]) }}>
    @foreach (range(1, $items) as $i)
        <div class="h-32 bg-base-200/50 rounded-lg border border-base-300/30 flex flex-col items-center justify-between p-4 px-2">
            <div class="h-10 w-16 bg-base-300 rounded-lg"></div>
            <div class="h-3 bg-base-300 rounded w-full"></div>
            <div class="h-2 bg-base-200 rounded w-2/3"></div>
        </div>
    @endforeach
</div>
