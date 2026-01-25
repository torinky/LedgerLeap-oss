@props([
    'target' => null,
    'tier' => 2,
    'message' => null,
    'delay' => true,
])

@php
    $overlayClasses = match((int)$tier) {
        1 => 'fixed inset-0 z-[100] flex flex-col items-center justify-center bg-base-300/40 backdrop-blur-md transition-all duration-500 pointer-events-none',
        default => 'absolute inset-0 z-40 flex flex-col items-center justify-center bg-base-100/30 backdrop-blur-[2px] transition-all duration-300 rounded-box pointer-events-none',
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
    {{-- 中央に配置し、不必要に広がらないように抑制 --}}
    <div class="flex flex-col items-center justify-center">
        {{-- スピナーを強調するための Glow 効果 --}}
        <div class="relative flex items-center justify-center">
            <div class="absolute inset-0 bg-primary/30 blur-2xl rounded-full scale-150 animate-pulse"></div>
            <span class="{{ $spinnerClasses }} relative z-10 drop-shadow-lg"></span>
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
