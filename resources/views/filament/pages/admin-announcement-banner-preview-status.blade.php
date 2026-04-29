@props([
    'scopeLabels' => [],
    'sticky' => false,
])

@php
    $scopeLabels = is_array($scopeLabels) ? $scopeLabels : [];
@endphp

<div class="rounded-2xl border border-base-300 bg-base-200/40 px-4 py-3 shadow-sm">
    <div class="flex items-center gap-2">
        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-info/15 text-info">
            <x-mary-icon name="o-eye" class="h-4 w-4" />
        </span>

        <span class="text-sm font-semibold text-base-content">
            {{ __('ledger.admin_announcement_banner_display_state_title') }}
        </span>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        @foreach ($scopeLabels as $scopeLabel)
            <span class="badge badge-outline badge-sm gap-1">
                <x-mary-icon name="o-squares-2x2" class="h-3.5 w-3.5" />
                {{ $scopeLabel }}
            </span>
        @endforeach

        <span class="badge {{ $sticky ? 'badge-primary' : 'badge-ghost' }} badge-sm gap-1">
            <x-mary-icon name="o-bookmark" class="h-3.5 w-3.5" />
            {{ $sticky ? __('ledger.admin_announcement_banner_sticky_on') : __('ledger.admin_announcement_banner_sticky_off') }}
        </span>
    </div>
</div>