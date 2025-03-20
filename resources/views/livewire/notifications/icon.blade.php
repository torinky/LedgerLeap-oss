<div>
    <a tabindex="4" class="btn btn-ghost btn-sm tooltip tooltip-bottom pt-2" data-tip="{{ __('Notifications') }}"
       href="{{ route('notifications.index') }}">
        <i class="fas fa-bell"></i>
        {{--        <span wire:poll.5s="refreshUnreadCount">--}}
        <span wire:poll.600s="refreshUnreadCount">
            @if($unreadCount > 0)
                <span class="badge badge-error">{{ $unreadCount }}</span>
            @endif

        </span>
    </a>
</div>
