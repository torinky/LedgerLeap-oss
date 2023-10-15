<input type="date" wire:model.live="content.{{$columnDefine->id}}" id="content[{{$columnDefine->id}}]"
       name="content[{{$columnDefine->id}}]"
       value="{{$this->content[$columnDefine->id] ?? ''}}"
       class="input input-bordered datepicker @if($columnDefine->required) input-accent @endif"
/>
