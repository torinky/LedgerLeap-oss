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
        <div class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
            <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                <div class="card-body p-4">
                    <div class="flex  items-center justify-center gap-4">
                        @if($formDisabled)
                            {{-- 削除後は閉じるボタンのみ --}}
                            <x-mary-button label="{{ __('ledger.close_window') }}" onclick="window.close()"
                                           class="btn-primary"/>
                        @else
                            @if($justSaved && !$isCreating)
                                {{-- 更新保存直後 --}}
                                <x-mary-button label="{{ __('ledger.continue_editing') }}"
                                               class="btn-ghost"
                                               wire:click="$set('justSaved', false)"/>
                                <x-ledger.close-window-button/>
{{--
                                <x-mary-button label="{{ __('actions.close_window_after_save') }}"
                                               onclick="window.close()"
                                               class="btn-primary" icon="o-x-mark"/>
--}}
                            @elseif($justSaved && $isCreating)
                                {{-- 新規保存直後 --}}
                                <x-mary-button label="{{ __('ledger.create_another') }}" class="btn-outline"
                                               wire:click="resetFormForNew" spinner="resetFormForNew"/>
                                <x-mary-button label="{{ __('ledger.edit_this_folder') }}"
                                               wire:click="$set('justSaved', false)" class="btn-ghost"/>
                                <x-ledger.close-window-button/>
{{--
                                <x-mary-button label="{{ __('ledger.close_window_after_save') }}"
                                               onclick="window.close()"
                                               class="btn-primary"/>
--}}
                            @else
                                {{-- 通常の保存/更新ボタン --}}
                                <x-mary-button
                                        label="{{ $isCreating ? __('actions.create') : __('actions.update') }}"
                                        class="btn-primary md:btn-wide" type="submit" spinner="save"
                                        icon="o-pencil-square"/>
                                <x-ledger.close-window-button/>
{{--
                                <x-mary-button label="{{ __('ledger.cancel_and_close') }}"
                                               onclick="window.close()"
                                               class="btn-ghost" icon="o-x-mark"/>
--}}
                            @endif
                        @endif
                        @if(!$isCreating && $folder->exists && !$formDisabled)
                            <x-mary-button label="{{ __('actions.delete') }}"
                                           wire:click="confirmFolderDeletion"
                                           icon="o-trash" class="btn-error btn-outline"
                                           spinner="confirmFolderDeletion"/>
                        @endif
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