@props([
    'announcements' => [],
    'bannerStickyOverride' => null,
    'bannerSyncOffset' => true,
    'bannerRespectDismissed' => true,
    'bannerDismissible' => true,
    'bannerContainerClass' => 'm-2',
])

@if (filled($announcements))
{{--
    @if (count($announcements) === 1)
        <x-admin.announcement-banner
            :announcement="$announcements[0]"
            :sticky-override="$bannerStickyOverride"
            :sync-offset="$bannerSyncOffset"
            :respect-dismissed="$bannerRespectDismissed"
            :dismissible="$bannerDismissible"
            :container-class="$bannerContainerClass"
        />
    @else
--}}
        @foreach ($announcements as $announcement)
            <x-admin.announcement-banner
                :announcement="$announcement"
                :sticky-override="$bannerStickyOverride"
                :sync-offset="$bannerSyncOffset"
                :respect-dismissed="$bannerRespectDismissed"
                :dismissible="$bannerDismissible"
                :container-class="$bannerContainerClass"
            />
        @endforeach
{{--    @endif--}}
@endif

