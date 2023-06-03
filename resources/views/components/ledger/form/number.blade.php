<input type="text" wire:model="content.{{$columnDefine->id}}" id="content[{{$columnDefine->id}}]"
       class="input input-bordered"
       name="content[{{$columnDefine->id}}]" value="{{$this->content[$columnDefine->id] ?? ''}}"/>
