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

        <x-mary-accordion wire:model="descriptionGroup">
            <x-mary-collapse name="createDescription">
                <x-slot:heading>
                    {{--                                        {{__('ledger.define.create_description')}}--}}
                    <button
                        wire:click.prevent="toggleDescriptionGroup('createDescription')">{{__('ledger.define.create_description')}}</button>
                </x-slot:heading>
                <x-slot:content>
                    <x-mary-markdown
                        {{--                        label="{{__('ledger.define.create_description')}}"--}}
                        wire:model="createDescription"
                    />
                </x-slot:content>
            </x-mary-collapse>
            <x-mary-collapse name="listDescription">
                <x-slot:heading>
                    {{--                                        {{__('ledger.define.list_description')}}--}}
                    <button
                        wire:click.prevent="toggleDescriptionGroup('listDescription')">{{__('ledger.define.list_description')}}</button>
                </x-slot:heading>
                <x-slot:content>
                    <x-mary-markdown
                        {{--                        label="{{__('ledger.define.list_description')}}"--}}
                        wire:model="listDescription"
                    />
                </x-slot:content>
            </x-mary-collapse>
            <x-mary-collapse name="detailDescription">
                <x-slot:heading>
                    {{--                                        {{__('ledger.define.detail_description')}}--}}
                    <button
                        wire:click.prevent="toggleDescriptionGroup('detailDescription')">{{__('ledger.define.detail_description')}}</button>
                </x-slot:heading>
                <x-slot:content>
                    <x-mary-markdown
                        {{--                        label="{{__('ledger.define.detail_description')}}"--}}
                        wire:model="detailDescription"
                    />
                </x-slot:content>
            </x-mary-collapse>
        </x-mary-accordion>

        <x-slot:actions>
            <x-mary-button label="{{__('ledger.save')}}"
                           icon="o-pencil-square"
                           class="btn btn-primary btn-sm"
                           type="submit"
                           spinner="store"/>
        </x-slot:actions>
    </x-mary-form>
</div>
