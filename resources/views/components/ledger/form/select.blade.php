@props([
    'class'=>'input-primary',
    'icon'=>'o-chevron-up-down',
    'isDemo'=>false,
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


@php
    $type = $isDemo ? (count($tmpOptions) > 5 ? 'demo-select' : 'demo-radio') : (count($tmpOptions) > 5 ? 'select' : 'radio');
@endphp

@switch($type)
    @case('demo-select')
        <x-mary-select
            label="{{$columnDefine->name}}"
            icon="{{$icon}}"
            id="content[{{$columnDefine->id}}]"
            name="content[{{$columnDefine->id}}]"
            class="{{$class}}@if($columnDefine->required) input-accent @endif"
            :options="$tmpOptions"
            required="{{$columnDefine->required}}"
            hint="{{$columnDefine->hint}}"
        ></x-mary-select>
        @break
    @case('demo-radio')
        <x-mary-radio
            label="{{$columnDefine->name}}"
            :options="$tmpOptions"
            required="{{$columnDefine->required}}"
            class="flex w-full"
            hint="{{$columnDefine->hint}}"
        ></x-mary-radio>
        @break
    @case('select')
        <x-mary-select
            wire:model.live="content.{{$columnDefine->id}}"
            label="{{$columnDefine->name}}"
            icon="{{$icon}}"
            id="content[{{$columnDefine->id}}]"
            name="content[{{$columnDefine->id}}]"
            class="{{$class}}@if($columnDefine->required) input-accent @endif"
            :options="$tmpOptions"
            required="{{$columnDefine->required}}"
            hint="{{$columnDefine->hint}}"
        ></x-mary-select>
        @break
    @case('radio')
        <x-mary-radio
            wire:model.live="content.{{$columnDefine->id}}"
            label="{{$columnDefine->name}}"
            :options="$tmpOptions"
            required="{{$columnDefine->required}}"
            class="flex w-full"
            hint="{{$columnDefine->hint}}"
        ></x-mary-radio>
        @break
@endswitch
