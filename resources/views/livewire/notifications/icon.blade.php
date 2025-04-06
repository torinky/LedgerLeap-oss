<div>
    <a tabindex="4" class="btn btn-ghost btn-sm tooltip tooltip-bottom "
       data-tip="{{ __('ledger.navigation.notifications') }}"
       href="{{ route('notifications.index') }}">
        <i class="fas fa-bell"></i>
        {{--        <span wire:poll.5s="refreshUnreadCount">--}}
        <span wire:poll.600s="refreshUnreadCount">
            @if($unreadCount > 0)
                <span class="badge badge-sm badge-secondary">{{ $unreadCount }}</span>
            @endif

        </span>
    </a>
</div>
