<div>
    <x-slot name="header">
        <x-mary-header :title="__('ledger.notification.settings.title')" with-anchor separator/>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-base-100 overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6">
                {{-- 説明文を翻訳キーに --}}
                <p class="mb-6 text-base-content/70">
                    {{ __('permission.settings_description') }}
                </p>

                <x-mary-form wire:submit="save">
                    @forelse($notificationSettings as $permissionName => $setting)
                        {{-- disabled 状態に応じてクラスを追加 --}}
                        <div class="mb-6 p-4 border border-base-300 rounded-lg {{ $setting['disabled'] ? 'opacity-60 bg-base-200 cursor-not-allowed' : '' }}">
                            {{-- ツールチップ表示のために親要素で囲む (DaisyUI tooltip クラスを使用) --}}
                            {{-- tooltip を表示する場合のみ div で囲む --}}
                            @if($setting['disabled'])
                                <div class="tooltip w-full" data-tip="{{ __('permission.role_enforced_tooltip') }}">
                                    @endif

                                    <x-mary-toggle
                                            wire:model="notificationSettings.{{ $permissionName }}.enabled"
                                            id="toggle-{{ $permissionName }}"
                                            :label="$setting['label']"
                                            :hint="$setting['description']"
                                            :disabled="$setting['disabled']"
                                            {{-- disabled 時はクリックイベントも無効化 --}}
                                            class="mb-2 {{ $setting['disabled'] ? 'pointer-events-none' : '' }}"
                                    />
                                    {{-- 無効化されている場合のインジケーター (ツールチップは親要素で表示) --}}
                                    @if($setting['disabled'])
                                        <div class="text-xs text-warning mt-1">
                                            <x-mary-icon name="o-lock-closed" class="inline-block w-4 h-4 mr-1"/>
                                            {{ __('permission.role_enforced_label') }} {{-- 翻訳キー使用 --}}
                                        </div>
                                    @endif

                                    {{-- tooltip 用の div 閉じタグ --}}
                                    @if($setting['disabled'])
                                </div>
                            @endif
                        </div>
                    @empty
                        {{-- 設定項目がない場合のメッセージ --}}
                        <p>{{ __('permission.no_settings_available') }}</p>
                    @endforelse

                    <x-slot:actions>
                        {{-- 保存ボタンに wire:disabled を追加。 Computed プロパティ $this->canSaveChanges() を参照 --}}
                        @if($this->canSaveChanges())
                            <x-mary-button label="{{__('ledger.save')}}"
                                           icon="o-pencil"
                                           class="btn-primary btn-wide btn-lg"
                                           type="submit"
                                           spinner="save"
                            />
                        @else
                            <x-mary-button label="{{__('ledger.save')}}" class="btn-primary btn-wide btn-lg"
                                           icon="o-pencil"
                                           type="submit"
                                           spinner="save" disabled
                            />
                        @endif
                    </x-slot:actions>
                </x-mary-form>
            </div>
        </div>
    </div>
</div>