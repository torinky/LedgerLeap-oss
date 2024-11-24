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
  //    dd($tmpOptions);

  /*if($columnDefine->required){
      $label='<i class="fas fa-check-circle text-accent"></i>'.$columnDefine->name;
  }else{
      $label=$columnDefine->name;
  }*/
@endphp


{{--
<div class="form-control">
    <div class="label">
        <span class="label-text font-semibold">
             {{$columnDefine->name}}
        </span>
    </div>

    <select wire:model.live="content.{{$columnDefine->id}}" id="content[{{$columnDefine->id}}]"
            name="content[{{$columnDefine->id}}]"
            class="select select-bordered @if($columnDefine->required) input-accent @endif"
    >
        <option disabled>{{__('ledger.form.pickSelections')}}</option>

        @foreach ($columnDefine->options as $option)
            <option value="{{$option}}" @php(($option==($this->content[$columnDefine->id]??'') )? 'selected' : '')>
                {{$option}}
            </option>
        @endforeach
    </select>
</div>
--}}


<x-mary-select label="{{$columnDefine->name}}"
               icon="{{$icon}}"
               id="content[{{$columnDefine->id}}]"
               name="content[{{$columnDefine->id}}]"
               wire:model="content.{{$columnDefine->id}}"
               :options="$tmpOptions"
               class="@if($columnDefine->required) input-accent @endif"
               :required="$columnDefine->required"
               :class="$class"
/>
