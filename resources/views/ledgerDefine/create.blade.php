<x-app-layout title="{{ __('ledger.define.create_title') }}" class="bg-warning/30">
    <div class="container max-w-full px-0 md:px-4 mt-4">
        {{-- Unified header card matching the detail page pattern --}}
        <x-mary-card shadow class="bg-base-100/30 border border-base-300 mb-6">
            <x-slot:title>
                <div class="flex flex-col w-full">
                    <div class="flex items-center gap-3 w-full">
                        <div class="shrink-0 hidden md:block">
                            <x-mary-icon name="o-document-plus" class="text-warning w-15" />
                        </div>
                        <div class="flex flex-col min-w-0 w-full">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 w-full mb-3">
                                <div class="min-w-0">
                                    @if(!empty($breadcrumbs))
                                        <x-ledger.livewire-breadcrumbs
                                            :breadcrumbs="$breadcrumbs"
                                            :isLivewire="false" />
                                    @endif
                                    <h2 class="flex text-xl md:text-2xl font-black tracking-tighter text-base-content truncate mt-2 space-x-4">
                                        <span class="text-base-content/50">{{ __('ledger.define.create_title') }}</span>
                                    </h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-slot:title>
        </x-mary-card>

        <div class="grid grid-cols-1 gap-6 items-start mb-20">
            <x-mary-card separator shadow class="border border-base-300 overflow-hidden" body-class="p-6 md:p-8">
                <x-slot:title>
                    <div class="flex items-center gap-2 text-neutral">
                        <x-mary-icon name="o-cog-6-tooth" />
                        {{ __('ledger.define.basic_setting') }}
                    </div>
                </x-slot:title>
                <livewire:ledger-define.create />
            </x-mary-card>
        </div>
    </div>

</x-app-layout>
