@props([
    'target' => null,
    'tier' => 2,
    'message' => null,
    'delay' => true,
])

@php
    $overlayClasses = match((int)$tier) {
        1 => 'fixed inset-0 z-[100] flex flex-col items-center justify-center bg-base-100/50 backdrop-blur-[2px] transition-all',
        default => 'absolute inset-0 z-40 flex flex-col items-center justify-center bg-base-100/30 backdrop-blur-[1px] rounded-[inherit] transition-all',
    };

    $spinnerClasses = match((int)$tier) {
        1 => 'loading loading-spinner loading-lg text-primary',
        default => 'loading loading-spinner loading-md text-primary/70',
    };
@endphp

<div
    @if($delay) wire:loading.delay @else wire:loading @endif
    @if($target) wire:target="{{ $target }}" @endif
    {{ $attributes->merge(['class' => $overlayClasses]) }}
>
    <div class="flex flex-col items-center p-6 bg-base-100/80 rounded-2xl shadow-xl ring-1 ring-base-content/5">
        <span class="{{ $spinnerClasses }}"></span>
        @if($message)
            <span class="mt-4 text-sm font-semibold tracking-wide text-base-content/80 animate-pulse">
                {{ $message }}
            </span>
        @endif
    </div>
</div>
