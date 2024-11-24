<div>
    @if (session('status'))
        @include('components.ledger.alert',[
           'type'=>'success',
           'message'=>session('status'),
           'refreshParentWindow'=>true,
        ])
    @endif

    <h1 class="text-2xl my-3 font-black">
        <x-mary-icon name="o-home"/>{{$ledgerDefineRecord->title}}</h1>
    <div class="divider"></div>
    <div class="alert alert-neutral">
        <x-mary-icon name="o-folder"/>{{$ledgerDefineRecord->folder->title}}</div>
    <div class="divider"></div>
    <h1 class="text-xl my-3 font-bold">{{__('ledger.column.group_title')}}</h1>

    <div class=" space-y-8 mt-5 h-fit">
        @foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)
            @if($columnDefine->type=='files')
                <x-dynamic-component :component="'ledger.form.'.$columnDefine->type"
                                     wire:model.live="content"
                                     wire:model.live="deletedContent"
                                     :columnDefine="$columnDefine"
                                     :ledgerRecord="$ledgerRecord??[]"
                                     multiple
                                     allowImagePreview
                                     imagePreviewMaxHeight="200"
                />

            @else
                {{--
                                @if($columnDefine->type=='textarea')
                                    @dd(Str::kebab($columnDefine->type))
                                    @dd($columnDefine)
                                @endif
                --}}
                <x-dynamic-component
                    :component="'ledger.form.'. Str::kebab($columnDefine->type)"
                    wire:model.live="content"
                    :columnDefine="$columnDefine"
                    :ledgerRecord="$ledgerRecord??[]"
                />
            @endif
        @endforeach
    </div>
</div>
