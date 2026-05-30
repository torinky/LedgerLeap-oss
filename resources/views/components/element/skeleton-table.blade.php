@props(['rows' => 5, 'cols' => 10])

<div {{ $attributes->merge(['class' => 'w-full shimmer overflow-hidden bg-base-100 rounded-b-box']) }}>
    <div class="overflow-x-auto w-full">
        <table class="table table-zebra table-compact table-auto w-full border-separate border-spacing-0">
            <thead>
                <tr>
                    @foreach (range(1, $cols) as $i)
                        <th class="bg-base-200/50 py-4 px-2 border-b border-base-300">
                            <div class="h-4 bg-base-content/10 rounded w-full mx-auto"></div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach (range(1, $rows) as $i)
                    <tr class="hover:bg-base-200/5">
                        @foreach (range(1, $cols) as $j)
                            <td class="py-4 px-2 border-b border-base-200/30">
                                <div class="h-3 bg-base-content/5 rounded w-full"></div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
