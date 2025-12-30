@php
    $mockData = $this->mockData;
    $mockCreatorName = $this->mockCreatorName;
@endphp

<div x-data="{
    open: @entangle('open'),
    isLoading: @entangle('isLoading'),

    {{-- Toast notification handled via Alpine --}}
    notify(title, type = 'success') {
        const icon = '';
        const css = type === 'success' ? 'alert-success' : 'alert-error';
        this.$dispatch('mary-toast', {
            toast: {
                type,
                title,
                description: '',
                icon,
                css
            }
        });
    }
}" @keydown.escape.window="open = false; $wire.close()"
     @open-file-inspector.window="open = true; isLoading = true; $wire.openInspector($event.detail)"
     class="relative z-50">

    {{-- Drawer overlay --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="open = false; $wire.close()"
         class="fixed inset-0 bg-base-content/20 backdrop-blur-xs" aria-hidden="true"></div>

    {{-- Drawer content --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200 transform" x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed inset-y-0 right-0 w-full md:w-[600px] bg-base-100 shadow-2xl flex flex-col focus:outline-hidden"
         role="dialog" aria-modal="true" aria-labelledby="drawer-title" x-cloak>

        {{-- Skeleton UI (Always rendered, controlled by isLoading) --}}
        @include('livewire.attached-file.file-inspector.skeleton')

        {{-- Actual Content (shown when file is loaded) --}}
        @if ($file)
            <div x-show="!isLoading" class="flex flex-col flex-1 h-full" x-cloak>
                @include('livewire.attached-file.file-inspector.header')

                {{-- Scrollable content area --}}
                <div class="flex-1 overflow-y-auto" style="scrollbar-width: thin;">
                    @include('livewire.attached-file.file-inspector.quick-actions')
                    @include('livewire.attached-file.file-inspector.preview')

                    <div class="flex-1 flex flex-col min-h-0 px-2 pb-2">
                        <x-mary-tabs wire:model="selectedTab"
                                     tabsClass="flex flex-col mt-2"
                                     activeClass="border-b-0"
                                     labelDivClass="tabs tabs-lift ml-3"
                                     class="w-full">

                            <x-mary-tab name="content" label="{{ __('ledger.file_inspector.tabs.content') }}"
                                        icon="o-document-text"
                                        class="shadow-md tab-content bg-base-100 border-base-300 p-6 border-t-0"
                            >
                                @include('livewire.attached-file.file-inspector.tabs.content')
                            </x-mary-tab>

                            <x-mary-tab name="details" label="{{ __('ledger.file_inspector.tabs.details') }}"
                                        icon="o-information-circle"
                                        class="shadow-md tab-content bg-base-100 border-base-300 p-6 border-t-0"
                            >
                                @include('livewire.attached-file.file-inspector.tabs.details')
                            </x-mary-tab>

                            <x-mary-tab name="history" label="{{ __('ledger.file_inspector.tabs.history') }}"
                                        icon="o-clock"
                                        class="shadow-md tab-content bg-base-100 border-base-300 p-6 border-t-0"
                            >
                                @include('livewire.attached-file.file-inspector.tabs.history')
                            </x-mary-tab>

                            <x-mary-tab name="permissions" label="{{ __('ledger.file_inspector.tabs.permissions') }}"
                                        icon="o-shield-check"
                                        class="shadow-md tab-content bg-base-100 border-base-300 p-6 border-t-0"
                            >
                                @include('livewire.attached-file.file-inspector.tabs.permissions')
                            </x-mary-tab>
                        </x-mary-tabs>
                    </div>
                </div>

                @include('livewire.attached-file.file-inspector.footer')
            </div>
        @endif
    </div>
</div>
