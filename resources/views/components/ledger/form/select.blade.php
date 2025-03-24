@props([
    'class'=>'input-primary',
    'icon'=>'o-chevron-up-down',
    'isDemo'=>false,
    'columnDefine' => null,
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
    $required = $columnDefine->required ? 'required=required' : "";
@endphp
@if ($type === 'demo-select')
    <x-mary-select
        label="{{ $columnDefine->name }}"
        icon="{{ $icon }}"
        id="content[{{ $columnDefine->id }}]"
        name="content[{{ $columnDefine->id }}]"
        class="{{ $class . ($columnDefine->required ? ' input-accent' : '') }}"
        :options="$tmpOptions"
        required="{{ $columnDefine->required }}"
        hint="{{ $columnDefine->hint }}"
    ></x-mary-select>
@elseif ($type === 'demo-radio')
    <x-mary-radio
        label="{{ $columnDefine->name }}"
        :options="$tmpOptions"
        required="{{ $columnDefine->required }}"
        hint="{{ $columnDefine->hint }}"
        inline
    ></x-mary-radio>
@elseif ($type === 'select' && $columnDefine->required)
    <x-mary-select
        wire:model.live="content.{{ $columnDefine->id }}"
        label="{{ $columnDefine->name }}"
        icon="{{ $icon }}"
        id="content[{{ $columnDefine->id }}]"
        name="content[{{ $columnDefine->id }}]"
        class="{{ $class . ($columnDefine->required ? ' input-accent' : '') }}"
        :options="$tmpOptions"
        hint="{{ $columnDefine->hint }}"
        required
    ></x-mary-select>
@elseif ($type === 'select')
    <x-mary-select
        wire:model.live="content.{{ $columnDefine->id }}"
        label="{{ $columnDefine->name }}"
        icon="{{ $icon }}"
        id="content[{{ $columnDefine->id }}]"
        name="content[{{ $columnDefine->id }}]"
        class="{{ $class . ($columnDefine->required ? ' input-accent' : '') }}"
        :options="$tmpOptions"
        hint="{{ $columnDefine->hint }}"
    ></x-mary-select>
@elseif($columnDefine->required)
    <x-mary-radio
        wire:model.live="content.{{ $columnDefine->id }}"
        label="{{ $columnDefine->name }}"
        :options="$tmpOptions"
        hint="{{ $columnDefine->hint }}"
        required
        inline
    ></x-mary-radio>
@else
    <x-mary-radio
        wire:model.live="content.{{ $columnDefine->id }}"
        label="{{ $columnDefine->name }}"
        :options="$tmpOptions"
        required="{{ $columnDefine->required }}"
        hint="{{ $columnDefine->hint }}"
        inline
    ></x-mary-radio>
@endif
