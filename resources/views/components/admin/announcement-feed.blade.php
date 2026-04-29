@props([
    'announcements' => [],
])

@if (is_array($announcements) && ! empty($announcements))
    @php
        $count = count($announcements);
    @endphp

    <x-mary-card
        :subtitle="__('ledger.admin_announcement_banner_preview_summary')"
        shadow="sm"
        separator
        class="border border-base-300 bg-base-100"
        data-admin-announcement-feed
    >
        <x-slot:title>
            <span class="flex items-center gap-2">
                <x-mary-icon name="o-bell" class="h-5 w-5" />
                {{ __('ledger.admin_announcement_banner_title') }}
            </span>
        </x-slot:title>
        <x-slot:menu>
            <span class="badge badge-secondary badge-sm gap-2">
                <x-mary-icon name="o-bookmark" class="h-4 w-4" />
                {{ $count }}
            </span>
        </x-slot:menu>

        <div class="space-y-3">
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
    </x-mary-card>
@endif
