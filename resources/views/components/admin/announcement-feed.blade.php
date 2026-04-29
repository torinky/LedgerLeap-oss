@props([
    'announcements' => [],
])

@if (is_array($announcements) && ! empty($announcements))
    @php
        $count = count($announcements);
    @endphp

    <section class="rounded-2xl border border-base-300 bg-base-100 shadow-sm" data-admin-announcement-feed>
        <div class="flex items-start justify-between gap-4 border-b border-base-200 px-4 py-4 sm:px-5">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-bold sm:text-lg">{{ __('ledger.admin_announcement_banner_title') }}</h2>
                    <span class="badge badge-secondary badge-sm">{{ $count }}</span>
                </div>
                <p class="text-sm leading-6 text-base-content/70">
                    {{ __('ledger.admin_announcement_banner_preview_summary') }}
                </p>
            </div>

            <span class="badge badge-outline badge-sm">{{ __('ledger.details') }}</span>
        </div>

        <div class="space-y-3 p-4 sm:p-5">
            @foreach ($announcements as $announcement)
                <x-admin.announcement-banner
                    :announcement="$announcement"
                    :sync-offset="false"
                    container-class="m-0"
                    :respect-dismissed="false"
                    :dismissible="false"
                />
            @endforeach
        </div>
    </section>
@endif
