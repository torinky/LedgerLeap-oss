<div class="mb-3 flex items-center justify-end">
    <button
        type="button"
        class="btn btn-ghost btn-sm"
        onclick="window.resetAdminAnnouncementBannerPreview && window.resetAdminAnnouncementBannerPreview()"
    >
        {{ $label }}
    </button>
</div>

<script>
    window.resetAdminAnnouncementBannerPreview = window.resetAdminAnnouncementBannerPreview || (() => {
        const prefix = 'ledgerleap.admin_announcement_banner.preview:';

        Object.keys(localStorage)
            .filter((key) => key.startsWith(prefix))
            .forEach((key) => localStorage.removeItem(key));

        window.location.reload();
    });
</script>
