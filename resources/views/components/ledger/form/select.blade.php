@props([
    'class'=>'input-primary',
    'icon'=>'o-chevron-up-down',
    ])
@php
    $tmpOptions=[];
    if($columnDefine->required){
        $icon='c-check-circle';
        $class="input-accent";
    }else{
        $tmpOptions[]=['id'=>'', 'name'=>'-', 'selected'=>false, 'class'=>$class];
    }

    foreach ($columnDefine->options as $oKey => $option){
      $tmpSelected = ($option==($this->content[$columnDefine->id]??'') )? true : false;
      $tmpOptions[] = [
          'id'=>$option,
          'name'=>$option,
          'selected'=>$tmpSelected,
          'class'=>$class
          ];
    }
@endphp

@if(count($tmpOptions) > 5)
    <x-mary-select label="{{$columnDefine->name}}"
                   icon="{{$icon}}"
                   id="content[{{$columnDefine->id}}]"
                   name="content[{{$columnDefine->id}}]"
                   wire:model.blur="content.{{$columnDefine->id}}"
                   :options="$tmpOptions"
                   class="@if($columnDefine->required) input-accent @endif"
                   :required="$columnDefine->required"
                   :class="$class"
                   :hint="$columnDefine->hint"

    />
@else
    {{--
        <div class="flex flex-wrap items-center space-y-2">
            @if($columnDefine->required)
                <i class="fas fa-check-circle text-neutral/50 mr-2 mt-8"></i>
            @endif
    --}}
    <x-mary-radio label="{{$columnDefine->name}}"
                  :options="$tmpOptions"
                  wire:model.live="content.{{$columnDefine->id}}"
                  :required="$columnDefine->required"
                  class="flex w-full"
                  :hint="$columnDefine->hint"
    />
    {{--    </div>--}}
@endif
