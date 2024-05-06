<label for="window_close_modal"
       class="btn btn-outline btn-info ml-10 btn-sm justify-self-end">
    <i class="fa-solid fa-close mr-2"></i>{{__('ledger.close_window')}}</label>

<input type="checkbox" id="window_close_modal" class="modal-toggle"/>
<div class="modal" role="dialog">
    <div class="modal-box">
        <h3 class="font-bold text-lg"><i
                class="fa-solid fa-close mr-2"></i>{{__('ledger.close_window_modal')}}
        </h3>
        <p class="py-4">{{__('ledger.close_window_message')}}</p>
        <div class="modal-action">
            <a href="#" class="btn btn-error" onclick="window.close();"><i
                    class="fa-solid fa-close mr-2"></i>{{__('ledger.close_window')}}</a>
            <label for="window_close_modal" class="btn"><i
                    class="fa-solid fa-pencil mr-2"></i>{{__('ledger.continue_edit')}}
            </label>
        </div>
    </div>
</div>
