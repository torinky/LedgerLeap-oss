<div>


    <div
        class="background-image-change"
        x-data="{
            currentBg: null,
            updateBackground(columnId) {
                this.currentBg = $wire.backgroundImages[columnId] || null;

//                console.log($wire.backgroundImages);
//                console.log(this.currentBg);

                if(this.currentBg == null || this.currentBg.length == 0) {
                    document.querySelector('.background-image-change').style.backgroundImage = ``;
                }else{
                    document.querySelector('.background-image-change').style.backgroundImage = `url('${this.currentBg}')`;
                }
            },
            focusFirstInput() {
                const firstInput = document.querySelector('.background-image-change input:first-child');
                if (firstInput) {
                    firstInput.focus();
                }
            }
        }"
        x-init="focusFirstInput()"
    >


        {{--
                @if (session('status'))
                    @include('components.ledger.alert',[
                       'type'=>'success',
                       'message'=>session('status'),
                       'refreshParentWindow'=>true,
                    ])
                @endif
        --}}

        <h1 class="text-2xl my-3 font-black">
            <x-mary-icon name="o-home"/>{{$ledgerDefineRecord->title}}</h1>

        <div class="divider"></div>
        <div class="alert alert-neutral">
            <x-mary-icon name="o-folder"/>{{$ledgerDefineRecord->folder->title}}</div>
        <div class="divider"></div>

        <x-mary-accordion wire:model="descriptionGroup" class="rounded-lg bg-base-200 border-base-300 border">
            <x-mary-collapse name="createDescription">
                <x-slot:heading>
                    {{--                    {{__('ledger.define.create_description')}}--}}
                    <button
                        wire:click="toggleDescriptionGroup('createDescription')">{{__('ledger.define.create_description')}}</button>
                </x-slot:heading>
                <x-slot:content>
                    <x-markdown class="prose text-sm leading-relaxed max-w-none">
                        {!! $ledgerDefineRecord->create_description !!}
                    </x-markdown>
                </x-slot:content>
            </x-mary-collapse>
            <x-mary-collapse name="listDescription">
                <x-slot:heading>
                    {{--                    {{__('ledger.define.list_description')}}--}}
                    <button
                        wire:click="toggleDescriptionGroup('listDescription')">{{__('ledger.define.list_description')}}</button>
                </x-slot:heading>
                <x-slot:content>
                    <x-markdown class="prose text-sm leading-relaxed max-w-none">
                        {!! $ledgerDefineRecord->list_description !!}
                    </x-markdown>
                </x-slot:content>
            </x-mary-collapse>
            <x-mary-collapse name="detailDescription">
                <x-slot:heading>
                    {{--                    {{__('ledger.define.detail_description')}}--}}
                    <button
                        wire:click="toggleDescriptionGroup('detailDescription')">{{__('ledger.define.detail_description')}}</button>
                </x-slot:heading>
                <x-slot:content>
                    <x-markdown class="prose text-sm leading-relaxed max-w-none">
                        {!! $ledgerDefineRecord->detail_description !!}
                    </x-markdown>
                </x-slot:content>
            </x-mary-collapse>
        </x-mary-accordion>

        <div class="divider"></div>

        <h1 class="text-xl my-3 font-bold">{{__('ledger.column.group_title')}}</h1>

        <div class=" space-y-3 mt-5 h-fit">
            @foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)
                <div
                    x-on:mouseenter="updateBackground('{{ $columnDefine->id }}')"
                    class="opacity-control-block opacity-50 hover:opacity-100 transition-opacity duration-500 ease-in-out p-2 rounded hover:bg-base-100/80  {{ $loop->first ? 'initial-opacity-100' : '' }}"
                    @if($loop->first)
                        x-on:mouseleave="event.target.classList.remove('initial-opacity-100')"
                    x-init="updateBackground('{{ $columnDefine->id }}')"
                    @endif
                >
                    @if($columnDefine->type=='files')
                        <div class="form-control">
                            <div class="pt-0 label label-text font-semibold">
                        <span>
                             {{$columnDefine->name}}
                            @if($columnDefine->required)
                                <span class="text-error">*</span>
                            @endif
                        </span>
                            </div>

                            <button class="btn btn-lg w-full">{{__('ledger.column.file.upload')}}</button>
                            @if($columnDefine->hint)
                                <div class="label-text-alt text-gray-400 ps-1 mt-2"
                                     x-classes="label-text-alt text-gray-400 ps-1 mt-2">{{ $columnDefine->hint }}</div>
                            @endif

                        </div>
                    @else
                        @php
                            $componentName = 'ledger.form.'. Str::kebab($columnDefine->type);
                            // auto_number タイプの場合、text コンポーネントを使用
                            if ($columnDefine->type === 'auto_number') {
                                $componentName = 'ledger.form.text';
                            }
                        @endphp
                        <x-dynamic-component
                            :component="$componentName"
                            wire:model.live="content"
                            :columnDefine="$columnDefine"
                            :ledgerRecord="$ledgerRecord??[]"
                            :isDemo="true"
                        />
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
