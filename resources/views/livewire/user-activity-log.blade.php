<div>
    @if(Auth::user()->can('view_activity_logs'))
        <table class="table w-full h-full table-zebra">
            <thead>
            <tr>
                <th class="px-4 py-2 text-base-content">{{ __('ledger.created_at') }}</th>
                <th class="px-4 py-2 text-base-content">{{ __('ledger.user') }}</th>
                <th class="px-4 py-2 text-base-content">{{ __('ledger.description') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($activities as $activity)
                <tr class="hover">
                    <td class="px-4 py-2 text-base-content/70">
                        {{ $activity->created_at->format('Y-m-d H:i:s') }}</br>
                        ({{ $activity->created_at->diffForHumans() }})
                    </td>
                    <td class="px-4 py-2 text-base-content">{{ $activity->causer ? $activity->causer->name : 'システム' }}</td>
                    <td class="px-4 py-2 text-base-content">
                        <p>{{ $activity->description }}</p>
                        @if(isset($activity->properties['attributes']))
                            <div class="mt-2">
                                <h6 class="text-sm font-medium">{{ __('ledger.changes') }}:</h6>
                                <div class="overflow-x-auto">
                                    <table class="table table-compact w-full">
                                        <thead>
                                        <tr>
                                            <th>{{ __('ledger.attribute') }}</th>
                                            <th>{{ __('ledger.before_change') }}</th>
                                            <th>{{ __('ledger.after_change') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($activity->properties['attributes'] as $attribute => $newValue)
                                            @php
                                                $oldValue = $activity->properties['old'][$attribute] ?? null;
                                            @endphp
                                            <x-diff-display :attribute="$attribute" :old="$oldValue" :new="$newValue"/>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">{{ __('ledger.no_activity_logs') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">
            {{ $activities->links() }}
        </div>

    @else
        <p class="text-center">{{ __('activitylog.no_permission') }}</p>
    @endif

</div>
