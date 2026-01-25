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
        {{-- ★★★ ワークフロー有効化トグル (新規追加) ★★★ --}}
        <x-mary-toggle wire:model="workflow_enabled" label="{{ __('ledger.define.enable_workflow') }}"
                       hint="{{ __('ledger.define.enable_workflow_hint') }}" right tight/>

        {{-- Alpine.js controlled accordion for instantaneous feedback --}}
        <div class="rounded-lg bg-base-200 border-base-300 border overflow-hidden"
             x-data="{
                 descriptionGroup: @entangle('descriptionGroup'),
                 toggle(name) {
                     this.descriptionGroup = (this.descriptionGroup === name) ? '' : name;
                 }
             }">

            {{-- Create Description --}}
            <div class="collapse collapse-arrow border-b border-base-300 rounded-none"
                 :class="{ 'collapse-open': descriptionGroup === 'createDescription' }">
                <div class="collapse-title text-base font-bold cursor-pointer" @click="toggle('createDescription')">
                    {{__('ledger.define.create_description')}}
                </div>
                <div class="collapse-content">
                    <x-mary-markdown wire:model="createDescription" />
                </div>
            </div>

            {{-- List Description --}}
            <div class="collapse collapse-arrow border-b border-base-300 rounded-none"
                 :class="{ 'collapse-open': descriptionGroup === 'listDescription' }">
                <div class="collapse-title text-base font-bold cursor-pointer" @click="toggle('listDescription')">
                    {{__('ledger.define.list_description')}}
                </div>
                <div class="collapse-content">
                    <x-mary-markdown wire:model="listDescription" />
                </div>
            </div>

            {{-- Detail Description --}}
            <div class="collapse collapse-arrow rounded-none"
                 :class="{ 'collapse-open': descriptionGroup === 'detailDescription' }">
                <div class="collapse-title text-base font-bold cursor-pointer" @click="toggle('detailDescription')">
                    {{__('ledger.define.detail_description')}}
                </div>
                <div class="collapse-content">
                    <x-mary-markdown wire:model="detailDescription" />
                </div>
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="{{__('ledger.save')}}"
                           icon="o-pencil-square"
                           class="btn btn-primary btn-sm"
                           type="submit"
                           spinner="store"/>
        </x-slot:actions>
    </x-mary-form>
</div>
