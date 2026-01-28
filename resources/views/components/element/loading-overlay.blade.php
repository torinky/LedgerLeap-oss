@props([
    'target' => null,
    'tier' => 2,
    'message' => null,
    'delay' => true,
    'manual' => false, // New prop to disable wire:loading
])

@php
    $overlayClasses = match((int)$tier) {
        1 => 'fixed inset-0 z-[110] flex items-center justify-center bg-base-300/60 backdrop-blur-sm transition-all duration-500 pointer-events-none min-h-screen w-full',
        default => 'absolute inset-0 z-30 flex items-center justify-center bg-transparent backdrop-blur-sm transition-all duration-300 pointer-events-none h-full w-full',
    };

    $spinnerClasses = match((int)$tier) {
        1 => 'loading loading-spinner w-24 text-primary',
        default => 'loading loading-spinner loading-lg text-primary',
    };
@endphp

<div
    @if(!$manual)
        @if($delay) wire:loading.delay @else wire:loading @endif
        @if($target) wire:target="{{ $target }}" @endif
    @endif
    {{ $attributes->merge(['class' => $overlayClasses]) }}
>
    {{-- Content centered by parent flex container --}}
    <div class="flex flex-col items-center justify-center space-y-4">
        {{-- スピナーのみを表示 (Glow効果を削除) --}}
        <div class="relative inline-flex items-center justify-center">
            <span class="{{ $spinnerClasses }} relative z-10 drop-shadow-xl"></span>
        </div>

        @if($message)
            <div class="mt-4">
                <span class="text-xs font-black tracking-widest text-primary uppercase animate-pulse">
                    {{ $message }}
                </span>
            </div>
        @endif
    </div>
</div>
