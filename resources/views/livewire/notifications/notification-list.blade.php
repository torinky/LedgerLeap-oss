<div>
    @if($notifications->isEmpty())
        <p class="text-base-content p-4">{{__('ledger.no_notification')}}</p>
    @else
        <div class="p-4 space-y-4">
            <div class="flex justify-end">
                <button wire:click="markAllAsRead"
                        class="btn btn-sm btn-primary">{{ __('ledger.mark_all_as_read') }}</button>
            </div>

            @foreach($notifications as $notification)
                @php
                    //dd($notification);
                    $routeName = $notification->data['payload']['route']??'ledger.index';
                    $subjectId = $notification->data['payload']['subject_id'] ?? null;
                    $causerName = $notification->data['payload']['causer_name'] ?? null;
                    $event = $notification->data['payload']['event'];
                    $label = __('ledger.notification_types.'.$notification->data['payload']['subject_type']);
                    $eventLabel = __('ledger.action_'.$event);
                @endphp
                <div class="border-b border-base-content/50 last:border-b-0 pb-4">
                    <div>
                        <div>
                            @if($notification->unread())
                                <span class="badge badge-error">{{ __('ledger.unread') }}</span>
                            @endif
                            <div class="text-base-content">
                                <p>
                                    @isset($causerName)
                                        <strong>{{ $causerName }}</strong> {{__('ledger.user_action_suffix')}}
                                    @endisset

                                    {{ $label }}
                                    @isset($subjectId)
                                        @if(Route::has($routeName))
                                            <a href="{{ route($routeName, $subjectId) }}"
                                               class="link">
                                                {{ $notification->data['payload']['ledger_name'] ?? ucfirst($routeName) . ' ID: ' . $subjectId }}
                                            </a>
                                        @else
                                            {{ $notification->data['payload']['ledger_name'] ?? ucfirst($routeName) . ' ID: ' . $subjectId }}
                                        @endif
                                    @endisset
                                    {{ $eventLabel }}
                                </p>
                                <div
                                        class="text-base-content/70 text-sm">{{ $notification->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    </div>

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
                                        <x-diff-display
                                                :attribute="$attribute"
                                                :old="$notification->data['payload']['changes']['old'][$attribute] ?? null"
                                                :new="$newValue"
                                        />
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                    <div class="flex justify-end">
                        @if($notification->unread())
                            <button wire:click="markAsRead('{{ $notification->id }}')"
                                    class="btn btn-xs btn-primary">{{ __('ledger.mark_as_read') }}</button>
                        @endif
                    </div>
                </div>
            @endforeach
            <div class="z-20 fixed bottom-4 left-0 right-0 mx-auto flex justify-center">
                <div
                        class="card bg-base-300 opacity-70 transition-opacity hover:opacity-100 shadow-lg">
                    <div class="card-body">
                        {!! $notifications->links() !!}
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
