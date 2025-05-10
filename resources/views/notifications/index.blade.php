@props([
    'initialNotificationCount'=>0,
    'initialTaskCount'=>0,
    'activeTab'=>'notifications'
])

<x-app-layout :title="__('ledger.notifications')"> {{-- レイアウト適用とタイトル設定 --}}
    <x-slot name="header">
        <div class="ttl_3d5 info md:flex md:items-center space-x-4 bg-info/40 rounded">
            <h2 class="font-black text-xl text-info-content/60 md:text-2xl flex items-center">
                {{ __('ledger.notifications') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-2">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-base-100 overflow-hidden shadow-xl sm:rounded-lg">
                {{-- Alpine.js でタブの状態と件数を管理 --}}
                <div x-data="{
                        activeTab: '{{ $activeTab }}',
                        notificationCount: {{$initialNotificationCount}},
                        activityCount: 0, // アクティビティログも同様にする場合
                        taskCount: {{$initialTaskCount}},
                        updateCount(event) {
                            if (event.detail.tab === 'notifications') {
                                this.notificationCount = event.detail.count;
                            } else if (event.detail.tab === 'activity') {
                                this.activityCount = event.detail.count;
                            } else if (event.detail.tab === 'tasks') {
                                this.taskCount = event.detail.count;
                            }
                        }
                    }
                    "
                     {{-- Livewire からのイベントを捕捉 --}}
                     @update-tab-count.window="updateCount($event)"

                >

                    {{-- タブコンテナ --}}
                    {{-- MaryUI の Tabs コンポーネントは wire:model が必要なので、 --}}
                    {{-- 親が Blade の場合は Alpine.js などでタブ制御するか、 --}}
                    {{-- DaisyUI の標準タブを使う方が簡単かもしれません。 --}}
                    {{-- ここでは DaisyUI のタブを想定した例を示します。 --}}

                    {{-- タブヘッド --}}
                    <div role="tablist" class="tabs tabs-lifted">
                        <label class="tab">
                            <input type="radio" name="notification_tabs" role="tab" class="tab"
                                   aria-label="{{ __('ledger.workflow.pending_tasks') }}"
                                   {{ $activeTab === 'tasks' ? 'checked' : '' }}
                                   :checked="activeTab === 'tasks'" @click="activeTab = 'tasks'"
                            />
                            {{ __('ledger.workflow.pending_tasks') }}
                            <span class=" badge badge-sm badge-info ml-2"
                                  x-show="taskCount > 0"
                                  x-text=" taskCount "></span>
                        </label>
                        <div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">
                            {{-- 自分宛のタスクリスト --}}
                            <div>
                                @livewire('workflow.pending-list', key('pending-list-main'))
                            </div>

                            {{-- その他の関連タスクリスト --}}
                            <div>
                                @livewire('workflow.other-related-tasks-list', key('other-related-tasks-main'))
                            </div>
                        </div>

                        <label class="tab">
                            <input type="radio" name="notification_tabs" role="tab" class="tab"
                                   aria-label="{{ __('ledger.notifications') }}"
                                   {{ $activeTab === 'notifications' ? 'checked' : '' }}
                                   :checked="activeTab === 'notifications'" @click="activeTab = 'notifications'"
                            />
                            {{ __('ledger.notifications') }}
                            {{-- ラベルに件数を表示 (Alpine.js 変数を使用) --}}
                            <span class="badge badge-sm badge-secondary ml-2"
                                  x-show="notificationCount > 0"
                                  x-text="notificationCount"></span>
                        </label>
                        <div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">
                            {{-- 通知リスト Livewire コンポーネント呼び出し --}}
                            @livewire('notifications.notification-list')
                        </div>

                        <input type="radio" name="notification_tabs" role="tab" class="tab"
                               aria-label="{{ __('activitylog.activitylog') }}" {{ $activeTab === 'activity' ? 'checked' : '' }} />
                        <div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">
                            {{-- アクティビティログ Livewire コンポーネント呼び出し --}}
                            @livewire('user-activity-log') {{-- 既存コンポーネント名 --}}
                        </div>

                    </div>

                    {{-- MaryUI Tabs を使う場合の代替案 (JavaScript でのタブ制御が必要) --}}
                    {{--
                    <x-mary-tabs :selected="$activeTab" class="tabs-boxed">
                         <x-mary-tab name="notifications" :label="__('ledger.notifications')">
                              @livewire('notifications.notification-list')
                         </x-mary-tab>
                         <x-mary-tab name="activity" :label="__('activitylog.activitylog')">
                              @livewire('user-activity-log')
                         </x-mary-tab>
                         <x-mary-tab name="tasks" :label="__('ledger.workflow.pending_tasks')">
                               @livewire('workflow.pending-list')
                         </x-mary-tab>
                    </x-mary-tabs>
                    --}}

                </div>
            </div>
        </div>
</x-app-layout>