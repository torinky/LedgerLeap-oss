<x-filament-widgets::widget>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
        @foreach ($groups as $group)
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-lg font-medium mb-4 flex items-center">
                    @svg($group['icon'], 'w-6 h-6 me-3 text-primary-500')
                    <span class="text-gray-900 dark:text-gray-100">{{ $group['title'] }}</span>
                </h3>
                <div class="space-y-3">
                    @foreach ($group['links'] as $link)
                        @php
                            $iconColor = match ($link['color'] ?? 'primary') {
                                'primary' => 'rgba(var(--primary-500), 1)',
                                'secondary' => 'rgba(var(--gray-500), 1)',
                                'danger' => 'rgba(var(--danger-600), 1)',
                                'warning' => '#d97706',
                                'success' => '#16a34a',
                                'info' => '#0ea5e9',
                                default => 'rgba(var(--c-500), 1)',
                            };
                        @endphp
                        <a href="{{ $link['url'] }}"
                           class="flex items-center p-2 rounded-lg transition-all duration-200 bg-gray-100/10 link-hover-color-{{ $link['color'] ?? 'primary' }} hover:bg-amber-100! dark:hover:bg-amber-800/30! hover:shadow-sm"
                        >
                            <span class="me-3 shrink-0" style="color: {{ $iconColor }}">
                                @svg($link['icon'], 'w-5 h-5')
                            </span>
                            <span
                                    class="text-gray-700 dark:text-gray-500">{{ $link['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>