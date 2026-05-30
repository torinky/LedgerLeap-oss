@php
@endphp

<div class="filament-tree-infolist flex flex-wrap gap-3">
    @foreach ($getComponents() as $infolistComponent)
        {{ $infolistComponent }}
    @endforeach
</div>
