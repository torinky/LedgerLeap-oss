<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('My Portal') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ __('Select Tenant') }}</h3>
                    @if($tenants->isNotEmpty())
                        <ul class="space-y-4">
                            @foreach($tenants as $tenant)
                                <li>
                                    <a href="{{ route('my-portal', ['tenant' => $tenant->id]) }}" class="block p-4 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                        <div class="font-semibold text-lg">{{ $tenant->name ?? $tenant->id }}</div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p>{{ __('You do not belong to any tenants.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
