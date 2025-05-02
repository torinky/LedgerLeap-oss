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
                @php $display = $notification->displayData; @endphp {{-- 整形済みデータを取得 --}}
                <div class="border-b border-base-content/50 last:border-b-0 pb-4"
                     wire:key="notification-{{ $notification->id }}">
                    <div>
                        <div>
                            @if($display['is_unread'])
                                <span class="badge badge-xs badge-error mr-1 align-middle">{{ __('ledger.unread') }}</span>
                            @endif
                            <div class="text-base-content inline-block">
                                {{-- 整形済みメッセージを表示 --}}
                                <p>
                                    {!! $display['message'] !!}
                                    {{-- 整形済みリンクを表示 --}}
                                    @if ($display['link'])
                                        <a href="{{ $display['link'] }}"
                                           class="link text-xs ml-1">[{{ $display['link_text'] }}]</a>
                                    @elseif($display['link_text'])
                                        {{-- リンクはないがテキストはある場合 --}}
                                        <span class="text-xs ml-1 text-gray-400">[{{ $display['link_text'] }}]</span>
                                    @endif
                                </p>
                                {{-- コメントがあれば表示 --}}
                                @if ($display['comments'])
                                    <div class="text-xs mt-1 p-1 bg-base-200 rounded"
                                         title="{{ __('ledger.workflow.comments') }}">
                                        <i class="fas fa-comment-dots mr-1 opacity-60"></i>{!! nl2br(e($display['comments'])) !!}
                                    </div>
                                @endif
                                {{-- 通知日時 --}}
                                <div class="text-base-content/70 text-sm">{{ $display['timestamp'] }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- 変更履歴表示 (changes があれば) --}}
                    @if ($display['changes'] && isset($display['changes']['attributes']))
                        <div class="mt-2">
                            <h6 class="text-sm font-medium">{{__('ledger.changes')}}:</h6>
                            <div class="overflow-x-auto">
                                <table class="table table-xs w-full">
                                    {{-- ... (テーブルヘッダー) ... --}}
                                    <tbody>
                                    @foreach($display['changes']['attributes'] as $attribute => $newValue)
                                        <x-diff-display
                                                :attribute="$attribute"
                                                :old="$display['changes']['old'][$attribute] ?? null"
                                                :new="$newValue"
                                        />
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- 既読ボタン --}}
                    <div class="flex justify-end mt-1">
                        @if($display['is_unread'])
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
