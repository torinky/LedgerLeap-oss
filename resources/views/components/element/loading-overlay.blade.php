@props([
    'target' => null,
    'tier' => 2,
    'message' => null,
    'delay' => true,
])

@php
    $overlayClasses = match((int)$tier) {
        1 => 'fixed inset-0 z-[110] flex items-center justify-center bg-base-300/60 backdrop-blur-md transition-all duration-500 pointer-events-none min-h-screen w-full',
        default => 'absolute inset-0 z-30 flex items-center justify-center bg-base-100/30 backdrop-blur-[2px] transition-all duration-300 rounded-box pointer-events-none h-full w-full',
    };

    $spinnerClasses = match((int)$tier) {
        1 => 'loading loading-spinner w-24 text-primary',
        default => 'loading loading-spinner loading-lg text-primary',
    };
@endphp

<div
    @if($delay) wire:loading.delay @else wire:loading @endif
    @if($target) wire:target="{{ $target }}" @endif
    {{ $attributes->merge(['class' => $overlayClasses]) }}
>
    {{-- Content centered by parent flex container --}}
    <div class="flex flex-col items-center justify-center space-y-4 m-auto">
        {{-- スピナーを強調するための Glow 効果 --}}
        <div class="relative inline-flex items-center justify-center">
            <div class="absolute inset-0 bg-primary/20 blur-3xl rounded-full scale-[3.0] animate-pulse"></div>
            <span class="{{ $spinnerClasses }} relative z-10 drop-shadow-2xl"></span>
        </div>

        @if($message)
            <div class="mt-6 bg-base-100/80 backdrop-blur px-6 py-2 rounded-full border border-base-content/10 shadow-lg animate-bounce">
                <span class="text-sm font-black tracking-widest text-base-content uppercase">
                    {{ $message }}
                </span>
            </div>
        @endif
    </div>
</div>
