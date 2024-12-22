<div>

    <div class="flex flex-wrap items-center justify-center">
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
            <x-slot:actions>
                <x-mary-button label="{{__('ledger.save')}}"
                               icon="o-pencil-square"
                               class="btn btn-primary btn-sm"
                               type="submit"
                               spinner="store"/>
            </x-slot:actions>

        </x-mary-form>
    </div>

</div>
