<div class="space-y-4 pb-32">
    <x-mary-form wire:submit="store" class="space-y-4">
        {{-- 基本設定セクション --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="card bg-base-100 border border-base-300 shadow-sm transition-all hover:border-primary/30">
                <div class="card-body p-4 space-y-3">
                    <h3 class="text-sm font-black text-base-content/40 uppercase tracking-widest flex items-center gap-2 mb-2">
                        <x-mary-icon name="o-cog-6-tooth" class="w-4 h-4" />
                        {{__('ledger.define.basic_setting')}}
                    </h3>

                    <x-mary-input label="{{__('ledger.define.title')}}"
                                  wire:model="title"
                                  placeholder="{{$title}}" icon="o-pencil-square"
                                  required
                                  class="input-bordered focus:input-primary"
                    />

                    <x-mary-select
                            label="{{__('ledger.folder.containing')}}"
                            icon="o-folder" :options="$folderIdNameMap"
                            wire:model="parentFolderId" required
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

            <div class="card bg-base-100 border border-base-300 shadow-sm transition-all hover:border-primary/30">
                <div class="card-body p-4 space-y-3">
                    <h3 class="text-sm font-black text-base-content/40 uppercase tracking-widest flex items-center gap-2 mb-2">
                        <x-mary-icon name="o-arrow-path" class="w-4 h-4" />
                        {{ __('ledger.workflow.title') }}
                    </h3>

                    <div class="bg-base-200/30 p-3 rounded-lg border border-base-200">
                        <x-mary-toggle wire:model="workflow_enabled"
                                       label="{{ __('ledger.define.enable_workflow') }}"
                                       right tight class="toggle-sm toggle-primary"
                                       hint="{{ __('ledger.workflow.enable_description') }}"
                        />
                    </div>

                </div>
            </div>
        </div>

        {{-- 説明文セクション --}}
        <div class="card bg-base-100 border border-base-300 shadow-sm overflow-hidden transition-all hover:border-primary/20">
            <div x-data="{
                     descriptionGroup: @entangle('descriptionGroup'),
                     toggle(name) {
                         this.descriptionGroup = (this.descriptionGroup === name) ? '' : name;
                     }
                 }" class="divide-y divide-base-200">

                {{-- Create Description --}}
                <div class="collapse collapse-arrow rounded-none"
                     :class="{ 'collapse-open': descriptionGroup === 'createDescription' }">
                    <div class="collapse-title text-base font-bold cursor-pointer hover:bg-base-200/30 transition-colors py-3 min-h-0" @click="toggle('createDescription')">
                        <div class="flex items-center gap-3">
                            <x-mary-icon name="o-plus-circle" class="w-5 h-5 text-primary" />
                            {{__('ledger.define.create_description')}}
                        </div>
                    </div>
                    <div class="collapse-content bg-base-200/5">
                        <div class="pt-2 px-1">
                            <x-mary-markdown wire:model="createDescription" />
                        </div>
                    </div>
                </div>

                {{-- List Description --}}
                <div class="collapse collapse-arrow rounded-none"
                     :class="{ 'collapse-open': descriptionGroup === 'listDescription' }">
                    <div class="collapse-title text-base font-bold cursor-pointer hover:bg-base-200/30 transition-colors py-3 min-h-0" @click="toggle('listDescription')">
                        <div class="flex items-center gap-3">
                            <x-mary-icon name="o-list-bullet" class="w-5 h-5 text-success" />
                            {{__('ledger.define.list_description')}}
                        </div>
                    </div>
                    <div class="collapse-content bg-base-200/5">
                        <div class="pt-2 px-1">
                            <x-mary-markdown wire:model="listDescription" />
                        </div>
                    </div>
                </div>

                {{-- Detail Description --}}
                <div class="collapse collapse-arrow rounded-none"
                     :class="{ 'collapse-open': descriptionGroup === 'detailDescription' }">
                    <div class="collapse-title text-base font-bold cursor-pointer hover:bg-base-200/30 transition-colors py-3 min-h-0" @click="toggle('detailDescription')">
                        <div class="flex items-center gap-3">
                            <x-mary-icon name="o-magnifying-glass-circle" class="w-5 h-5 text-secondary" />
                            {{__('ledger.define.detail_description')}}
                        </div>
                    </div>
                    <div class="collapse-content bg-base-200/5">
                        <div class="pt-2 px-1">
                            <x-mary-markdown wire:model="detailDescription" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-ledger.sticky-action-bar>
            <x-slot:left>
                <x-ledger.close-window-button />
                @if ($ledgerDefineRecord?->id)
                    <x-mary-button label="{{ __('ledger.go_to') }}" icon="o-arrow-right-circle"
                        class="btn-outline btn-neutral h-12"
                        link="{{ route('ledgersByDefineId', ['tenant' => tenant()?->id, 'defineId' => $ledgerDefineRecord->id]) }}" />
                @endif
                <x-mary-button label="{{ __('ledger.define.remove') }}" icon="o-trash"
                               class="btn-outline btn-error font-medium h-12"
                               onclick="document.getElementById('delete-modal').showModal()" />
            </x-slot:left>
            <x-slot:right>
                <x-mary-button label="{{ __('ledger.save') }}" icon="o-check"
                    class="btn-primary btn-lg px-8 tracking-wide shadow-md" type="submit" spinner="store" />
            </x-slot:right>
        </x-ledger.sticky-action-bar>
    </x-mary-form>
</div>
