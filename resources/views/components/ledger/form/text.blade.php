{{--<input type="hidden" name="content[{{$columnDefine->id}}]" value=" ">--}}

<input type="text" wire:model.lazy="content.{{$columnDefine->id}}" id="content[{{$columnDefine->id}}]"
       class="input input-bordered"
       name="content[{{$columnDefine->id}}]" value="{{$this->content[$columnDefine->id] ?? ''}}"
       style="width:{{mb_strlen($this->content[$columnDefine->id])<10?10:mb_strlen($this->content[$columnDefine->id])+2}}em"
       onInput="if(this.value.length>10){this.style.width = (this.value.length+2)+'em'}"
/>

