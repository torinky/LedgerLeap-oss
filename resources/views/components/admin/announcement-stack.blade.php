@props([
    'announcements' => [],
    'stackClass' => 'relative z-50 space-y-2',
    'bannerStickyOverride' => null,
    'bannerSyncOffset' => true,
    'bannerRespectDismissed' => true,
    'bannerDismissible' => true,
    'bannerContainerClass' => 'm-2',
])

@if (is_array($announcements) && ! empty($announcements))
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
        <div
            data-admin-announcement-banner
            class="{{ $stackClass }}"
            x-data="{
                init() {
                    this.syncOffset();
                },
                syncOffset() {
                    requestAnimationFrame(() => {
                        document.documentElement.style.setProperty('--admin-announcement-banner-offset', `${this.$el.offsetHeight}px`);
                    });
                },
            }"
            x-init="init()"
            @resize.window="syncOffset()"
        >
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
        </div>
    @endif
@endif

