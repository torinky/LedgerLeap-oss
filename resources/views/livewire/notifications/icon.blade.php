<div>
    <div class="dropdown dropdown-end">
        <a tabindex="4" class="btn btn-ghost btn-sm tooltip tooltip-bottom pt-2" data-tip="{{ __('Notifications') }}"
           href="{{ route('notifications.index') }}">
            <i class="fas fa-bell"></i>
            @if($unreadCount > 0)
                <span class="badge badge-error">{{ $unreadCount }}</span>
            @endif
        </a>
    </div>
</div>
