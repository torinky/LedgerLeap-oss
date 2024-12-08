<!-- text.blade.php -->
<!--
    テキスト入力フォームの幅を動的に調整するためのLivewireコンポーネントのビューファイルです。
    $columnDefine->idに基づいて動的なフォームを作成します。
    フォームの入力内容はcontent[$columnDefine->id]というLivewireのプロパティで管理されます。
-->
@props([
    'class'=>'input-primary',
    'icon'=>'o-chat-bubble-oval-left-ellipsis',
    'columnDefine'=>[],
    'isDemo'=>false,
    ])

{{--
@php
    if($columnDefine->required){
        $icon='c-check-circle';
        $class="input-accent";
    }

@endphp
--}}

@if($isDemo)
    <x-mary-input
        {{--        wire:model.blur="content.{{$columnDefine->id}}"--}}
        label="{{$columnDefine->name}}"
        name="content[{{$columnDefine->id}}]"
        placeholder="{{$columnDefine->name}}"
        icon="{{$icon}}"
        clearable
        class="{{$class}}"
        required="{{$columnDefine->required}}"
        hint="{{$columnDefine->hint}}"
        x-on:focus="
        const opacityBlock = event.target.closest('.opacity-control-block');
        opacityBlock.classList.add('opacity-100');
        opacityBlock.classList.remove('opacity-50');
        updateBackground('{{$columnDefine->id}}');
        "
        x-on:blur="
        const opacityBlock = event.target.closest('.opacity-control-block');
        opacityBlock.classList.add('opacity-50');
        opacityBlock.classList.remove('opacity-100');
        "
    />
@else
    <x-mary-input
        wire:model.blur="content.{{$columnDefine->id}}"
        label="{{$columnDefine->name}}"
        name="content[{{$columnDefine->id}}]"
        placeholder="{{$columnDefine->name}}"
        icon="{{$icon}}"
        clearable
        class="{{$class}} focus:opacity-100"
        required="{{$columnDefine->required}}"
        hint="{{$columnDefine->hint}}"
        x-on:focus="
        const opacityBlock = event.target.closest('.opacity-control-block');
        opacityBlock.classList.add('opacity-100');
        opacityBlock.classList.remove('opacity-50');
        updateBackground('{{$columnDefine->id}}');
        "
        x-on:blur="
        const opacityBlock = event.target.closest('.opacity-control-block');
        opacityBlock.classList.add('opacity-50');
        opacityBlock.classList.remove('opacity-100');
        "
    />
@endif
