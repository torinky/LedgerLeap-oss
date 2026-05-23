{{-- History Tab --}}
<div class="p-4 space-y-4 min-w-0 max-w-full overflow-x-hidden" x-data="{
    showAllLogs: false,
    showAllActivity: false,
    maxInitialLogs: 3,
    maxInitialActivity: 5
}">
    @if (empty($mockData) && $file && $file->exists)
        {{-- DYNAMIC CONTENT --}}
        <div>
            <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                <i class="fa-solid fa-list-check text-success"></i>
                {{ __('ledger.file_inspector.history.processing_log') }}
            </h3>
            @php
                $sysEvents = $file->system_timeline ?? collect();
            @endphp

            {{-- 未最終化警告 --}}
            @if ($file && !$file->processing_finalized_at)
                <div class="alert alert-warning text-xs shadow-sm border border-warning mb-3">
                    <div class="flex items-start gap-2">
                        <x-mary-icon name="o-clock" class="w-5 h-5 shrink-0 mt-0.5" />
                        <div>
                            <h4 class="font-semibold">{{ __('ledger.file_inspector.history.waiting_finalization') }}</h4>
                            <p class="text-xs text-base-content/70 mt-1">
                                {{ __('ledger.file_inspector.history.finalization_desc') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if ($sysEvents->isEmpty())
                <div class="alert alert-ghost text-xs shadow-sm border border-base-200">
                    <i class="fa-solid fa-circle-info text-info"></i>
                    <span>{{ __('ledger.file_inspector.history.no_system_logs') }}</span>
                </div>
            @else
                {{-- System Logs List --}}
                <div class="relative">
                    <div class="overflow-y-auto transition-all duration-500 ease-in-out" :class="showAllLogs ? 'max-h-[80vh]' : 'max-h-64'"
                        style="scrollbar-width: thin;">
                        <ul class="steps steps-vertical text-sm w-full">
                            @foreach ($sysEvents as $index => $event)
                                <li class="step step-{{ $event['color'] }} min-h-[4rem]"
                                    @if ($index >= 3)
                                        x-show="showAllLogs"
                                        x-cloak
                                        x-transition:enter="transition ease-out duration-500"
                                        x-transition:enter-start="opacity-0 -translate-y-4"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in duration-300"
                                        x-transition:leave-start="opacity-100 translate-y-0"
                                        x-transition:leave-end="opacity-0 -translate-y-4"
                                    @endif>
                                    <div class="text-left ml-3 w-full">
                                        <div class="font-semibold flex items-center gap-2">
                                            <x-mary-icon name="{{ $event['icon'] }}" class="w-4 h-4 opacity-70" />
                                            {{ $event['title'] }}
                                        </div>
                                        <div class="text-xs text-base-content/60">
                                            {{ \Carbon\Carbon::parse($event['timestamp'])->format('Y-m-d H:i:s') }}
                                        </div>
                                        @if ($event['description'])
                                            <div class="text-xs text-base-content/70 mt-0.5">
                                                {{ $event['description'] }}
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    @if ($sysEvents->count() > 3)
                        <div class="absolute bottom-0 left-0 right-0 h-8 bg-linear-to-t from-base-100 to-transparent pointer-events-none"
                            x-show="!showAllLogs"></div>
                    @endif
                </div>

                @if ($sysEvents->count() > 3)
                    <div class="mt-3 text-center">
                        <button @click="showAllLogs = !showAllLogs"
                            class="btn btn-ghost btn-sm gap-2 text-primary hover:text-primary-focus">
                            <template x-if="!showAllLogs">
                                <span>
                                    <i class="fa-solid fa-chevron-down"></i>
                                    {{ __('ledger.show_more') }}
                                </span>
                            </template>
                            <template x-if="showAllLogs">
                                <span>
                                    <i class="fa-solid fa-chevron-up"></i>
                                    {{ __('ledger.show_less') }}
                                </span>
                            </template>
                        </button>
                    </div>
                @endif
            @endif
        </div>

        <div class="divider"></div>

        <div>
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-primary"></i>
                    {{ __('ledger.file_inspector.history.activity') }}
                </h3>
                <span
                    class="text-xs text-base-content/50">{{ __('ledger.file_inspector.history.recent_30days') }}</span>
            </div>
            @php
                $usrEvents = $file->user_timeline ?? collect();
            @endphp
            @if ($usrEvents->isEmpty())
                <div class="alert alert-ghost text-xs shadow-sm border border-base-200">
                    <i class="fa-solid fa-circle-info text-info"></i>
                    <span>{{ __('ledger.file_inspector.history.no_user_activity') }}</span>
                </div>
            @else
                <div class="relative">
                    <div class="space-y-2 overflow-y-auto transition-all duration-500 ease-in-out" :class="showAllActivity ? 'max-h-[80vh]' : 'max-h-64'"
                        style="scrollbar-width: thin;">
                        @foreach ($usrEvents as $index => $activity)
                            <div class="card card-compact bg-base-200 hover:bg-base-300 transition-colors"
                                @if ($index >= 5)
                                    x-show="showAllActivity"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-500"
                                    x-transition:enter-start="opacity-0 translate-y-4"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    x-transition:leave="transition ease-in duration-300"
                                    x-transition:leave-start="opacity-100 translate-y-0"
                                    x-transition:leave-end="opacity-0 translate-y-4"
                                @endif>
                                <div class="card-body">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <x-mary-icon name="o-{{ $activity['icon'] }}"
                                                class="text-{{ $activity['color'] }} w-4 h-4" />
                                            <span class="font-medium text-sm">{{ $activity['title'] }}</span>
                                        </div>
                                        <div class="text-xs text-base-content/60">
                                            {{ \Carbon\Carbon::parse($activity['timestamp'])->format('Y-m-d H:i') }}
                                        </div>
                                    </div>
                                    <div class="text-xs text-base-content/70 mt-1">
                                        {{ $activity['user'] }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if ($usrEvents->count() > 5)
                        <div class="absolute bottom-0 left-0 right-0 h-8 bg-linear-to-t from-base-100 to-transparent pointer-events-none"
                            x-show="!showAllActivity"></div>
                    @endif
                </div>

                @if ($usrEvents->count() > 5)
                    <div class="mt-3 text-center">
                        <button @click="showAllActivity = !showAllActivity"
                            class="btn btn-ghost btn-sm gap-2 text-primary hover:text-primary-focus">
                            <template x-if="!showAllActivity">
                                <span>
                                    <i class="fa-solid fa-chevron-down"></i>
                                    {{ __('ledger.show_more') }}
                                </span>
                            </template>
                            <template x-if="showAllActivity">
                                <span>
                                    <i class="fa-solid fa-chevron-up"></i>
                                    {{ __('ledger.show_less') }}
                                </span>
                            </template>
                        </button>
                    </div>
                @endif
            @endif
        </div>
    @else
        {{-- MOCK NOTICE --}}
        <div class="alert alert-info shadow-sm">
            <i class="fa-solid fa-circle-info"></i>
            <span>{{ __('ledger.file_inspector.history.mock_notice') }}</span>
        </div>
    @endif
</div>
