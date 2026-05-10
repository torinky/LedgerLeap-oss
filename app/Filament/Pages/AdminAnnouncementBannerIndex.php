<?php

namespace App\Filament\Pages;

use App\Services\AdminAnnouncementService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class AdminAnnouncementBannerIndex extends Page
{
    public static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.admin-announcement-banner-index';

    protected static ?string $slug = 'admin-announcement-banners';

    public function getTitle(): string|Htmlable
    {
        return __('ledger.admin_announcement_banner_management_title');
    }

    public function getHeaderActions(): array
    {
        $fromTenantId = session('filament_from_tenant_id');

        return [
            Action::make('createAnnouncement')
                ->label(__('ledger.create'))
                ->icon('heroicon-o-plus')
                ->color('success')
                ->url(fn (): string => AdminAnnouncementBannerSettings::getUrl().($fromTenantId ? '?tenant='.$fromTenantId : '')),
        ];
    }

    protected function getViewData(): array
    {
        $announcements = app(AdminAnnouncementService::class)->notificationCenterAnnouncements();

        return [
            'announcements' => $announcements,
            'count' => count($announcements),
        ];
    }
}
