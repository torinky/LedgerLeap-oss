<!-- text.blade.php -->
<!--
    テキスト入力フォームの幅を動的に調整するためのLivewireコンポーネントのビューファイルです。
    $columnDefine->idに基づいて動的なフォームを作成します。
    フォームの入力内容はcontent[$columnDefine->id]というLivewireのプロパティで管理されます。
-->
@props([
    'class'=>'input-primary',
    'icon'=>'o-chat-bubble-oval-left-ellipsis',
    'columnDefine'=>[]
    ])
@php
    if($columnDefine->required){
        $icon='c-check-circle';
        $class="input-accent";
    }

@endphp

<x-mary-input
    label="{{$columnDefine->name}}"
    name="content[{{$columnDefine->id}}]"
    placeholder="{{$columnDefine->name}}"
    icon="{{$icon}}"
    {{--    hint="{{$columnDefine->name}}"--}}
    wire:model.blur="content.{{$columnDefine->id}}"
    clearable
    :class="$class"
    :required="$columnDefine->required"
/>

