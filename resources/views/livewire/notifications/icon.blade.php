{{--
<div>
    <a tabindex="4"
       class="btn btn-ghost btn-sm tooltip tooltip-bottom {{ request()->routeIs('notifications.index') ? 'btn-active' : '' }}"
       data-tip="{{ __('ledger.navigation.notifications') }}"
       href="{{ route('notifications.index') }}">
        <i class="fas fa-bell"></i>
        --}}
{{--        <span wire:poll.5s="refreshUnreadCount">--}}{{--

        <span wire:poll.600s="refreshUnreadCount">
            @if($unreadCount > 0)
                <span class="badge badge-sm badge-secondary">{{ $unreadCount }}</span>
            @endif

        </span>
    </a>
</div>
--}}

<div>
    {{-- 既存の通知アイコンリンク --}}
    <div class="indicator mr-4">
        <a tabindex="4"
           @class(['btn btn-ghost btn-square btn btn=sm', 'btn-active' => request()->routeIs('notifications.index')])
           href="{{ route('notifications.index') }}"
           wire:poll.600s="refreshUnreadCount"
        >

            <span class="indicator-item indicator-top indicator-end flex gap-1"> {{-- バッジを右上に配置 --}}
                {{-- ワークフロー未処理件数バッジ (追加) --}}
                @if($pendingTaskCount > 0)
                    <span class="badge badge-xs badge-warning tooltip tooltip-bottom"
                          data-tip="{{ __('ledger.workflow.pending_tasks') }}: {{ $pendingTaskCount }}">{{ $pendingTaskCount }}</span>
                @endif

                {{-- 未読通知件数バッジ (既存) --}}
                @if($unreadCount > 0)
                    <span class="badge badge-xs badge-secondary tooltip tooltip-bottom"
                          data-tip="{{ __('ledger.navigation.unread_notifications') }}: {{ $unreadCount }}">{{ $unreadCount }}</span>
                @endif
            </span>

            <i class="fas fa-bell"></i> {{-- アイコン --}}
        </a>
    </div>
</div>