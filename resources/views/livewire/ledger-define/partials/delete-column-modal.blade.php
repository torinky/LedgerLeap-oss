@props(['column', 'index'])

<div x-data>
    <template x-teleport="body">
        <div>
            <input type="checkbox" id="delete-modal-{{$column['id']}}" class="modal-toggle hidden"/>
            <div class="modal !z-[9999]" role="dialog">
                <div class="modal-box">
                    <h3 class="font-bold text-lg">{{__('ledger.column.remove')}}</h3>
                    <p class="py-4">{{__('ledger.column.remove_message',['name'=>$column['name']])}}</p>
                    <p class="text-lg text-bold text-error">{{__('ledger.column.will_ledger_delete_message')}}</p>
                    <div class="modal-action">
                        <label for="delete-modal-{{$column['id']}}"
                               wire:click.prevent="removeColumn({{$index}})"
                               class="btn btn-error">{{__('ledger.column.remove')}}</label>
                        <label for="delete-modal-{{$column['id']}}"
                               class="btn btn-outline ml-5">{{__('actions.cancel')}}</label>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
