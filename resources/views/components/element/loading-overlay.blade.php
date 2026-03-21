@props([
    'target' => null,
    'tier' => 2,
    'message' => null,
    'delay' => true,
    'manual' => false, // New prop to disable wire:loading
])

@php
    $hasSlot = $slot->isNotEmpty();

    $overlayClasses = match((int)$tier) {
        1 => 'fixed inset-0 z-[110] bg-base-300/60 backdrop-blur-sm transition-all duration-500 pointer-events-none min-h-screen w-full flex items-center justify-center',
        2 => 'absolute inset-0 z-30 h-full w-full flex items-center justify-center bg-base-100/10 backdrop-blur-sm transition-all duration-300 pointer-events-none',
        default => 'absolute inset-0 z-30 h-full w-full flex items-center justify-center bg-base-100/10 backdrop-blur-sm transition-all duration-300 pointer-events-none',
    };

    $spinnerClasses = match((int)$tier) {
        1 => 'loading loading-spinner loading-xl text-primary',
        2 => 'loading loading-spinner loading-lg text-primary',
        default => 'loading loading-spinner loading-md text-primary/80',
    };

    // Build wire:loading attributes as a string
    $wireLoadingAttr = '';
    if (!$manual) {
        $wireLoadingAttr = $delay ? 'wire:loading.delay' : 'wire:loading';
        if ($target) {
            $wireLoadingAttr .= ' wire:target="' . $target . '"';
        }
    }
@endphp

<div
    {!! $wireLoadingAttr !!}
    {{ $attributes->merge(['class' => $overlayClasses]) }}
>
    @if($slot->isNotEmpty())
        {{ $slot }}
    @else
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
    @endif
</div>
