<div class="space-y-6">
    {{-- Version Info Nudge Skeleton (if showChanges might be toggleable, we can show a placeholder for it) --}}
    <div class="h-12 bg-base-200/50 rounded-lg border border-base-300 shimmer mb-4"></div>

    {{-- Main content groups skeleton --}}
    <div class="space-y-8">
        @foreach(range(1, 4) as $groupCount)
            <div class="collapse collapse-arrow bg-base-100 border border-base-200 shadow-sm overflow-hidden">
                <input type="checkbox" disabled {{ $groupCount == 1 ? 'checked' : '' }} />
                <div class="collapse-title">
                    <div class="h-5 bg-base-300 rounded w-48 shimmer"></div>
                </div>
                <div class="collapse-content">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 pt-4">
                        @foreach(range(1, 6) as $fieldCount)
                            <div class="space-y-2">
                                <div class="h-4 bg-base-200 rounded w-24 shimmer"></div>
                                <div class="h-10 bg-base-100 border border-base-300 rounded-lg shimmer"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
