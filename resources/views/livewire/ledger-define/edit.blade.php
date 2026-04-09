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

                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <x-ledger.close-window-button />
                        <x-mary-button
                                label="{{__('ledger.go_to')}}"
                                icon="o-arrow-right-circle"
                                class="btn-outline btn-neutral h-12"
                                link="{{ route('ledgersByDefineId', ['tenant' => tenant()?->id, 'defineId' => $ledgerDefineRecord->id]) }}"
                        />
                        <label for="delete-modal" class="btn btn-outline btn-error font-medium h-12">
                            <i class="fa-solid fa-trash mr-1"></i>{{__('ledger.define.remove')}}
                        </label>
                    </div>
                    <x-mary-button label="{{__('ledger.save')}}"
                                   icon="o-check"
                                   class="btn-primary btn-lg px-8 tracking-wide shadow-md"
                                   type="submit"
                                   spinner="store"/>
                </div>
            </x-mary-card>
        </div>
    </x-mary-form>
</div>
