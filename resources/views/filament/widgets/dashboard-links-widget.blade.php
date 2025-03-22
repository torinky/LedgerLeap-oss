<x-filament-widgets::widget>
    <x-filament::grid :default="1" :sm="2" :md="3" :lg="4" :xl="4" class="gap-4">
        @foreach ($groups as $group)
            <x-filament::card>
                <h3 class="text-lg font-medium mb-4 flex items-center">
                    @svg($group['icon'], 'w-6 h-6 me-3 text-custom-500')
                    <span class="text-gray-900 dark:text-gray-100">{{ $group['title'] }}</span>
                </h3>
                <div class="space-y-3">
                    @foreach ($group['links'] as $link)
                        <a href="{{ $link['url'] }}"
                           class="flex items-center p-2 rounded-lg transition-colors duration-200 bg-gray-100/10 hover:bg-gray-100 dark:hover:bg-gray-700 ">
                            @svg($link['icon'], 'w-5 h-5 me-3 text-' . $link['color'] . '-500')
                            <span
                                class="text-sm font-medium text-gray-700 dark:text-gray-500">{{ $link['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            </x-filament::card>
        @endforeach
    </x-filament::grid>
</x-filament-widgets::widget>
