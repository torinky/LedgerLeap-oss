<div>
    <div class="indicator mr-4">
        @if (tenant())
            <a href="{{ route('notifications.index', ['tenant' => $this->tenantId]) }}"
               _target="LedgerLeap_PendingList"
               wire:poll.600s="refreshCounts"
               tabindex="4"
                    @class([
                        'btn  btn-circle btn-sm',
                        'btn-ghost'=>($pendingTaskCount==0),
                        'btn-info' => ($pendingTaskCount > 0),
                        'btn-active' => request()->routeIs('notifications.index')
                    ])
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

                <i @class(['fas fa-bell']) ></i> {{-- アイコン --}}
            </a>
        @endif
    </div>
</div>