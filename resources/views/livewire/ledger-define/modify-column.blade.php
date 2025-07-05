<div>
    {{-- Loading indicator --}}
    <div wire:loading wire:target="save,addColumn,removeColumn" class="z-50 fixed inset-0 bg-base-300/50 transition-opacity">
        <div class="flex h-screen justify-center items-center">
            <span class="loading loading-dots loading-lg"></span>
        </div>
    </div>

    {{-- Parent window reload script (no changes needed) --}}
    <script>
        window.addEventListener('reloadParentWindow', () => {
            if (window.opener && !window.opener.closed) {
                window.opener.location.reload();
            }
        });
    </script>

    <form wire:submit.prevent="save">
        @if(!empty($columns))
            <ul wire:sortable="updateColumnOrder" wire:sortable.options="{ animation: 500 }" class="space-y-3">
                @foreach($columns as $index => $column)
                    <li wire:sortable.item="{{ $column['id'] }}" wire:key="column-{{ $column['id'] }}" class="">
                        <div wire:sortable.handle
                             class="flex collapse-title bg-primary/20 text-primary-content peer-checked:bg-secondary peer-checked:text-secondary-content peer-checked:pl-16 hover:opacity-80 rounded-tl-lg rounded-tr-lg">
                            <div class="flex flex-row w-full">
                                <h3 class="text-lg">
                                    {{ $column['id'] }}: {{ $column['name'] }} : {{ $columnInputTypes[$column['type']] ?? $column['type'] }}
                                </h3>
                            </div>
                            <button class="btn btn-sm tooltip tooltip-left" data-tip="{{__('ledger.column.drag2sort')}}">
                                <i class="fa-solid fa-grip-lines"></i>
                            </button>
                        </div>
                        <div class="collapse rounded-none bg-base-200 text-primary-content">
                            <input type="checkbox" name="collapse_{{$column['id']}}" id="collapse_{{$column['id']}}" class="peer collapse_swap hidden"/>
                            <div class="collapse-content">
                                <div class="items-center flex flex-row space-x-10">
                                    <div class="basis-1/2 space-y-4">
                                        <x-mary-input label="{{__('ledger.column.title')}}" placeholder="{{__('ledger.column.title')}}" icon="o-table-cells" required wire:model="columns.{{$index}}.name" wire:key="name-{{$column['id']}}" class="input-accent"/>

                                        @php
                                            $typeOptions = array_map(function($value, $name) {
                                                return ['id' => $value, 'name' => $name];
                                            }, array_keys($columnInputTypes), array_values($columnInputTypes));
                                        @endphp
                                        <x-mary-select label="{{__('ledger.column.type')}}" icon="o-chevron-up-down" wire:model="columns.{{$index}}.type" wire:key="type-{{$column['id']}}" :options="$typeOptions" class="input-accent" required/>

                                        <hr/>
                                        <x-mary-checkbox label="{{__('ledger.column.required')}}" wire:model="columns.{{$index}}.required" wire:key="required-{{$column['id']}}"/>
                                        <x-mary-checkbox label="{{__('ledger.column.unique')}}" wire:model="columns.{{$index}}.unique" wire:key="unique-{{$column['id']}}"/>
                                        <x-mary-checkbox label="{{__('ledger.column.sort')}}" wire:model="columns.{{$index}}.sortBy" wire:key="sortBy-{{$column['id']}}"/>
                                    </div>

                                    <div class="basis-1/2 space-y-4 m-3">
                                        <x-mary-textarea label="{{__('ledger.column.hint')}}" wire:model="columns.{{$index}}.hint" class="input-accent w-full" wire:key="hint-{{$column['id']}}"/>

                                        @if(isset($column['file']['path']))
                                            <a href="{{ asset('storage/'.$column['file']['path']) }}" target="_blank">
                                                <img src="{{ asset('storage/thumbnails/'.$column['file']['path']) }}" alt="{{ $column['file']['name'] }}">
                                            </a>
                                            <button class="btn btn-sm tooltip" data-tip="{{__('ledger.column.delete_file')}}" wire:click="deleteFile({{$index}})">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        @else
                                            <x-mary-file label="{{__('ledger.column.bg_file')}}" wire:model="columnUploadedFile.{{$column['id']}}" class="input-accent" wire:key="file-{{$column['id']}}" hint="png, jpg, jpeg, gif, svg"/>
                                        @endif

                                        @if((new \App\Models\ColumnDefine((object)$column))->useOptions)
                                            <x-mary-tags label="{{__('ledger.options')}}" wire:model="columns.{{$index}}.options" wire:key="options-{{$column['id']}}" icon="o-tag" hint="Hit enter to create a new tag"/>
                                        @endif

                                        <div class="mt-3 flex items-center justify-end w-full space-x-2">
                                            <x-mary-button label="{{__('actions.save')}}" wire:click="saveColumn({{$index}})" class="btn-primary btn-sm" spinner="saveColumn({{$index}})"/>
                                            <label for="delete-modal-{{$column['id']}}" class="btn btn-outline btn-sm btn-error ml-10 justify-self-end"><i class="fa-solid fa-trash mr-1"></i> {{__('ledger.column.remove')}}</label>
                                        </div>
                                        <input type="checkbox" id="delete-modal-{{$column['id']}}" class="modal-toggle hidden"/>
                                        <div class="modal" role="dialog">
                                            <div class="modal-box">
                                                <h3 class="font-bold text-lg">{{__('ledger.column.remove')}}</h3>
                                                <p class="py-4">{{__('ledger.column.remove_message',['name'=>$column['name']])}}</p>
                                                <p class="text-lg text-bold text-error">{{__('ledger.column.will_ledger_delete_message')}}</p>
                                                <div class="modal-action">
                                                    <label for="delete-modal-{{$column['id']}}" wire:click.prevent="removeColumn({{$index}})" class="btn btn-error">{{__('ledger.column.remove')}}</label>
                                                    <label for="delete-modal-{{$column['id']}}" class="btn btn-outline ml-5">{{__('actions.cancel')}}</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <label for="collapse_{{ $column['id'] }}" class="btn btn-sm btn-primary bg-primary/20 hover:bg-primary/50 w-full tooltip rounded-none rounded-bl-lg rounded-br-lg" data-tip="{{__('ledger.collapse')}}">
                            <div class="pt-2">
                                <span class="swap-on"> <i class="fas fa-angles-down"></i></span>
                                <span class="swap-off"> <i class="fas fa-angles-up"></i></span>
                            </div>
                        </label>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-6 flex justify-between items-center">
            <button type="button" wire:click="addColumn" class="btn btn-outline btn-secondary btn-sm">
                <i class="fa-solid fa-plus-circle mr-1"></i>
                {{__('ledger.column.add')}}
            </button>
            <x-mary-button label="{{__('actions.save')}}" type="submit" class="btn-primary" spinner="save"/>
        </div>
    </form>
</div>
