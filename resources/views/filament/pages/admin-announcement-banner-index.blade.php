<x-filament-panels::page>
    <div class="space-y-4">
        <section class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1">
                    <h2 class="text-base font-bold sm:text-lg">{{ __('ledger.admin_announcement_banner_management_title') }}</h2>
                    <p class="text-sm leading-6 text-base-content/70">
                        {{ __('ledger.admin_announcement_banner_management_hint') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="badge badge-secondary badge-sm">{{ $count }}</span>
                    <span class="badge badge-outline badge-sm">{{ __('ledger.admin_announcement_banner_list_published_title') }}</span>
                </div>
            </div>
        </section>

        @if ($count > 0)
            <x-admin.announcement-feed :announcements="$announcements" />
        @else
            <section class="rounded-2xl border border-dashed border-base-300 bg-base-100 p-6 text-sm text-base-content/70 shadow-sm">
                {{ __('ledger.admin_announcement_banner_list_empty') }}
            </section>
        @endif
    </div>
</x-filament-panels::page>