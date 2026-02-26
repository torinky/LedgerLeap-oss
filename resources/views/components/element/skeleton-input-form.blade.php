@props(['rows' => 3])

<div {{ $attributes->merge(['class' => 'w-full space-y-8 shimmer bg-base-100 p-8 rounded-2xl border border-base-200 shadow-sm']) }}>
    @foreach (range(1, $rows) as $i)
        <div class="space-y-4">
            <div class="h-4 bg-base-300 rounded w-1/4"></div>
            <div class="h-12 bg-base-200 rounded-xl w-full"></div>
            <div class="h-3 bg-base-100 rounded w-1/3 border border-base-200"></div>
        </div>
    @endforeach

    <div class="pt-6 flex justify-end gap-4 border-t border-base-200">
        <div class="h-10 bg-base-200 rounded-lg w-24"></div>
        <div class="h-10 bg-base-300 rounded-lg w-32"></div>
    </div>
</div>
