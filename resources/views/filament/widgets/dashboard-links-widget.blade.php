<x-filament-widgets::widget>
    @once
        @vite('resources/sass/filamentCustom.scss')
    @endonce

    <x-filament::grid :default="1" :sm="2" :md="3" :lg="4" :xl="4" class="gap-4">
        @foreach ($groups as $group)
            <x-filament::card>
                <h3 class="text-lg font-medium mb-4 flex items-center">
                    @svg($group['icon'], 'w-6 h-6 me-3 text-custom-500')
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
                           class="flex items-center p-2 rounded-lg transition-colors duration-200 bg-gray-100/10 link-hover-color-{{ $link['color'] ?? 'primary' }}"
                        >
                            <span class="me-3 shrink-0" style="color: {{ $iconColor }}">
                                @svg($link['icon'], 'w-5 h-5')
                            </span>
                            <span
                                    class="text-gray-700 dark:text-gray-500">{{ $link['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            </x-filament::card>
        @endforeach
    </x-filament::grid>
</x-filament-widgets::widget>