<div class="space-y-8 pb-32">
    <x-mary-form wire:submit="store" class="space-y-6">
        {{-- 基本設定セクション --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="card bg-base-100 border border-base-300 shadow-sm">
                <div class="card-body p-6 space-y-4">
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
                </div>
            </div>

            <div class="card bg-base-100 border border-base-300 shadow-sm">
                <div class="card-body p-6">
                    <h3 class="text-sm font-black text-base-content/40 uppercase tracking-widest flex items-center gap-2 mb-4">
                        <x-mary-icon name="o-arrow-path" class="w-4 h-4" />
                        {{ __('ledger.workflow.title') }}
                    </h3>

                    <div class="bg-base-200/50 p-4 rounded-xl border border-base-200">
                        <x-mary-toggle wire:model="workflow_enabled"
                                       label="{{ __('ledger.define.enable_workflow') }}"
                                       hint="{{ __('ledger.define.enable_workflow_hint') }}"
                                       right tight class="toggle-primary" />
                    </div>

                    <div class="mt-4 text-xs text-base-content/50 leading-relaxed italic">
                        {{ __('ledger.workflow.enable_description') ?? '有効にすると、この台帳の登録・更新時に承認フローが必須となります。' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- 説明文セクション --}}
        <div class="card bg-base-100 border border-base-300 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-base-200 flex justify-between items-center bg-base-200/20">
                <h3 class="text-sm font-black text-base-content/40 uppercase tracking-widest flex items-center gap-2">
                    <x-mary-icon name="o-document-text" class="w-4 h-4" />
                    {{ __('ledger.explanation') }}
                </h3>
                {{-- トグルによる全展開管理はここでは descriptionGroup (単一選択) なので不要 --}}
            </div>

            <div x-data="{
                     descriptionGroup: @entangle('descriptionGroup'),
                     toggle(name) {
                         this.descriptionGroup = (this.descriptionGroup === name) ? '' : name;
                     }
                 }" class="divide-y divide-base-200">

                {{-- Create Description --}}
                <div class="collapse collapse-arrow rounded-none"
                     :class="{ 'collapse-open': descriptionGroup === 'createDescription' }">
                    <div class="collapse-title text-sm font-bold cursor-pointer hover:bg-base-200/30 transition-colors" @click="toggle('createDescription')">
                        <div class="flex items-center gap-2">
                            <x-mary-icon name="o-plus-circle" class="w-4 h-4 text-primary" />
                            {{__('ledger.define.create_description')}}
                        </div>
                    </div>
                    <div class="collapse-content bg-base-200/10">
                        <div class="pt-4 px-2">
                            <x-mary-markdown wire:model="createDescription" />
                        </div>
                    </div>
                </div>

                {{-- List Description --}}
                <div class="collapse collapse-arrow rounded-none"
                     :class="{ 'collapse-open': descriptionGroup === 'listDescription' }">
                    <div class="collapse-title text-sm font-bold cursor-pointer hover:bg-base-200/30 transition-colors" @click="toggle('listDescription')">
                        <div class="flex items-center gap-2">
                            <x-mary-icon name="o-list-bullet" class="w-4 h-4 text-success" />
                            {{__('ledger.define.list_description')}}
                        </div>
                    </div>
                    <div class="collapse-content bg-base-200/10">
                        <div class="pt-4 px-2">
                            <x-mary-markdown wire:model="listDescription" />
                        </div>
                    </div>
                </div>

                {{-- Detail Description --}}
                <div class="collapse collapse-arrow rounded-none"
                     :class="{ 'collapse-open': descriptionGroup === 'detailDescription' }">
                    <div class="collapse-title text-sm font-bold cursor-pointer hover:bg-base-200/30 transition-colors" @click="toggle('detailDescription')">
                        <div class="flex items-center gap-2">
                            <x-mary-icon name="o-magnifying-glass-circle" class="w-4 h-4 text-secondary" />
                            {{__('ledger.define.detail_description')}}
                        </div>
                    </div>
                    <div class="collapse-content bg-base-200/10">
                        <div class="pt-4 px-2">
                            <x-mary-markdown wire:model="detailDescription" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-4 mt-8">
            <x-ledger.close-window-button />
            <x-mary-button label="{{__('ledger.save')}}"
                           icon="o-check"
                           class="btn-primary btn-md px-10 shadow-lg"
                           type="submit"
                           spinner="store"/>
        </div>
    </x-mary-form>
</div>
