<div class="space-y-4 pb-32">
    <x-mary-form wire:submit="store" class="space-y-4">
        {{-- 基本設定セクション --}}
        <div class="card bg-base-100 border border-base-300 shadow-sm transition-all hover:border-primary/30">
            <div class="card-body p-4 space-y-3">
                <h3 class="text-sm font-black text-base-content/40 uppercase tracking-widest flex items-center gap-2 mb-2">
                    <x-mary-icon name="o-cog-6-tooth" class="w-4 h-4" />
                    {{__('ledger.define.basic_setting')}}
                </h3>

                <x-mary-input
                    label="{{__('ledger.define.title')}}"
                    wire:model="title"
                    placeholder="{{__('ledger.type_here')}}"
                    icon="o-book-open"
                    required
                    class="input-bordered focus:input-primary"
                />

                <x-mary-select
                    label="{{__('ledger.folder.containing')}}"
                    icon="o-folder"
                    :options="$folderIdNameMap"
                    wire:model="parentFolderId"
                    required
                    class="select-bordered focus:select-primary"
                />

                <hr class="border-base-200">

                {{-- 秘密区分・公開範囲 --}}
                <x-mary-select
                    label="{{ __('ledger.confidentiality.level.label') }}"
                    wire:model="confidentialityLevel"
                    :options="$confidentialityLevelOptions"
                    placeholder="{{ __('ledger.folder.form.placeholder.select_roles') }}"
                    allow-clear
                    class="select-bordered focus:select-primary"
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
        </div>

        <x-ledger.sticky-action-bar>
            <x-slot:left>
                <x-ledger.close-window-button />
            </x-slot:left>
            <x-slot:right>
                <x-mary-button label="{{ __('ledger.save') }}"
                    icon="o-check"
                    class="btn-primary btn-lg px-8 tracking-wide shadow-md"
                    type="submit"
                    spinner="store" />
            </x-slot:right>
        </x-ledger.sticky-action-bar>
    </x-mary-form>
</div>
