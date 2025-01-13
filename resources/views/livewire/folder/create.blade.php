<div>
    <div class="flex flex-wrap items-center justify-center">
        <x-mary-form wire:submit="store">
            @csrf

            <x-mary-input
                wire:model="title"
                label="{{__('ledger.folder.title')}}"
                placeholder="{{__('ledger.type_here')}}"
                icon="o-folder"
                required
            />

            <x-mary-select
                label="{{__('ledger.folder.containing')}}"
                icon="o-folder" :options="$folderIdNameMap"
                {{--            wire:change="applyParentFolder"--}}
                wire:model="parentFolderId" required
            />
            {{--
                        <x-slot:actions>
                            <x-mary-button label="{{__('ledger.save')}}"
                                           icon="o-pencil-square"
                                           class="btn btn-primary btn-sm"
                                           type="submit"
                                           spinner="store"/>
                        </x-slot:actions>
            --}}
            <div
                class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
                <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                    <div class="card-body ">
                        <div class="card-actions justify-center place-items-center">
                            <x-mary-button
                                type="submit"
                                class="btn btn-primary btn-lg btn-wide"
                                spinner="store"
                            >
                                <i class="fas fa-plus-circle mr-2"></i> {{__('ledger.save')}}
                            </x-mary-button>
                            <x-ledger.close-window-button/>

                        </div>
                    </div>
                </div>

            </div>

        </x-mary-form>
    </div>
</div>
