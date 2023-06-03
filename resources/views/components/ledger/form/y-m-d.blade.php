<input type="date" wire:model="content.{{$columnDefine->id}}" id="content[{{$columnDefine->id}}]"
       name="content[{{$columnDefine->id}}]"
       value="{{$this->content[$columnDefine->id] ?? ''}}"
       class="input input-bordered datepicker"
/>
