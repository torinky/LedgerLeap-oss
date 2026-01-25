@props(['rows' => 5, 'cols' => 4])

<div {{ $attributes->merge(['class' => 'w-full animate-pulse border border-base-200 rounded-xl overflow-hidden bg-base-100 shadow-sm']) }}>
    <div class="overflow-x-auto">
        <table class="table table-compact w-full">
            <thead>
                <tr>
                    @foreach (range(1, $cols) as $i)
                        <th class="bg-base-200/50 py-4">
                            <div class="h-4 bg-base-300 rounded w-2/3"></div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach (range(1, $rows) as $i)
                    <tr class="border-b border-base-100 last:border-0">
                        @foreach (range(1, $cols) as $j)
                            <td class="py-4">
                                <div class="h-3 bg-base-200 rounded w-full"></div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
