<div class="card p-4">
    <h3 class="text-xl font-semibold mb-4">{{ __('ledger.activity.title') }}</h3>

    {{-- ローディングインジケーター --}}
    <x-mary-loading wire:loading class="my-4" />

    {{-- ログ閲覧権限がない場合 --}}
    @if(!auth()->check() || !auth()->user()->can('view', \App\Models\CustomActivity::class))
        <p class="text-center text-gray-500 py-8">{{ __('ledger.activity.no_permission') }}</p>
    @else
        @if($activities->isEmpty())
            <div class="text-gray-500 text-center py-8">
                {{ __('ledger.activity.no_activities_found') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                    <tr>
                        <th class="cursor-pointer" wire:click="sortBy('created_at')">
                            {{ __('ledger.activity.column.time') }}
                            {{-- TODO: ソートアイコンを追加 --}}
                        </th>
                        <th>{{ __('ledger.activity.column.causer') }}</th>
                        <th>{{ __('ledger.activity.column.subject') }}</th>
                        <th>{{ __('ledger.activity.column.operation') }}</th>
                        <th>{{ __('ledger.activity.column.changes') }}</th>
                        <th>{{ __('ledger.activity.column.comment') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($activities as $activity)
                        <tr>
                            <td class="whitespace-nowrap">
                                {{ $activity->created_at->format('Y/m/d H:i') }}<br>
                                <span class="text-sm text-base-content/70">({{ $activity->created_at->diffForHumans() }})</span>
                            </td>
                            <td>
                                {{-- getCauserDetailLink は現時点ではnullを返すため、直接表示 --}}
                                {{ $this->getCauserDisplayName($activity) }}
                            </td>
                            <td>
                                @if($this->getSubjectDetailLink($activity))
                                    <a href="{{ $this->getSubjectDetailLink($activity) }}" class="link link-primary">
                                        {{ $this->getSubjectDisplay($activity) }}
                                    </a>
                                @else
                                    {{ $this->getSubjectDisplay($activity) }}
                                @endif
                            </td>
                            <td>{{ $this->getOperationDescription($activity) }}</td>
                            <td>{!! $this->formatChanges($activity) !!}</td>
                            <td>{{ $this->formatComment($activity) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
{{--                {{ $activities->links() }}--}}
            </div>
        @endif
    @endif
</div>