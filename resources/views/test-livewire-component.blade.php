<x-app-layout title="Livewire Component Test"> {{-- レイアウトを適用し、タイトルを設定 --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Livewire Component Test
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                {{-- ここで動的にコンポーネントをレンダリングする --}}
                @livewire($componentName, $componentProps)
            </div>
        </div>
    </div>
</x-app-layout>