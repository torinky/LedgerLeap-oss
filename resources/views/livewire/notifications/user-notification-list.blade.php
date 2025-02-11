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
                            <div class="p-4 space-y-4">
                                {{-- 「すべて既読にする」ボタン --}}
                                <div class="flex justify-end">
                                    <button wire:click="markAllAsRead"
                                            class="btn btn-sm btn-primary">{{ __('ledger.mark_all_as_read') }}</button>
                                </div>
                                <div class="flex justify-center ">
                                    {{ $notifications->links() }}
                                </div>

                                @foreach($notifications as $notification)
                                    <div class="border-b border-base-content/50 last:border-b-0 pb-4">
                                        {{-- 通知メッセージと未読バッジ --}}
                                        <div>
                                            <div>
                                                @if($notification->unread())
                                                    <span class="badge badge-error">{{ __('ledger.unread') }}</span>
                                                @endif
                                                <div class="text-base-content">
                                                    {{-- 通知メッセージ --}}
                                                    <p>
                                                        @isset($notification->data['payload']['causer_name'])
                                                            <strong>{{ $notification->data['payload']['causer_name'] }}</strong> {{__('ledger.user_action_suffix')}}
                                                        @endisset

                                                        @if($notification->data['payload']['event'] === 'created')
                                                            {{__('ledger.ledger')}}
                                                            @isset($notification->data['payload']['subject_id'])
                                                                <a href="{{ route('ledger.show', $notification->data['payload']['subject_id']) }}"
                                                                   class="link">
                                                                    {{ $notification->data['payload']['ledger_name'] ?? 'Ledger ID: ' . $notification->data['payload']['subject_id'] }}
                                                                </a>
                                                            @endisset
                                                            {{__('ledger.action_created')}}
                                                        @elseif($notification->data['payload']['event'] === 'updated')
                                                            {{__('ledger.ledger')}}
                                                            @isset($notification->data['payload']['subject_id'])
                                                                <a href="{{ route('ledger.show', $notification->data['payload']['subject_id']) }}"
                                                                   class="link">
                                                                    {{ $notification->data['payload']['ledger_name'] ?? 'Ledger ID: ' . $notification->data['payload']['subject_id'] }}
                                                                </a>
                                                            @endisset
                                                            {{__('ledger.of')}}
                                                            @if(isset($notification->data['payload']['changes']['attributes']))
                                                                @php
                                                                    $changedAttributes = array_keys($notification->data['payload']['changes']['attributes']);
                                                                @endphp
                                                                <strong>{{ implode('、', $changedAttributes) }}</strong>
                                                            @endif
                                                            {{__('ledger.action_updated')}}
                                                        @elseif($notification->data['payload']['event'] === 'deleted')
                                                            {{__('ledger.ledger')}}
                                                            @isset($notification->data['payload']['subject_id'])
                                                                {{-- 削除された台帳へのリンクは表示しない --}}
                                                                {{ $notification->data['payload']['ledger_name'] ?? 'Ledger ID: ' . $notification->data['payload']['subject_id'] }}
                                                            @endisset
                                                            {{__('ledger.action_deleted')}}
                                                        @else
                                                            {{ $notification->data['payload']['description'] }} {{-- その他のイベント --}}
                                                        @endif
                                                    </p>
                                                    <div
                                                        class="text-base-content/70 text-sm">{{ $notification->created_at->diffForHumans() }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- 変更内容 (更新の場合) --}}
                                        @if(isset($notification->data['payload']['changes']['attributes']))
                                            <div class="mt-2">
                                                <h6 class="text-sm font-medium">{{__('ledger.changes')}}:</h6>
                                                <div class="overflow-x-auto">
                                                    <table class="table table-compact w-full">
                                                        <thead>
                                                        <tr>
                                                            <th>{{__('ledger.attribute')}}</th>
                                                            <th>{{__('ledger.before_change')}}</th>
                                                            <th>{{__('ledger.after_change')}}</th>
                                                        </tr>
                                                        </thead>
                                                        <tbody>
                                                        @foreach($notification->data['payload']['changes']['attributes'] as $attribute => $newValue)
                                                            <tr>
                                                                <td>{{ $attribute }}</td>
                                                                <td>
                                                                    @isset($notification->data['payload']['changes']['old'][$attribute])
                                                                        <pre
                                                                            class="text-xs">{{ json_encode($notification->data['payload']['changes']['old'][$attribute], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                                    @else
                                                                        -
                                                                    @endisset
                                                                </td>
                                                                <td>
                                                                    <pre
                                                                        class="text-xs">{{ json_encode($newValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        @endif
                                        {{-- 既読ボタン --}}
                                        <div class="mt-2 flex justify-end ">
                                            @if($notification->unread())
                                                <button wire:click="markAsRead('{{ $notification->id }}')"
                                                        class="btn btn-xs btn-primary">{{ __('ledger.mark_as_read') }}</button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                                <div class="flex justify-center ">
                                    {{ $notifications->links() }}
                                </div>
                            </div>
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
