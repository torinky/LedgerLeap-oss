<div class="space-y-5">
    <x-mary-input label="{{__('ledger.define.title')}}"
                  wire:model="title"
                  wire:change="applyTitle"
                  placeholder="{{__('ledger.define.title')}}" icon="o-home" hint="Ledger title"
                  required
    />


    <x-mary-select
        label="{{__('ledger.folder.containing')}}"
        icon="o-folder" :options="$folderIdNameMap"
        wire:change="applyParentFolder"
        wire:model="parentFolderId" required
    />


</div>
