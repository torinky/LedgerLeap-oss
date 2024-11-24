<div class="space-y-5">
    <x-mary-form wire:submit="store">
        <x-mary-input label="{{__('ledger.define.title')}}"
                      wire:model="title"
                      {{--                      wire:change="applyTitle"--}}
                      placeholder="{{$title}}" icon="o-home" hint="Ledger title"
                      required
        />
        <x-mary-select
            label="{{__('ledger.folder.containing')}}"
            icon="o-folder" :options="$folderIdNameMap"
            {{--            wire:change="applyParentFolder"--}}
            wire:model="parentFolderId" required
        />
        <x-slot:actions>
            <x-mary-button label="{{__('ledger.save')}}"
                           icon="o-pencil-square"
                           class="btn-primary btn-sm"
                           type="submit"
                           spinner="store"/>
        </x-slot:actions>
    </x-mary-form>
</div>
