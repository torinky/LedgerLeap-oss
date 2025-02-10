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
                                    <li class="border-b border-base-content/50 py-4">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                @if($notification->unread())
                                                    <span
                                                        class="bg-error text-error-content text-xs font-medium me-2 px-2.5 py-0.5 rounded">{{ __('ledger.unread') }}</span>
                                                @endif
                                                <div class="text-base-content">
                                                    {{-- 通知メッセージの動的生成 --}}
                                                    @isset($notification->data['payload']['causer_name'])
                                                        {{ $notification->data['payload']['causer_name'] }} が
                                                    @endisset
                                                    {{--  $notification->data['description'] を $notification->payload['description'] に変更 --}}
                                                    {{ $notification->data['payload']['description'] }}
                                                    @isset($notification->data['payload']['subject_id'])
                                                        (
                                                        <a href="{{ route('ledger.show', $notification->data['payload']['subject_id']) }}"
                                                           class="link">{{-- 実際のルートに合わせる --}}
                                                            {{-- $notification->data['ledger_name'] を $notification->payload['ledger_name'] に変更 (存在する場合) --}}
                                                            {{ $notification->data['payload']['ledger_name'] ?? 'Ledger ID: ' . $notification->data['payload']['subject_id'] }}
                                                        </a>)
                                                    @endisset

                                                </div>
                                                <div
                                                    class="text-base-content/70 text-sm">{{ $notification->created_at->diffForHumans() }}</div>

                                                {{-- 変更前後の属性値を表示 (更新の場合) --}}
                                                @if(isset($notification->data['payload']['changes']['attributes']))
                                                    <div class="mt-2">
                                                        <h6 class="text-sm font-medium">変更点:</h6>
                                                        <ul class="text-sm">
                                                            @foreach($notification->data['payload']['changes']['attributes'] as $attribute => $newValue)
                                                                @isset($notification->data['payload']['changes']['old'][$attribute])
                                                                    <li>
                                                                        {{ $attribute }}:
                                                                        <span
                                                                            class="text-base-content/70">{{implode(', ', collect( $notification->data['payload']['changes']['old'][$attribute])->flatten()->toArray()) }}</span>
                                                                        ->
                                                                        <span
                                                                            class="text-base-content">{{implode(', ', collect( $newValue)->flatten()->toArray())  }}</span>
                                                                    </li>
                                                                @else
                                                                    <li>{{ $attribute }}: <span
                                                                            class="text-base-content">{{ $newValue }}</span>
                                                                        (新規追加)
                                                                    </li>
                                                                @endisset
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                            </div>
                                            <div>
                                                @if($notification->unread())
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
