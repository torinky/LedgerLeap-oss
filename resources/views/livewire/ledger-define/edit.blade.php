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

        <x-mary-accordion wire:model="descriptionGroup" class="rounded-lg bg-base-200 border-base-300 border">
            <x-mary-collapse name="createDescription">
                <x-slot:heading>
                    {{--                                        {{__('ledger.define.create_description')}}--}}
                    <button
                            wire:click.prevent="toggleDescriptionGroup('createDescription')">{{__('ledger.define.create_description')}}</button>
                </x-slot:heading>
                <x-slot:content>
                    <x-mary-textarea
                            label="{{__('ledger.define.create_description')}}"
                            wire:model.live="createDescription"
                            rows="5"
                    />
                    <div class="mt-2 p-4 border rounded-md bg-base-200">
                        <label class="label-text font-bold">プレビュー</label>
                        <div class="prose text-sm leading-relaxed max-w-none mt-1">
                            {!! $this->createDescriptionPreview !!}
                        </div>
                    </div>
                </x-slot:content>
            </x-mary-collapse>
            <x-mary-collapse name="listDescription">
                <x-slot:heading>
                    {{--                                        {{__('ledger.define.list_description')}}--}}
                    <button
                            wire:click.prevent="toggleDescriptionGroup('listDescription')">{{__('ledger.define.list_description')}}</button>
                </x-slot:heading>
                <x-slot:content>
                    <x-mary-textarea
                            label="{{__('ledger.define.list_description')}}"
                            wire:model.live="listDescription"
                            rows="5"
                    />
                    <div class="mt-2 p-4 border rounded-md bg-base-200">
                        <label class="label-text font-bold">プレビュー</label>
                        <div class="prose text-sm leading-relaxed max-w-none mt-1">
                            {!! $this->listDescriptionPreview !!}
                        </div>
                    </div>
                </x-slot:content>
            </x-mary-collapse>
            <x-mary-collapse name="detailDescription">
                <x-slot:heading>
                    {{--                                        {{__('ledger.define.detail_description')}}--}}
                    <button
                            wire:click.prevent="toggleDescriptionGroup('detailDescription')">{{__('ledger.define.detail_description')}}</button>
                </x-slot:heading>
                <x-slot:content>
                    <x-mary-textarea
                            label="{{__('ledger.define.detail_description')}}"
                            wire:model.live="detailDescription"
                            rows="5"
                    />
                    <div class="mt-2 p-4 border rounded-md bg-base-200">
                        <label class="label-text font-bold">プレビュー</label>
                        <div class="prose text-sm leading-relaxed max-w-none mt-1">
                            {!! $this->detailDescriptionPreview !!}
                        </div>
                    </div>
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
