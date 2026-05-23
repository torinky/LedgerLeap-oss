<div class="space-y-4" wire:poll.30s="refreshAnnouncements">
    @php
        $hasAdminAnnouncements = filled($adminAnnouncements ?? null);
        $hasWorkflowNotifications = $notifications->isNotEmpty();
    @endphp

    @if ($hasAdminAnnouncements)
        <x-admin.announcement-feed
            :announcements="$adminAnnouncements"
            :respect-dismissed="false"
            :dismissible="false"
        />
    @endif

    @if($hasWorkflowNotifications)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="badge badge-secondary badge-lg gap-2">
                    <x-mary-icon name="o-bell" class="h-4 w-4" />
                    <span>{{ __('ledger.unread') }}</span>
                </span>
                <span class="text-sm text-base-content/60">{{ $workflowNotificationCount }}</span>
            </div>

            <button wire:click="markAllAsRead" class="btn btn-sm btn-primary">
                {{ __('ledger.mark_all_as_read') }}
            </button>
        </div>

        <div class="space-y-3">
            @foreach($notifications as $notification)
                @php
                    $display = $notification->displayData;
                    $hasComments = filled($display['comments'] ?? null);
                    $hasChanges = filled($display['changes_formatted'] ?? null);
                @endphp
                <article
                    wire:key="notification-{{ $notification->id }}"
                    class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm {{ $display['is_unread'] ? 'ring-1 ring-secondary/20' : '' }}"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                @if($display['is_unread'])
                                    <span class="badge badge-secondary badge-sm">{{ __('ledger.unread') }}</span>
                                @endif
                                @if($display['link'])
                                    <a href="{{ $display['link'] }}" class="link link-primary text-sm font-medium">
                                        {{ $display['link_text'] }}
                                    </a>
                                @elseif($display['link_text'])
                                    <span class="text-sm font-medium text-base-content/60">{{ $display['link_text'] }}</span>
                                @endif
                            </div>

                            <div class="space-y-2 text-base-content">
                                <p class="leading-7">
                                    {!! $display['message'] !!}
                                </p>

                                @if($hasComments)
                                    <div class="rounded-xl bg-base-200/70 p-3 text-sm text-base-content/80">
                                        <div class="mb-2 text-xs uppercase tracking-wide text-base-content/50">
                                            {{ __('ledger.workflow.comments') }}
                                        </div>
                                        <div>{!! nl2br(e($display['comments'])) !!}</div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="shrink-0 text-right text-xs text-base-content/60">
                            {{ $display['timestamp'] }}
                        </div>
                    </div>

                    @if($hasChanges)
                        <div class="mt-4 rounded-xl border border-base-200 bg-base-200/50 p-3">
                            <div class="mb-2 text-xs uppercase tracking-wide text-base-content/50">
                                {{ __('ledger.activity.column.changes') }}
                            </div>
                            <div class="overflow-x-auto">
                                {!! $display['changes_formatted'] !!}
                            </div>
                        </div>
                    @endif

                    @if($display['is_unread'])
                        <div class="mt-4 flex justify-end">
                            <button wire:click="markAsRead('{{ $notification->id }}')" class="btn btn-xs btn-primary">
                                {{ __('ledger.mark_as_read') }}
                            </button>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="flex justify-center pt-2">
            {!! $notifications->links('components.common.pagination-links', ['position' => 'notification']) !!}
        </div>
    @elseif (! $hasAdminAnnouncements)
        <div class="rounded-2xl border border-base-300 bg-base-100 p-8 text-center shadow-sm">
            <x-mary-icon name="o-bell" class="mx-auto h-12 w-12 text-base-content/40" />
            <p class="mt-4 text-base font-medium text-base-content">{{ __('ledger.no_notification') }}</p>
        </div>
    @endif
</div>
