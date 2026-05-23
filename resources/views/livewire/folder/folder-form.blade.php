<div>
    <x-mary-header
            :title="$isCreating ?
                __('ledger.folder.form.header.create') :
                __('ledger.folder.form.header.edit_name', ['name' => $folder->title ?? ''])"
            separator
            class="mx-2 md:mx-8 lg:mx-12 mt-2 md:mt-4 lg:mt-6"
    >
        {{--
                <x-slot:actions>
                    --}}
        {{-- 「ウィンドウを閉じる」ボタンは常に表示して良い --}}{{--

                    <x-mary-button label="{{ __('ledger.close_window') }}" onclick="window.close()" icon="o-x-mark"
                                   class="btn-ghost"/>
                </x-slot:actions>
        --}}
    </x-mary-header>

    <x-mary-form wire:submit="save"
                 class="card mb-32 bg-neutral-500/10 shadow-xl mx-2 md:mx-8 lg:mx-12"
    >

        <div class="card-body space-y-4 p-4 {{ $formDisabled ? 'opacity-50 pointer-events-none' : '' }}"> {{-- 削除後は無効化 --}}
            <x-mary-input label="{{ __('ledger.folder.form.label.title') }}" wire:model="title" required/>

            {{-- 秘密区分・公開範囲（モックアップ） --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-select
                        label="{{ __('ledger.confidentiality.level.label') }}"
                        wire:model="confidentialityLevel"
                        :options="$confidentialityLevelOptions"
                        placeholder="{{ __('ledger.folder.form.placeholder.select_roles') }}"
                        allow-clear
                />

                <x-mary-choices-offline
                        label="{{ __('ledger.confidentiality.scope.label') }}"
                        wire:model="confidentialityScopes"
                        :options="$confidentialityScopeOptions"
                        multiple
                        searchable
                        placeholder="{{ __('ledger.confidentiality.scope.placeholder') }}"
                        no-result-text="{{ __('messages.info.no_results_found') }}"
                />
            </div>

            {{-- 親フォルダ選択 --}}
            @if($isCreating && App\Models\Folder::count() === 0)
                <p class="text-sm text-gray-500">{{ __('ledger.folder.form.message.first_folder_is_root') }}</p>
            @else
                {{-- 親フォルダ選択には ChoicesOffline は階層表示に不向きなため、シンプルな Select を維持するか、
                     専用の階層表示コンポーネントが必要。ここでは一旦 Select のまま。
                     もし検索が必要なら、Livewire 側で検索ロジックと options の動的更新が必要。 --}}
                <x-mary-select
                        label="{{ __('ledger.folder.form.label.parent_id') }}"
                        wire:model="parentId"
                        :options="$availableParentFolders" {{-- ['id' => ..., 'name' => ...] のコレクション --}}
                        placeholder="{{ __('ledger.folder.form.placeholder.select_parent_or_null') }}" {{-- 翻訳キー変更 --}}
                        allow-clear
                />
            @endif
            @error('parentId') <span class="text-xs text-error">{{ $message }}</span> @enderror

            <hr class="my-6">

            <h3 class="text-lg font-semibold mb-2">{{ __('ledger.workflow.required_roles_setting') }}</h3>
            <p class="text-sm text-gray-500 mb-4">
                {{ __('ledger.workflow.required_roles_setting_helper') }}
            </p>

            {{-- 必須点検ロール選択 (ChoicesOffline を使用) --}}
            <x-mary-choices-offline
                    label="{{ __('ledger.workflow.required_inspector_roles') }}"
                    wire:model="selectedInspectorRoleIds"
                    :options="$availableRoles" {{-- [['id' => ..., 'name' => ...]] のコレクション --}}
                    multiple
                    searchable
                    placeholder="{{ __('ledger.folder.form.placeholder.select_roles') }}"
                    no-result-text="{{ __('messages.info.no_results_found') }}" {{-- 翻訳キー --}}
                    class="mb-4" {{-- 下にマージン --}}
            />
            @error('selectedInspectorRoleIds') <span class="text-xs text-error">{{ $message }}</span> @enderror

            {{-- 必須承認ロール選択 (ChoicesOffline を使用) --}}
            <x-mary-choices-offline
                    label="{{ __('ledger.workflow.required_approver_roles') }}"
                    wire:model="selectedApproverRoleIds"
                    :options="$availableRoles"
                    multiple
                    searchable
                    placeholder="{{ __('ledger.folder.form.placeholder.select_roles') }}"
                    no-result-text="{{ __('messages.info.no_results_found') }}"
            />
            @error('selectedApproverRoleIds') <span class="text-xs text-error">{{ $message }}</span> @enderror
        </div>

        {{--        <x-slot:actions>--}}
        {{-- 統一アクションバー（透過・ホバー＆スライドアップ対応） --}}
        <div class="mx-auto w-full lg:w-2/3 fixed bottom-0 lg:bottom-4 inset-x-0 z-50 lg:px-4 transition-transform duration-300 ease-in-out"
             x-data="{ expanded: false, isLg: window.innerWidth >= 1024 }"
             @resize.window="isLg = window.innerWidth >= 1024"
             :style="(!isLg && !expanded) ? 'transform: translateY(calc(100% - 3.5rem));' : 'transform: translateY(0);'"
             @click.outside="if(!isLg) expanded = false"
        >
            <div class="shadow-[0_-10px_40px_rgba(0,0,0,0.1)] lg:shadow-md bg-base-300 transition-opacity duration-300 opacity-100 lg:opacity-[0.65] lg:hover:opacity-100 rounded-t-3xl lg:rounded-box border-t border-base-200 lg:border-none overflow-hidden flex flex-col">
                {{-- タブレット用引き上げタブ (Edge-to-Edge) --}}
                <div class="lg:hidden w-full flex flex-col items-center justify-center cursor-pointer h-14 bg-base-300 hover:bg-base-200 active:bg-base-200 transition-colors border-b border-base-content/10 flex-shrink-0" @click="expanded = !expanded">
                    <div class="w-20 h-1.5 bg-base-content/30 rounded-full mb-2"></div>
                    <div class="flex items-center text-base-content/80 text-sm font-bold tracking-wider gap-2">
                        <i class="fa-solid fa-chevron-up transition-transform duration-300" :class="expanded ? 'rotate-180' : ''"></i>
                        <span x-text="expanded ? '{{ __('ledger.action_bar_close') }}' : '{{ __('ledger.action_bar_open') }}'"></span>
                    </div>
                </div>

                <div class="p-4 lg:p-4 pb-8 lg:pb-4 overflow-y-auto max-h-[60vh]">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-wrap items-center justify-center md:justify-between gap-4">
                            <div class="flex flex-wrap items-center justify-center gap-2 order-2 md:order-1">
                                @if($formDisabled)
                                    {{-- 削除後は閉じるボタンのみ --}}
                                    <x-mary-button label="{{ __('ledger.close_window') }}" onclick="window.close()" class="btn-primary" icon="o-x-mark"/>
                                @else
                                    @if($justSaved && !$isCreating)
                                        {{-- 更新保存直後 --}}
                                        <x-mary-button label="{{ __('ledger.continue_editing') }}" class="btn-ghost" wire:click="$set('justSaved', false)"/>
                                        <x-ledger.close-window-button/>
                                    @elseif($justSaved && $isCreating)
                                        {{-- 新規保存直後 --}}
                                        <x-mary-button label="{{ __('ledger.create_another') }}" class="btn-outline px-4" wire:click="resetFormForNew" spinner="resetFormForNew"/>
                                        <x-mary-button label="{{ __('ledger.edit_this_folder') }}" wire:click="$set('justSaved', false)" class="btn-ghost"/>
                                        <x-ledger.close-window-button/>
                                    @else
                                        {{-- 通常の閉じるボタン --}}
                                        <x-ledger.close-window-button/>
                                    @endif
                                @endif

                                @if(!$isCreating && $folder->exists && !$formDisabled)
                                    <label wire:click="confirmFolderDeletion" class="btn btn-outline btn-error font-medium">
                                        <i class="fa-solid fa-trash mr-1"></i>{{ __('actions.delete') }}
                                    </label>
                                @endif
                            </div>
                            
                            <div class="flex flex-wrap items-center justify-center gap-2 order-1 md:order-2">
                                @if(!$formDisabled)
                                    @if($justSaved)
                                        {{-- 保存直後はメインボタンを隠すか、必要なら再表示。今回は元の仕様を尊重しボタン表示なし --}}
                                    @else
                                        {{-- 通常の保存/更新ボタン --}}
                                        <x-mary-button
                                                label="{{ $isCreating ? __('actions.create') : __('actions.update') }}"
                                                class="btn-primary btn-lg px-8 tracking-wide shadow-md" type="submit" spinner="save"
                                                icon="o-pencil-square"/>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{--        </x-slot:actions>--}}
    </x-mary-form>

    {{-- 削除確認モーダル --}}
    <x-mary-modal wire:model="confirmingFolderDeletion"
                  title="{{ __('ledger.folder.form.modal_title.confirm_delete') }}" persistent>
        <div>
            <p class="mb-4">{{ __('ledger.folder.remove_message', ['name' => $folder->title ?? '']) }}</p>
            <p class="text-sm text-warning">{{ __('ledger.folder.form.warning.cannot_delete_if_children_exist') }}</p>
        </div>
        <x-slot:actions>
            <x-mary-button label="{{ __('actions.cancel') }}" @click="$wire.confirmingFolderDeletion = false"
                           icon="o-x-mark"/>
            <x-mary-button label="{{ __('ledger.delete_confirm') }}" class="btn-error" wire:click="deleteFolder"
                           spinner="deleteFolder" icon="o-trash"/>
        </x-slot:actions>
    </x-mary-modal>
</div>