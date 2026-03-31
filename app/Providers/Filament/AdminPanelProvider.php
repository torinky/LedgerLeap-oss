<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\DashboardLinksWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // URLクエリパラメータからfrom_tenantを取得し、セッションに保存
        if ($fromTenantId = request()->query('tenant')) {
            if (! empty($fromTenantId)) {
                session()->put('filament_from_tenant_id', $fromTenantId);
            }
        }
        $fromTenantId = session('filament_from_tenant_id');

        return $panel
            ->default()
            ->id('admin')
            ->path('app')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->plugins([
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                //                Pages\Dashboard::class,
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                //                Widgets\AccountWidget::class,
                DashboardLinksWidget::class,

                //                Widgets\FilamentInfoWidget::class,
            ])
            ->topNavigation()  // これによりトップナビゲーションが有効になります
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
//            ->navigation(false)
            ->navigationItems([
                NavigationItem::make(__('ledger.navigation.back_to_tenant'))
                    ->url(function () {
                        $fromTenantId = session('filament_from_tenant_id');

                        return $fromTenantId ? route('my-portal', ['tenant' => $fromTenantId]) : '#';
                    })
                    ->icon('heroicon-o-arrow-uturn-left')
//                    ->visible(!empty(session('filament_from_tenant_id')))
                    ->visible(function () {
                        $fromTenantId = session('filament_from_tenant_id');

                        return ! empty($fromTenantId);
                    })
                    ->sort(2),
                NavigationItem::make(__('ledger.setting'))
                    ->icon('heroicon-o-adjustments-vertical')
                    ->activeIcon('heroicon-s-adjustments-vertical')
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.dashboard'))
                    ->url(fn (): string => Dashboard::getUrl().'?tenant='.$fromTenantId)
                    ->sort(1),
            ])
            ->navigationGroups([])
            /*            ->navigationGroups([
                            NavigationGroup::make()
                                ->label(__('ledger.setting'))
                                ->icon('heroicon-o-cog-8-tooth'),
                        ])
                        ->navigationItems([
                            NavigationItem::make(__('ledger.search_view'))
                                ->url(fn() => route('ledger.index'))
                                ->icon('heroicon-o-book-open')
            //                    ->group('カスタムリンク')  // オプション：グループを指定
                                ->sort(3),
                            NavigationItem::make(__('ledger.general_settings'))
                                ->url(fn() => route('ledgerDefine.index'))
            //                    ->icon('heroicon-o-book-open')
                                ->group(__('ledger.setting'))  // オプション：グループを指定
                                ->sort(3),
                            NavigationItem::make(__('ledger.technical_term'))
                                ->url(fn() => route('filament.admin.resources.synonym.technical-term-groups.index'))
            //                    ->icon('heroicon-o-chat-bubble-left-right')
                                ->group(__('ledger.setting'))  // オプション：グループを指定
                                ->sort(3),
                            // 他のナビゲーションアイテムをここに追加
                        ])*/
            ->resources([
                // 表示したいリソースのみを列挙
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
//            ->theme(asset('css/filament/admin/theme.css'))

//            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(
                'panels::global-search.after',
                fn (): string => Blade::render('
                    <div class="flex items-center gap-x-3">
                        @livewire(\App\Livewire\Common\PageQrCode::class, ["triggerType" => "filament"])
                        <livewire:tenant-switcher-filament :show-folders="false" />
                    </div>
                '),
            );
    }
}
