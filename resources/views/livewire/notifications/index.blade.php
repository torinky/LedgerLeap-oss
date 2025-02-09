<div>
    <x-slot name="header">
        <h2 class="font-semibold text-base-content leading-tight">
            {{ __('Notifications') }}
        </h2>
    </x-slot>

    <div class="py-2">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-base-100 overflow-hidden shadow-xl sm:rounded-lg">
                <x-mary-tabs wire:model="selectedTab" class="tabs-boxed">
                    <x-mary-tab name="notifications" :label="__('ledger.notifications')">
                        @if($notifications->isEmpty())
                            <p class="text-base-content p-4">{{__('ledger.no_notification')}}</p>
                        @else
                            <ul class="p-4">
                                @foreach($notifications as $notification)
                                    {{--                                    @dd($notification)--}}
                                    <li class="border-b border-base-content/50 py-4">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                @if(is_null($notification->read_at))
                                                    <span
                                                        class="bg-error text-error-content text-xs font-medium me-2 px-2.5 py-0.5 rounded">{{ __('ledger.unread') }}</span>
                                                @endif
                                                <div
                                                    class="text-base-content">{{ $notification->data['ledger_name']??'' }}
                                                    が更新されました
                                                </div>
                                                <div
                                                    class="text-base-content/70 text-sm">{{ $notification->created_at->diffForHumans() }}
                                                    ({{ $notification->data['causer_name'] ?? 'システム' }})
                                                </div>
                                            </div>
                                            <div>
                                                @if(is_null($notification->read_at))
                                                    <button wire:click="markAsRead('{{ $notification->id }}')"
                                                            class="btn btn-xs btn-ghost">{{ __('Mark as read') }}</button>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-mary-tab>
                    <x-mary-tab name="activityLog" :label="__('ledger.activity_log')">
                        <div class="p-4">
                            <livewire:user-activity-log/>
                        </div>
                    </x-mary-tab>
                </x-mary-tabs>
            </div>
        </div>
    </div>
</div>
