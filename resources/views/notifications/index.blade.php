@props([
    'initialNotificationCount' => 0,
    'initialTaskCount' => 0,
    'activeTab' => 'notifications',
])

<x-app-layout :title="__('ledger.notifications')">
    <x-slot name="header">
        <x-mary-header :title="__('ledger.notifications')" icon="o-bell" separator>
            <x-slot:actions>
                <div class="flex flex-wrap items-center gap-2 ">
                    <span class="badge badge-secondary gap-2">
                        <x-mary-icon name="o-bell" class="" />
                        <span>{{ __('ledger.notifications') }}</span>
                        <span class="badge badge-secondary ">{{ $initialNotificationCount }}</span>
                    </span>
                    <span class="badge badge-info gap-2">
                        <x-mary-icon name="o-briefcase" class="" />
                        <span>{{ __('ledger.workflow.pending_tasks') }}</span>
                        <span class="badge badge-info ">{{ $initialTaskCount }}</span>
                    </span>
                    <span class="badge badge-outline gap-2">
                        <x-mary-icon name="o-clock" class="" />
                        <span>{{ __('ledger.activity.title') }}</span>
                    </span>
                </div>
            </x-slot:actions>
        </x-mary-header>
    </x-slot>

    <div
        x-data="{
            notificationCount: {{ (int) $initialNotificationCount }},
            activityCount: 0,
            taskCount: {{ (int) $initialTaskCount }},
            updateCount(event) {
                if (event.detail.tab === 'notifications') {
                    this.notificationCount = event.detail.count;
                } else if (event.detail.tab === 'activity') {
                    this.activityCount = event.detail.count;
                } else if (event.detail.tab === 'tasks') {
                    this.taskCount = event.detail.count;
                }
            },
        }"
        @update-tab-count.window="updateCount($event)"
        class="py-4 sm:py-6"
    >
        <div class="mx-auto w-full max-w-screen-2xl space-y-4 sm:px-6 lg:px-8 2xl:px-10">
            <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm">
                <x-mary-tabs
                    :selected="$activeTab"
                    active-class="border-b-0"
                    label-class="text-base sm:text-lg font-semibold"
                    label-div-class="tabs tabs-lifted tabs-xl px-3 pt-3"
                    tabs-class="w-full"
                >
                    <x-mary-tab name="tasks" icon="o-briefcase" class="tab-content bg-base-100 border-base-300 p-4 sm:p-6">
                        <x-slot:label>
                            <span class="flex items-center gap-2 text-base sm:text-lg font-semibold">
                                <span>{{ __('ledger.workflow.pending_tasks') }}</span>
                                <span class="badge badge-info badge-sm" x-show="taskCount > 0" x-text="taskCount"></span>
                            </span>
                        </x-slot:label>

                        <div class="space-y-6">
                            <div class="rounded-2xl border border-base-200 bg-base-200/40 p-4 sm:p-5">
                                <livewire:workflow.pending-list :key="'pending-list-main'" />
                            </div>

                            <div class="rounded-2xl border border-base-200 bg-base-200/40 p-4 sm:p-5">
                                <livewire:workflow.other-related-tasks-list :key="'other-related-tasks-main'" />
                            </div>
                        </div>
                    </x-mary-tab>

                    <x-mary-tab name="notifications" icon="o-bell" class="tab-content bg-base-100 border-base-300 p-4 sm:p-6">
                        <x-slot:label>
                            <span class="flex items-center gap-2 text-base sm:text-lg font-semibold">
                                <span>{{ __('ledger.notifications') }}</span>
                                <span class="badge badge-secondary badge-sm" x-show="notificationCount > 0" x-text="notificationCount"></span>
                            </span>
                        </x-slot:label>

                        @livewire('notifications.notification-list', ['adminAnnouncements' => $adminAnnouncements])
                    </x-mary-tab>

                    <x-mary-tab name="activity" icon="o-clock" class="tab-content bg-base-100 border-base-300 p-4 sm:p-6">
                        <x-slot:label>
                            <span class="flex items-center gap-2 text-base sm:text-lg font-semibold">
                                <span>{{ __('ledger.activity.title') }}</span>
                                <span class="badge badge-outline badge-sm" x-show="activityCount > 0" x-text="activityCount"></span>
                            </span>
                        </x-slot:label>

                        @livewire('common.activity-history-display')
                    </x-mary-tab>
                </x-mary-tabs>
            </div>
        </div>
    </div>
</x-app-layout>

