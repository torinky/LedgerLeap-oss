<div>
    <div class="indicator">
        <a href="{{ route('notifications.index') }}"
           _target="LedgerLeap_PendingList"
           wire:poll.30s="refreshCounts"
                @class([
                    'btn  btn-circle btn-sm',
                    'btn-ghost' => (($pendingTaskCount == 0) && ($notificationCount == 0)),
                    'btn-info' => ($pendingTaskCount > 0 || $notificationCount > 0),
                    'btn-active' => request()->routeIs('notifications.index')
                ])
        >

            <span class="indicator-item indicator-top indicator-end flex gap-1"> {{-- バッジを右上に配置 --}}
                {{-- 通知件数バッジ --}}
                @if($notificationCount > 0)
                    <span
                        class="badge badge-xs badge-secondary tooltip tooltip-bottom"
                        data-tip="{{ __('ledger.notifications') }}: {{ $notificationCount }}"
                        data-notification-count="{{ $notificationCount }}"
                    >
                        {{ $notificationCount }}
                    </span>
                @endif

                {{-- ワークフロー未処理件数バッジ (追加) --}}
                @if($pendingTaskCount > 0)
                    <span class="badge badge-xs badge-warning tooltip tooltip-bottom"
                          data-tip="{{ __('ledger.workflow.pending_tasks') }}: {{ $pendingTaskCount }}">{{ $pendingTaskCount }}</span>
                @endif
            </span>

            <x-mary-icon name="o-bell" class="h-5 w-5" />
        </a>
    </div>
</div>

