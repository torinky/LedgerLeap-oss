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
                {{--                @dd($notification, $notification->data)--}}
                @php
                    // data['payload'] が存在するか確認
                    $payload = $notification->data['payload'] ?? null;
                    $isValidPayload = is_array($payload);

                    // 各種情報をペイロードから取得 (存在しない場合はデフォルト値)
                    $causerName = $isValidPayload ? ($payload['causer_name'] ?? null) : null;
                    $subjectType = $isValidPayload ? ($payload['subject_type'] ?? null) : null;
                    $subjectId = $isValidPayload ? ($payload['subject_id'] ?? null) : null;
                    $routeName = $isValidPayload ? ($payload['route'] ?? null) : null;
                    $event = $isValidPayload ? ($payload['event'] ?? null) : null;
                    $changes = $isValidPayload ? ($payload['changes'] ?? null) : null;
                    $comments = $isValidPayload ? ($payload['comments'] ?? null) : null;
                    $notificationTypeName = $isValidPayload ? ($payload['notification_type_name'] ?? $notification->type) : $notification->type; // フォールバック

                    // --- 表示用テキスト生成 ---
                    $subjectLabel = $subjectType ? (__('ledger.notification_types.'.$subjectType) ?? class_basename($subjectType)) : ''; // モデル名の翻訳
                    $eventLabel = $event ? __('ledger.action_'.$event) : ''; // アクション名の翻訳
                    $subjectName = ''; // 対象レコードの名前やタイトル
                    if ($subjectType === 'App\\Models\\Ledger') {
                        $subjectName = $payload['ledger_name'] ?? "ID:{$subjectId}";
                    } elseif ($subjectId) {
                         // 他のモデルの場合、名前を特定する共通の方法があれば追加 (例: User なら name)
                         // なければタイプとIDを表示
                         $subjectName = "{$subjectLabel} ID:{$subjectId}";
                    }

                    // 基本メッセージ
                    $baseMessage = $subjectName ? "{$subjectLabel}「{$subjectName}」" : "{$subjectLabel} ";
                    if ($causerName) {
                            $baseMessage = "<strong>{$causerName}</strong> ". __('ledger.performed_action'). $baseMessage;
                    }
                    $message = $baseMessage . $eventLabel;

                @endphp

                <div class="border-b border-base-content/50 last:border-b-0 pb-4">
                    <div>
                        <div>
                            @if($notification->unread())
                                <span class="badge badge-xs badge-error mr-1 align-middle">{{ __('ledger.unread') }}</span>
                            @endif
                            <div class="text-base-content inline-block"> {{-- inline-blockを追加 --}}
                                {{-- 生成したメッセージを表示 --}}
                                <p>
                                    {!! $message !!}
                                    {{-- 詳細へのリンク (ルートとIDがあれば) --}}
                                    @if ($routeName && $subjectId && Route::has($routeName))
                                        <a href="{{ route($routeName, $subjectId) }}"
                                           class="link text-xs ml-1">[{{ __('ledger.view_details') }}]</a>
                                    @elseif($subjectId)
                                        <span class="text-xs ml-1 text-gray-400">[{{ __('ledger.link_unavailable') }}]</span> {{-- 新しい翻訳キー --}}
                                    @endif
                                </p>
                                {{-- コメントがあれば表示 --}}
                                @if ($comments)
                                    <div class="text-xs mt-1 p-1 bg-base-200 rounded"
                                         title="{{ __('ledger.workflow.comments') }}">
                                        <i class="fas fa-comment-dots mr-1 opacity-60"></i>{!! nl2br(e($comments)) !!}
                                    </div>
                                @endif
                                {{-- 通知日時 --}}
                                <div class="text-base-content/70 text-sm">{{ $notification->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- 変更履歴表示 (changes があれば) --}}
                    @if ($changes && isset($changes['attributes']))
                        <div class="mt-2">
                            <h6 class="text-sm font-medium">{{__('ledger.changes')}}:</h6>
                            <div class="overflow-x-auto">
                                <table class="table table-xs w-full"> {{-- table-xs に変更 --}}
                                    <thead>
                                    <tr>
                                        <th>{{__('ledger.attribute')}}</th>
                                        <th>{{__('ledger.before_change')}}</th>
                                        <th>{{__('ledger.after_change')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($changes['attributes'] as $attribute => $newValue)
                                        {{-- ToDo: attribute 名も翻訳できるようにする？ --}}
                                        <x-diff-display
                                                :attribute="$attribute"
                                                :old="$changes['old'][$attribute] ?? null"
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
                        @if($notification->unread())
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
