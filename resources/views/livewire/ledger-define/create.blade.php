<div>

    <div class="flex flex-wrap items-center justify-center pb-32">
        <x-mary-form wire:submit="store">
            @csrf

            <x-mary-input
                wire:model="title"
                label="{{__('ledger.define.title')}}"
                placeholder="{{__('ledger.type_here')}}"
                icon="o-book-open"
                hint="Your full name"/>

            <x-mary-select
                label="{{__('ledger.folder.containing')}}"
                icon="o-folder" :options="$folderIdNameMap"
                {{--            wire:change="applyParentFolder"--}}
                wire:model="parentFolderId" required
            />

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
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <x-ledger.close-window-button />
                            <x-mary-button label="{{__('ledger.save')}}"
                                           icon="o-pencil-square"
                                           class="btn-primary btn-lg px-8 tracking-wide shadow-md"
                                           type="submit"
                                           spinner="store"/>
                        </div>
                    </div>
                </div>
            </div>

        </x-mary-form>
    </div>

</div>
