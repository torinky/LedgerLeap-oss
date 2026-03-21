@php
    $mockData = $this->mockData;
    $mockCreatorName = $this->mockCreatorName;
    $performanceEnabled = config('ledgerleap.performance.enabled', false);
    $drawerOpenMetricEnabled = config('ledgerleap.performance.metrics.drawer_open', true);
    $tabSwitchMetricEnabled = config('ledgerleap.performance.metrics.tab_switch', true);
@endphp

<div x-data="{
    open: @entangle('open'),
    isLoading: @entangle('isLoading'),
    @if($performanceEnabled)
    performanceMetrics: {
        drawerOpenStart: null,
        drawerOpenEnd: null,
        tabSwitchTimes: []
    },
    @endif

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
    }@if($performanceEnabled && $drawerOpenMetricEnabled),

    {{-- Performance measurement: Drawer open --}}
    measureDrawerOpen() {
        this.performanceMetrics.drawerOpenStart = performance.now();
        console.log('[FileInspector Performance] Drawer open started at:', this.performanceMetrics.drawerOpenStart);
    },
    measureDrawerOpened() {
        if (this.performanceMetrics.drawerOpenStart) {
            this.performanceMetrics.drawerOpenEnd = performance.now();
            const duration = this.performanceMetrics.drawerOpenEnd - this.performanceMetrics.drawerOpenStart;
            console.log('[FileInspector Performance] Drawer open duration:', duration.toFixed(2), 'ms');
            // リセット
            this.performanceMetrics.drawerOpenStart = null;
        }
    }@endif
    @if($performanceEnabled && $tabSwitchMetricEnabled),
    {{-- Performance measurement: Tab switch --}}
    measureTabSwitch(fromTab, toTab) {
        const start = performance.now();
        requestAnimationFrame(() => {
            const duration = performance.now() - start;
            console.log('[FileInspector Performance] Tab switch:', fromTab, '->', toTab, duration.toFixed(2), 'ms');
            this.performanceMetrics.tabSwitchTimes.push({ from: fromTab, to: toTab, duration });
        });
    }
    @endif

}"
     @if($performanceEnabled && $drawerOpenMetricEnabled)
     x-init="$watch('isLoading', (value) => { if (!value && performanceMetrics.drawerOpenStart) { measureDrawerOpened(); } })"
     @endif
     @keydown.escape.window="open = false; $wire.close()"
     @open-file-inspector.window="
        open = true;
        isLoading = true;
        @if($performanceEnabled && $drawerOpenMetricEnabled)measureDrawerOpen();@endif
        $wire.openInspector($event.detail)
     "
     @open-in-new-tab.window="window.open($event.detail.url, '_blank')"
     class="relative z-50">

    {{-- Drawer overlay --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="open = false; $wire.close()"
         class="fixed inset-0 bg-base-content/20 backdrop-blur-xs z-55" aria-hidden="true"></div>

    {{-- Drawer content --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200 transform" x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed inset-y-0 right-0 w-full md:w-[600px] bg-base-100 shadow-2xl flex flex-col focus:outline-hidden z-60"
         role="dialog" aria-modal="true" aria-labelledby="drawer-title" x-cloak>

        {{-- Skeleton UI (Always rendered, controlled by isLoading) --}}
        @include('livewire.attached-file.file-inspector.skeleton')

        {{-- Actual Content (shown when file is loaded) --}}
        @if ($file)
            <div x-show="!isLoading"
                 x-transition:enter="transition ease-out duration-200 delay-100"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="flex flex-col flex-1 h-full"
                 x-cloak>
                @include('livewire.attached-file.file-inspector.header')

                {{-- Scrollable content area --}}
                <div class="flex-1 overflow-y-auto relative" style="scrollbar-width: thin;">

                    @include('livewire.attached-file.file-inspector.quick-actions')
                    @include('livewire.attached-file.file-inspector.preview')

                    <div class="flex-1 flex flex-col min-h-0 px-2 pb-2 relative min-h-[400px]"
                        @if($performanceEnabled && $tabSwitchMetricEnabled)
                            x-data="{
                                previousTab: 'content',
                                init() {
                                    this.$watch('$wire.selectedTab', (value, oldValue) => {
                                        if (oldValue && value !== oldValue) {
                                            // タブ切り替え時間を測定
                                            const start = performance.now();
                                            requestAnimationFrame(() => {
                                                const duration = performance.now() - start;
                                                console.log('[FileInspector Performance] Tab switch:', oldValue, '->', value, duration.toFixed(2), 'ms');
                                            });
                                            this.previousTab = value;
                                        }
                                    });
                                }
                            }"
                        @endif>
                        {{-- Tier 2: Tab switching loading overlay REMOVED per user request (Reference: Phase 6 remediation) --}}
                        <x-mary-tabs wire:model="selectedTab"
                                     tabsClass="flex flex-col mt-2"
                                     activeClass="border-b-0"
                                     labelDivClass="tabs tabs-lift ml-3"
                                     class="w-full">

                            <x-mary-tab name="content" label="{{ __('ledger.file_inspector.tabs.content') }}"
                                        icon="o-document-text"
                                        class="shadow-md tab-content bg-base-100 border-base-300 p-6 border-t-0"
                            >
                                @if ($this->isTabLoaded('content'))
                                    @include('livewire.attached-file.file-inspector.tabs.content')
                                @endif
                            </x-mary-tab>

                            <x-mary-tab name="details" label="{{ __('ledger.file_inspector.tabs.details') }}"
                                        icon="o-information-circle"
                                        class="shadow-md tab-content bg-base-100 border-base-300 p-6 border-t-0"
                            >
                                @if ($this->isTabLoaded('details'))
                                    @include('livewire.attached-file.file-inspector.tabs.details')
                                @endif
                            </x-mary-tab>

                            <x-mary-tab name="history" label="{{ __('ledger.file_inspector.tabs.history') }}"
                                        icon="o-clock"
                                        class="shadow-md tab-content bg-base-100 border-base-300 p-6 border-t-0"
                            >
                                @if ($this->isTabLoaded('history'))
                                    @include('livewire.attached-file.file-inspector.tabs.history')
                                @endif
                            </x-mary-tab>

                            <x-mary-tab name="permissions" label="{{ __('ledger.file_inspector.tabs.permissions') }}"
                                        icon="o-shield-check"
                                        class="shadow-md tab-content bg-base-100 border-base-300 p-6 border-t-0"
                            >
                                @if ($this->isTabLoaded('permissions'))
                                    @include('livewire.attached-file.file-inspector.tabs.permissions')
                                @endif
                            </x-mary-tab>
                        </x-mary-tabs>
                    </div>
                </div>

                @include('livewire.attached-file.file-inspector.footer')
            </div>
        @endif
    </div>
</div>
